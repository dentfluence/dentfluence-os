<?php

namespace App\Console\Commands;

use App\Services\Relationship\TodayActionsProjector;
use Illuminate\Console\Command;

/**
 * today:rebuild-projection — Phase 1 · Workstream E (slice E1).
 *
 * Rebuilds the Today's Actions projection (`today_actions`) from the live
 * TodayActionsEngine. In slice E1 this is SHADOW — the page still reads the live
 * engine; this just populates and lets us prove parity before the E2 cutover.
 *
 *   php artisan today:rebuild-projection            # rebuild the projection
 *   php artisan today:rebuild-projection --check     # parity check only (no rebuild)
 *
 * Later slices will schedule the rebuild (Automation, Phase 2). Rebuilding is
 * always safe — the projection is a derived view, not a source of truth.
 */
class RebuildTodayActions extends Command
{
    protected $signature = 'today:rebuild-projection {--check : Compare the current projection to a fresh live read; do not rebuild}';
    protected $description = "Rebuild (or parity-check) the Today's Actions projection. Shadow in Phase 1 — reads still use the live engine until the E2 cutover.";

    public function handle(TodayActionsProjector $projector): int
    {
        if ($this->option('check')) {
            $r = $projector->parity();

            if ($r['match']) {
                $this->info("Parity OK — projection matches the live engine ({$r['projection_total']} items).");
                return self::SUCCESS;
            }

            $this->warn('Parity MISMATCH — projection differs from the live engine. Run without --check to rebuild.');
            $this->table(
                ['Category', 'Live', 'Projection'],
                collect($r['diffs'])->map(fn ($d, $cat) => [$cat, $d['live'], $d['projection']])->values()->all(),
            );
            $this->line("Live total: {$r['live_total']}  ·  Projection total: {$r['projection_total']}");
            return self::FAILURE;
        }

        $r = $projector->rebuild();
        $this->info("Today's Actions projection rebuilt: {$r['rows']} items across {$r['categories']} categories.");
        $this->line('Shadow only — the page still reads the live engine until the E2 read cutover.');
        return self::SUCCESS;
    }
}
