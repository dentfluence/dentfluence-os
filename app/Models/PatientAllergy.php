<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A patient's recorded allergy. Maps to FHIR AllergyIntolerance.
 */
class PatientAllergy extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id', 'substance', 'snomed_code', 'category',
        'criticality', 'reaction', 'recorded_by', 'source',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
