<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch ABDM configuration (HIP/HIU ids, gateway endpoint, key references).
 *
 * Credential columns hold REFERENCES into the secret store, never secrets.
 * `is_enabled` defaults false — the per-facility kill switch.
 */
class FacilityAbdmConfig extends Model
{
    protected $table = 'facility_abdm_config';

    protected $fillable = [
        'branch_id',
        'environment',
        'hip_id',
        'hiu_id',
        'hfr_id',
        'gateway_base_url',
        'client_id_ref',
        'client_secret_ref',
        'signing_key_ref',
        'consent_default_expiry_days',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled'                  => 'boolean',
        'consent_default_expiry_days' => 'integer',
    ];

    /** Hide credential references from any serialization, just in case. */
    protected $hidden = [
        'client_id_ref',
        'client_secret_ref',
        'signing_key_ref',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
