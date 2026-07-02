<?php

namespace App\Console\Commands;

use App\Modules\PracticeProtocols\Services\ProtocolGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * php artisan protocols:generate
 *
 * Materialises active practice protocols into real tasks for the staff who
 * hold the matching role, for the given date (default: today).
 *
 * Idempotent — safe to run repeatedly; it never creates duplicate tasks.
 *
 * Manual trigger: php artisan protocols:generate
 * For a date:     php artisan protocols:generate --date=2026-06-27
 * Preview only:   php artisan protocols:generate --dry-run
 */
class GenerateProtocolTasks extends Command
{
    protected $signature = 'protocols:generate
                                {--date= : Generate for a specific date (Y-m-d). Defaults to today.}
                                {--dry-run : Show what would be created without writing.}';

    protected $description = 'Generate tasks from active practice protocols for the given day';

    public function handle(ProtocolGenerationService $service): int
    {
        $date   = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Generating protocol tasks for {$date->toDateString()}...");

        $result = $service->generateFor($date, $dryRun);

        $this->info("Protocols due: {$result['protocols']}");
        $this->info(($dryRun ? 'Would create' : 'Created') . ": {$result['created']} task(s)");
        $this->info("Skipped (already existed): {$result['skipped']}");

        return Command::SUCCESS;
    }
}
