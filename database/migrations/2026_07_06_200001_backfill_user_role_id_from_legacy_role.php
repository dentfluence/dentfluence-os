<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfills users.role_id for any user who has a legacy `role` string but
 * no role_id yet. role_id is what actually drives module permissions
 * (User::canAccess() / RoleModulePermission); the HR > Staff form only ever
 * set the legacy string, so anyone hired through HR previously ended up
 * with zero permissions anywhere in the app until someone separately
 * assigned a role via Settings > Staff. HrStaffController now keeps both
 * in sync going forward — this migration is the one-time catch-up for
 * accounts created before that fix.
 *
 * Non-destructive: only fills in role_id where it is currently null. Never
 * overwrites a role_id someone already assigned deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleIdsBySlug = Role::pluck('id', 'slug');

        if ($roleIdsBySlug->isEmpty()) {
            // Roles/modules haven't been seeded yet on this environment — nothing to backfill.
            return;
        }

        DB::table('users')
            ->whereNull('role_id')
            ->whereNotNull('role')
            ->select('id', 'role')
            ->chunkById(200, function ($users) use ($roleIdsBySlug) {
                foreach ($users as $user) {
                    $slug   = Role::slugForLegacyRoleString($user->role);
                    $roleId = $roleIdsBySlug[$slug] ?? null;

                    if ($roleId) {
                        DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally a no-op — we don't want a rollback to wipe role_id
        // assignments that may since have been deliberately customized.
    }
};
