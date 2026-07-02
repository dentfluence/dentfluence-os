<?php

namespace App\Models\Finance;

use App\Models\Patient;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MembershipBenefitLog
 *
 * Audit record for every free / discounted benefit a patient
 * has availed through their AOCP membership.
 *
 * Table: membership_benefit_logs
 */
class MembershipBenefitLog extends Model
{
    protected $table = 'membership_benefit_logs';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'membership_id',
        'invoice_id',
        'benefit_type',
        'benefit_label',
        'amount_saved',
        'notes',
        'created_by',
        'availed_at',
    ];

    protected $casts = [
        'amount_saved' => 'decimal:2',
        'availed_at'   => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FinancePatientMembership::class, 'membership_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Icon / colour hint for the benefit type — used in Blade.
     */
    public function getBadgeColorAttribute(): string
    {
        return match ($this->benefit_type) {
            'free_consultation' => 'blue',
            'free_xray'         => 'indigo',
            'free_scaling'      => 'teal',
            'free_treatment'    => 'green',
            'pct_discount'      => 'purple',
            default             => 'gray',
        };
    }

    /**
     * Friendly label for the benefit type.
     */
    public function getBenefitTypeLabelAttribute(): string
    {
        return match ($this->benefit_type) {
            'free_consultation' => 'Free Consultation',
            'free_xray'         => 'Free X-Ray',
            'free_scaling'      => 'Free Scaling',
            'free_treatment'    => 'Free Treatment',
            'pct_discount'      => 'Discount',
            default             => ucfirst(str_replace('_', ' ', $this->benefit_type)),
        };
    }
}
