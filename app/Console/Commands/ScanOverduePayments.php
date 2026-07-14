<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Console\Command;

/**
 * ScanOverduePayments — production hardening 2026-07-14.
 *
 * The 'payment_overdue_3d' RulesEngine rule has been enabled since it was
 * written, but NOTHING in the app ever emitted 'payment.overdue' — so it has
 * never once fired and no overdue-payment follow-up call has ever been created
 * automatically.
 *
 * Deliberately mirrors RunMembershipRenewalScan (membership:scan-expiring):
 * a thin command over a plain query, no new engine. Fires once, on the day an
 * invoice crosses the overdue threshold. RulesEngine's own 7-day cooldown on
 * payment_overdue_3d + TaskEngine's dedup guard then ensure the "Overdue
 * payment follow-up" task is only created once per cycle.
 *
 * Usage:
 *   php artisan payments:scan-overdue
 *   php artisan payments:scan-overdue --dry-run
 *   php artisan payments:scan-overdue --days=5
 */
class ScanOverduePayments extends Command
{
    protected $signature = 'payments:scan-overdue
        {--days= : Days past due_date to trigger on (default: config, 3)}
        {--dry-run : Preview only, no Activity logged}';

    protected $description = 'Fire payment.overdue for invoices that crossed the overdue threshold today';

    public function handle(): int
    {
        $days       = (int) ($this->option('days') ?: config('relationship_rules.today_actions.payment_overdue_days', 3));
        $isDryRun   = (bool) $this->option('dry-run');
        $targetDate = now()->subDays($days)->toDateString();

        // Reuse the SAME minimum-balance threshold the Action Board already
        // applies, so a trivial rounding balance never generates a call task
        // and the two surfaces can't drift apart on a second hardcoded number.
        $minBalance = (float) config('relationship_rules.today_actions.payment_reminder_threshold', 500);

        // Invoices whose due_date crossed the threshold exactly today, that
        // still owe money and aren't cancelled. Matching on a single day (not
        // "<= threshold") keeps this a one-shot event per invoice rather than
        // re-firing every night for the same debt.
        $invoices = Invoice::with('patient')
            ->whereDate('due_date', $targetDate)
            ->where('balance_due', '>=', $minBalance)
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->get();

        $this->line("Overdue payment scan — {$invoices->count()} invoice(s) hit {$days} days past due on {$targetDate} (min balance Rs. " . number_format($minBalance, 0) . ").");

        if ($isDryRun) {
            foreach ($invoices as $inv) {
                $this->line("  • {$inv->invoice_number} — patient #{$inv->patient_id}, balance Rs. " . number_format((float) $inv->balance_due, 2));
            }

            return self::SUCCESS;
        }

        $logged = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->patient_id) {
                continue;
            }

            app(ActivityEngine::class)->log(
                subject:        $invoice,
                event:          'payment.overdue',
                actor:          null,
                metadata:       [
                    'patient_id'     => $invoice->patient_id,
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'balance_due'    => (float) $invoice->balance_due,
                    'days_overdue'   => $days,
                ],
                relationshipId: $invoice->patient?->relationship_id,
                description:    'Payment overdue by ' . $days . ' days on invoice ' . $invoice->invoice_number,
            );

            $logged++;
        }

        $this->line("  \xE2\x9C\x93 {$logged} logged.");

        return self::SUCCESS;
    }
}
