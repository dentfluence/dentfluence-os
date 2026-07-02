<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Patient;

class FinanceTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'finance_transactions';

    protected $fillable = [
        'clinic_id', 'type', 'direction', 'source_type', 'source_id',
        'amount', 'gst_amount', 'discount_amount', 'net_amount',
        'payment_mode', 'payment_reference', 'bank_account_id',
        'status', 'patient_id', 'user_id', 'vendor_id',
        'gst_applicable', 'gst_rate', 'cgst', 'sgst', 'igst', 'hsn_sac',
        'transaction_date', 'notes',
        'created_by', 'updated_by', 'updated_reason', 'ip_address', 'device_info',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'gst_applicable'   => 'boolean',
        'amount'           => 'decimal:2',
        'net_amount'       => 'decimal:2',
        'gst_amount'       => 'decimal:2',
    ];

    public function patient()   { return $this->belongsTo(Patient::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
    public function vendor()    { return $this->belongsTo(FinanceVendor::class, 'vendor_id'); }
    public function bankAccount(){ return $this->belongsTo(FinanceBankAccount::class, 'bank_account_id'); }
    public function source()    { return $this->morphTo(); }
    public function gstRecord() { return $this->hasOne(FinanceGstRecord::class, 'transaction_id'); }

    // Scopes
    public function scopeIncome($q)   { return $q->where('type', 'income'); }
    public function scopeExpense($q)  { return $q->where('type', 'expense'); }
    public function scopeToday($q)    { return $q->whereDate('transaction_date', today()); }
    public function scopeThisMonth($q){ return $q->whereMonth('transaction_date', now()->month)->whereYear('transaction_date', now()->year); }
}
