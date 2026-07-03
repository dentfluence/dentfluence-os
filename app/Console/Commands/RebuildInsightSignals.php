<?php

namespace App\Console\Commands;

use App\Services\Insights\InsightsProjector;
use Illuminate\Console\Command;

/**
 * insights:rebuild-signals — Phase 6 · Slice 1 (Insights Engine).
 *
 *   php artisan insights:rebuild-signals                        # rebuild every relationship
 *   php artisan insights:rebuild-signals --relationship=123     # rebuild just one
 *   php artisan insights:rebuild-signals --check                # parity: stored vs fresh recompute, all
 *   php artisan insights:rebuild-signals --relationship=123 --check   # parity for just one
 *
 * Shadow only, same as today:rebuild-projection was in Phase 1 Workstream E —
 * nothing in the live app reads this projection yet. Rebuilding is always
 * safe: the projection is a derived view, never a source of truth.
 */
class RebuildInsightSignals extends Command
{
    protected $signature = 'insights:rebuild-signals
        {--relationship= : Only rebuild/check this relationship ID}
        {--check : Compare stored signals to a fresh recompute; do not write}';

    protected $description = "Rebuild (or parity-check) the Insights Engine signals (Health/LTV/Risk). Shadow — nothing reads this projection in any live UI yet.";

    public function handle(InsightsProjector $projector): int
    {
        $relationshipId = $this->option('relationship') !== null ? (int) $this->option('relationship') : null;

        if ($this->option('check')) {
            return $this->runCheck($projector, $relationshipId);
        }

        if ($relationshipId !== null) {
            $r = $projector->rebuildFor($relationshipId);

            if (! $r['found']) {
                $this->error("Relationship #{$relationshipId} not found.");
                return self::FAILURE;
            }

            $this->info("Rebuilt {$r['rows']} signal(s) for relationship #{$relationshipId}.");
            return self::SUCCESS;
        }

        $r = $projector->rebuildAll();
        $this->info("Insight signals rebuilt: {$r['rows']} rows across {$r['relationships']} relationships.");
        $this->line('Shadow only — insights.signals gates only the incremental listener; no live read path uses this yet.');
        return self::SUCCESS;
    }

    protected function runCheck(InsightsProjector $projector, ?int $relationshipId): int
    {
        $r = $projector->parity($relationshipId !== null ? null : 200, $relationshipId);

        if ($r['match']) {
            $this->info("Parity OK — {$r['checked']} relationship(s) checked, stored signals match a fresh recompute.");
            return self::SUCCESS;
        }

        $this->warn("Parity MISMATCH — {$r['checked']} checked, " . count($r['diffs']) . ' relationship(s) differ.');

        foreach ($r['diffs'] as $diffRelationshipId => $signals) {
            foreach ($signals as $signal => $diff) {
                $this->line("  relationship #{$diffRelationshipId} · {$signal} · " . json_encode($diff));
            }
        }

        return self::FAILURE;
    }
}
