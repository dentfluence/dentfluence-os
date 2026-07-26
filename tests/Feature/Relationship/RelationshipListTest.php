<?php

namespace Tests\Feature\Relationship;

use App\Models\Lead;
use App\Models\Relationship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 5 — Relationships index (search + filters).
 */
class RelationshipListTest extends TestCase
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

    private function rel(string $name, string $phone, string $status = 'active'): Relationship
    {
        return Relationship::create([
            'name' => $name, 'phone' => $phone, 'status' => $status,
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('relationship.list'))->assertRedirect();
    }

    public function test_lists_relationships(): void
    {
        $this->rel('Asha Rao', '9001');

        $response = $this->actingAs($this->user())->get(route('relationship.list'));

        $response->assertOk();
        $response->assertSee('All Relationships');
        $response->assertSee('Asha Rao');
    }

    public function test_search_filters_by_name_or_phone(): void
    {
        $this->rel('Findable Person', '9111');
        $this->rel('Other Person', '9222');

        $response = $this->actingAs($this->user())->get(route('relationship.list', ['q' => 'Findable']));

        $response->assertOk();
        $response->assertSee('Findable Person');
        $response->assertDontSee('Other Person');
    }

    public function test_status_filter(): void
    {
        $this->rel('Active One', '9301', 'active');
        $this->rel('Lost One', '9302', 'lost');

        $response = $this->actingAs($this->user())->get(route('relationship.list', ['status' => 'lost']));

        $response->assertOk();
        $response->assertSee('Lost One');
        $response->assertDontSee('Active One');
    }

    public function test_has_lead_filter(): void
    {
        $withLead = $this->rel('Has Lead', '9401');
        $this->rel('No Lead', '9402');

        Lead::withoutEvents(function () use ($withLead) {
            $l = new Lead(['name' => 'Has Lead', 'phone' => '9401']);
            $l->relationship_id = $withLead->id;
            $l->save();
        });

        $response = $this->actingAs($this->user())->get(route('relationship.list', ['has' => 'lead']));

        $response->assertOk();
        $response->assertSee('Has Lead');
        $response->assertDontSee('No Lead');
    }

    public function test_pagination_caps_the_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->rel("Person {$i}", '95' . str_pad((string) $i, 5, '0', STR_PAD_LEFT));
        }

        $response = $this->actingAs($this->user())->get(route('relationship.list'));

        $response->assertOk();
        $paginator = $response->viewData('relationships');
        $this->assertSame(30, $paginator->total());
        $this->assertSame(25, $paginator->count()); // one page = 25 rows
    }
}
