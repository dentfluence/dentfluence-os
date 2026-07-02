<?php

namespace App\Traits;

use App\Models\Scopes\BranchScope;

/**
 * BelongsToBranch (Phase A — data isolation)
 * ------------------------------------------
 * Add `use BelongsToBranch;` to any model that has a `branch_id` column to
 * automatically scope all of its queries to the current user's branch (see
 * {@see BranchScope} for the safe-bypass rules).
 *
 * Use `Model::withoutGlobalScope(BranchScope::class)` on the rare query that
 * intentionally needs to cross branches.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());
    }
}
