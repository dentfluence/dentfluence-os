<?php

namespace App\Console\Commands;

use App\Services\Automation\ReminderAutomationRunner;
use Illuminate\Console\Command;

/**
 * RunAppointmentReminders — Phase 4, Relationship Engine.
 *
 * Runs daily at 8:00am (scheduled in routes/console.php).
 * Finds all appointments tomorrow and auto-creates reminder call Tasks for today.
 * Deduplicates — safe to re-run at any time.
 *
 * Usage:
 *   php artisan relationship:appointment-reminders           — standard run
 *   php artisan relationship:appointment-reminders --dry-run — preview count only
 */
class RunAppointmentReminders extends Command
{
    protected $signature   = 'relationship:appointment-reminders {--dry-run : Preview only, no tasks created}';
    protected $description = 'Auto-create reminder call tasks for appointments scheduled for tomorrow';

    public function handle(ReminderAutomationRunner $runner): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>📅 Appointment Reminder Engine</> — ' . now()->format('D d M Y, H:i'));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('  ⚠  DRY RUN — no tasks will be created.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->line('  Generating reminder tasks for tomorrow\'s appointments...');
        $this->newLine();

        // ── Slice 8 — one canonical reminder producer ──────────────────────────
        // Appointment-reminder generation ALWAYS runs through
        // ReminderAutomationRunner, which supplies a valid created_by + branch_id
        // (fixing the legacy null-created_by defect). This is decoupled from the
        // automation.engine flag on purpose: that flag still governs recall/retry
        // and other automation, which this slice does not touch. The broken legacy
        // AppointmentReminderEngine has been retired.
        $result = $runner->generateAppointmentReminders();

        $this->table(
            ['Result', 'Count'],
            [
                ['Tasks created',  "<fg=green>{$result['created']}</>"],
                ['Skipped (dups)', "<fg=gray>{$result['skipped']}</>"],
            ]
        );

        $this->newLine();
        $this->line("  <fg=green;options=bold>✓ Done. {$result['created']} reminder task(s) created.</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
