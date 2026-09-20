<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2.6, slice A — one source for staff role.
 *
 * users.role is a STAFF TYPE label. Anyone with HR edit can set it, including
 * on their own record. role_id plus the owner-configured
 * roles/modules/role_module_permissions grid is the access authority.
 *
 * V.18 closed this on the web on 17 Sep by making isAdminRole() follow
 * role_id. Two methods were left reading the string:
 *
 *   User::hasRole()  — tested the string FIRST and returned true outright.
 *                      EnsureApiRole gates every `api.role:<name>` route
 *                      through it, so typing your own staff type passed an
 *                      API role check your assigned role denies.
 *   User::isAdmin()  — had no role_id path at all, and gates voiding a
 *                      finance voucher, two inventory writes and the void
 *                      buttons on four screens.
 *
 * Both now follow the assigned role. The legacy string still speaks for a
 * user with NO role_id — that transition bypass is deliberate and is pinned
 * below so this fix cannot quietly lock out an unmigrated account.
 */
class StaffTypeStringCannotGrantARoleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /**
     * A restricted user who has typed 'admin' into her own staff type.
     * Her assigned role carries no permissions at all.
     */
    private function selfPromotedUser(): User
    {
        return $this->fresh($this->zeroPermUser('admin'));
    }

    public function test_a_staff_type_string_does_not_grant_a_role_the_assigned_role_denies(): void
    {
        $user = $this->selfPromotedUser();

        $this->assertSame('admin', $user->role, 'precondition: the legacy string says admin');
        $this->assertNotNull($user->role_id, 'precondition: she has an assigned role');

        $this->assertFalse($user->hasRole(Role::ADMIN),
            'The legacy staff-type string is still granting a role.');
        $this->assertFalse($user->isAdmin(),
            'isAdmin() is still reading the staff-type string.');
        $this->assertFalse($user->isAdminRole(),
            'isAdminRole() regressed — V.18 must keep holding.');
    }

    public function test_the_api_role_gate_refuses_a_self_promoted_staff_type(): void
    {
        // The one route in the app authorized by a role NAME rather than a
        // permission (documented exception in ApiAccessParityCharacterizationTest).
        Sanctum::actingAs($this->selfPromotedUser(), ['*']);

        $this->getJson('/api/v1/auth/admin-check')->assertForbidden();
    }

    public function test_a_genuinely_assigned_admin_still_passes(): void
    {
        $this->seedAccessRoles();

        $admin = User::factory()->create([
            'role'      => 'assistant',          // staff type says otherwise
            'role_id'   => Role::where('slug', Role::ADMIN)->firstOrFail()->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $admin = $this->fresh($admin);

        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isAdminRole());

        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/v1/auth/admin-check')->assertOk();
    }

    public function test_the_unmigrated_account_bypass_is_deliberately_kept(): void
    {
        // role_id NULL — nothing has been assigned yet. The legacy string is
        // the only thing that can speak, so it does. Removing this would lock
        // out any account that predates the grid.
        //
        // The user is built by hand rather than through
        // BuildsAccessPersonas::legacyAdminUser(), which despite its name and
        // its 'role_id => null' CANNOT produce one: UserFactory::configure()
        // has an afterCreating hook that gives the real Admin role to any
        // factory user with role 'admin' and no role_id. That hook exists for
        // a good reason (Slice 1.2 — old tests built admins that way and would
        // otherwise exercise a retired shortcut), but it means the persona
        // named after this bypass has never actually exercised it.
        $legacy = $this->legacyAdminUser();
        User::whereKey($legacy->id)->update(['role_id' => null]);
        $legacy = $this->fresh($legacy);

        $this->assertNull($legacy->role_id, 'precondition: genuinely unmigrated');
        $this->assertTrue($legacy->hasRole(Role::ADMIN));
        $this->assertTrue($legacy->isAdmin());
    }

    public function test_a_non_admin_assigned_role_is_matched_by_its_own_slug(): void
    {
        // hasRole() must still answer yes for the role the user actually holds.
        $user = $this->fresh($this->userWithModulePerm('patients', true, true, false));
        $slug = $user->roleModel->slug;

        $this->assertTrue($user->hasRole($slug));
        $this->assertFalse($user->hasRole(Role::ADMIN));
    }
}
