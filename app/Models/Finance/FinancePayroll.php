<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancePayroll extends Model
{
    use SoftDeletes;

    protected $table = 'finance_payroll';

    protected $fillable = [
        'clinic_id', 'user_id', 'transaction_id', 'month', 'year', 'payment_date',
        'fixed_salary', 'incentives', 'bonus', 'deductions', 'advance_adjusted', 'net_salary',
        'payment_mode', 'bank_account_id', 'reference_number',
        'notes', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'payment_date'     => 'date',
        'net_salary'       => 'decimal:2',
        'fixed_salary'     => 'decimal:2',
    ];

    public function staff() { return $this->belongsTo(\App\Models\User::class, 'user_id'); }

    public function getMonthNameAttribute(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month)->format('F Y');
    }
}
