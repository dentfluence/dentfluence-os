<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceVendor extends Model
{
    use SoftDeletes;

    protected $table = 'finance_vendors';

    protected $fillable = [
        'clinic_id', 'vendor_name', 'company_name', 'vendor_type',
        'phone', 'email', 'address', 'city', 'state', 'pincode',
        'gstin', 'pan', 'credit_days', 'credit_limit',
        'total_purchases', 'total_paid', 'outstanding_amount', 'last_purchase_date',
        'bank_name', 'account_number', 'ifsc_code', 'account_name',
        'documents', 'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'last_purchase_date'  => 'date',
        'is_active'           => 'boolean',
        'documents'           => 'array',
        'total_purchases'     => 'decimal:2',
        'outstanding_amount'  => 'decimal:2',
    ];

    public function expenses()  { return $this->hasMany(FinanceExpense::class, 'vendor_id'); }
    public function payments()  { return $this->hasMany(FinanceVendorPayment::class, 'vendor_id'); }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->vendor_name;
    }
}
