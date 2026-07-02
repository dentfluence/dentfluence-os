<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generated FHIR resource/Bundle, owned by any internal record (polymorphic).
 */
class FhirDocument extends Model
{
    protected $fillable = [
        'owner_type', 'owner_id', 'resource_type', 'fhir_id', 'version',
        'status', 'bundle_type', 'content', 'content_ref', 'content_hash',
        'signed', 'signature_ref', 'generated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'signed'  => 'boolean',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** Decode the stored FHIR JSON back into an array. */
    public function toFhirArray(): array
    {
        return $this->content ? (json_decode($this->content, true) ?: []) : [];
    }
}
