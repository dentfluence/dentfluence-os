<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * RetentionPolicy (DPDP 5.4)
 * --------------------------
 * How long a given kind of data is kept. Used by the dry-run report; no
 * automatic deletion happens unless purging is explicitly enabled later.
 */
class RetentionPolicy extends Model
{
    use Auditable;

    protected $auditModule = 'retention';

    public const DATA_TYPES = ['audit_logs', 'data_requests', 'breaches', 'consent_logs', 'inactive_patients'];
    public const ACTIONS    = ['report', 'anonymise', 'delete'];

    protected $fillable = ['name', 'data_type', 'description', 'retain_days', 'action', 'active'];

    protected $casts = [
        'retain_days' => 'integer',
        'active'      => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
