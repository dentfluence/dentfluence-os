<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleModulePermission extends Model
{
    // W-3: every create / update / delete lands in audit_logs
    // (hash-chained, append-only, shown at Settings > Activity Log).
    // the actual view/edit/delete/settings grid.
    use \App\Traits\Auditable;

    protected $auditModule = 'settings';

    protected $fillable = [
        'role_id', 'module_id', 'can_view', 'can_edit', 'can_delete', 'can_settings', 'data_scope',
    ];

    protected function casts(): array
    {
        return [
            'can_view'     => 'boolean',
            'can_edit'     => 'boolean',
            'can_delete'   => 'boolean',
            'can_settings' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
