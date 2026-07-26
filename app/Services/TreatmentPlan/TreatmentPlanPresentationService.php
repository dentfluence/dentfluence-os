<?php

namespace App\Services\TreatmentPlan;

use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Facades\DB;

/**
 * TreatmentPlanPresentationService — Phase 2 · Slice 2.2.
 *
 * THE single door for "this plan was presented to the patient", used by web
 * and API alike (Phase 1 rule: same business truth, both surfaces, one
 * service). Before this slice, presentation was written only as an
 * Opportunity stage by the web controller and had no API path at all.
 *
 * SEMANTICS (CEO-approved):
 *
 *   • presented_at records the FIRST presentation and is never overwritten.
 *     Re-opening, re-printing or re-explaining a plan does not rewrite when
 *     the patient first saw it. Re-presentation history lives in the Activity
 *     ledger, not by destroying the original timestamp.
 *
 *   • Presentation is NOT a decision. It never touches accepted_at, never
 *     changes plan status, never starts treatment, never bills, and never
 *     implies rejection. presented_at + no decision = Decision Pending
 *     (conceptually — the decision model itself is Slice 2.3).
 *
 *   • The linked Opportunity is still synced to 'quoted' so the existing
 *     pipeline UI and staff workflow keep behaving exactly as they do today.
 *     That sync is now a PROJECTION of clinical truth, not the source of it —
 *     the compatibility bridge until the Opportunity pipeline is reworked in
 *     a later slice.
 */
class TreatmentPlanPresentationService
{
    public function __construct(
        private readonly ActivityEngine $activity,
        private readonly TreatmentPlanOpportunitySync $opportunitySync,
    ) {}

    /**
     * Record that $plan was presented to the patient.
     *
     * Idempotent with respect to the clinical fact: calling it again leaves
     * presented_at untouched and reports first_presentation = false.
     *
     * @param  string $source  'clinic' | 'mobile' | 'presentation' — where the action came from
     * @return array{plan: TreatmentPlan, first_presentation: bool}
     *
     * @throws \RuntimeException when the plan cannot sensibly be presented
     */
    public function markPresented(TreatmentPlan $plan, ?User $actor = null, string $source = 'clinic'): array
    {
        $this->guard($plan);

        return DB::transaction(function () use ($plan, $actor, $source) {
            $isFirst = is_null($plan->presented_at);

            if ($isFirst) {
                $plan->forceFill(['presented_at' => now()])->save();
            }

            $plan->loadMissing(['patient', 'items']);

            // One meaningful clinical event per explicit presentation. This is
            // an explicit staff action, never a page view, so it is not noisy —
            // and repeated calls are exactly the re-presentation history the
            // immutable presented_at deliberately does not carry.
            $this->activity->log(
                subject:        $plan,
                event:          'treatment_plan.presented',
                actor:          $actor,
                metadata:       [
                    'patient_id'         => $plan->patient_id,
                    'plan_id'            => $plan->id,
                    'source'             => $source,
                    'first_presentation' => $isFirst,
                    'value'              => $plan->total ? (float) $plan->total : null,
                ],
                relationshipId: $plan->patient?->relationship_id,
                description:    $isFirst
                    ? 'Treatment plan presented to patient'
                    : 'Treatment plan presented again to patient',
            );

            // Compatibility bridge — keeps the Opportunity board working.
            // Never downgrades a plan that has already moved past 'quoted'.
            if (! $plan->accepted_at) {
                $this->opportunitySync->syncStage($plan, 'quoted', [
                    'actor'       => $actor,
                    'created_by'  => $actor?->id,
                    'source'      => 'treatment_plan_presented',
                    'description' => 'Opportunity — estimate given (plan presented)',
                ]);
            }

            return [
                'plan'               => $plan->fresh(['items', 'patient']),
                'first_presentation' => $isFirst,
            ];
        });
    }

    /**
     * Transitions that make no clinical sense.
     *
     * A cancelled plan cannot be "presented" — the presentation would be a
     * claim about a plan that no longer stands. Everything else (pending,
     * ongoing, completed) may legitimately be shown to the patient again.
     */
    private function guard(TreatmentPlan $plan): void
    {
        if ($plan->status === 'cancelled') {
            throw new \RuntimeException('A cancelled treatment plan cannot be marked as presented.');
        }

        if (! $plan->patient_id) {
            throw new \RuntimeException('This plan is not linked to a patient, so it cannot be presented.');
        }
    }
}
