<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JourneyCustomOption — a treatment the dentist added to a specific journey's
 * options, drawn from this clinic's Treatment list (priced live). Shows as an
 * extra option card in the patient microsite alongside the authored tree.
 */
class JourneyCustomOption extends Model
{
    protected $fillable = [
        'patient_journey_id', 'treatment_id', 'label', 'is_recommended', 'sort_order',
    ];

    protected $casts = [
        'is_recommended' => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(PatientJourney::class, 'patient_journey_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }
}
