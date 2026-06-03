<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * Show the Role & Permissions management page.
     */
    public function index()
    {
        $roles   = Role::withCount('users')->orderBy('id')->get();
        $modules = Module::orderBy('sort_order')->get()->groupBy('section');

        return view('settings.roles.index', compact('roles', 'modules'));
    }

    /**
     * Return permission matrix for a specific role as JSON (for Alpine.js).
     */
    public function show(Role $role)
    {
        $permissions = RoleModulePermission::where('role_id', $role->id)
            ->with('module')
            ->get()
            ->keyBy('module.slug');

        return response()->json($permissions);
    }

    /**
     * Save updated permissions for a role.
     * Expects: { permissions: { module_slug: { view, edit, delete } } }
     */
    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions'              => 'required|array',
            'permissions.*.view'       => 'boolean',
            'permissions.*.edit'       => 'boolean',
            'permissions.*.delete'     => 'boolean',
        ]);

        $modules = Module::all()->keyBy('slug');

        foreach ($data['permissions'] as $slug => $perms) {
            if (! isset($modules[$slug])) continue;

            RoleModulePermission::updateOrCreate(
                ['role_id' => $role->id, 'module_id' => $modules[$slug]->id],
                [
                    'can_view'   => $perms['view']   ?? false,
                    'can_edit'   => $perms['edit']   ?? false,
                    'can_delete' => $perms['delete'] ?? false,
                ]
            );
        }

        return response()->json(['message' => 'Permissions saved.']);
    }

    /**
     * Create a custom role.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:60',
            'description' => 'nullable|string|max:255',
            'color'       => 'nullable|string|max:7',
        ]);

        $slug = strtolower(str_replace(' ', '_', $data['name']));

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#6a0f70',
            'is_system'   => false,
        ]);

        return response()->json(['role' => $role]);
    }

    /**
     * Delete a custom (non-system) role.
     */
    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
