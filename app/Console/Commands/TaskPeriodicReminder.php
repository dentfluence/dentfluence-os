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
                ->where('status', '!=', 'done')
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
                AppNotification::notify(
                    userId:      $userId,
                    type:        'task_reminder',
                    title:       "{$count} task(s) still pending",
                    message:     $msg,
                    actionUrl:   route('tasks.index'),
                    actionLabel: 'View My Tasks',
                );
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
