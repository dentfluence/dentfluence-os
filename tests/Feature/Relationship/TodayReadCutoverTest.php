<?php

namespace Tests\Feature\Relationship;

use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayActionsProjector;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream E (slice E2) — Today's Actions read cutover.
 *
 * With `today.projection` OFF (default) the page reads the live engine.
 * With it ON the page reads the pre-computed projection and does NOT consult
 * the engine for data. Proven by making the two sources return different items.
 */
class TodayReadCutoverTest extends TestCase
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

    private function item(string $name, int $leadId): array
    {
        return [
            'category' => 'lead_followups', 'patient_name' => $name, 'patient_id' => null,
            'lead_id' => $leadId, 'relationship_id' => null, 'reason' => 'Follow-up due today',
            'priority' => 'high', 'suggested_action' => 'Call', 'link' => '/relationship/1', 'meta' => [],
        ];
    }

    public function test_flag_off_reads_live_engine(): void
    {
        $this->app->instance(TodayActionsEngine::class, $this->stub([
            'lead_followups' => [$this->item('Live Person', 1)],
        ]));

        $response = $this->actingAs($this->user())->get(route('relationship.today'));

        $response->assertOk();
        $response->assertSee('Live Person');
    }

    public function test_flag_on_reads_projection_not_engine(): void
    {
        // Build the projection from one source…
        $this->app->instance(TodayActionsEngine::class, $this->stub([
            'lead_followups' => [$this->item('Projected Person', 1)],
        ]));
        app(TodayActionsProjector::class)->rebuild();

        // …then make the LIVE engine return something different.
        $this->app->instance(TodayActionsEngine::class, $this->stub([
            'lead_followups' => [$this->item('Live Person', 2)],
        ]));

        Feature::set('today.projection', true);

        $response = $this->actingAs($this->user())->get(route('relationship.today'));

        $response->assertOk();
        $response->assertSee('Projected Person');   // came from the projection
        $response->assertDontSee('Live Person');     // engine was not consulted
    }
}
