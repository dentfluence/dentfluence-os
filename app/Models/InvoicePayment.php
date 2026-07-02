<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Finance\FinanceBankAccount;

class InvoicePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_id', 'patient_id', 'amount', 'payment_mode',
        'payment_date', 'reference_no', 'notes', 'created_by',
        // Clinic account received in (Phase 2 — Income Module)
        'clinic_account_id', 'clinic_account_name',
        // Cheque
        'bank_name', 'cheque_no', 'cheque_date', 'cheque_status',
        // Credit card
        'convenience_fee',
        // EMI
        'emi_provider', 'emi_tenure', 'emi_interest_rate', 'emi_amount', 'emi_start_date',
        // Provider EMI
        'emi_type', 'emi_provider_scheme_id', 'emi_upfront_amount', 'clinic_net_amount',
        // Void audit
        'void_reason', 'voided_by', 'void_refund_method', 'void_refund_amount', 'void_charge_deducted',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'convenience_fee'   => 'decimal:2',
        'emi_interest_rate' => 'decimal:2',
        'emi_amount'        => 'decimal:2',
        'payment_date'       => 'date',
        'cheque_date'        => 'date',
        'emi_start_date'     => 'date',
        'emi_upfront_amount' => 'decimal:2',
        'clinic_net_amount'  => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'clinic_account_id');
    }

    public function emiSchedules(): HasMany
    {
        return $this->hasMany(EmiSchedule::class)->orderBy('instalment_no');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function totalCharged(): float
    {
        return (float) $this->amount + (float) ($this->convenience_fee ?? 0);
    }

    public function modeLabel(): string
    {
        return match($this->payment_mode) {
            'cash'          => 'Cash',
            'card'          => 'Credit Card',
            'debit_card'    => 'Debit Card',
            'upi'           => 'UPI',
            'cheque'        => 'Cheque',
            'netbanking'    => 'Net Banking',
            'bank_transfer' => 'Bank Transfer',
            'emi'           => 'EMI',
            default         => ucfirst($this->payment_mode),
        };
    }
}
