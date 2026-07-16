<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CaseConsentSnapshot — IMMUTABLE, pinned at ACCEPT (frozen §5.5/§6). What the
 * patient confirmed (shown + chosen + prices) with IP / user-agent for audit.
 */
class CaseConsentSnapshot extends Model
{
    protected $fillable = [
        'patient_journey_id', 'snapshot', 'estimate_total', 'taken_at', 'ip', 'user_agent',
    ];

    protected $casts = [
        'snapshot'       => 'array',
        'estimate_total' => 'decimal:2',
        'taken_at'       => 'datetime',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(PatientJourney::class, 'patient_journey_id');
    }
}
