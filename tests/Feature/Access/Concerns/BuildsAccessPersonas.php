<?php

namespace Tests\Feature\Access\Concerns;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Phase 1 · Slice 1.1 — persona builders for access characterization.
 *
 * Mirrors tests/Feature/Appointments/Concerns/InteractsWithPermissions (the
 * frozen Appointments pattern): every persona flows through the REAL
 * roles / modules / role_module_permissions tables — the owner-configured
 * system the middleware actually reads. Nothing is stubbed.
 */
trait BuildsAccessPersonas
{
    private bool $accessRolesSeeded = false;

    protected function seedAccessRoles(): void
    {
        if (! $this->accessRolesSeeded) {
            $this->seed(RolePermissionSeeder::class);
            $this->accessRolesSeeded = true;
        }
    }

    /**
     * A user whose custom role has ZERO module permission rows — the
     * strictest possible owner configuration. Legacy role string is a
     * non-admin value so the legacy-admin bypass never fires.
     */
    protected function zeroPermUser(string $legacyRole = 'assistant'): User
    {
        $this->seedAccessRoles();

        $role = Role::create([
            'name'      => 'Zero Access ' . uniqid(),
            'slug'      => 'zero_access_' . uniqid(),
            'category'  => Role::CATEGORY_STAFF,
            'is_system' => false,
        ]);

        return User::factory()->create([
            'role'      => $legacyRole,
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /** A user with one ad-hoc module permission triple on an arbitrarily-named role. */
    protected function userWithModulePerm(string $moduleSlug, bool $view, bool $edit, bool $delete, string $roleName = null): User
    {
        $this->seedAccessRoles();
        $module = Module::where('slug', $moduleSlug)->firstOrFail();

        $role = Role::create([
            'name'      => $roleName ?? ('AccessTest ' . uniqid()),
            'slug'      => 'access_test_' . uniqid(),
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
            'role'      => 'assistant',
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /** The legacy transition bypass: role string 'admin' with NO role_id. */
    protected function legacyAdminUser(): User
    {
        return User::factory()->create([
            'role'      => 'admin',
            'role_id'   => null,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /** Fresh model instance so canAccess() never reuses a cached roleModel relation. */
    protected function fresh(User $user): User
    {
        return User::findOrFail($user->id);
    }
}
