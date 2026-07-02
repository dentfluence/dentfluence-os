<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationEngine;
use App\Services\Automation\ReminderAutomationRunner;
use App\Services\Relationship\AppointmentReminderEngine;
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

    public function handle(AppointmentReminderEngine $engine): int
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

        // ── Phase 2, Slice 5 — reminders cutover ───────────────────────────────
        // When automation.engine is ON, the Automation runner owns appointment
        // reminders (and fixes the legacy created_by=null bug). When OFF (default),
        // the legacy engine runs exactly as before. Instant rollback = flag off.
        if (app(AutomationEngine::class)->enabled()) {
            $this->line('  <fg=magenta>Automation Engine owns this reminder path.</>');
            $result = app(ReminderAutomationRunner::class)->generateAppointmentReminders();
        } else {
            $result = $engine->generateReminders();
        }

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
