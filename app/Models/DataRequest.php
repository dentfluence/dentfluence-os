<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DataRequest (DPDP 5.2)
 * ----------------------
 * One patient-rights request: access / correction / erasure / grievance / nominee.
 */
class DataRequest extends Model
{
    use Auditable, SoftDeletes;

    protected $auditModule = 'data_rights';

    public const TYPES    = ['access', 'correction', 'erasure', 'grievance', 'nominee'];
    public const STATUSES = ['pending', 'in_progress', 'completed', 'rejected'];

    protected $fillable = [
        'reference', 'patient_id', 'branch_id', 'type', 'status', 'details',
        'requested_via', 'requester_name', 'requested_at', 'due_at',
        'assigned_to', 'resolution', 'resolved_by', 'resolved_at', 'payload',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'due_at'       => 'datetime',
        'resolved_at'  => 'datetime',
        'payload'      => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Open = still needs work. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    /** Past its SLA due date and not yet resolved. */
    public function isOverdue(): bool
    {
        return $this->due_at
            && in_array($this->status, ['pending', 'in_progress'], true)
            && $this->due_at->isPast();
    }
}
