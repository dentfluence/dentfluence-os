<?php

namespace App\Services\Analytics;

use App\Http\Controllers\Relationship\AnalyticsController;
use App\Models\AnalyticsSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * AnalyticsProjector — Phase 6 · Slice 2 (Analytics Engine).
 *
 * Materialises the `/relationship/analytics` dashboard metrics into the
 * derived `analytics_snapshots` table, the same "build → rebuild command →
 * parity" discipline already proven by TodayActionsProjector (Phase 1) and
 * InsightsProjector (Phase 6 · Slice 1).
 *
 * Deliberately calls AnalyticsController's own (now-public) metric methods
 * rather than re-implementing the queries — there is exactly one place that
 * knows how to compute each metric, so the projection can never drift out of
 * sync with the live dashboard. AnalyticsController and its cached methods
 * are completely untouched otherwise; nothing reads this projection in any
 * live UI yet.
 */
class AnalyticsProjector
{
    /**
     * Known metric keys, in display order. Adding a metric later is a new
     * entry here + one new (public) method on AnalyticsController — never a
     * rewrite of this projector.
     */
    public const METRICS = [
        'growth',
        'conversion',
        'recall_success',
        'avg_ltv',
        'score_distribution',
        'staff_kpis',
        'total_relationships',
    ];

    public function __construct(private readonly AnalyticsController $controller) {}

    /**
     * Rebuild every known metric snapshot. Safe to run repeatedly (upsert on
     * the unique `metric` key — no duplicate rows).
     *
     * @return array{metrics:int, generated_at:string}
     */
    public function rebuildAll(): array
    {
        $now = now();
        $rows = [];

        foreach (self::METRICS as $metric) {
            $rows[] = $this->rowFor($metric, $now);
        }

        $this->upsert($rows);

        return ['metrics' => count($rows), 'generated_at' => $now->toIso8601String()];
    }

    /**
     * Recompute a single metric snapshot.
     *
     * @return array{metric:string, known:bool}
     */
    public function rebuildFor(string $metric): array
    {
        if (! in_array($metric, self::METRICS, true)) {
            return ['metric' => $metric, 'known' => false];
        }

        $now = now();
        $this->upsert([$this->rowFor($metric, $now)]);

        return ['metric' => $metric, 'known' => true];
    }

    /**
     * Read every stored snapshot, keyed by metric name. This is the read path
     * a future dashboard cutover would use — nothing calls it yet in Slice 2.
     *
     * @return array<string,array<string,mixed>>
     */
    public function snapshotsFor(): array
    {
        return AnalyticsSnapshot::query()
            ->get()
            ->keyBy('metric')
            ->map(fn (AnalyticsSnapshot $row) => [
                'metric'      => $row->metric,
                'value'       => $row->value,
                'computed_at' => $row->computed_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Self-check: recompute every (or one) metric fresh via the SAME
     * controller methods and diff against what's stored, WITHOUT writing
     * anything. Unlike Insights, Analytics has a real legacy system to shadow
     * — but since the projector calls the exact same code as the dashboard,
     * a mismatch here means the projection is stale (data changed since the
     * last rebuild), not a logic divergence.
     *
     * @param  string|null  $onlyMetric
     * @return array{match:bool, checked:int, diffs:array<string,array<string,mixed>>}
     */
    public function parity(?string $onlyMetric = null): array
    {
        $metrics = $onlyMetric !== null ? [$onlyMetric] : self::METRICS;
        $stored  = AnalyticsSnapshot::query()->get()->keyBy('metric');

        $checked = 0;
        $diffs   = [];

        foreach ($metrics as $metric) {
            if (! in_array($metric, self::METRICS, true)) {
                $diffs[$metric] = ['reason' => 'unknown_metric'];
                continue;
            }

            $checked++;
            $fresh     = $this->compute($metric);
            $storedRow = $stored->get($metric);

            if (! $storedRow) {
                $diffs[$metric] = ['reason' => 'missing_stored_row'];
                continue;
            }

            if ($this->normalise($fresh) !== $this->normalise($storedRow->value)) {
                $diffs[$metric] = ['fresh' => $fresh, 'stored' => $storedRow->value];
            }
        }

        return [
            'match'   => $diffs === [],
            'checked' => $checked,
            'diffs'   => $diffs,
        ];
    }

    /**
     * Call the one controller method that owns this metric's computation.
     *
     * @return mixed
     */
    protected function compute(string $metric)
    {
        // fresh: true bypasses AnalyticsController's Cache::remember wrapper —
        // without it, two calls inside the same 1-hour TTL would both return
        // the cached value and parity() could never detect real drift.
        return match ($metric) {
            'growth'              => $this->controller->relationshipGrowth(fresh: true),
            'conversion'           => $this->controller->leadConversionRate(fresh: true),
            'recall_success'       => $this->controller->recallSuccessRate(fresh: true),
            'avg_ltv'              => $this->controller->avgLifetimeValue(fresh: true),
            'score_distribution'   => $this->controller->scoreDistribution(fresh: true),
            'staff_kpis'           => $this->controller->staffKpis(fresh: true),
            'total_relationships'  => $this->controller->totalRelationships(),
        };
    }

    /**
     * @return array<string,mixed>
     */
    protected function rowFor(string $metric, \Illuminate\Support\Carbon $now): array
    {
        return [
            'metric'       => $metric,
            'value'        => json_encode($this->compute($metric), JSON_UNESCAPED_UNICODE),
            'computed_at'  => $now,
            'generated_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }

    protected function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('analytics_snapshots')->upsert(
            $rows,
            ['metric'],
            ['value', 'computed_at', 'generated_at', 'updated_at'],
        );
    }

    /**
     * Normalise a value for comparison. MySQL's JSON column type does NOT
     * preserve object key order on storage/retrieval (e.g. a stored
     * {"month":..,"count":..} can come back as {"count":..,"month":..}) —
     * comparing raw json_encode() output would report false mismatches on
     * every parity check. Sorting keys recursively (for associative/"object"
     * arrays only — sequential lists keep their meaningful order) makes the
     * comparison key-order-independent before diffing.
     */
    protected function normalise(mixed $value): string
    {
        return json_encode($this->sortKeysRecursively($value), JSON_UNESCAPED_UNICODE);
    }

    protected function sortKeysRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn ($item) => $this->sortKeysRecursively($item), $value);

        if (array_is_list($value)) {
            return $value;
        }

        ksort($value);

        return $value;
    }
}
