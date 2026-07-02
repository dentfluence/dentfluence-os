<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceBankAccount extends Model
{
    use SoftDeletes;

    protected $table = 'finance_bank_accounts';

    protected $fillable = [
        'clinic_id', 'account_name', 'bank_name', 'account_number', 'ifsc_code',
        'branch', 'account_type', 'opening_balance', 'current_balance',
        'is_primary', 'is_active', 'upi_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'is_primary'       => 'boolean',
        'is_active'        => 'boolean',
        'current_balance'  => 'decimal:2',
        // Encrypted financial PHI at rest (Phase A). Resilient casts — see
        // app/Casts/Encrypted.php. Not searched anywhere (verified).
        'account_number'   => \App\Casts\Encrypted::class,
        'ifsc_code'        => \App\Casts\Encrypted::class,
        'upi_id'           => \App\Casts\Encrypted::class,
    ];

    public function transactions() { return $this->hasMany(FinanceBankTransaction::class, 'bank_account_id'); }
}
