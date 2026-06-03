<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class FinanceCashbook extends Model
{
    protected $table = 'finance_cashbook';

    protected $fillable = [
        'clinic_id', 'book_date', 'opening_balance', 'cash_in', 'cash_out',
        'closing_balance', 'physical_count', 'difference',
        'status', 'notes', 'reconciled_by', 'reconciled_at', 'created_by',
    ];

    protected $casts = [
        'book_date'       => 'date',
        'reconciled_at'   => 'datetime',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'difference'      => 'decimal:2',
    ];
}
