<?php

namespace App\Console\Commands;

use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Slice 0.3 — category-health surface for the Today's Actions board.
 *
 * The board's per-category try/catch means a broken category returns []
 * and looks identical to a quiet day; three categories were dead for weeks
 * this way. This command reads the health record written on every
 * TodayActionsEngine::generate() run and makes silent death visible.
 *
 * Exit code 1 when any category is failing — safe to wire into cron/uptime
 * monitoring. `--fresh` runs the engine now (read-only queries) instead of
 * showing the last recorded run.
 */
class TodayActionsHealth extends Command
{
    protected $signature = 'today-actions:health {--fresh : Run the engine now instead of reading the last recorded run}';

    protected $description = 'Show per-category health of the Today\'s Actions engine (last run, item count, last error)';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            app(TodayActionsEngine::class)->generate();
        }

        $health = Cache::get(TodayActionsEngine::HEALTH_CACHE_KEY);

        if (! $health) {
            $this->warn('No health record yet — the board has not run since this feature was added.');
            $this->line('Run with --fresh to generate one now.');

            return self::FAILURE;
        }

        $rows    = [];
        $failing = 0;

        foreach ($health as $category => $h) {
            $rows[] = [
                $category,
                $h['ok'] ? '<info>OK</info>' : '<error>FAIL</error>',
                $h['count'],
                $h['last_run'],
                $h['error'] ?? '—',
            ];
            $failing += $h['ok'] ? 0 : 1;
        }

        $this->table(['Category', 'Status', 'Items', 'Last run', 'Last error'], $rows);

        $total = count($health);
        $ok    = $total - $failing;
        $this->line($failing === 0
            ? "<info>{$ok}/{$total} categories OK</info>"
            : "<error>{$failing} of {$total} categories FAILING</error>");

        return $failing === 0 ? self::SUCCESS : self::FAILURE;
    }
}
