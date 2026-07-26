<?php

namespace Tests\Feature\Access;

use App\Models\Module;
use App\Models\RoleModulePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.1 — proves the EXISTING owner-configured Role Management
 * system is the working canonical source of truth: arbitrary role names,
 * View/Edit/Delete per module, immediate effect of edits and revocations,
 * admin-only management surface — and documents the two current exceptions
 * (legacy-admin bypass, missing PRE/prescriptions module rows).
 *
 * Characterization only. No authorization behavior is changed by this slice.
 */
class RoleManagementCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_owner_defined_role_name_is_irrelevant_only_permissions_matter(): void
    {
        // Arbitrary, job-title-free name — the application must not care.
        $user = $this->userWithModulePerm('patients', view: true, edit: false, delete: false,
            roleName: 'Green Unicorn Desk ' . uniqid());

        $this->assertTrue($this->fresh($user)->canAccess('patients', 'view'));
        $this->assertFalse($this->fresh($user)->canAccess('patients', 'edit'));
        $this->assertFalse($this->fresh($user)->canAccess('patients', 'delete'));
        $this->assertFalse($this->fresh($user)->canAccess('billing', 'view'));
    }

    public function test_permission_edits_and_revocation_take_effect_without_code_changes(): void
    {
        $user = $this->userWithModulePerm('patients', view: true, edit: false, delete: false);
        $perm = RoleModulePermission::where('role_id', $user->role_id)->firstOrFail();

        // Owner grants Edit later.
        $perm->update(['can_edit' => true]);
        $this->assertTrue($this->fresh($user)->canAccess('patients', 'edit'));

        // Owner revokes the module entirely.
        $perm->delete();
        $this->assertFalse($this->fresh($user)->canAccess('patients', 'view'));
        $this->assertFalse($this->fresh($user)->canAccess('patients', 'edit'));
    }

    public function test_web_module_gate_obeys_owner_configuration(): void
    {
        // Zero permissions → patients module denied (current contract: browser
        // denial is a redirect, not 403 — see Patients module gotcha).
        $denied = $this->zeroPermUser();
        $this->actingAs($denied)->get(route('patients.index'))->assertRedirect();

        // View granted → 200. Same code path, only Settings differ.
        $viewer = $this->userWithModulePerm('patients', true, false, false);
        $this->actingAs($viewer)->get(route('patients.index'))->assertOk();
    }

    public function test_roles_and_permissions_management_is_hard_admin_gated(): void
    {
        $roleRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'hr.roles.'));

        $this->assertTrue($roleRoutes->isNotEmpty(), 'hr.roles.* routes missing');

        foreach ($roleRoutes as $r) {
            $this->assertContains('admin.only', $r->gatherMiddleware(),
                "Route [{$r->getName()}] is not admin.only-gated");
        }
    }

    public function test_legacy_admin_bypass_is_retired(): void
    {
        // Slice 1.2: the transition backdoor (role string 'admin' + NO role_id
        // ⇒ full access to everything, including modules that don't exist) is
        // gone. VPS census confirmed 0 of 12 production users depended on it.
        // A user with no assigned role now has no access at all.
        $roleless = \App\Models\User::factory()->create([
            'role'      => 'assistant', // any non-admin legacy string
            'role_id'   => null,
            'branch_id' => 1,
        ]);

        $this->assertFalse($roleless->canAccess('patients', 'view'));
        $this->assertFalse($roleless->canAccess('patients', 'delete'));
        $this->assertFalse($roleless->canAccess('nonexistent_module', 'view'));
    }

    public function test_admin_role_keeps_owner_level_access(): void
    {
        // The remaining exception is role-BACKED (Clinic Owner), not a legacy
        // job-title string — so a newly registered module is usable by the
        // owner before they configure it for anyone else.
        $admin = $this->legacyAdminUser(); // factory attaches the real Admin role

        $this->assertNotNull($admin->fresh()->role_id, 'admin user has no role row');
        $this->assertTrue($this->fresh($admin)->canAccess('patients', 'delete'));
        $this->assertTrue($this->fresh($admin)->canAccess('relationship', 'edit'));
    }

    public function test_relationship_and_prescriptions_are_configurable_modules(): void
    {
        // Slice 1.3: the two approved catalogue registrations. Before this,
        // neither surface could be configured in Settings at all.
        $this->seedAccessRoles();

        $this->assertNotNull(Module::where('slug', 'relationship')->first(),
            'relationship module row missing — PRE would be unconfigurable');
        $this->assertNotNull(Module::where('slug', 'prescriptions')->first(),
            'prescriptions module row missing');
    }
}
