<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Settings\RolePermissionController;
use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;

class HrRoleController extends RolePermissionController
{
    /**
     * Override index() to render the HR layout view instead of the Settings one.
     * All other methods (store, update, destroy, permissions) are inherited unchanged.
     */
    public function index()
    {
        $roles = Role::withCount('users')
                     ->orderByRaw("FIELD(category, 'doctor', 'staff')")
                     ->orderBy('id')
                     ->get();

        $rolesByCategory = $roles->groupBy('category');
        $modules         = Module::orderBy('sort_order')->get()->groupBy('section');

        $allPermissions = RoleModulePermission::with('module')
            ->whereIn('role_id', $roles->pluck('id'))
            ->get()
            ->groupBy('role_id')
            ->map(function ($perms) {
                $map = [];
                foreach ($perms as $p) {
                    if ($p->module) {
                        $map[$p->module->slug] = [
                            'view'   => (bool) $p->can_view,
                            'edit'   => (bool) $p->can_edit,
                            'delete' => (bool) $p->can_delete,
                        ];
                    }
                }
                return $map;
            });

        return view('hr.roles.index', compact('roles', 'rolesByCategory', 'modules', 'allPermissions'));
    }
}
