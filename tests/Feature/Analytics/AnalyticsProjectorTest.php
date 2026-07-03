<?php

namespace Tests\Feature\Analytics;

use App\Models\Lead;
use App\Services\Analytics\AnalyticsProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 · Slice 2 — Analytics Engine (incremental aggregate projections).
 *
 * Unlike Insights, there IS a legacy system here (AnalyticsController's own
 * cached metric methods) — the projector deliberately calls that SAME code
 * rather than re-implementing the queries, so these tests focus on the
 * projector's own behaviour (rebuild/idempotency/parity), not re-testing the
 * metric math itself.
 */
class AnalyticsProjectorTest extends TestCase
{
    use RefreshDatabase;

    private function lead(string $stage): Lead
    {
        return Lead::withoutEvents(function () use ($stage) {
            $l = new Lead(['name' => 'Analytics Lead', 'phone' => '9' . random_int(100000000, 999999999)]);
            $l->stage = $stage;
            $l->save();
            return $l;
        });
    }

    public function test_rebuild_all_persists_every_known_metric(): void
    {
        $this->lead('converted');
        $this->lead('new_lead');

        $result = app(AnalyticsProjector::class)->rebuildAll();

        $this->assertSame(count(AnalyticsProjector::METRICS), $result['metrics']);
        $this->assertSame(
            count(AnalyticsProjector::METRICS),
            DB::table('analytics_snapshots')->count(),
        );

        $conversion = DB::table('analytics_snapshots')->where('metric', 'conversion')->value('value');
        $conversion = json_decode($conversion, true);

        $this->assertSame(2, $conversion['total']);
        $this->assertSame(1, $conversion['converted']);
        // json round-trip may turn a whole-number float (50.0) into an int
        // (50) — compare loosely rather than assertSame's strict type check.
        $this->assertEquals(50.0, $conversion['rate']);
    }

    public function test_rebuild_is_idempotent_no_duplicate_rows(): void
    {
        $projector = app(AnalyticsProjector::class);
        $projector->rebuildAll();
        $projector->rebuildAll();

        $this->assertSame(count(AnalyticsProjector::METRICS), DB::table('analytics_snapshots')->count());
    }

    public function test_rebuild_for_a_single_metric_only_touches_that_row(): void
    {
        $projector = app(AnalyticsProjector::class);

        $r = $projector->rebuildFor('total_relationships');

        $this->assertTrue($r['known']);
        $this->assertSame(1, DB::table('analytics_snapshots')->count());
        $this->assertDatabaseHas('analytics_snapshots', ['metric' => 'total_relationships']);
    }

    public function test_rebuild_for_rejects_an_unknown_metric(): void
    {
        $r = app(AnalyticsProjector::class)->rebuildFor('not_a_real_metric');

        $this->assertFalse($r['known']);
        $this->assertSame(0, DB::table('analytics_snapshots')->count());
    }

    public function test_parity_matches_immediately_after_rebuild(): void
    {
        $this->lead('converted');

        $projector = app(AnalyticsProjector::class);
        $projector->rebuildAll();

        $parity = $projector->parity();

        $this->assertTrue($parity['match'], json_encode($parity['diffs']));
        $this->assertSame(count(AnalyticsProjector::METRICS), $parity['checked']);
    }

    public function test_parity_detects_drift_when_a_new_lead_arrives_without_rebuild(): void
    {
        $projector = app(AnalyticsProjector::class);
        $projector->rebuildFor('conversion'); // stored while there are 0 leads

        $this->lead('converted'); // data changes; we do NOT rebuild

        $parity = $projector->parity('conversion');

        $this->assertFalse($parity['match']);
        $this->assertArrayHasKey('conversion', $parity['diffs']);
    }

    public function test_snapshots_for_returns_stored_rows_keyed_by_metric(): void
    {
        app(AnalyticsProjector::class)->rebuildAll();

        $snapshots = app(AnalyticsProjector::class)->snapshotsFor();

        foreach (AnalyticsProjector::METRICS as $metric) {
            $this->assertArrayHasKey($metric, $snapshots);
        }
    }
}
