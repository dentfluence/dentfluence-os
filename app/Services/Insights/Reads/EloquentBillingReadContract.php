<?php

namespace App\Services\Insights\Reads;

use App\Contracts\Insights\BillingReadContract;
use Illuminate\Support\Facades\DB;

/**
 * EloquentBillingReadContract — Phase 6 · Slice 4.
 *
 * Queries moved verbatim out of LtvSignalCalculator (Slice 1) — pure
 * extraction, no behaviour change.
 */
class EloquentBillingReadContract implements BillingReadContract
{
    public function totalPayments(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        return (float) DB::table('invoice_payments')
            ->whereIn('patient_id', $patientIds)
            ->whereNull('deleted_at')
            ->sum('amount');
    }

    public function acceptedTreatmentPlanValue(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        return (float) DB::table('treatment_plans')
            ->whereIn('patient_id', $patientIds)
            ->whereNull('deleted_at')
            ->whereNotNull('accepted_at')
            ->sum('total');
    }

    public function invoicedAgainstPlans(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        return (float) DB::table('invoices')
            ->whereIn('patient_id', $patientIds)
            ->whereNotNull('treatment_plan_id')
            ->whereNull('deleted_at')
            ->sum('total_amount');
    }
}
