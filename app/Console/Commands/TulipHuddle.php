<?php

namespace App\Console\Commands;

use App\Services\Huddle\HuddleService;
use Illuminate\Console\Command;

/**
 * tulip:huddle — print the daily morning-huddle briefing.
 * ----------------------------------------------------------------------------
 *   php artisan tulip:huddle                 (today, all branches)
 *   php artisan tulip:huddle --date=2026-06-24
 *   php artisan tulip:huddle --branch=1
 *
 * Pure data — no AI needed, so it's instant and always accurate. Can be wired
 * to Laravel's scheduler to run/print every morning (H3 explains how).
 */
class TulipHuddle extends Command
{
    protected $signature = 'tulip:huddle {--date= : Date (YYYY-MM-DD), defaults to today}
                                         {--branch= : Branch ID to scope to}';

    protected $description = 'Print the daily huddle briefing (schedule, alerts, money, opportunities).';

    public function handle(HuddleService $huddle): int
    {
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $date   = $this->option('date') ?: null;

        $this->newLine();
        $this->line($huddle->render($branch, $date));
        $this->newLine();

        return self::SUCCESS;
    }
}
