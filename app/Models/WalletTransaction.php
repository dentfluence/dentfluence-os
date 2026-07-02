<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'patient_id',
        'direction',
        'credit_type',
        'source',
        'campaign_name',
        'applicable_treatments',   // JSON array of treatment IDs; null = all
        'amount',
        'payment_mode',            // how the cash moved for advances/refunds (nullable)
        'expiry_date',
        'invoice_id',
        'invoice_number',          // Denormalized — stored on debit/refund for audit trail
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'expiry_date'            => 'date',
        'applicable_treatments'  => 'array',   // auto encode/decode JSON
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCredits($query)
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', 'debit');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('credit_type', 'promotional')
                     ->where('direction', 'credit')
                     ->whereBetween('expiry_date', [today(), today()->addDays($days)]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    /**
     * Check if this promo credit is usable for the given treatment IDs.
     * Returns true when: unrestricted (applicable_treatments is null), or overlap exists.
     */
    public function isApplicableFor(array $treatmentIds): bool
    {
        if ($this->applicable_treatments === null) {
            return true; // unrestricted — valid for all treatments
        }

        if (empty($treatmentIds)) {
            return false; // restricted credit, but no treatment on invoice — block
        }

        return count(array_intersect($this->applicable_treatments, $treatmentIds)) > 0;
    }

    /**
     * Human-readable label for applicable treatments.
     */
    public function applicableTreatmentsLabel(): string
    {
        if ($this->applicable_treatments === null) {
            return 'All treatments';
        }

        $names = \App\Models\Treatment::whereIn('id', $this->applicable_treatments)
            ->pluck('name')
            ->join(', ');

        return $names ?: 'Unknown treatments';
    }
}
