<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponCode extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_uses_global',
        'max_uses_per_patient',
        'uses_count',
        'valid_from',
        'valid_until',
        'applicable_treatments',
        'min_invoice_amount',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'discount_value'         => 'decimal:2',
        'min_invoice_amount'     => 'decimal:2',
        'applicable_treatments'  => 'array',
        'valid_from'             => 'date',
        'valid_until'            => 'date',
        'is_active'              => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('valid_until')
                           ->orWhere('valid_until', '>=', today());
                     })
                     ->where(function ($q) {
                         $q->whereNull('valid_from')
                           ->orWhere('valid_from', '<=', today());
                     });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Calculate the discount ₹ amount for a given invoice total.
     * Returns 0 if the coupon is not applicable.
     */
    public function calculateDiscount(float $invoiceTotal): float
    {
        if ($invoiceTotal < $this->min_invoice_amount) return 0;

        if ($this->discount_type === 'flat') {
            return min($this->discount_value, $invoiceTotal); // can't exceed invoice total
        }

        return round($invoiceTotal * ($this->discount_value / 100), 2);
    }

    /** Check if this patient can still use this coupon. */
    public function canBeUsedByPatient(int $patientId): bool
    {
        if (!$this->is_active) return false;

        // Global limit
        if ($this->max_uses_global > 0 && $this->uses_count >= $this->max_uses_global) {
            return false;
        }

        // Per-patient limit
        $patientUses = $this->usages()->where('patient_id', $patientId)->count();
        if ($patientUses >= $this->max_uses_per_patient) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isPercentage(): bool
    {
        return $this->discount_type === 'percentage';
    }

    /** e.g. "10% off" or "₹200 flat" */
    public function discountLabel(): string
    {
        return $this->discount_type === 'percentage'
            ? $this->discount_value . '% off'
            : '₹' . number_format($this->discount_value, 0) . ' flat';
    }
}
