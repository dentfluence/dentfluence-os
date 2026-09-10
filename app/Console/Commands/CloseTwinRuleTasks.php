<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

/**
 * W-10 step 2 (2026-09-10). Six RulesEngine rules produced a system Task
 * that duplicated a card the Today's Actions board already computes live
 * (membership_renewals, opportunities, pending_estimates,
 * missed_appointments_yesterday, lab_ready, payment_reminders — the lab one
 * was even a TRIPLE with the recall engine's queue row). The rules are switched off in
 * config/relationship_rules.php; this closes the tasks they already left
 * behind so the board stops listing the same patient twice.
 *
 * Dry-run by default. Idempotent — run it on production after the deploy.
 */
class CloseTwinRuleTasks extends Command
{
    protected $signature = 'today:close-twin-rule-tasks {--apply : Actually close the tasks (otherwise dry-run)}';

    protected $description = 'Close pending system tasks left by the six duplicate RulesEngine rules switched off in W-10.';

    /** Must match the rule keys in config/relationship_rules.php. */
    private const TWIN_RULES = [
        'membership_renewal_30d',
        'opportunity_nudge_7d',
        'missed_appointment_followup',
        'lab_ready_call',
        'payment_overdue_3d',
        'estimate_followup_3d',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = Task::query()
            ->where('task_type', 'system')
            ->whereIn('status', ['pending', 'escalated'])
            ->whereIn('description', array_map(fn ($r) => "[Auto] Rule: {$r}", self::TWIN_RULES));

        $counts = (clone $query)
            ->selectRaw('description, count(*) as n')
            ->groupBy('description')
            ->pluck('n', 'description');

        if ($counts->isEmpty()) {
            $this->info('Nothing to close — no open twin-rule tasks.');
            return self::SUCCESS;
        }

        foreach ($counts as $description => $n) {
            $this->line(sprintf('%-45s %5d', $description, $n));
        }
        $this->line(sprintf('%-45s %5d', 'TOTAL', $counts->sum()));

        if (! $apply) {
            $this->comment('Dry-run. Re-run with --apply to close them.');
            return self::SUCCESS;
        }

        $closed = $query->update([
            'status'  => 'done',
            'done_at' => now(),
        ]);

        $this->info("Closed {$closed} task(s).");

        return self::SUCCESS;
    }
}
