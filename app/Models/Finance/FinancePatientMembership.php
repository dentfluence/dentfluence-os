<?php

namespace App\Models\Finance;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * FinancePatientMembership
 *
 * Enrollment record — one per patient per membership purchase.
 * A patient can have only one ACTIVE membership at a time.
 *
 * Table: finance_patient_memberships
 */
class FinancePatientMembership extends Model
{
    protected $table = 'finance_patient_memberships';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'plan_id',
        'transaction_id',
        'start_date',
        'end_date',
        'amount_paid',
        'status',
        'created_by',
        // Family fields
        'member_type',
        'family_head_membership_id',
        'family_name',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'amount_paid'  => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(FinanceMembershipPlan::class, 'plan_id');
    }

    /**
     * The head enrollment this add-on belongs to.
     * Returns null for individual/head records.
     */
    public function familyHead(): BelongsTo
    {
        return $this->belongsTo(self::class, 'family_head_membership_id');
    }

    /**
     * All add-on enrollments under this head enrollment.
     * Only meaningful when called on a head record.
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(self::class, 'family_head_membership_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('end_date', '>=', Carbon::today());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Is this membership currently valid?
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->end_date >= Carbon::today();
    }

    /**
     * Days remaining on this membership.
     */
    public function getDaysRemainingAttribute(): int
    {
        if (!$this->isActive()) return 0;
        return (int) Carbon::today()->diffInDays($this->end_date, false);
    }

    /** Is this a family head enrollment? */
    public function isHead(): bool
    {
        return $this->member_type === 'head';
    }

    /** Is this a family add-on enrollment? */
    public function isAddon(): bool
    {
        return $this->member_type === 'addon';
    }

    /**
     * Display name for the member list:
     * - Add-on: uses own family_name, else the linked member's family_name
     * - Any other member: returns own family_name (may be null)
     */
    public function getFamilyDisplayNameAttribute(): ?string
    {
        if ($this->member_type === 'addon') {
            return $this->family_name
                ?? $this->familyHead?->family_name;
        }
        return $this->family_name;
    }

    /**
     * How many active add-ons are attached to this member?
     * Add-ons themselves never have their own add-ons, so return 0 for them.
     */
    public function getActiveFamilyMemberCountAttribute(): int
    {
        if ($this->member_type === 'addon') return 0;
        return $this->familyMembers()->where('status', 'active')->count();
    }

    /**
     * Auto-mark expired memberships when their end_date passes.
     * Call via scheduled command or on-read check.
     */
    public static function expireStale(): int
    {
        $expired = static::with('patient')
                         ->where('status', 'active')
                         ->where('end_date', '<', Carbon::today())
                         ->get();

        foreach ($expired as $enrollment) {
            $enrollment->update(['status' => 'expired']);

            // Keep the patient row in sync so the AOCP badge reflects expiry.
            $enrollment->patient?->update([
                'membership_status'     => 'expired',
                'membership_expires_at' => $enrollment->end_date,
            ]);
        }

        return $expired->count();
    }
}
