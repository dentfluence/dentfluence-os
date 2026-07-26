<?php

namespace Tests\Feature\Access;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 blocker fix (2026-07-26) — OWNER-DEFINED ROLES ARE ASSIGNABLE.
 *
 * CEO verification found that a role created in Settings → Roles & Permissions
 * ("Evening desk") did not appear in HR → Staff → Edit → Role, because that
 * dropdown was a hardcoded legacy job-title array. Worse, HR update() DERIVED
 * role_id from that legacy string, so a custom role assigned elsewhere was
 * silently reverted the next time anyone edited the staff member.
 *
 * These tests use a role name no hardcoded list could possibly know
 * ("Evening Unicorn Desk …") and drive the REAL application paths, then prove
 * the assignment governs web AND API authorization, and that changing the
 * role's permission — with no code change, no rename, no re-assignment —
 * changes behaviour on both surfaces.
 */
class CustomRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /** An owner-invented role with PRE view only. */
    private function eveningUnicornDesk(bool $edit = false): Role
    {
        $this->seedAccessRoles();

        $role = Role::create([
            'name'      => 'Evening Unicorn Desk ' . uniqid(),
            'slug'      => 'evening_unicorn_desk_' . uniqid(),
            'category'  => Role::CATEGORY_STAFF,
            'is_system' => false,
        ]);

        RoleModulePermission::create([
            'role_id'    => $role->id,
            'module_id'  => Module::where('slug', 'relationship')->firstOrFail()->id,
            'can_view'   => true,
            'can_edit'   => $edit,
            'can_delete' => false,
        ]);

        return $role;
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role'      => 'admin',   // factory attaches the real Admin role
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    // ── The dropdowns are built from the canonical roles table ───────────────

    public function test_custom_role_appears_in_staff_create_selector(): void
    {
        $role = $this->eveningUnicornDesk();

        $response = $this->actingAs($this->owner())->get(route('hr.staff.create'));

        $response->assertOk();
        $response->assertSee($role->name);                       // label
        $response->assertSee('value="' . $role->id . '"', false); // canonical id
    }

    public function test_custom_role_appears_in_staff_edit_selector(): void
    {
        $role  = $this->eveningUnicornDesk();
        $staff = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);

        $response = $this->actingAs($this->owner())->get(route('hr.staff.edit', $staff));

        $response->assertOk();
        $response->assertSee($role->name);
        $response->assertSee('value="' . $role->id . '"', false);
    }

    // ── Assignment through the real application path persists role_id ────────

    public function test_staff_create_persists_the_custom_role_id(): void
    {
        $role = $this->eveningUnicornDesk();

        $this->actingAs($this->owner())->post(route('hr.staff.store'), [
            'name'     => 'Evening Person',
            'email'    => 'evening' . uniqid() . '@example.test',
            'role_id'  => $role->id,      // ACCESS role
            'role'     => 'front_desk',   // staff type (classification only)
            'password' => 'Str0ng!Passw0rd#2026',
        ])->assertRedirect();

        $created = User::where('name', 'Evening Person')->firstOrFail();

        $this->assertSame($role->id, $created->role_id, 'custom role was not persisted');
        $this->assertSame($role->name, $created->roleModel->name);
        $this->assertTrue($created->canAccess('relationship', 'view'));
        $this->assertFalse($created->canAccess('relationship', 'edit'));
    }

    public function test_staff_edit_persists_the_custom_role_and_never_reverts_it(): void
    {
        $role  = $this->eveningUnicornDesk();
        $staff = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);

        $this->actingAs($this->owner())->put(route('hr.staff.update', $staff), [
            'name'    => $staff->name,
            'email'   => $staff->email,
            'role_id' => $role->id,
            'role'    => 'front_desk', // staff type CHANGES — must not touch access
        ])->assertRedirect();

        $this->assertSame($role->id, $staff->fresh()->role_id);

        // The old bug: a later edit that changed only the staff type reverted
        // the custom access role to the legacy-derived one. It must not.
        $this->actingAs($this->owner())->put(route('hr.staff.update', $staff), [
            'name'    => $staff->name,
            'email'   => $staff->email,
            'role_id' => $role->id,
            'role'    => 'assistant',
        ])->assertRedirect();

        $this->assertSame($role->id, $staff->fresh()->role_id,
            'custom access role was clobbered by the legacy staff-type string');
    }

    public function test_secondary_edit_forms_cannot_blank_the_access_role(): void
    {
        // The HR edit screen has four secondary forms (HR details, bank,
        // contact, documents) that post back without role_id.
        $role  = $this->eveningUnicornDesk();
        $staff = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true, 'role_id' => $role->id]);

        $this->actingAs($this->owner())->put(route('hr.staff.update', $staff), [
            'name'  => $staff->name,
            'email' => $staff->email,
            'role'  => 'assistant',
            'notes' => 'edited via a secondary form',
        ])->assertRedirect();

        $this->assertSame($role->id, $staff->fresh()->role_id, 'access role was blanked by a secondary form');
    }

    // ── The assignment governs web AND API, and follows Settings changes ─────

    public function test_assigned_custom_role_governs_web_and_api_and_follows_settings(): void
    {
        $role  = $this->eveningUnicornDesk();          // PRE view only
        $staff = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);

        $this->actingAs($this->owner())->put(route('hr.staff.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email,
            'role_id' => $role->id, 'role' => 'front_desk',
        ])->assertRedirect();

        $staff = $staff->fresh();
        $this->assertSame($role->id, $staff->role_id);

        // WEB: read allowed, mutation denied.
        $this->actingAs($this->fresh($staff))->get(route('relationship.today'))->assertOk();
        $this->actingAs($this->fresh($staff))
            ->postJson(route('relationship.today.close'), ['category' => 'recall_calls'])
            ->assertForbidden();

        // API: identical answers from the same permission row.
        Sanctum::actingAs($this->fresh($staff), ['*']);
        $this->getJson('/api/v1/relationships/today')->assertOk();
        Sanctum::actingAs($this->fresh($staff), ['*']);
        $this->postJson('/api/v1/relationships/today/close', ['category' => 'recall_calls'])->assertForbidden();

        // Owner ticks Edit on the SAME role — no rename, no re-assignment, no code.
        RoleModulePermission::where('role_id', $role->id)->update(['can_edit' => true]);

        $this->actingAs($this->fresh($staff))
            ->postJson(route('relationship.today.close'), ['category' => 'recall_calls'])
            ->assertOk();

        Sanctum::actingAs($this->fresh($staff), ['*']);
        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationships/today/close', ['category' => 'recall_calls'])->getStatusCode());
    }

    // ── Role management itself stays owner-only ─────────────────────────────

    public function test_non_admin_cannot_assign_an_access_role(): void
    {
        // HR routes are gated by module:hr (view), so an HR user can open these
        // screens — but assigning access is owner-only. Before this fix, HR view
        // was enough to mint a login and hand it the Admin role.
        $role  = $this->eveningUnicornDesk();
        $hrUser = $this->userWithModulePerm('hr', view: true, edit: true, delete: false, roleName: 'HR Clerk ' . uniqid());
        $staff  = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);

        // Cannot create a staff login at all.
        $this->actingAs($hrUser)->post(route('hr.staff.store'), [
            'name' => 'Sneaky Admin', 'email' => 'sneaky' . uniqid() . '@example.test',
            'role_id' => Role::where('slug', Role::ADMIN)->firstOrFail()->id,
            'role' => 'front_desk', 'password' => 'Str0ng!Passw0rd#2026',
        ])->assertForbidden();

        // Cannot change someone else's access role either.
        $this->actingAs($hrUser)->put(route('hr.staff.update', $staff), [
            'name' => $staff->name, 'email' => $staff->email,
            'role_id' => $role->id, 'role' => 'assistant',
        ])->assertForbidden();

        $this->assertNull($staff->fresh()->role_id);
    }

    public function test_hr_user_may_still_edit_non_access_staff_details(): void
    {
        $hrUser = $this->userWithModulePerm('hr', true, true, false);
        $staff  = User::factory()->create(['role' => 'assistant', 'branch_id' => 1, 'is_active' => true]);

        $this->actingAs($hrUser)->put(route('hr.staff.update', $staff), [
            'name' => 'Renamed Person', 'email' => $staff->email,
            'role' => 'assistant', 'notes' => 'HR edit without touching access',
        ])->assertRedirect();

        $this->assertSame('Renamed Person', $staff->fresh()->name);
    }
}
