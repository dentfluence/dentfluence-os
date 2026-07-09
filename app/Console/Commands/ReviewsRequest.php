<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * reviews:request — send review requests over WhatsApp (Phase B item 2.4).
 * ----------------------------------------------------------------------------
 * Two modes:
 *   • Auto (default): find appointments COMPLETED N days ago and ask each
 *     patient for a review. Idempotent — one request per appointment ever.
 *   • Manual test:   --patient=ID sends a single request now.
 *
 * Every send goes through the DPDP-gated WhatsApp pipeline. Dry-run safe.
 *
 * Scheduled daily in routes/console.php. Manual use:
 *   php artisan reviews:request --patient=7
 *   php artisan reviews:request --days=1
 *   php artisan reviews:request --dry-run
 */
class ReviewsRequest extends Command
{
    protected $signature = 'reviews:request
                            {--patient= : Send one request to this patient id (test)}
                            {--days=1 : Auto mode: appointments completed this many days ago}
                            {--dry-run : List who would be asked, send nothing}';

    protected $description = 'Send WhatsApp review requests after completed visits';

    public function handle(ReviewService $reviews): int
    {
        if (! config('reviews.enabled')) {
            $this->warn('Reviews are disabled (REVIEWS_ENABLED=false).');
            return self::SUCCESS;
        }

        // ── Manual single-patient test ──────────────────────────────────────────
        if ($this->option('patient')) {
            $patient = Patient::find((int) $this->option('patient'));
            if (! $patient) {
                $this->error('Patient not found.');
                return self::FAILURE;
            }
            $res = $reviews->requestFromPatient($patient, ['dedup_key' => 'review:manual:' . $patient->id . ':' . now()->toDateString()]);
            $this->info('Request '
                . (($res['send']['ok'] ?? false) ? 'sent (' . ($res['send']['message']->status ?? 'ok') . ')' : 'NOT sent: ' . ($res['send']['reason'] ?? ''))
                . " · link: " . $reviews->link($res['review']));
            return self::SUCCESS;
        }

        // ── Auto: completed appointments N days ago ─────────────────────────────
        $days   = (int) $this->option('days');
        $date   = Carbon::today()->subDays($days);
        $appts  = Appointment::with('patient')
            ->whereDate('appointment_date', $date)
            ->where('status', 'done') // real "finished" value app-wide; 'completed' never matches (see docs/backend-orchestration-plan.md §2.13)
            ->get();

        $this->info("Review requests for visits completed {$date->toDateString()} ({$appts->count()} completed)");

        $sent = $skipped = $blocked = 0;

        foreach ($appts as $appt) {
            $patient = $appt->patient;
            if (! $patient || ! $patient->phone) {
                continue;
            }

            // One review request per appointment, ever.
            if (Review::where('appointment_id', $appt->id)->exists()) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  • {$patient->name} ({$patient->phone})");
                continue;
            }

            $res = $reviews->requestFromPatient($patient, [
                'appointment_id' => $appt->id,
                'dedup_key'      => 'review:appt:' . $appt->id,
            ]);

            if ($res['send']['ok'] ?? false) {
                $sent++;
            } else {
                $blocked++;
            }
        }

        if (! $this->option('dry-run')) {
            $this->info("Done. sent={$sent} already-requested={$skipped} blocked={$blocked}");
        }

        return self::SUCCESS;
    }
}
