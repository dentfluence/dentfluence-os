<?php

namespace Tests\Feature\Appointments\Concerns;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Slice 3 helpers — build users whose access flows through the real
 * role_id / RoleModulePermission table (the same one the middleware reads),
 * so permission tests exercise production authorization, not a stub.
 */
trait InteractsWithPermissions
{
    private bool $rolesSeeded = false;

    protected function seedRoles(): void
    {
        if (! $this->rolesSeeded) {
            $this->seed(RolePermissionSeeder::class);
            $this->rolesSeeded = true;
        }
    }

    /** A user bound to a seeded SYSTEM role (admin/front_desk/doctor/assistant/…). */
    protected function userForSystemRole(string $slug, array $overrides = []): User
    {
        $this->seedRoles();
        $role = Role::where('slug', $slug)->firstOrFail();

        return User::factory()->create(array_merge([
            'role'      => $slug,       // legacy string (admin detection / doctor routing)
            'role_id'   => $role->id,   // governs module permissions
            'branch_id' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * A user with an ad-hoc appointments permission triple — used for the
     * view-only / edit-only / delete-only personas. Legacy role is a non-admin
     * string so the admin bypass never fires.
     */
    protected function userForAppointmentPermission(bool $view, bool $edit, bool $delete): User
    {
        $this->seedRoles();
        $module = Module::where('slug', 'appointments')->firstOrFail();

        $role = Role::create([
            'name'      => 'PermTest ' . uniqid(),
            'slug'      => 'perm_test_' . uniqid(),
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
            'role'      => 'assistant', // non-admin legacy string
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }
}
