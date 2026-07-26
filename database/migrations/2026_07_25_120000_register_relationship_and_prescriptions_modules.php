<?php

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Phase 1 · Slice 1.3 — module CATALOGUE REGISTRATION (CEO-approved).
 *
 * Relationships (PRE) and Prescriptions were the only production surfaces with
 * no Module row, which meant a Clinic Owner could not configure access to them
 * in Settings → Roles at all — the routes were therefore auth-only. This adds
 * the two missing catalogue entries to the EXISTING permission architecture.
 * No new permission system, no schema change: two rows in `modules`, plus
 * default `role_module_permissions` rows so nobody is locked out on deploy.
 *
 * Defaults are DERIVED from each role's existing configuration, never from job
 * titles:
 *   relationship  ← mirrors that role's existing `communication` grant
 *                   (relationship work is the communication work they already do)
 *   prescriptions ← mirrors that role's existing `patients` grant, minus delete
 *                   (prescribing follows clinical access; deleting an Rx is
 *                   destructive and must be granted deliberately by the owner)
 * Admin gets [1,1,1]. Roles with neither source grant get nothing and the owner
 * can switch them on in Settings at any time.
 *
 * Idempotent: updateOrCreate by slug / (role,module). Down() removes only these
 * two modules and their permission rows.
 */
return new class extends Migration
{
    private const MODULES = [
        [
            'name'       => 'Relationships (PRE)',
            'slug'       => 'relationship',
            'section'    => 'communication',
            'sort_order' => 101,
            'icon'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/>',
            'mirror'     => 'communication',
            'allow_delete_mirror' => true,
        ],
        [
            'name'       => 'Prescriptions',
            'slug'       => 'prescriptions',
            'section'    => 'clinical',
            'sort_order' => 102,
            'icon'       => '<path d="M4 3h6a4 4 0 0 1 0 8H4z"/><path d="M4 11v10"/><path d="M10 11l8 10"/><path d="M14 15l6-6"/>',
            'mirror'     => 'patients',
            'allow_delete_mirror' => false,
        ],
    ];

    public function up(): void
    {
        foreach (self::MODULES as $spec) {
            $module = Module::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'name'       => $spec['name'],
                    'icon'       => $spec['icon'],
                    'section'    => $spec['section'],
                    'sort_order' => $spec['sort_order'],
                ]
            );

            $mirrorModule = Module::where('slug', $spec['mirror'])->first();

            foreach (Role::all() as $role) {
                // Admin/owner keeps full access.
                if ($role->slug === Role::ADMIN) {
                    $this->grant($role->id, $module->id, 1, 1, 1);
                    continue;
                }

                if (! $mirrorModule) {
                    continue;
                }

                $mirror = RoleModulePermission::where('role_id', $role->id)
                    ->where('module_id', $mirrorModule->id)
                    ->first();

                if (! $mirror || ! $mirror->can_view) {
                    continue; // no source grant → owner decides in Settings
                }

                $this->grant(
                    $role->id,
                    $module->id,
                    1,
                    (int) $mirror->can_edit,
                    $spec['allow_delete_mirror'] ? (int) $mirror->can_delete : 0
                );
            }
        }
    }

    public function down(): void
    {
        $ids = Module::whereIn('slug', ['relationship', 'prescriptions'])->pluck('id');

        RoleModulePermission::whereIn('module_id', $ids)->delete();
        Module::whereIn('id', $ids)->delete();
    }

    private function grant(int $roleId, int $moduleId, int $view, int $edit, int $delete): void
    {
        RoleModulePermission::updateOrCreate(
            ['role_id' => $roleId, 'module_id' => $moduleId],
            ['can_view' => $view, 'can_edit' => $edit, 'can_delete' => $delete]
        );
    }
};
