<?php

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use Illuminate\Database\Migrations\Migration;

/**
 * Cleans up and activates the Roles & Permissions module list.
 *
 * 1. Removes the 'prm' module row. PRM was retired in Phase 8 (2026-07-03) —
 *    routes/prm.php was deleted, every write now goes through PRE
 *    (routes/relationship.php + routes/communication.php). The toggle in
 *    Roles & Permissions did nothing; it just confused the screen.
 *    (cascadeOnDelete on role_module_permissions.module_id cleans up the
 *    matching permission rows automatically.)
 *
 * 2. Adds a 'communication' module row for the Communication OS / PRE
 *    (routes/communication.php). That area was previously guarded only by
 *    CommunicationModuleAccess, which let any authenticated user in
 *    regardless of role — the permission toggle is now real
 *    (see CommunicationModuleAccess::handle()).
 *
 * 3. Fills in missing role_module_permission rows for 'hr', 'cms' and
 *    'communication' so that turning on real enforcement (see the
 *    accompanying route/middleware changes) does not lock anyone out of
 *    something they can access today. These three areas had little or no
 *    enforcement before this change, so "default" here means "preserve
 *    today's de-facto access", not an ideal target state. Existing rows are
 *    never overwritten — only missing role+module combinations are added.
 *    Sumit should review and tighten these per role now that the toggles
 *    actually do something.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Remove dead PRM module ───────────────────────────────────────
        Module::where('slug', 'prm')->delete();

        // ── 2. Add Communication OS module ──────────────────────────────────
        $communication = Module::updateOrCreate(
            ['slug' => 'communication'],
            [
                'name'       => 'Communication (PRE)',
                'icon'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                'section'    => 'communication',
                'sort_order' => 7, // matches sidebar order — see migration 200003
            ]
        );

        $roles = Role::all()->keyBy('slug');

        // Zero-regression baseline: Communication OS was fully open to any
        // authenticated user before this change, so grant full view+edit to
        // every system role today. Delete stays off by default for everyone.
        foreach ($roles as $slug => $role) {
            RoleModulePermission::firstOrCreate(
                ['role_id' => $role->id, 'module_id' => $communication->id],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => false]
            );
        }

        // ── 3. Fill gaps for 'hr' and 'cms' (missing = previously unrestricted) ──
        $hr  = Module::where('slug', 'hr')->first();
        $cms = Module::where('slug', 'cms')->first();

        $hrDefaults = [
            Role::DOCTOR     => [true, true, false],
            Role::ASSISTANT  => [true, true, false],
            Role::FRONT_DESK => [true, true, false],
            Role::ACCOUNTS   => [true, true, false],
        ];

        $cmsDefaults = [
            Role::MANAGER    => [true, true, false],
            Role::ASSISTANT  => [true, false, false],
            Role::FRONT_DESK => [true, true, false],
            Role::ACCOUNTS   => [true, false, false],
        ];

        foreach ([[$hr, $hrDefaults], [$cms, $cmsDefaults]] as [$module, $defaults]) {
            if (! $module) continue;

            foreach ($defaults as $slug => [$view, $edit, $delete]) {
                $role = $roles[$slug] ?? null;
                if (! $role) continue;

                RoleModulePermission::firstOrCreate(
                    ['role_id' => $role->id, 'module_id' => $module->id],
                    ['can_view' => $view, 'can_edit' => $edit, 'can_delete' => $delete]
                );
            }
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — this is a one-time data cleanup/activation,
        // not a reversible schema change. Re-running the seeder recreates the
        // old 'prm' row if it's ever genuinely needed again.
    }
};
