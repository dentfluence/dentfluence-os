<?php

namespace Tests\Feature\Relationship;

use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream E (slice E4) — shared Today's Actions summary.
 *
 * The Daily Huddle (and any surface) reads the projection summary via this
 * endpoint instead of running its own domain queries.
 */
class HuddleSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    private function stub(array $groups): TodayActionsEngine
    {
        return new class($groups) extends TodayActionsEngine {
            public function __construct(public array $groups) {}
            public function generate(): array { return $this->groups; }
        };
    }

    private function item(string $category, string $priority): array
    {
        return [
            'category' => $category, 'patient_name' => 'X', 'patient_id' => null,
            'lead_id' => null, 'relationship_id' => null, 'reason' => 'r',
            'priority' => $priority, 'suggested_action' => 'Call', 'link' => '#', 'meta' => [],
        ];
    }

    public function test_summary_endpoint_returns_projection_counts(): void
    {
        $this->app->instance(TodayActionsEngine::class, $this->stub([
            'lead_followups' => [$this->item('lead_followups', 'high'), $this->item('lead_followups', 'medium')],
            'lab_ready'      => [$this->item('lab_ready', 'medium')],
        ]));
        app(TodayActionsProjector::class)->rebuild();

        $response = $this->actingAs($this->user())->getJson(route('relationship.today.summary'));

        $response->assertOk();
        $response->assertJsonPath('total', 3);
        $response->assertJsonPath('by_category.lead_followups', 2);
        $response->assertJsonPath('by_priority.high', 1);
        $response->assertJsonStructure(['total', 'by_category', 'by_priority', 'generated_at']);
    }

    public function test_summary_endpoint_requires_authentication(): void
    {
        $this->getJson(route('relationship.today.summary'))->assertUnauthorized();
    }
}
