<?php

namespace App\Console\Commands;

use App\Models\CommunicationQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * relationship:requeue-due — PRE Sprint A (2026-08-24), the daily aging pass.
 *
 * Closes the biggest silent leak in the call loop: an outcome like
 * "will call back" (OutcomeAutomationService::onFollowUpScheduled) or a
 * logged attempt (CommunicationQueue::logAttempt) parks the row as
 * status = 'waiting_for_patient' with a future follow_up_date — and NOTHING
 * ever moved it back. Every Today's Actions reader filters status='pending',
 * so a rescheduled call vanished from the board forever the moment its
 * outcome was logged. This pass re-surfaces rows whose day has come.
 *
 * Also refreshes the is_overdue flag on open rows so the Pending Calls
 * surface (Today = due today · Pending = due earlier, still open) reads an
 * honest flag instead of each screen recomputing its own idea of "overdue".
 *
 * Deliberately NOT done here:
 *  - No status 'overdue' transitions (recalculateOverdue()'s pending→overdue
 *    flip would remove rows from every status='pending' reader — the exact
 *    vanishing bug this command exists to fix).
 *  - No touching closed rows, ever.
 *
 * Scheduled daily 06:45 — before recall:run (07:00) so the morning board and
 * briefing (07:05) see the re-surfaced rows.
 */
class RequeueDueFollowUps extends Command
{
    protected $signature = 'relationship:requeue-due {--dry-run : Report what would change without writing}';

    protected $description = 'Re-surface waiting_for_patient queue rows whose follow_up_date has arrived, and refresh is_overdue flags on open rows.';

    public function handle(): int
    {
        $today = now()->toDateString();
        $dry   = (bool) $this->option('dry-run');

        // 1) waiting_for_patient whose day has come → pending (back on the board)
        $dueQuery = CommunicationQueue::query()
            ->where('status', 'waiting_for_patient')
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', $today);

        $dueCount = (clone $dueQuery)->count();

        if (! $dry && $dueCount > 0) {
            $dueQuery->update(['status' => 'pending']);
        }

        // 2) Honest overdue flags on open rows (both directions — a row whose
        //    date was pushed to the future must also lose the flag).
        $openStatuses = ['pending', 'waiting_for_patient'];

        $nowOverdue = CommunicationQueue::query()
            ->whereIn('status', $openStatuses)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', $today)
            ->where('is_overdue', false);

        $noLongerOverdue = CommunicationQueue::query()
            ->whereIn('status', $openStatuses)
            ->where('is_overdue', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('follow_up_date')
                  ->orWhereDate('follow_up_date', '>=', $today);
            });

        $flaggedCount   = (clone $nowOverdue)->count();
        $unflaggedCount = (clone $noLongerOverdue)->count();

        if (! $dry) {
            if ($flaggedCount > 0) {
                $nowOverdue->update(['is_overdue' => true]);
            }
            if ($unflaggedCount > 0) {
                $noLongerOverdue->update(['is_overdue' => false, 'overdue_since' => null]);
            }
        }

        $prefix = $dry ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Re-surfaced {$dueCount} due row(s); flagged {$flaggedCount} overdue; cleared {$unflaggedCount} stale flag(s).");

        Log::info('relationship:requeue-due', [
            'dry_run'       => $dry,
            'resurfaced'    => $dueCount,
            'flagged'       => $flaggedCount,
            'flags_cleared' => $unflaggedCount,
        ]);

        return self::SUCCESS;
    }
}
