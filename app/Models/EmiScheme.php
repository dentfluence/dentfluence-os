<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmiScheme extends Model
{
    protected $fillable = [
        'emi_provider_id', 'scheme_name', 'tenure_months', 'upfront_emis',
        'clinic_interest_rate', 'gst_on_interest',
        'pass_cost_to_patient', 'is_active', 'created_by',
    ];

    protected $casts = [
        'clinic_interest_rate' => 'decimal:2',
        'gst_on_interest'      => 'decimal:2',
        'pass_cost_to_patient' => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmiProvider::class, 'emi_provider_id');
    }

    // ── Calculation helpers ──────────────────────────────────────────────────

    /**
     * Monthly EMI amount for patient.
     * When pass_cost_to_patient = true the loan base is (invoiceTotal + convenienceCharge)
     * because the patient's EMI agreement with the provider covers the full fee-inclusive amount.
     * When false, clinic absorbs the cost and the patient's loan is just invoiceTotal.
     */
    public function patientEmi(float $invoiceTotal): float
    {
        if ($this->tenure_months <= 0) return $invoiceTotal;

        $base = $this->pass_cost_to_patient
            ? round($invoiceTotal + $this->convenienceCharge($invoiceTotal), 2)
            : $invoiceTotal;

        return round($base / $this->tenure_months, 2);
    }

    /**
     * Upfront amount patient pays on day-1 (upfront_emis × monthly EMI).
     */
    public function upfrontAmount(float $invoiceTotal): float
    {
        return round($this->patientEmi($invoiceTotal) * $this->upfront_emis, 2);
    }

    /**
     * Interest cost borne by clinic (clinic_interest_rate % of invoice total).
     */
    public function clinicInterestCost(float $invoiceTotal): float
    {
        return round($invoiceTotal * ((float)$this->clinic_interest_rate / 100), 2);
    }

    /**
     * GST on the clinic's interest cost.
     */
    public function gstOnInterest(float $invoiceTotal): float
    {
        return round($this->clinicInterestCost($invoiceTotal) * ((float)$this->gst_on_interest / 100), 2);
    }

    /**
     * Total deduction provider makes from the invoice amount.
     */
    public function totalProviderDeduction(float $invoiceTotal): float
    {
        return round($this->clinicInterestCost($invoiceTotal) + $this->gstOnInterest($invoiceTotal), 2);
    }

    /**
     * Net amount clinic actually receives from provider.
     */
    public function clinicNetAmount(float $invoiceTotal): float
    {
        return round($invoiceTotal - $this->totalProviderDeduction($invoiceTotal), 2);
    }

    /**
     * Convenience charge to pass to patient (= total provider deduction).
     * Only applicable when pass_cost_to_patient is true.
     */
    public function convenienceCharge(float $invoiceTotal): float
    {
        if (! $this->pass_cost_to_patient) return 0;
        return $this->totalProviderDeduction($invoiceTotal);
    }

    /**
     * Full breakdown as array (used by AJAX endpoint).
     */
    public function breakdown(float $invoiceTotal): array
    {
        $monthly    = $this->patientEmi($invoiceTotal);
        $upfront    = $this->upfrontAmount($invoiceTotal);
        $interest   = $this->clinicInterestCost($invoiceTotal);
        $gst        = $this->gstOnInterest($invoiceTotal);
        $deduction  = $this->totalProviderDeduction($invoiceTotal);
        $netClinic  = $this->clinicNetAmount($invoiceTotal);
        $convFee    = $this->convenienceCharge($invoiceTotal);

        return [
            'scheme_name'             => $this->scheme_name,
            'tenure_months'           => $this->tenure_months,
            'upfront_emis'            => $this->upfront_emis,
            'patient_monthly_emi'     => $monthly,
            'patient_upfront_amount'  => $upfront,
            'clinic_interest_cost'    => $interest,
            'gst_on_interest'         => $gst,
            'total_provider_deduction'=> $deduction,
            'clinic_net_amount'       => $netClinic,
            'pass_cost_to_patient'    => (bool) $this->pass_cost_to_patient,
            'convenience_charge'      => $convFee,
            'receipt_total'           => round($invoiceTotal + $convFee, 2),
        ];
    }
}
