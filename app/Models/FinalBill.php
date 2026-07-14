<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalBill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bill_number',
        'invoice_id',
        'patient_id',
        'subtotal',
        'discount_amount',
        'wallet_applied',
        'coupon_discount',
        'gst_amount',
        'total_amount',
        'total_paid',
        'generated_date',
        'notes',
        'generated_by',
        // Deletion audit
        'deleted_reason',
        'deleted_by',
    ];

    protected $casts = [
        'generated_date'  => 'date',
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'wallet_applied'  => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'gst_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'total_paid'      => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Generate next bill number: BILL-YYYY-NNNNN */
    public static function nextNumber(): string
    {
        $year   = now()->year;
        $prefix = 'BILL-' . $year . '-';

        // lockForUpdate serializes concurrent generation (see Invoice::nextNumber).
        $last = self::withTrashed()
            ->whereYear('created_at', $year)
            ->where('bill_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('bill_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a FinalBill snapshot from a fully-paid Invoice.
     * Called automatically by InvoiceObserver when status hits 'paid'.
     */
    public static function generateFromInvoice(Invoice $invoice, int $userId): self
    {
        return self::create([
            'bill_number'     => self::nextNumber(),
            'invoice_id'      => $invoice->id,
            'patient_id'      => $invoice->patient_id,
            'subtotal'        => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'wallet_applied'  => $invoice->wallet_applied ?? 0,
            'coupon_discount' => $invoice->coupon_discount ?? 0,
            'gst_amount'      => $invoice->gst_amount,
            'total_amount'    => $invoice->total_amount,
            'total_paid'      => $invoice->paid_amount,
            'generated_date'  => today(),
            'generated_by'    => $userId,
        ]);
    }
}
