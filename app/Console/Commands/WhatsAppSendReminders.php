<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * whatsapp:send-reminders — auto-send appointment reminders over WhatsApp
 * (Phase B item 1.2 — "reminders live").
 * ----------------------------------------------------------------------------
 * Finds upcoming SCHEDULED appointments and sends the approved
 * `appointment_reminder` template to each patient. Fully reuses the Chunk 2/4
 * pipeline, so every send is DPDP consent-gated, recorded, and audit-logged.
 *
 * IDEMPOTENT: each reminder carries a dedup_key ("appt:{id}:{date}"), so running
 * the job daily (or twice) never double-messages a patient.
 *
 * Scheduled daily in routes/console.php. Manual use:
 *   php artisan whatsapp:send-reminders                 # default: tomorrow
 *   php artisan whatsapp:send-reminders --days=0        # today
 *   php artisan whatsapp:send-reminders --days=2        # 2 days ahead
 *   php artisan whatsapp:send-reminders --dry-run       # just show who'd get one
 */
class WhatsAppSendReminders extends Command
{
    protected $signature = 'whatsapp:send-reminders
                            {--days=1 : How many days ahead to remind (1 = tomorrow)}
                            {--branch= : Limit to one branch id}
                            {--dry-run : List who would be reminded, send nothing}';

    protected $description = 'Send WhatsApp appointment reminders for upcoming scheduled visits';

    public function handle(OutboundMessageService $outbound): int
    {
        $days       = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($days);
        $preview    = (bool) $this->option('dry-run');

        $query = Appointment::with('patient')
            ->whereDate('appointment_date', $targetDate)
            ->where('status', 'scheduled');

        if ($this->option('branch')) {
            $query->where('branch_id', (int) $this->option('branch'));
        }

        $appointments = $query->get();

        $this->info("Appointment reminders for {$targetDate->toDateString()} ({$appointments->count()} scheduled)");
        if (! $preview) {
            $this->line('  Mode: ' . (config('whatsapp.dry_run') ? 'WhatsApp DRY-RUN' : 'LIVE')
                . ' | enabled=' . (config('whatsapp.enabled') ? 'yes' : 'no'));
        }

        $sent = $skipped = $blocked = $noPhone = 0;

        foreach ($appointments as $appt) {
            $patient = $appt->patient;
            if (! $patient || ! $patient->phone) {
                $noPhone++;
                continue;
            }

            $first = trim(explode(' ', trim((string) $patient->name))[0] ?? 'there');
            $date  = $appt->appointment_date instanceof Carbon
                ? $appt->appointment_date->format('D, d M')
                : Carbon::parse($appt->appointment_date)->format('D, d M');
            $time  = $appt->appointment_time
                ? Carbon::parse($appt->appointment_time)->format('g:i A')
                : '';

            if ($preview) {
                $this->line("  • {$patient->name} ({$patient->phone}) — {$date} {$time}");
                continue;
            }

            $res = $outbound->sendTemplate(
                $patient->phone,
                'appointment_reminder',
                ['name' => $first, 'date' => $date, 'time' => $time],
                [
                    'patient_id' => $patient->id,
                    'dedup_key'  => 'appt:' . $appt->id . ':' . $targetDate->toDateString(),
                ],
            );

            if (! empty($res['skipped'])) {
                $skipped++;
            } elseif ($res['ok']) {
                $sent++;
            } else {
                $blocked++;
                $this->line("  - blocked {$patient->name}: " . ($res['reason'] ?? ''));
            }
        }

        if (! $preview) {
            $this->info("Done. sent={$sent} already-sent={$skipped} blocked={$blocked} no-phone={$noPhone}");
        }

        return self::SUCCESS;
    }
}
