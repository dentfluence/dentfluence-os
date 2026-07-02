<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single identifier belonging to a practitioner (user).
 * Types: internal | hpr_id | council_reg | fhir_logical.
 * Maps to FHIR Practitioner.identifier[].
 */
class PractitionerIdentifier extends Model
{
    use SoftDeletes;

    public const TYPE_INTERNAL     = 'internal';
    public const TYPE_HPR_ID       = 'hpr_id';
    public const TYPE_COUNCIL_REG  = 'council_reg';
    public const TYPE_FHIR_LOGICAL = 'fhir_logical';

    protected $fillable = [
        'user_id',
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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('identifier_type', $type);
    }
}
