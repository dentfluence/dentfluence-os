<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /** Tag audit-log entries for this model with the "billing" module. */
    protected $auditModule = 'billing';

    protected $fillable = [
        'invoice_number',
        'patient_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_pct',
        'discount_amount',
        'taxable_amount',
        'gst_amount',
        'wallet_applied',
        'coupon_id',
        'coupon_discount',
        'membership_id',
        'membership_discount',
        // Manual (doctor/manager) discount — accountable, separate from coupon
        'manual_discount_type',
        'manual_discount_value',
        'manual_discount_amount',
        'manual_discount_reason',
        'manual_discount_authorized_by',
        'manual_discount_applied_by',
        'manual_discount_at',
        'final_bill_id',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'treatment_plan_id',
        'appointment_id',
        'notes',
        'created_by',
        'updated_by',
        // Cancellation audit
        'cancelled_reason',
        'cancelled_by',
    ];

    protected $casts = [
        'invoice_date'        => 'date',
        'due_date'            => 'date',
        'subtotal'            => 'decimal:2',
        'discount_pct'        => 'decimal:2',
        'discount_amount'     => 'decimal:2',
        'taxable_amount'      => 'decimal:2',
        'gst_amount'          => 'decimal:2',
        'wallet_applied'      => 'decimal:2',
        'coupon_discount'     => 'decimal:2',
        'membership_discount' => 'decimal:2',
        'manual_discount_value'  => 'decimal:2',
        'manual_discount_amount' => 'decimal:2',
        'manual_discount_at'     => 'datetime',
        'total_amount'        => 'decimal:2',
        'paid_amount'         => 'decimal:2',
        'balance_due'         => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->latest();
    }

    public function finalBill(): HasOne
    {
        return $this->hasOne(FinalBill::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponCode::class, 'coupon_id');
    }

    public function billingPrompts(): HasMany
    {
        return $this->hasMany(BillingPrompt::class);
    }

    /** User who authorized the manual discount (may differ from applier). */
    public function manualDiscountAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_discount_authorized_by');
    }

    /** User who actually entered the manual discount. */
    public function manualDiscountApplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_discount_applied_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Recalculate totals from items + all discount layers and update DB.
     * Call after any item, coupon, wallet, or membership change.
     */
    public function recalculate(): void
    {
        $itemsNet = $this->items()->sum('net_amount');
        $itemsGst = $this->items()->sum('gst_amount');

        $discAmt     = round($itemsNet * ($this->discount_pct / 100), 2);
        $taxable     = $itemsNet - $discAmt;
        $gst         = $itemsGst;

        // Additional discount layers (set separately by InvoiceBuilder)
        $wallet      = (float) ($this->wallet_applied ?? 0);
        $coupon      = (float) ($this->coupon_discount ?? 0);
        $membership  = (float) ($this->membership_discount ?? 0);
        $manual      = (float) ($this->manual_discount_amount ?? 0);

        $total   = max(0, ($taxable + $gst) - $wallet - $coupon - $membership - $manual);
        $paid    = $this->payments()->sum('amount');
        $balance = max(0, $total - $paid);

        $this->update([
            'subtotal'        => $itemsNet,
            'discount_amount' => $discAmt,
            'taxable_amount'  => $taxable,
            'gst_amount'      => $gst,
            'total_amount'    => $total,
            'paid_amount'     => $paid,
            'balance_due'     => $balance,
            'status'          => $this->deriveStatus($total, $paid),
        ]);
    }

    private function deriveStatus(float $total, float $paid): string
    {
        if ($this->status === 'cancelled') return 'cancelled';
        if ($paid <= 0)              return 'draft';
        if ($paid >= $total)         return 'paid';
        return 'partial';
    }

    /** Generate next invoice number: INV-YYYY-NNNNN */
    public static function nextNumber(): string
    {
        $year   = now()->year;
        $prefix = 'INV-' . $year . '-';

        $last = self::withTrashed()
            ->whereYear('created_at', $year)
            ->where('invoice_number', 'like', $prefix . '%')
            ->max('invoice_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) : 0;

        return $prefix . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function hasFinalBill(): bool
    {
        return $this->finalBill()->exists();
    }

    // ── Status badge colour ──────────────────────────────────────────────────

    public function statusColor(): string
    {
        return match($this->status) {
            'paid'      => 'green',
            'partial'   => 'yellow',
            'sent'      => 'blue',
            'draft'     => 'gray',
            'cancelled' => 'red',
            'refunded'  => 'purple',
            default     => 'gray',
        };
    }
}
