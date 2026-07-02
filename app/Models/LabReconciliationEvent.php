<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabReconciliationEvent — append-only audit trail for reconciliation changes.
 * No updated_at column — events are never modified.
 */
class LabReconciliationEvent extends Model
{
    protected $table = 'lab_reconciliation_events';

    public $timestamps = false;  // only created_at (set via useCurrent in migration)

    protected $fillable = [
        'reconciliation_id',
        'event_type',
        'from_status',
        'to_status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(LabMonthlyReconciliation::class, 'reconciliation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /** Human-readable event label */
    public function getEventLabel(): string
    {
        return match ($this->event_type) {
            'created'          => 'Reconciliation created',
            'case_added'       => 'Case added',
            'case_removed'     => 'Case removed',
            'submitted'        => 'Submitted for review',
            'approved'         => 'Approved',
            'paid'             => 'Payment recorded',
            'disputed'         => 'Placed in dispute',
            'dispute_resolved' => 'Dispute resolved',
            'note_added'       => 'Note added',
            default            => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }
}
