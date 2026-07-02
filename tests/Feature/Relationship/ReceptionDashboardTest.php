<?php

namespace Tests\Feature\Relationship;

use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream E (slice E3) — Reception dashboard.
 *
 * Reads the Today's Actions projection and splits it into Today's Calls and
 * Today's Work. Read-only; no live domain reads.
 */
class ReceptionDashboardTest extends TestCase
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

    private function item(string $category, string $name): array
    {
        return [
            'category' => $category, 'patient_name' => $name, 'patient_id' => 1,
            'lead_id' => null, 'relationship_id' => null, 'reason' => 'Because reasons',
            'priority' => 'high', 'suggested_action' => 'Call', 'link' => '/relationship/1',
            'meta' => ['phone' => '9000000000'],
        ];
    }

    private function buildProjection(array $groups): void
    {
        $this->app->instance(TodayActionsEngine::class, $this->stub($groups));
        app(TodayActionsProjector::class)->rebuild();
    }

    public function test_reception_splits_calls_and_work(): void
    {
        $this->buildProjection([
            'lead_followups' => [$this->item('lead_followups', 'Call Person')],  // call queue
            'lab_ready'      => [$this->item('lab_ready', 'Work Person')],        // work queue
        ]);

        $response = $this->actingAs($this->user())->get(route('relationship.reception'));

        $response->assertOk();
        $response->assertSee("Today's Calls");
        $response->assertSee("Today's Work");
        $response->assertSee('Call Person');
        $response->assertSee('Work Person');

        $this->assertCount(1, $response->viewData('calls'));
        $this->assertCount(1, $response->viewData('work'));
        $this->assertSame(2, $response->viewData('summary')['total']);
    }

    public function test_reception_requires_authentication(): void
    {
        $this->get(route('relationship.reception'))->assertRedirect();
    }
}
