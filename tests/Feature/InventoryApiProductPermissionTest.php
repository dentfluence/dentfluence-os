<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Inventory module — API product-create permission gap (2026-08-04 P0 fix)
 * ─────────────────────────────────────────────────────────────────────────
 *  POST /api/v1/inventory/products had no `api.role:module:inventory,edit`
 *  middleware at all, while every sibling write route in routes/api.php
 *  (and the equivalent web route) required it. Any authenticated user —
 *  regardless of their Inventory permission — could create catalogue
 *  products from the mobile app. This proves the gate now matches its
 *  siblings.
 */
class InventoryApiProductPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithInventoryPermission(bool $edit): User
    {
        $module = Module::firstOrCreate(
            ['slug' => 'inventory'],
            ['name' => 'Inventory', 'icon' => '', 'sort_order' => 10]
        );

        $role = Role::create([
            'name'      => 'ApiInvPermTest ' . uniqid(),
            'slug'      => 'api_inv_perm_test_' . uniqid(),
            'category'  => Role::CATEGORY_STAFF,
            'is_system' => false,
        ]);

        RoleModulePermission::create([
            'role_id'    => $role->id,
            'module_id'  => $module->id,
            'can_view'   => true,
            'can_edit'   => $edit,
            'can_delete' => false,
        ]);

        return User::factory()->create([
            'role'      => 'front_desk',
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_view_only_user_is_rejected_creating_a_product_via_api(): void
    {
        $user = $this->userWithInventoryPermission(edit: false);
        Sanctum::actingAs($user, ['*']);

        $resp = $this->postJson('/api/v1/inventory/products', [
            'product_name' => 'API Gate Test Product',
            'item_code'    => 'API-GATE-' . now()->format('His') . rand(100, 999),
        ]);

        $resp->assertStatus(403);
        $this->assertDatabaseMissing('inventory_items', ['product_name' => 'API Gate Test Product']);
    }

    public function test_edit_permission_user_is_not_blocked_by_the_gate(): void
    {
        $user = $this->userWithInventoryPermission(edit: true);
        Sanctum::actingAs($user, ['*']);

        $resp = $this->postJson('/api/v1/inventory/products', [
            'product_name' => 'API Gate Test Product Edit',
            'item_code'    => 'API-GATE-EDIT-' . now()->format('His') . rand(100, 999),
        ]);

        // Must not be the 403 the permission gate would produce. Whatever
        // the controller's own validation/response shape is, it's a
        // different status than the gate's.
        $this->assertNotEquals(403, $resp->getStatusCode());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $resp = $this->postJson('/api/v1/inventory/products', [
            'product_name' => 'No Auth Product',
            'item_code'    => 'NOAUTH-' . now()->format('His'),
        ]);

        $resp->assertStatus(401);
    }
}
