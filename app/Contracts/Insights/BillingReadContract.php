<?php

namespace App\Contracts\Insights;

/**
 * BillingReadContract — Phase 6 · Slice 4 (read-contracts).
 *
 * The thin seam between the Insights signal calculators and the billing
 * tables (`invoice_payments`, `invoices`, `treatment_plans`). See
 * AppointmentReadContract for the rationale.
 */
interface BillingReadContract
{
    /**
     * Sum of every recorded payment across the given patient IDs.
     *
     * @param  array<int,int>  $patientIds
     */
    public function totalPayments(array $patientIds): float;

    /**
     * Sum of `total` on accepted (accepted_at not null) treatment plans
     * across the given patient IDs.
     *
     * @param  array<int,int>  $patientIds
     */
    public function acceptedTreatmentPlanValue(array $patientIds): float;

    /**
     * Sum of `total_amount` on invoices raised against a treatment plan,
     * across the given patient IDs.
     *
     * @param  array<int,int>  $patientIds
     */
    public function invoicedAgainstPlans(array $patientIds): float;
}
