<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JourneySentSnapshot — IMMUTABLE, pinned at SEND (frozen §5.5/§6). The exact
 * assembled block DTO + resolved prices + versions + curation the patient sees.
 */
class JourneySentSnapshot extends Model
{
    protected $fillable = [
        'patient_journey_id', 'snapshot', 'estimate_total', 'pinned_at',
    ];

    protected $casts = [
        'snapshot'       => 'array',
        'estimate_total' => 'decimal:2',
        'pinned_at'      => 'datetime',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(PatientJourney::class, 'patient_journey_id');
    }
}
