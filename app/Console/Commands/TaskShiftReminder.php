<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\HrStaffShift;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * TaskShiftReminder
 *
 * Runs every 5 minutes. Checks if any staff member's shift is starting
 * or ending within the current 5-minute window and sends them an in-app
 * notification with their pending task count.
 *
 * Scheduled: every 5 minutes via console.php
 * Manual:    php artisan tasks:shift-reminder
 * Dry run:   php artisan tasks:shift-reminder --dry-run
 */
class TaskShiftReminder extends Command
{
    protected $signature   = 'tasks:shift-reminder {--dry-run : Preview without saving notifications}';
    protected $description = 'Send shift-start and shift-end task reminders to staff';

    // How many minutes before/after the exact shift time we still fire (window)
    const WINDOW_MINUTES = 5;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $now      = now();

        $this->line('');
        $this->line("  <fg=cyan;options=bold>⏰ Task Shift Reminder</> — {$now->format('D d M Y, H:i')}");
        $this->line('');

        // Load all currently-active shift assignments with their shift times
        $assignments = HrStaffShift::with(['user', 'shift'])
            ->current()
            ->get()
            ->filter(fn ($a) => $a->user && $a->shift && $a->user->is_active);

        $fired = 0;

        foreach ($assignments as $assignment) {
            $user  = $assignment->user;
            $shift = $assignment->shift;

            // ── Shift START check ─────────────────────────────────────────
            $shiftStart = Carbon::createFromTimeString($shift->start_time, $now->timezone);
            if ($this->withinWindow($now, $shiftStart)) {
                $pendingCount = $this->pendingTaskCount($user->id);
                $msg = $pendingCount > 0
                    ? "You have {$pendingCount} pending task(s) for today. Let's get started!"
                    : "No pending tasks — you're all clear for your shift!";

                $this->line("  [SHIFT START] → {$user->name} ({$shift->name} starts {$shift->start_time}) — {$pendingCount} pending");

                if (!$isDryRun) {
                    AppNotification::notify(
                        userId:      $user->id,
                        type:        'shift_start',
                        title:       "Shift started — {$shift->name}",
                        message:     $msg,
                        actionUrl:   route('tasks.index'),
                        actionLabel: 'View My Tasks',
                    );
                }
                $fired++;
            }

            // ── Shift END check ───────────────────────────────────────────
            $shiftEnd = Carbon::createFromTimeString($shift->end_time, $now->timezone);
            if ($this->withinWindow($now, $shiftEnd)) {
                $pendingCount = $this->pendingTaskCount($user->id);
                $msg = $pendingCount > 0
                    ? "You still have {$pendingCount} incomplete task(s). Please wrap them up or escalate."
                    : "Great job — all tasks completed for today!";

                $this->line("  [SHIFT END]   → {$user->name} ({$shift->name} ends {$shift->end_time}) — {$pendingCount} pending");

                if (!$isDryRun) {
                    AppNotification::notify(
                        userId:      $user->id,
                        type:        'shift_end',
                        title:       "Shift ending — {$shift->name}",
                        message:     $msg,
                        actionUrl:   route('tasks.index'),
                        actionLabel: 'View My Tasks',
                    );
                }
                $fired++;
            }
        }

        $this->line('');
        $this->info("  Done. Notifications fired: {$fired}" . ($isDryRun ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    /**
     * True if $now falls within WINDOW_MINUTES of $target.
     * E.g. shift starts at 09:00 — fires if current time is 08:58–09:05.
     */
    private function withinWindow(Carbon $now, Carbon $target): bool
    {
        $diff = abs($now->diffInMinutes($target, false));
        return $diff <= self::WINDOW_MINUTES;
    }

    /**
     * Count non-done tasks assigned to this user due today or overdue.
     */
    private function pendingTaskCount(int $userId): int
    {
        return Task::where('assigned_to', $userId)
            ->where('status', '!=', 'done')
            ->whereDate('due_date', '<=', today())
            ->count();
    }
}
