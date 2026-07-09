<?php

namespace App\Console\Commands;

use App\Models\Finance\FinancePatientMembership;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Console\Command;

/**
 * RunMembershipRenewalScan — Backend Orchestration (docs/backend-orchestration-plan.md §2.12)
 *
 * The one item in the orchestration plan that needed genuinely new code
 * rather than pure wiring: no producer anywhere fires 'membership.expiring',
 * so the already-enabled membership_renewal_30d RulesEngine rule has never
 * once run. Mirrors the shape of RunRecallEngine (recall:run) — a thin
 * command over a plain query, no new engine.
 *
 * Fires once, on the single day a membership's end_date crosses the renewal
 * window (default 30 days ahead — reuses
 * config('relationship_rules.today_actions.membership_renewal_days_ahead'),
 * the same threshold the Action Board's membership_renewals category already
 * uses, so this never drifts out of sync with a second hardcoded number).
 * RulesEngine's own 25-day cooldown on membership_renewal_30d then makes sure
 * the resulting "Membership renewal call" task is only ever created once per
 * cycle, via TaskEngine's existing dedup guard.
 *
 * Usage:
 *   php artisan membership:scan-expiring
 *   php artisan membership:scan-expiring --dry-run
 */
class RunMembershipRenewalScan extends Command
{
    protected $signature   = 'membership:scan-expiring {--dry-run : Preview only, no Activity logged}';
    protected $description = 'Fire membership.expiring for memberships hitting the renewal window today';

    public function handle(): int
    {
        $daysAhead  = (int) config('relationship_rules.today_actions.membership_renewal_days_ahead', 30);
        $targetDate = now()->addDays($daysAhead)->toDateString();
        $isDryRun   = $this->option('dry-run');

        $memberships = FinancePatientMembership::with('patient')
            ->where('status', 'active')
            ->whereDate('end_date', $targetDate)
            ->get();

        $this->line("Membership renewal scan — {$memberships->count()} membership(s) expiring on {$targetDate} ({$daysAhead} days ahead).");

        if ($isDryRun) {
            foreach ($memberships as $m) {
                $this->line("  • Patient #{$m->patient_id} — membership #{$m->id}, ends {$m->end_date->toDateString()}");
            }
            return self::SUCCESS;
        }

        $logged = 0;
        foreach ($memberships as $membership) {
            if (! $membership->patient_id) {
                continue;
            }

            app(ActivityEngine::class)->log(
                subject:        $membership,
                event:          'membership.expiring',
                actor:          null,
                metadata:       ['patient_id' => $membership->patient_id, 'end_date' => $membership->end_date->toDateString()],
                relationshipId: $membership->patient?->relationship_id,
                description:    'Membership expiring in ' . $daysAhead . ' days',
            );
            $logged++;
        }

        $this->line("  \xE2\x9C\x93 {$logged} logged.");

        return self::SUCCESS;
    }
}
