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

    /**
     * Phase 1 · Slice 1.3: PRE surfaces are now gated by the owner-configured
     * `relationship` module. Test actors therefore need a role that grants it —
     * the role NAME is deliberately arbitrary (authorization must never depend
     * on job titles). Full view+edit+delete keeps these suites testing their
     * own subject matter rather than permissions.
     */
    private function preRole(): \App\Models\Role
    {
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => 'pre_test_actor'],
            ['name' => 'PRE Test Actor', 'category' => \App\Models\Role::CATEGORY_STAFF, 'is_system' => false]
        );

        foreach (['relationship', 'patients', 'daily_huddle', 'tasks'] as $slug) {
            $module = \App\Models\Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'section' => 'clinical', 'sort_order' => 90]
            );
            \App\Models\RoleModulePermission::updateOrCreate(
                ['role_id' => $role->id, 'module_id' => $module->id],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => true]
            );
        }

        return $role;
    }

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1, 'role_id' => $this->preRole()->id]);
    }

    private function stub(array $groups): TodayActionsEngine
    {
        return new class($groups) extends TodayActionsEngine {
            public function __construct(public array $groups) {}
            public function generate(bool $includeDone = false): array { return $this->groups; }
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
