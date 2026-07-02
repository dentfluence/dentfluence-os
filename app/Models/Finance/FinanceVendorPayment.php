<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceVendorPayment extends Model
{
    use SoftDeletes;

    protected $table = 'finance_vendor_payments';

    protected $fillable = [
        'clinic_id', 'vendor_id', 'transaction_id', 'purchase_order_id',
        'amount', 'payment_date', 'payment_mode', 'bank_account_id',
        'reference_number', 'notes', 'status', 'created_by',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function vendor() { return $this->belongsTo(FinanceVendor::class, 'vendor_id'); }
}
