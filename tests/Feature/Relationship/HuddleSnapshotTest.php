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
            // Signature must match the parent's generate(bool $includeDone = false): array
            // (the $includeDone param was added later; the stub was never updated).
            public function generate(bool $includeDone = false): array { return $this->groups; }
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
