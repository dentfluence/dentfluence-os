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

    public function test_legacy_admin_string_without_role_id_bypasses_every_module_check(): void
    {
        // CURRENT transition backdoor (User::canAccess line ~204). Documented
        // for the 1.1 CEO gate — classification pending (close vs keep).
        $legacy = $this->legacyAdminUser();

        $this->assertTrue($legacy->canAccess('patients', 'delete'));
        $this->assertTrue($legacy->canAccess('billing', 'edit'));
        $this->assertTrue($legacy->canAccess('nonexistent_module', 'view'));
        $this->assertTrue($legacy->isAdminRole());
    }

    public function test_module_catalogue_has_no_relationship_or_prescriptions_entry(): void
    {
        // CURRENT gap: PRE and prescriptions cannot be configured in Settings
        // at all because no module row exists. Slice 1.3 (post-approval) is
        // expected to add the catalogue entries — this test will then be
        // deliberately inverted.
        $this->seedAccessRoles();

        $this->assertNull(Module::where('slug', 'relationship')->first());
        $this->assertNull(Module::where('slug', 'relationships')->first());
        $this->assertNull(Module::where('slug', 'pre')->first());
        $this->assertNull(Module::where('slug', 'prescriptions')->first());
    }
}
