<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\HrStaffShift;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * TaskPeriodicReminder
 *
 * Runs every 2 hours (via scheduler). For every active staff member who
 * is currently within their shift window AND has pending tasks today,
 * creates an in-app nudge so they don't forget.
 *
 * Avoids spamming by:
 *  - Only firing for users currently in their shift
 *  - Only firing if they have ≥ 1 pending / overdue task
 *
 * Scheduled: every 2 hours via console.php
 * Manual:    php artisan tasks:periodic-reminder
 * Dry run:   php artisan tasks:periodic-reminder --dry-run
 */
class TaskPeriodicReminder extends Command
{
    protected $signature   = 'tasks:periodic-reminder {--dry-run : Preview without saving notifications}';
    protected $description = 'Send periodic in-shift reminders about incomplete tasks';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $now      = now();

        $this->line('');
        $this->line("  <fg=cyan;options=bold>Periodic Task Reminder</> — {$now->format('D d M Y, H:i')}");
        $this->line('');

        // Find staff who are currently mid-shift
        $inShiftUserIds = $this->getUserIdsCurrentlyInShift($now);

        if ($inShiftUserIds->isEmpty()) {
            $this->line('  No staff currently in shift — nothing to do.');
            return self::SUCCESS;
        }

        $fired = 0;

        foreach ($inShiftUserIds as $userId) {
            $pendingTasks = Task::with('patient')
                ->where('assigned_to', $userId)
                ->open() // not done AND not cancelled — 'cancelled' exists since 22 Sep
                ->visibleToReception()
                ->whereDate('due_date', '<=', today())
                ->orderBy('due_date')
                ->get();

            if ($pendingTasks->isEmpty()) continue;

            $user     = User::find($userId);
            $count    = $pendingTasks->count();
            $overdue  = $pendingTasks->filter(fn ($t) => $t->due_date->lt(today()))->count();

            $titles   = $pendingTasks->take(3)->pluck('title')->implode(', ');
            $extra    = $count > 3 ? ' and ' . ($count - 3) . ' more' : '';
            $urgency  = $overdue > 0 ? "{$overdue} overdue — " : '';

            $msg = "{$urgency}You have {$count} incomplete task(s): {$titles}{$extra}.";

            $this->line("  → {$user->name}: {$count} pending ({$overdue} overdue)");

            if (!$isDryRun) {
                // Through the engine, not AppNotification::notify(). The direct
                // call skipped notification_rules and the Settings matrix, and
                // wrote a row with no push intent — so this nudge has never
                // reached anyone's phone.
                //
                // DEDUPE IS THE POINT HERE. This command runs every two hours;
                // dedupe_scope pins the group key to (today, this user), so the
                // first run of the day writes the row and the five after it are
                // silent no-ops. One buzz a day about your own backlog, not six
                // — six is how staff learn to swipe alerts away unread.
                //
                // The manager also gets one row per staff member per day
                // (catalogue default). An admin who finds that noisy turns the
                // manager column off for task.overdue in the Settings matrix.
                app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.overdue', [
                    'title'        => "{$count} task(s) still pending",
                    'message'      => $msg,
                    'branch_id'    => $user->branch_id,
                    'owner'        => $userId,
                    'actor_id'     => null, // a scheduled run has no actor
                    // The SOURCE is the staff member, because this is a digest
                    // about their whole list, not about one task. It must be
                    // set: NotificationDispatcher::groupKey() falls back to a
                    // random key when source_type/source_id are absent, and a
                    // random key dedupes against nothing — the every-two-hours
                    // schedule would then buzz six times a day.
                    'source_type'  => \App\Models\User::class,
                    'source_id'    => $userId,
                    'dedupe_scope' => today()->toDateString(),
                    'action_url'   => route('tasks.index'),
                    'action_label' => 'View My Tasks',
                ]);
            }
            $fired++;
        }

        $this->line('');
        $this->info("  Done. Reminders sent: {$fired}" . ($isDryRun ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    /**
     * Returns a collection of user_ids whose shift is currently active.
     * Uses HrStaffShift + HrShift start_time / end_time.
     */
    private function getUserIdsCurrentlyInShift(Carbon $now): \Illuminate\Support\Collection
    {
        $currentTime = $now->format('H:i:s');

        return HrStaffShift::with(['user', 'shift'])
            ->current()
            ->get()
            ->filter(function ($assignment) use ($currentTime) {
                if (!$assignment->user || !$assignment->shift) return false;
                if (!$assignment->user->is_active) return false;

                $start = $assignment->shift->start_time; // e.g. "09:00:00"
                $end   = $assignment->shift->end_time;   // e.g. "18:00:00"

                // Handle overnight shifts (end < start)
                if ($end < $start) {
                    return $currentTime >= $start || $currentTime <= $end;
                }

                return $currentTime >= $start && $currentTime <= $end;
            })
            ->pluck('user_id')
            ->unique();
    }
}
