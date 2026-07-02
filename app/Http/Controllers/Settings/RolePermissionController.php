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
        // Order: doctors first within each category, then by id
        $roles = Role::withCount('users')
                     ->orderByRaw("FIELD(category, 'doctor', 'staff')")
                     ->orderBy('id')
                     ->get();

        // Group for sidebar: [ 'doctor' => Collection, 'staff' => Collection ]
        $rolesByCategory = $roles->groupBy('category');

        $modules = Module::orderBy('sort_order')->get()->groupBy('section');

        // Pre-load ALL permissions for ALL roles → embed in page as JSON
        // so Alpine never needs an async fetch just to display toggles
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

        return view('settings.roles.index', compact('roles', 'rolesByCategory', 'modules', 'allPermissions'));
    }

    /**
     * Return permissions for a role in the format expected by settings/index.blade.php JS.
     * URL: GET /settings/roles/{role}/permissions
     * Response: { permissions: { module_slug: { view, edit, delete } } }
     */
    public function permissions(Role $role)
    {
        $perms = RoleModulePermission::where('role_id', $role->id)
            ->with('module')
            ->get();

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

        return response()->json(['permissions' => $map]);
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

        \App\Models\AuditLog::event('role_permissions_updated', auth()->id(),
            ['permissions' => $data['permissions']],
            ['module' => 'roles', 'auditable_type' => Role::class, 'auditable_id' => $role->id]);

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
            'category'    => 'nullable|in:doctor,staff',
        ]);

        $slug = strtolower(str_replace(' ', '_', $data['name']));

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'category'    => $data['category'] ?? 'staff',
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#6a0f70',
            'is_system'   => false,
        ]);

        \App\Models\AuditLog::event('role_created', auth()->id(),
            ['name' => $role->name, 'slug' => $role->slug],
            ['module' => 'roles', 'auditable_type' => Role::class, 'auditable_id' => $role->id]);

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

        \App\Models\AuditLog::event('role_deleted', auth()->id(),
            ['name' => $role->name, 'slug' => $role->slug],
            ['module' => 'roles', 'auditable_type' => Role::class, 'auditable_id' => $role->id]);

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
