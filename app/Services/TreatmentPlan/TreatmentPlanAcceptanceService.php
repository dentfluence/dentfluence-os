<?php

namespace App\Services\TreatmentPlan;

use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Facades\DB;

/**
 * TreatmentPlanAcceptanceService
 * ------------------------------
 * The single place a treatment plan becomes "accepted".
 *
 * Acceptance was previously implemented three times:
 *   - TreatmentPlanController::accept()        (in-clinic, full orchestration)
 *   - PublicPresentationController::accept()   (patient via Smart Presentation,
 *                                               a hand-copied clone of the above)
 *   - Api/V1/TreatmentPlanController::accept() (mobile — which only flipped the
 *                                               status and silently skipped the
 *                                               activity log AND the Opportunity)
 *
 * so the same action produced different downstream records depending on which
 * door it came through. This service is that one door.
 *
 * On acceptance it:
 *   1. Stamps accepted_at + status = ongoing.
 *   2. Logs treatment_plan.accepted on the Timeline.
 *   3. Creates the follow-up TreatmentOpportunity (guarded — re-accepting a
 *      plan after a revert never creates a second one) and logs
 *      opportunity.created, which fires the opportunity_nudge_7d rule.
 */
class TreatmentPlanAcceptanceService
{
    /**
     * @param  TreatmentPlan  $plan     the plan being accepted
     * @param  User|null      $actor    who accepted (null for a patient-driven accept)
     * @param  string         $via      'clinic' | 'smart_presentation' | 'mobile'
     * @param  int|null       $createdBy user id to stamp on the Opportunity
     */
    public function accept(
        TreatmentPlan $plan,
        ?User $actor = null,
        string $via = 'clinic',
        ?int $createdBy = null
    ): TreatmentPlan {
        return DB::transaction(function () use ($plan, $actor, $via, $createdBy) {
            $plan->update([
                'accepted_at' => now(),
                'status'      => 'ongoing',
            ]);

            $plan->load(['items', 'patient']);

            $relationshipId = $plan->patient?->relationship_id;
            $createdBy    ??= $actor?->id;

            app(ActivityEngine::class)->log(
                subject:        $plan,
                event:          'treatment_plan.accepted',
                actor:          $actor,
                metadata:       ['patient_id' => $plan->patient_id, 'via' => $via],
                relationshipId: $relationshipId,
                description:    $this->acceptDescription($via),
            );

            // Guarded: one Opportunity per plan, ever.
            if (! TreatmentOpportunity::where('treatment_plan_id', $plan->id)->exists()) {
                $firstItem = $plan->items->first();

                $opportunity = TreatmentOpportunity::create([
                    'patient_id'        => $plan->patient_id,
                    'treatment_plan_id' => $plan->id,
                    'relationship_id'   => $relationshipId,
                    'type'              => 'other',
                    'label'             => $firstItem?->treatment_name ?? $plan->plan_name,
                    'status'            => 'prospect',
                    'priority'          => 'medium',
                    'created_by'        => $createdBy,
                ]);

                // Fires the already-enabled opportunity_nudge_7d rule
                // (RulesEngine -> TaskEngine::autoCreate, dedup-guarded).
                app(ActivityEngine::class)->log(
                    subject:        $opportunity,
                    event:          'opportunity.created',
                    actor:          $actor,
                    metadata:       [
                        'stage'      => 'prospect',
                        'patient_id' => $plan->patient_id,
                        'source'     => 'treatment_plan_accepted',
                        'via'        => $via,
                    ],
                    relationshipId: $relationshipId,
                    description:    'Opportunity created from accepted treatment plan',
                );
            }

            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    private function acceptDescription(string $via): string
    {
        return match ($via) {
            'smart_presentation' => 'Treatment plan accepted by patient via Smart Presentation',
            'mobile'             => 'Treatment plan accepted (mobile)',
            default              => 'Treatment plan accepted',
        };
    }
}
