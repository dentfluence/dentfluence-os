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
    ];

    protected $casts = [
        'expense_date'    => 'date',
        'next_due_date'   => 'date',
        'approved_at'     => 'datetime',
        'gst_applicable'  => 'boolean',
        'is_recurring'    => 'boolean',
        'attachments'     => 'array',
        'amount'          => 'decimal:2',
        'total_amount'    => 'decimal:2',
    ];

    public function category()    { return $this->belongsTo(FinanceExpenseCategory::class, 'category_id'); }
    public function vendor()      { return $this->belongsTo(FinanceVendor::class, 'vendor_id'); }
    public function transaction() { return $this->belongsTo(FinanceTransaction::class, 'transaction_id'); }
    public function approvedBy()  { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }

    public function scopeThisMonth($q)
    {
        return $q->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year);
    }
}
