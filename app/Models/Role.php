<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'color', 'is_system',
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

    /* ── System role slugs ── */
    const ADMIN      = 'admin';
    const MANAGER    = 'manager';
    const DOCTOR     = 'doctor';
    const ASSISTANT  = 'assistant';
    const FRONT_DESK = 'front_desk';
    const ACCOUNTS   = 'accounts';
}
