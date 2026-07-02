<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinanceGstRecord extends Model
{
    protected $table = 'finance_gst_records';

    protected $fillable = [
        'clinic_id', 'transaction_id', 'gst_type', 'gstin_clinic', 'gstin_party',
        'hsn_sac', 'description', 'taxable_amount', 'gst_rate',
        'cgst_rate', 'sgst_rate', 'igst_rate',
        'cgst_amount', 'sgst_amount', 'igst_amount', 'total_tax', 'invoice_total',
        'invoice_number', 'invoice_date', 'gstr_period', 'filed', 'filed_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'filed_at'     => 'datetime',
        'filed'        => 'boolean',
    ];

    public function transaction() { return $this->belongsTo(FinanceTransaction::class, 'transaction_id'); }
}
