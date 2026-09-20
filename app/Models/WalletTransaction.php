<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    // W-3: every create / update / delete lands in audit_logs
    // (hash-chained, append-only, shown at Settings > Activity Log).
    // the patient-credit ledger (Wallet itself is a derived balance, not audited).
    use \App\Traits\Auditable;

    protected $auditModule = 'finance';

    protected $fillable = [
        'wallet_id',
        'patient_id',
        'direction',
        'credit_type',
        'funding',                 // U8: 'patient' (cash-backed liability) | 'clinic' (concession)
        'source',
        'campaign_name',
        'applicable_treatments',   // JSON array of treatment IDs; null = all
        'amount',
        'payment_mode',            // how the cash moved for advances/refunds (nullable)
        'expiry_date',
        'invoice_id',
        'invoice_number',          // Denormalized — stored on debit/refund for audit trail
        'reversal_of_transaction_id', // set on the DEBIT that cancels a mistaken credit
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

    /**
     * The ADV- receipt this wallet credit produced, when it came from an
     * advance. Null for every other kind of credit.
     */
    public function advanceReceipt(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Receipt::class, 'wallet_transaction_id');
    }

    /** The credit this row was written to cancel (set on the reversing debit). */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }

    /** The debit that cancelled this credit, if any. */
    public function reversal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_transaction_id');
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

    /**
     * Exclude entries that have since been cancelled by a reversing debit.
     *
     * A reversal leaves the ORIGINAL row in place by design — nothing is ever
     * deleted from a money ledger. That is correct for the ledger and wrong for
     * a report: an advance marked a wrong entry never happened, so a report
     * that counts it is claiming cash the clinic does not hold. Balances are
     * safe without this (recalculate() nets the debit); anything that counts
     * the credit ROWS is not.
     */
    public function scopeNotReversed($query)
    {
        return $query->whereDoesntHave('reversal');
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

    /** Has this credit already been cancelled by a reversing debit? */
    public function isReversed(): bool
    {
        return self::where('reversal_of_transaction_id', $this->id)->exists();
    }

    /**
     * Sources that may be cancelled by a row-level reversal.
     *
     * Only entries where NO cash changed hands. A wrong number typed into Add
     * Credit or a manual adjustment is a bookkeeping mistake and is undone in
     * the ledger. An advance is money the patient physically handed over: that
     * has to leave the building again through the refund path, not be erased
     * here. Everything else (invoice debits, expiry forfeits, referral rewards,
     * prior reversals) is written by another document and must be undone from
     * that document, or the two records drift apart.
     */
    public const REVERSIBLE_SOURCES = ['admin_credit', 'campaign', 'adjustment'];

    /**
     * Why this row cannot be reversed — null when it can be.
     * Single source of truth for both the button and the service guard.
     */
    public function reversalBlockedReason(): ?string
    {
        if ($this->direction !== 'credit') {
            return 'Only a credit can be reversed.';
        }
        if ($this->funding !== 'clinic') {
            // Cash was recorded as received, so three records exist: this wallet
            // credit, an ADV- receipt and a FinanceTransaction. Undoing only the
            // wallet row would leave the cashbook claiming money that is not
            // there. Refund is not the answer either — a refund records cash
            // going back OUT, which for a mistaken entry never happened. The
            // receipt void (correction type "never received") reverses all
            // three together, and that is the only correct path.
            return 'Cash was recorded against this. Reverse it from its receipt '
                 . '(choose "money never received") so the cashbook is corrected too.';
        }
        if (! in_array($this->source, self::REVERSIBLE_SOURCES, true)) {
            return 'This entry was written by another document and must be undone there.';
        }
        if ($this->isReversed()) {
            return 'Already reversed.';
        }
        // An expired promotional lot has already left the balance — expiry did
        // the cancelling. Writing a debit against it now would post money the
        // wallet no longer holds and leave a debit that matches no live lot.
        if ($this->isExpired()) {
            return 'This credit has already expired — there is nothing left to reverse.';
        }

        return null;
    }

    public function isReversible(): bool
    {
        return $this->reversalBlockedReason() === null;
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
