<?php

namespace Tests\Feature\Relationship;

use App\Models\TodayAction;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream E (slice E1) — Today's Actions projection.
 *
 * These test the PROJECTOR's own logic (materialise / idempotent / parity /
 * read-back shape) against a deterministic stub engine. The real 12-domain
 * TodayActionsEngine is exercised end-to-end by the `today:rebuild-projection`
 * command; here we isolate the projector so the tests are fast and stable.
 */
class TodayActionsProjectorTest extends TestCase
{
    use RefreshDatabase;

    /** A mutable stub standing in for the real engine. */
    private function stubEngine(array $groups): TodayActionsEngine
    {
        return new class($groups) extends TodayActionsEngine {
            public function __construct(public array $groups) {}
            public function generate(): array { return $this->groups; }
        };
    }

    /** Build a projector wired to a stub engine returning $groups. */
    private function projectorReturning(array $groups): TodayActionsProjector
    {
        $this->app->instance(TodayActionsEngine::class, $this->stubEngine($groups));
        return app(TodayActionsProjector::class);
    }

    /** One engine item in the canonical shape. */
    private function item(array $overrides = []): array
    {
        return array_merge([
            'category'         => 'lead_followups',
            'patient_name'     => 'Follow Lead',
            'patient_id'       => null,
            'lead_id'          => 123,
            'relationship_id'  => 5,
            'reason'           => 'Follow-up due today',
            'priority'         => 'high',
            'suggested_action' => 'Call and update lead stage',
            'link'             => '/relationship/5',
            'meta'             => ['phone' => '9000000000', 'stage' => 'new_lead'],
        ], $overrides);
    }

    public function test_rebuild_materialises_engine_items(): void
    {
        $projector = $this->projectorReturning([
            'lead_followups' => [$this->item()],
            'birthdays'      => [],
        ]);

        $result = $projector->rebuild();

        $this->assertSame(1, $result['rows']);
        $this->assertSame(2, $result['categories']);
        $this->assertDatabaseHas('today_actions', [
            'category' => 'lead_followups',
            'lead_id'  => 123,
        ]);
    }

    public function test_rebuild_is_idempotent(): void
    {
        $projector = $this->projectorReturning(['lead_followups' => [$this->item()]]);

        $first  = $projector->rebuild();
        $second = $projector->rebuild();

        $this->assertSame($first['rows'], $second['rows']);
        $this->assertSame(1, TodayAction::where('lead_id', 123)->count());
    }

    public function test_parity_matches_after_rebuild(): void
    {
        $projector = $this->projectorReturning([
            'lead_followups' => [$this->item(), $this->item(['lead_id' => 124])],
        ]);

        $projector->rebuild();
        $parity = $projector->parity();

        $this->assertTrue($parity['match']);
        $this->assertSame($parity['live_total'], $parity['projection_total']);
        $this->assertSame(2, $parity['projection_total']);
    }

    public function test_parity_detects_drift(): void
    {
        // Rebuild against an empty engine, then the engine gains an item.
        $stub = $this->stubEngine([]);
        $this->app->instance(TodayActionsEngine::class, $stub);
        $projector = app(TodayActionsProjector::class);

        $projector->rebuild();                       // projection is empty

        $stub->groups = ['lead_followups' => [$this->item()]]; // live now has 1

        $parity = $projector->parity();

        $this->assertFalse($parity['match']);
        $this->assertArrayHasKey('lead_followups', $parity['diffs']);
        $this->assertSame(['live' => 1, 'projection' => 0], $parity['diffs']['lead_followups']);
    }

    public function test_grouped_returns_projection_in_engine_shape(): void
    {
        $projector = $this->projectorReturning(['lead_followups' => [$this->item()]]);
        $projector->rebuild();

        $grouped = $projector->grouped();

        $this->assertArrayHasKey('lead_followups', $grouped);
        $item = collect($grouped['lead_followups'])->firstWhere('lead_id', 123);
        $this->assertNotNull($item);
        $this->assertSame('Follow Lead', $item['patient_name']);
        $this->assertIsArray($item['meta']);
        $this->assertSame('9000000000', $item['meta']['phone']);
    }
}
