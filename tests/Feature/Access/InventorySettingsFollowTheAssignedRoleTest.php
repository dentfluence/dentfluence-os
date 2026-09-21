<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Signal Board row 2.6C — the inventory settings gates.
 *
 * Fourteen methods in InventoryController asked
 * `auth()->user()?->role !== 'admin'` and aborted 403 otherwise: settings,
 * categories, sub-types, variants and locations, destroyLocation included.
 * users.role is a STAFF TYPE label anyone with HR edit can set on their own
 * record, so typing "admin" into your own staff type opened all fourteen.
 *
 * Slice 2.6A fixed User::isAdmin() and User::hasRole(), but these never called
 * a method — they compared the column inline, so nothing reached them. That is
 * why this slice exists, and why the test below asserts on the ROUTES rather
 * than on the model: a method-level test would have passed throughout.
 *
 * The routes already carry module:inventory,edit|delete as a second layer
 * (P0-6 hardening, 4 Aug). The personas here deliberately HOLD those grants,
 * so the only thing that can refuse them is the controller gate.
 */
class InventorySettingsFollowTheAssignedRoleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /**
     * Full inventory rights through the grid, and a staff-type string that
     * claims admin. Under the old gate this user was an inventory admin.
     */
    private function selfPromotedInventoryUser(): User
    {
        $user = $this->userWithModulePerm('inventory', true, true, true);
        User::whereKey($user->id)->update(['role' => 'admin']);

        return $this->fresh($user);
    }

    private function realAdmin(): User
    {
        $this->seedAccessRoles();

        return $this->fresh(User::factory()->create([
            'role'      => 'assistant',   // staff type says otherwise
            'role_id'   => Role::where('slug', Role::ADMIN)->firstOrFail()->id,
            'branch_id' => 1,
            'is_active' => true,
        ]));
    }

    public function test_a_self_promoted_staff_type_cannot_open_inventory_settings(): void
    {
        $user = $this->selfPromotedInventoryUser();

        $this->assertSame('admin', $user->role, 'precondition: staff type claims admin');
        $this->assertTrue($user->canAccess('inventory', 'edit'), 'precondition: holds inventory edit');

        $this->actingAs($user)->get(route('inventory.settings'))->assertForbidden();
    }

    public function test_a_self_promoted_staff_type_cannot_create_an_inventory_category(): void
    {
        $this->actingAs($this->selfPromotedInventoryUser())
            ->post(route('inventory.settings.categories.store'), ['name' => 'Smuggled Category'])
            ->assertForbidden();
    }

    public function test_a_self_promoted_staff_type_cannot_create_a_variant(): void
    {
        $this->actingAs($this->selfPromotedInventoryUser())
            ->post(route('inventory.settings.variants.store'), ['name' => 'Smuggled Variant'])
            ->assertForbidden();
    }

    public function test_a_genuinely_assigned_admin_is_not_locked_out(): void
    {
        // The fix must close the hole without closing the door: an admin by
        // role_id passes even though her staff-type string says 'assistant'.
        //
        // Measured, not assumed: this route answers 200. routes/web.php:740
        // says "GET redirects to unified Settings", which is stale — worth
        // knowing, since that comment is the reason the write routes below it
        // carry their own middleware.
        $this->actingAs($this->realAdmin())
            ->get(route('inventory.settings'))
            ->assertOk();
    }

    public function test_no_inventory_gate_reads_the_legacy_string_any_more(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/InventoryController.php'));

        // The controller's own comment deliberately avoids quoting the old
        // form, so this is a plain search of the whole file with nothing to
        // strip and nothing to get wrong.
        $this->assertStringNotContainsString("user()?->role !== 'admin'", $source,
            'An inventory gate is comparing the legacy staff-type string again.');
    }
}
