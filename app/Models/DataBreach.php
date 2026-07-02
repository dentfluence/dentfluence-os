<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DataBreach (DPDP 5.3)
 * ---------------------
 * One personal-data breach record + its reporting/notification milestones.
 */
class DataBreach extends Model
{
    use Auditable, SoftDeletes;

    protected $auditModule = 'data_breaches';

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const STATUSES   = ['open', 'contained', 'reported', 'closed'];

    protected $fillable = [
        'reference', 'title', 'description', 'severity', 'nature',
        'affected_scope', 'affected_count', 'occurred_at', 'discovered_at',
        'status', 'reported_to_board_at', 'board_reference',
        'patients_notified_at', 'created_by',
    ];

    protected $casts = [
        'occurred_at'          => 'datetime',
        'discovered_at'        => 'datetime',
        'reported_to_board_at' => 'datetime',
        'patients_notified_at' => 'datetime',
        'affected_count'       => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReported(): bool
    {
        return ! is_null($this->reported_to_board_at);
    }

    public function patientsNotified(): bool
    {
        return ! is_null($this->patients_notified_at);
    }
}
