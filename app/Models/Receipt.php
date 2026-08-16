<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_number',
        'receipt_kind',   // U8: 'payment' (settles an invoice) | 'advance' (money in, no invoice)
        'invoice_id',
        'invoice_payment_id',
        'patient_id',
        'amount',
        'payment_mode',
        'receipt_date',
        'reference_no',
        'invoice_total',
        'amount_paid_before',
        'balance_after',
        'notes',
        'created_by',
        'receipt_type',   // 'patient_upfront' | 'provider_settlement' | null (regular)
    ];

    protected $casts = [
        'receipt_date'       => 'date',
        'amount'             => 'decimal:2',
        'invoice_total'      => 'decimal:2',
        'amount_paid_before' => 'decimal:2',
        'balance_after'      => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Generate next receipt number: RCP-YYYY-NNNNN */
    public static function nextNumber(): string
    {
        $year = now()->year;
        $prefix = 'RCP-' . $year . '-';

        // withTrashed ensures soft-deleted receipts still count,
        // so voided receipts never cause a duplicate number collision.
        // lockForUpdate serializes concurrent generation (see Invoice::nextNumber).
        $last = self::withTrashed()
            ->whereYear('created_at', $year)
            ->where('receipt_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('receipt_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * U8 (CEO decision B4) — advance receipts use their own ADV- series.
     *
     * A receipt with no invoice is a different document from a settlement
     * receipt, so it gets its own number space. This also keeps the RCP-
     * sequence meaning exactly one thing ("an invoice was settled"), which
     * matters for audit.
     *
     * Same concurrency contract as Receipt::nextNumber() — MUST be called
     * inside a DB transaction; the lockForUpdate serialises generation and the
     * UNIQUE index on receipt_number is the backstop.
     */
    public static function nextAdvanceNumber(): string
    {
        $year = now()->year;
        $prefix = 'ADV-' . $year . '-';

        $last = self::withTrashed()
            ->whereYear('created_at', $year)
            ->where('receipt_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('receipt_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);
    }

    /** U8 — true for an advance receipt (money received before any service). */
    public function isAdvance(): bool
    {
        return $this->receipt_kind === 'advance';
    }

    public function isFullPayment(): bool
    {
        return $this->balance_after <= 0;
    }
}
