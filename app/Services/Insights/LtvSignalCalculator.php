<?php

namespace App\Services\Insights;

use App\Contracts\Insights\BillingReadContract;
use App\Models\Relationship;
use App\Services\Insights\Support\ResolvesPatientIds;

/**
 * LtvSignalCalculator — Phase 6 · Slice 1, Insights Engine.
 *
 * Independent signal: Lifetime Value, realized + projected. Reads ONLY
 * invoice_payments, invoices, and treatment_plans (its own tables). Pure/
 * deterministic — no external calls, no randomness.
 *
 * value_realized  = sum of all recorded payments (money actually collected).
 * value_projected = value_realized + pending_accepted, where pending_accepted
 *                   is the value of ACCEPTED treatment plans not yet covered
 *                   by an invoice against that plan. This is a simple,
 *                   documented heuristic for "near-term expected value" —
 *                   refinable later without changing the table shape.
 *
 * Phase 6 · Slice 4 (read-contracts): raw queries now live behind
 * BillingReadContract — same computed values, only where the queries live
 * moved. See HealthSignalCalculator.
 */
class LtvSignalCalculator
{
    use ResolvesPatientIds;

    public function __construct(private readonly BillingReadContract $billing) {}

    /**
     * @return array{value_realized:float, value_projected:float, level:string, factors:array<string,float>}
     */
    public function compute(Relationship $relationship): array
    {
        $patientIds = $this->resolvePatientIds($relationship);

        $realized = $this->realizedValue($patientIds);
        $pending  = $this->pendingAcceptedValue($patientIds);

        $projected = $realized + $pending;

        return [
            'value_realized'  => round($realized, 2),
            'value_projected' => round($projected, 2),
            'level'           => $this->tierFor($projected),
            'factors'         => [
                'realized'          => round($realized, 2),
                'pending_accepted'  => round($pending, 2),
            ],
        ];
    }

    /**
     * Sum of every recorded payment across all patients linked to this
     * relationship. Excludes soft-deleted (voided) payments explicitly,
     * since this is a raw DB query and bypasses Eloquent's global scope.
     */
    protected function realizedValue(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        return $this->billing->totalPayments($patientIds);
    }

    /**
     * Value of accepted treatment plans not yet matched by an invoice raised
     * against that same plan. A simple, conservative "what's likely coming"
     * figure — never negative.
     */
    protected function pendingAcceptedValue(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        $acceptedTotal        = $this->billing->acceptedTreatmentPlanValue($patientIds);
        $invoicedAgainstPlans = $this->billing->invoicedAgainstPlans($patientIds);

        return max(0.0, $acceptedTotal - $invoicedAgainstPlans);
    }

    protected function tierFor(float $valueProjected): string
    {
        $tiers = config('insights.ltv.tiers', []);

        // Tiers are configured high-to-low; return the first whose floor we clear.
        foreach ($tiers as $key => $tier) {
            if ($valueProjected >= ($tier['min'] ?? 0)) {
                return $key;
            }
        }

        return 'bronze';
    }
}
