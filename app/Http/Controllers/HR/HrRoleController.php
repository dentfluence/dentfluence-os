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
                            'view'     => (bool) $p->can_view,
                            'edit'     => (bool) $p->can_edit,
                            'delete'   => (bool) $p->can_delete,
                            // `settings` and `scope` MUST be here even though
                            // this screen has no toggle for `settings`: the page
                            // posts this very map back to update(), so any key
                            // missing here is written as false/null on every
                            // save. can_settings was being silently wiped that
                            // way before 2026-08-26.
                            'settings' => (bool) $p->can_settings,
                            'scope'    => $p->data_scope ?? '',
                        ];
                    }
                }
                return $map;
            });

        return view('hr.roles.index', compact('roles', 'rolesByCategory', 'modules', 'allPermissions'));
    }
}
