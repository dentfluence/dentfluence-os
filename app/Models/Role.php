<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'color', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /* ── Relationships ── */

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RoleModulePermission::class);
    }

    /** Fine-grained billing action rules (manual discount, wallet refund, …). */
    public function billingPermissions(): HasMany
    {
        return $this->hasMany(RoleBillingPermission::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'role_module_permissions')
                    ->withPivot('can_view', 'can_edit', 'can_delete')
                    ->withTimestamps();
    }

    /* ── Helpers ── */

    /**
     * Check if this role can perform an action on a module slug.
     * Action: 'view' | 'edit' | 'delete'
     */
    public function can(string $module, string $action = 'view'): bool
    {
        $perm = $this->permissions()
                     ->whereHas('module', fn($q) => $q->where('slug', $module))
                     ->first();

        if (! $perm) return false;

        return match ($action) {
            'view'   => (bool) $perm->can_view,
            'edit'   => (bool) $perm->can_edit,
            'delete' => (bool) $perm->can_delete,
            default  => false,
        };
    }

    /**
     * Whether this role may perform a fine-grained billing action.
     * Admin is always allowed. Everyone else is checked against
     * role_billing_permissions (default: not allowed).
     */
    public function billingCan(string $actionKey): bool
    {
        if ($this->slug === self::ADMIN) {
            return true;
        }

        $perm = $this->billingPermissions()
                     ->where('action_key', $actionKey)
                     ->first();

        return $perm ? (bool) $perm->is_allowed : false;
    }

    /**
     * Numeric limit for a value-bearing action (e.g. max manual discount).
     * Returns ['value' => float|null, 'type' => 'percentage'|'flat'|null].
     * A null value means unlimited (used for Admin and uncapped roles).
     */
    public function billingLimit(string $actionKey): array
    {
        if ($this->slug === self::ADMIN) {
            return ['value' => null, 'type' => null]; // unlimited
        }

        $perm = $this->billingPermissions()
                     ->where('action_key', $actionKey)
                     ->first();

        if (! $perm || ! $perm->is_allowed) {
            return ['value' => 0, 'type' => null]; // not allowed = zero
        }

        return [
            'value' => $perm->limit_value !== null ? (float) $perm->limit_value : null,
            'type'  => $perm->limit_type,
        ];
    }

    /* ── System role slugs ── */
    const ADMIN      = 'admin';
    const MANAGER    = 'manager';
    const DOCTOR     = 'doctor';
    const ASSISTANT  = 'assistant';
    const FRONT_DESK = 'front_desk';
    const ACCOUNTS   = 'accounts';

    /* ── Role categories ── */
    const CATEGORY_DOCTOR = 'doctor';
    const CATEGORY_STAFF  = 'staff';
}
