<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — Web route permission gating (P0-6 hardening fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  Before the fix, the entire /inventory route group was gated only by
 *  `module:inventory` (defaults to the 'view' action). Write routes (stock
 *  in/out, purchase orders, deletes, etc.) carried no stricter 'edit' /
 *  'delete' middleware, so any role granted VIEW-ONLY access to Inventory
 *  could still create/edit/delete data through the web app — a privilege
 *  escalation the mobile API never had (it already required
 *  api.role:module:inventory,edit|delete on the equivalent endpoints).
 *
 *  These tests build an ad-hoc role with an explicit view/edit/delete
 *  permission triple (same pattern as
 *  Tests\Feature\Appointments\Concerns\InteractsWithPermissions) and prove:
 *    1. A view-only role is now denied on write routes.
 *    2. A role with edit permission is unaffected (no regression).
 *    3. A role with edit-but-not-delete is denied on delete routes.
 */
class InventoryPermissionGatingTest extends TestCase
{
    use RefreshDatabase;

    /** Build a user whose role has an explicit (view, edit, delete) grant for Inventory only. */
    private function userWithInventoryPermission(bool $view, bool $edit, bool $delete): User
    {
        $module = Module::firstOrCreate(
            ['slug' => 'inventory'],
            ['name' => 'Inventory', 'icon' => '', 'sort_order' => 10]
        );

        $role = Role::create([
            'name'      => 'InvPermTest ' . uniqid(),
            'slug'      => 'inv_perm_test_' . uniqid(),
            'category'  => Role::CATEGORY_STAFF,
            'is_system' => false,
        ]);

        RoleModulePermission::create([
            'role_id'    => $role->id,
            'module_id'  => $module->id,
            'can_view'   => $view,
            'can_edit'   => $edit,
            'can_delete' => $delete,
        ]);

        return User::factory()->create([
            'role'      => 'front_desk', // non-admin legacy string, so the admin bypass never fires
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_view_only_role_is_denied_on_stock_in(): void
    {
        $user = $this->userWithInventoryPermission(view: true, edit: false, delete: false);

        $item = InventoryItem::create([
            'product_name' => 'Gate Test Item ' . now()->format('His'),
            'item_code'    => 'GATE-' . now()->format('His') . rand(100, 999),
        ]);

        $resp = $this->actingAs($user)->post(route('inventory.stock-in.store'), [
            'inventory_item_id' => $item->id,
            'to_location_id'    => 1,
            'qty'               => 5,
        ]);

        // Denied by CheckModulePermission (redirect + flashed access_denied reason),
        // never reaches the controller.
        $resp->assertRedirect();
        $resp->assertSessionHas('access_denied');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_edit_role_can_still_reach_stock_in_controller(): void
    {
        // Regression guard: confirms the new middleware doesn't block a role
        // that legitimately has edit access (front_desk/manager-equivalent).
        $user = $this->userWithInventoryPermission(view: true, edit: true, delete: false);

        $item = InventoryItem::create([
            'product_name' => 'Gate Test Item Edit ' . now()->format('His'),
            'item_code'    => 'GATE-EDIT-' . now()->format('His') . rand(100, 999),
        ]);

        $resp = $this->actingAs($user)->post(route('inventory.stock-in.store'), [
            'inventory_item_id' => $item->id,
            'to_location_id'    => 1,
            'qty'               => 5,
        ]);

        // Should NOT be blocked by the permission gate. (It may still fail
        // validation on to_location_id if location 1 doesn't exist in this
        // test's DB — that's a controller-level 422/redirect-with-errors,
        // not the access_denied flash the permission gate produces.)
        $resp->assertSessionMissing('access_denied');
    }

    public function test_view_and_edit_role_is_denied_on_grn_reversal_which_requires_delete(): void
    {
        $user = $this->userWithInventoryPermission(view: true, edit: true, delete: false);

        $po = PurchaseOrder::create([
            'order_no'   => 'GATE-PO-' . now()->format('His') . rand(100, 999),
            'order_date' => today()->toDateString(),
            'status'     => 'ordered',
        ]);

        $resp = $this->actingAs($user)->delete(route('inventory.purchase.grn.reverse', $po));

        $resp->assertRedirect();
        $resp->assertSessionHas('access_denied');
    }

    public function test_delete_role_is_not_blocked_by_the_gate_on_grn_reversal(): void
    {
        $user = $this->userWithInventoryPermission(view: true, edit: true, delete: true);

        $po = PurchaseOrder::create([
            'order_no'   => 'GATE-PO2-' . now()->format('His') . rand(100, 999),
            'order_date' => today()->toDateString(),
            'status'     => 'ordered',
        ]);

        $resp = $this->actingAs($user)->delete(route('inventory.purchase.grn.reverse', $po));

        // Should NOT be blocked by the permission gate (the controller/service
        // may still reject it for a business reason, e.g. no GRN exists yet —
        // that's a RuntimeException -> withErrors(), not access_denied).
        $resp->assertSessionMissing('access_denied');
    }
}
