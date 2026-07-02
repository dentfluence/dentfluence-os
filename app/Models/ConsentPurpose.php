<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ConsentPurpose
 * --------------
 * One thing a patient can consent to (treatment, WhatsApp, ABDM sharing, ...).
 * See the consent_purposes migration for the full explanation.
 *
 * Changes to purposes are themselves audited via the Auditable trait, so we
 * have a record of when wording/settings changed.
 */
class ConsentPurpose extends Model
{
    use Auditable;

    /** Tag audit-log rows for this model with the "consent" module name. */
    protected $auditModule = 'consent';

    protected $fillable = [
        'key', 'name', 'description', 'category',
        'is_mandatory', 'requires_explicit',
        'version', 'retention_days', 'active', 'sort_order',
    ];

    protected $casts = [
        'is_mandatory'      => 'boolean',
        'requires_explicit' => 'boolean',
        'active'            => 'boolean',
        'version'           => 'integer',
        'retention_days'    => 'integer',
        'sort_order'        => 'integer',
    ];

    /** All per-patient consent rows pointing at this purpose. */
    public function patientConsents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    /** Only purposes that are currently in use, in display order. */
    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
