<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsProjector;
use Illuminate\Console\Command;

/**
 * analytics:rebuild-snapshots — Phase 6 · Slice 2 (Analytics Engine).
 *
 *   php artisan analytics:rebuild-snapshots                       # rebuild every metric
 *   php artisan analytics:rebuild-snapshots --metric=staff_kpis   # rebuild just one
 *   php artisan analytics:rebuild-snapshots --check               # parity: stored vs fresh recompute, all
 *   php artisan analytics:rebuild-snapshots --metric=growth --check  # parity for just one
 *
 * Shadow only — nothing in the live app reads this projection yet; the
 * /relationship/analytics dashboard keeps rendering from
 * AnalyticsController's own cached methods. Rebuilding is always safe: the
 * projection is a derived view, never a source of truth.
 */
class RebuildAnalyticsSnapshots extends Command
{
    protected $signature = 'analytics:rebuild-snapshots
        {--metric= : Only rebuild/check this metric (see AnalyticsProjector::METRICS)}
        {--check : Compare stored snapshots to a fresh recompute; do not write}';

    protected $description = 'Rebuild (or parity-check) the Analytics Engine snapshots. Shadow — nothing reads this projection in any live UI yet.';

    public function handle(AnalyticsProjector $projector): int
    {
        $metric = $this->option('metric');

        if ($this->option('check')) {
            return $this->runCheck($projector, $metric);
        }

        if ($metric !== null) {
            $r = $projector->rebuildFor($metric);

            if (! $r['known']) {
                $this->error("Unknown metric [{$metric}]. Known: " . implode(', ', AnalyticsProjector::METRICS));
                return self::FAILURE;
            }

            $this->info("Rebuilt metric [{$metric}].");
            return self::SUCCESS;
        }

        $r = $projector->rebuildAll();
        $this->info("Analytics snapshots rebuilt: {$r['metrics']} metrics.");
        $this->line('Shadow only — the live dashboard still reads AnalyticsController\'s own cached methods.');
        return self::SUCCESS;
    }

    protected function runCheck(AnalyticsProjector $projector, ?string $metric): int
    {
        $r = $projector->parity($metric);

        if ($r['match']) {
            $this->info("Parity OK — {$r['checked']} metric(s) checked, stored snapshots match a fresh recompute.");
            return self::SUCCESS;
        }

        $this->warn("Parity MISMATCH — {$r['checked']} checked, " . count($r['diffs']) . ' metric(s) differ.');

        foreach ($r['diffs'] as $diffMetric => $diff) {
            $this->line("  {$diffMetric} · " . json_encode($diff));
        }

        return self::FAILURE;
    }
}
