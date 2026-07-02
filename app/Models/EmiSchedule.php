<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmiSchedule extends Model
{
    protected $fillable = [
        'invoice_payment_id', 'invoice_id', 'patient_id',
        'instalment_no', 'due_date',
        'principal', 'interest', 'emi_amount',
        'status', 'paid_date', 'payment_reference', 'notes',
        'created_by',
    ];

    protected $casts = [
        'due_date'  => 'date',
        'paid_date' => 'date',
        'principal' => 'decimal:2',
        'interest'  => 'decimal:2',
        'emi_amount'=> 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'invoice_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generate a flat-rate EMI schedule array (reducing balance method).
     *
     * @param  float  $principal    Total loan principal
     * @param  float  $annualRate   Annual interest rate (%)
     * @param  int    $tenure       Number of months
     * @param  string $startDate    First due date (Y-m-d)
     * @return array  Array of ['instalment_no', 'due_date', 'principal', 'interest', 'emi_amount']
     */
    public static function buildSchedule(float $principal, float $annualRate, int $tenure, string $startDate): array
    {
        $schedule = [];

        if ($annualRate <= 0 || $tenure <= 0 || $principal <= 0) {
            // Zero-interest: split principal equally
            $monthly = round($principal / $tenure, 2);
            $date    = \Carbon\Carbon::parse($startDate);
            for ($i = 1; $i <= $tenure; $i++) {
                $schedule[] = [
                    'instalment_no' => $i,
                    'due_date'      => $date->toDateString(),
                    'principal'     => $monthly,
                    'interest'      => 0,
                    'emi_amount'    => $monthly,
                ];
                $date->addMonth();
            }
            return $schedule;
        }

        // Standard EMI formula: EMI = P × r(1+r)^n / [(1+r)^n − 1]
        $r      = ($annualRate / 100) / 12;
        $factor = pow(1 + $r, $tenure);
        $emi    = round($principal * $r * $factor / ($factor - 1), 2);

        $balance = $principal;
        $date    = \Carbon\Carbon::parse($startDate);

        for ($i = 1; $i <= $tenure; $i++) {
            $interest  = round($balance * $r, 2);
            $princ     = round($emi - $interest, 2);

            // Last instalment: adjust for rounding
            if ($i === $tenure) {
                $princ    = round($balance, 2);
                $interest = round($emi - $princ, 2);
                if ($interest < 0) { $interest = 0; }
            }

            $schedule[] = [
                'instalment_no' => $i,
                'due_date'      => $date->toDateString(),
                'principal'     => $princ,
                'interest'      => $interest,
                'emi_amount'    => round($princ + $interest, 2),
            ];

            $balance -= $princ;
            $date->addMonth();
        }

        return $schedule;
    }
}
