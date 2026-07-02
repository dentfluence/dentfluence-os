<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Branch (a clinic facility).
 *
 * The branches table has existed since multi-branch support was added, but never
 * had a model class. ABDM needs one because a branch maps to a FHIR Organization
 * and holds the Health Facility Registry (HFR) identity + per-facility ABDM config.
 *
 * Nothing here changes existing behaviour — it just gives the existing table a model.
 */
class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'is_active',
        // ABDM / facility identity (added 2026-06-27)
        'hfr_id',
        'facility_verification_status',
        'facility_type',
        'organization_mapping_id',
        'geo_lat',
        'geo_lng',
        'fhir_organization_id',
        'fhir_location_id',
        'digital_certificate_ref',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'geo_lat'   => 'decimal:7',
        'geo_lng'   => 'decimal:7',
    ];

    /* ── Relationships ── */

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'branch_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /** Per-facility ABDM configuration (HIP/HIU ids, endpoints, key references). */
    public function abdmConfig(): HasOne
    {
        return $this->hasOne(FacilityAbdmConfig::class, 'branch_id');
    }

    /** Per-branch settings (groups: abdm, fhir, consent, sync, feature_flags...). */
    public function settings(): HasMany
    {
        return $this->hasMany(BranchSetting::class, 'branch_id');
    }

    /* ── Helpers ── */

    /** Has this facility been verified against the Health Facility Registry? */
    public function isHfrVerified(): bool
    {
        return $this->facility_verification_status === 'verified';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
