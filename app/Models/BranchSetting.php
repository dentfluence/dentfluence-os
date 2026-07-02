<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch key-value setting. Mirrors AppSetting but scoped to a branch.
 * Usage:
 *   BranchSetting::get($branchId, 'abdm_enabled', '0')
 *   BranchSetting::set($branchId, 'abdm_enabled', '1', 'feature_flags')
 *   BranchSetting::group($branchId, 'abdm')
 */
class BranchSetting extends Model
{
    protected $fillable = ['branch_id', 'group', 'key', 'value', 'updated_by'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Get a single value for a branch. */
    public static function get(int $branchId, string $key, mixed $default = null): mixed
    {
        $row = static::where('branch_id', $branchId)->where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    /** Set a single value for a branch. */
    public static function set(int $branchId, string $key, mixed $value, string $group = 'general', ?int $userId = null): void
    {
        static::updateOrCreate(
            ['branch_id' => $branchId, 'group' => $group, 'key' => $key],
            ['value' => $value, 'updated_by' => $userId]
        );
    }

    /** Get all settings in a group for a branch as key => value. */
    public static function group(int $branchId, string $group): array
    {
        return static::where('branch_id', $branchId)
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }
}
