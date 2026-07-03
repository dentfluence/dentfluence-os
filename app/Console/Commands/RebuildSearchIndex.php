<?php

namespace App\Console\Commands;

use App\Services\Search\SearchIndexProjector;
use Illuminate\Console\Command;

/**
 * search:rebuild-index — Phase 6 · Slice 3 (Search Engine).
 *
 *   php artisan search:rebuild-index                        # rebuild every relationship
 *   php artisan search:rebuild-index --relationship=123     # rebuild just one
 *   php artisan search:rebuild-index --check                # parity: stored vs fresh, all
 *   php artisan search:rebuild-index --relationship=123 --check   # parity for just one
 *
 * Shadow only — ProfileController::search() still queries `relationships`
 * live; `search.index` stays off until a future cutover. Rebuilding is
 * always safe: the index is a derived view, never a source of truth.
 */
class RebuildSearchIndex extends Command
{
    protected $signature = 'search:rebuild-index
        {--relationship= : Only rebuild/check this relationship ID}
        {--check : Compare stored index rows to a fresh read; do not write}';

    protected $description = 'Rebuild (or parity-check) the Search Engine index. Shadow — the live search route is untouched.';

    public function handle(SearchIndexProjector $projector): int
    {
        $relationshipId = $this->option('relationship') !== null ? (int) $this->option('relationship') : null;

        if ($this->option('check')) {
            $r = $projector->parity($relationshipId !== null ? null : 200, $relationshipId);

            if ($r['match']) {
                $this->info("Parity OK — {$r['checked']} relationship(s) checked, index matches a fresh read.");
                return self::SUCCESS;
            }

            $this->warn("Parity MISMATCH — {$r['checked']} checked, " . count($r['diffs']) . ' relationship(s) differ.');
            foreach ($r['diffs'] as $diffRelationshipId => $diff) {
                $this->line("  relationship #{$diffRelationshipId} · " . json_encode($diff));
            }
            return self::FAILURE;
        }

        if ($relationshipId !== null) {
            $r = $projector->rebuildFor($relationshipId);

            if (! $r['found']) {
                $this->error("Relationship #{$relationshipId} not found.");
                return self::FAILURE;
            }

            $this->info("Rebuilt index for relationship #{$relationshipId}.");
            return self::SUCCESS;
        }

        $r = $projector->rebuildAll();
        $this->info("Search index rebuilt: {$r['relationships']} relationships.");
        $this->line('Shadow only — ProfileController::search() still reads `relationships` live.');
        return self::SUCCESS;
    }
}
