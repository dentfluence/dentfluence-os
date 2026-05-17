<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class HuddleSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'role',
        'key',
        'value',
        'label',
        'description',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where(function (Builder $q) use ($role) {
            $q->where('role', $role)->orWhereNull('role');
        });
    }

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Get a setting value for a branch + role + key.
     * Returns $default if not found.
     */
    public static function getValue(
        int $branchId,
        string $key,
        ?string $role = null,
        mixed $default = null
    ): mixed {
        $query = static::forBranch($branchId)->forKey($key);

        if ($role !== null) {
            $query->forRole($role);
        }

        $setting = $query->latest('id')->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Upsert a setting.
     */
    public static function setValue(
        int $branchId,
        string $key,
        mixed $value,
        ?string $role = null,
    ): static {
        return static::updateOrCreate(
            ['branch_id' => $branchId, 'role' => $role, 'key' => $key],
            ['value' => $value]
        );
    }
}
