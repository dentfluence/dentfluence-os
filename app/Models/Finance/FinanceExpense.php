<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceExpense extends Model
{
    use SoftDeletes;

    protected $table = 'finance_expenses';

    protected $fillable = [
        'clinic_id', 'transaction_id', 'category_id', 'vendor_id',
        'title', 'description', 'expense_date',
        'amount', 'gst_applicable', 'gst_rate', 'gst_amount', 'total_amount',
        'payment_mode', 'bank_account_id', 'payment_reference',
        'is_recurring', 'recurring_period', 'next_due_date',
        'attachments', 'status', 'approved_by', 'approved_at', 'rejection_reason',
        'notes', 'created_by', 'updated_by', 'updated_reason',
        // Payment tracking
        'payment_status', 'due_date',
        'paid_at', 'paid_amount', 'paid_mode', 'paid_reference',
        // Clinic account + cheque fields (Phase 3)
        'paid_clinic_account_id', 'paid_clinic_account_name', 'paid_cheque_number',
        // Auto-bill source tracing
        'source_type', 'source_id',
        // Vendor invoice tracking (for PO-linked expenses)
        'vendor_invoice_no', 'grn_number',
    ];

    protected $casts = [
        'expense_date'   => 'date',
        'next_due_date'  => 'date',
        'due_date'       => 'date',
        'paid_at'        => 'date',
        'approved_at'    => 'datetime',
        'gst_applicable' => 'boolean',
        'is_recurring'   => 'boolean',
        'attachments'    => 'array',
        'amount'         => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'paid_amount'    => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function category()    { return $this->belongsTo(FinanceExpenseCategory::class, 'category_id'); }
    public function vendor()      { return $this->belongsTo(FinanceVendor::class, 'vendor_id'); }
    public function transaction() { return $this->belongsTo(FinanceTransaction::class, 'transaction_id'); }
    public function approvedBy()  { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }

    /** Phase 2: Voucher generated when this expense is paid */
    public function voucher()        { return $this->hasOne(FinanceVoucher::class, 'expense_id'); }
    /** Phase 3: Clinic account used for payment */
    public function paidClinicAccount() { return $this->belongsTo(FinanceBankAccount::class, 'paid_clinic_account_id'); }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeThisMonth($q)
    {
        return $q->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year);
    }

    public function scopeUnpaid($q)
    {
        return $q->where('payment_status', 'unpaid');
    }

    public function scopePaid($q)
    {
        return $q->where('payment_status', 'paid');
    }

    public function scopeOverdue($q)
    {
        return $q->where('payment_status', 'unpaid')
                 ->whereNotNull('due_date')
                 ->where('due_date', '<', today());
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->payment_status === 'unpaid'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getPaymentStatusBadge(): array
    {
        if ($this->payment_status === 'paid') {
            return ['label' => 'Paid', 'class' => 'bg-green-100 text-green-700'];
        }
        if ($this->isOverdue()) {
            return ['label' => 'Overdue', 'class' => 'bg-red-100 text-red-700'];
        }
        return ['label' => 'Unpaid', 'class' => 'bg-orange-100 text-orange-700'];
    }
}
