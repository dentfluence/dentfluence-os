<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One qualification/registration held by a practitioner (user).
 * Maps to FHIR Practitioner.qualification[].
 */
class PractitionerQualification extends Model
{
    protected $fillable = [
        'user_id',
        'degree',
        'institution',
        'year',
        'registration_number',
        'council_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
