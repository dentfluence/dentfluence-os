<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single identifier belonging to a patient.
 *
 * A patient has MANY of these (internal id, ABHA number, ABHA address, gov id,
 * insurance...). This is the polymorphic-identity table that maps to FHIR
 * Patient.identifier[] and frees us from ever adding another ID column.
 */
class PatientIdentifier extends Model
{
    use SoftDeletes;

    /** Identifier type constants (kept as strings for forward-compat). */
    public const TYPE_INTERNAL      = 'internal';
    public const TYPE_ABHA_NUMBER   = 'abha_number';
    public const TYPE_ABHA_ADDRESS  = 'abha_address';
    public const TYPE_AADHAAR_REF   = 'aadhaar_ref';
    public const TYPE_INSURANCE     = 'insurance';
    public const TYPE_FHIR_LOGICAL  = 'fhir_logical';

    protected $fillable = [
        'patient_id',
        'identifier_type',
        'system_uri',
        'value',
        'value_last4',
        'status',
        'is_primary',
        'verified_at',
        'source',
        'meta',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'verified_at' => 'datetime',
        'meta'        => 'array',
        // Encrypted PHI at rest (Phase A). The full identifier (ABHA number,
        // gov-id, insurance no.) is encrypted; `value_last4` stays plaintext for
        // display/search. Resilient cast — see app/Casts/Encrypted.php.
        'value'       => \App\Casts\Encrypted::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /* ── Scopes ── */

    public function scopeOfType($query, string $type)
    {
        return $query->where('identifier_type', $type);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}
