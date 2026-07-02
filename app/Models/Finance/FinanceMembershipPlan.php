<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FinanceMembershipPlan
 *
 * Represents an AOCP membership tier (e.g. Basic, Premium, Family).
 * Benefits stored as JSON — see getBenefitList() for structure.
 *
 * Table: finance_membership_plans
 */
class FinanceMembershipPlan extends Model
{
    protected $table = 'finance_membership_plans';

    protected $fillable = [
        'clinic_id',
        'plan_name',
        'description',
        'price',
        'duration',
        'benefits',
        'discount_percentage',
        'is_active',
        // Family options
        'family_option',
        'addon_price',
        'max_family_members',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'addon_price'         => 'decimal:2',
        'max_family_members'  => 'integer',
        'benefits'            => 'array',
        'is_active'           => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function patientMemberships(): HasMany
    {
        return $this->hasMany(FinancePatientMembership::class, 'plan_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return benefits array with safe defaults.
     * Structure:
     * [
     *   'free_consultation'  => true/false,
     *   'free_xray'          => true/false,
     *   'free_scaling'       => true/false,
     *   'discount_percent'   => 0-100 (applied to all treatments),
     *   'free_treatments'    => ['Cleaning', 'Fluoride', ...],  // treatment names
     *   'notes'              => 'Any custom text shown to front desk',
     * ]
     */
    public function getBenefitList(): array
    {
        $defaults = [
            'free_consultation' => false,
            'free_xray'         => false,
            'free_scaling'      => false,
            'discount_percent'  => 0,
            'free_treatments'   => [],
            'notes'             => '',
        ];

        return array_merge($defaults, $this->benefits ?? []);
    }

    /**
     * Human-readable duration label.
     */
    public function getDurationLabelAttribute(): string
    {
        return match ($this->duration) {
            'monthly'     => '1 Month',
            'quarterly'   => '3 Months',
            'half_yearly' => '6 Months',
            'yearly'      => '1 Year',
            default       => ucfirst($this->duration),
        };
    }

    // -------------------------------------------------------------------------
    // Family helpers
    // -------------------------------------------------------------------------

    /** Does this plan support any family option? */
    public function isFamilyPlan(): bool
    {
        return $this->family_option !== 'none';
    }

    /** Per-member add-on pricing model (head + individual add-ons). */
    public function isAddonModel(): bool
    {
        return $this->family_option === 'addon';
    }

    /** Flat bundle price covering all family members together. */
    public function isBundleModel(): bool
    {
        return $this->family_option === 'bundle';
    }

    /**
     * Human-readable family option label.
     */
    public function getFamilyOptionLabelAttribute(): string
    {
        return match ($this->family_option) {
            'addon'  => 'Add-on (per member)',
            'bundle' => 'Bundle (flat family price)',
            default  => 'Individual only',
        };
    }

    /**
     * Summary of benefits as a readable string (for front desk tooltip / display).
     */
    public function getBenefitSummaryAttribute(): string
    {
        $b    = $this->getBenefitList();
        $bits = [];

        if ($b['free_consultation']) $bits[] = 'Free consultation';
        if ($b['free_xray'])         $bits[] = 'Free X-ray';
        if ($b['free_scaling'])      $bits[] = 'Free single scaling';
        if ($b['discount_percent'] > 0) $bits[] = $b['discount_percent'] . '% off all treatments';
        if (!empty($b['free_treatments'])) {
            $bits[] = 'Free: ' . implode(', ', $b['free_treatments']);
        }

        return empty($bits) ? 'No benefits defined' : implode(' · ', $bits);
    }
}
