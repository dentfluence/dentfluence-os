<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * BranchScope (Phase A — data isolation)
 * --------------------------------------
 * Automatically limits queries on branch-owned models to the logged-in user's
 * branch, so one clinic branch can never accidentally read another's records —
 * even if a developer forgets a manual `where('branch_id', ...)`.
 *
 * Deliberately a NO-OP in three cases, to stay safe:
 *   1. No authenticated user (console commands, queue jobs, seeders, webhooks)
 *      — they must be able to operate across all branches.
 *   2. Admin / clinic-owner roles — they legitimately see every branch.
 *   3. A user with no branch_id set — fail open rather than hide everything.
 *
 * Because the app is currently single-login (everyone is admin), this scope is
 * effectively inert today and changes nothing — it switches on automatically
 * once per-role logins are enabled (Phase 12), exactly like the API role gates.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return; // console / jobs / unauthenticated
        }

        if (method_exists($user, 'isAdminRole') && $user->isAdminRole()) {
            return; // admins see all branches
        }

        if (empty($user->branch_id)) {
            return; // no branch assigned — don't hide everything
        }

        $builder->where($model->getTable() . '.branch_id', $user->branch_id);
    }
}
