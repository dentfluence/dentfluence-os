<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrStaffAdvance extends Model
{
    protected $table = 'hr_staff_advances';

    protected $fillable = [
        'user_id', 'reason', 'principal', 'given_date', 'with_interest',
        'interest_rate', 'tenure_months', 'emi_amount', 'total_payable',
        'amount_paid', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'given_date'    => 'date',
        'principal'     => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'emi_amount'    => 'decimal:2',
        'total_payable' => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'with_interest' => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Balance remaining */
    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->total_payable - (float) $this->amount_paid);
    }

    /** EMIs remaining */
    public function getEmisRemainingAttribute(): int
    {
        if (! $this->emi_amount) return 0;
        return (int) ceil($this->balance / (float) $this->emi_amount);
    }

    /**
     * Calculate EMI using standard formula.
     * For interest-free: simply principal / months.
     * For with-interest: P × r(1+r)^n / ((1+r)^n - 1)
     */
    public static function calculateEmi(float $principal, int $months, float $annualRate = 0): array
    {
        if ($annualRate <= 0) {
            $emi   = round($principal / $months, 2);
            $total = $principal;
        } else {
            $r     = $annualRate / 12 / 100;
            $emi   = round($principal * $r * pow(1 + $r, $months) / (pow(1 + $r, $months) - 1), 2);
            $total = round($emi * $months, 2);
        }
        return ['emi' => $emi, 'total' => $total];
    }
}
