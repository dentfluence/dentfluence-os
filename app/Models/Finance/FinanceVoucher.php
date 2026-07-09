<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FinanceVoucher — permanent payment record generated when an expense is paid.
 *
 * Vouchers are never edited or hard-deleted — they are financial audit documents.
 * To correct a mistake, void it (with a reason, via VoucherController::destroy())
 * and issue a fresh one — the original stays visible with status='voided'.
 * Auto-number format: VCH-YYYY-NNNN
 */
class FinanceVoucher extends Model
{
    protected $table = 'finance_vouchers';

    protected $fillable = [
        'voucher_number',
        'expense_id',
        'vendor_id',
        'vendor_name',
        'voucher_date',
        'amount',
        'payment_mode',
        'reference',
        // Clinic account (Phase 3)
        'clinic_account_id',
        'clinic_account_name',
        'cheque_number',
        'notes',
        'purpose',
        'created_by',
        'approved_by',
        'approved_at',
        'source_type',
        'source_id',
        // Void (never hard-deleted — see migration 2026_07_09_000002)
        'status',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'approved_at'  => 'datetime',
        'voided_at'    => 'datetime',
        'amount'       => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function expense(): BelongsTo
    {
        return $this->belongsTo(FinanceExpense::class, 'expense_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(FinanceVendor::class, 'vendor_id');
    }

    public function clinicAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'clinic_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'voided_by');
    }

    // ── Void state ────────────────────────────────────────────────────────

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ── Auto-numbering ────────────────────────────────────────────────────

    /**
     * Generate the next voucher number: VCH-YYYY-NNNN
     */
    public static function generateNumber(): string
    {
        $year = now()->year;

        $last = static::whereYear('created_at', $year)
            ->orderByDesc('id')
            ->value('voucher_number');

        $seq = 1;
        if ($last && preg_match('/VCH-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return 'VCH-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Human-readable payment mode label */
    public function getPaymentModeLabel(): string
    {
        return match ($this->payment_mode) {
            'cash'          => 'Cash',
            'upi'           => 'UPI',
            'card'          => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'cheque'        => 'Cheque',
            default         => ucfirst($this->payment_mode ?? 'Other'),
        };
    }
}
