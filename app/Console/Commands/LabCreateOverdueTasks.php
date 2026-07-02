<?php

namespace App\Console\Commands;

use App\Services\LabAlertService;
use Illuminate\Console\Command;

/**
 * php artisan lab:create-overdue-tasks
 *
 * Scans all open lab cases and auto-creates Tasks for:
 *   - Cases whose expected_return_date is in the past with no active task
 *   - Trial-received cases sitting 2+ days with no doctor review task
 *
 * Schedule in Console/Kernel.php: $schedule->command('lab:create-overdue-tasks')->dailyAt('09:00');
 */
class LabCreateOverdueTasks extends Command
{
    protected $signature   = 'lab:create-overdue-tasks
                                {--branch= : Limit to a specific branch_id}';

    protected $description = 'Auto-create Tasks for overdue lab cases and stale trial reviews';

    public function handle(LabAlertService $service): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;

        $this->info('Scanning lab cases for overdue follow-ups...');

        $count = $service->createOverdueTasks($branchId);

        if ($count === 0) {
            $this->info('No new tasks needed — all overdue cases already have active tasks.');
        } else {
            $this->info("Created {$count} task(s) for overdue / pending lab cases.");
        }

        return Command::SUCCESS;
    }
}
