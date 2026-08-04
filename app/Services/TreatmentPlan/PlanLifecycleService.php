<?php

namespace App\Services\TreatmentPlan;

use App\Enums\PlanLifecycleState;
use App\Models\PlanDecision;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Clinical\DerivedProgressService;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Support\Facades\DB;

/**
 * F1 — PLAN LIFECYCLE OWNERSHIP.
 * Canonical Treatment Lifecycle V1 §4, §12, §15.
 *
 * THE ONE WRITER of treatment_plans.status. Nothing else in the codebase may
 * write that column. Billing must never move it (§10); Treatment Visit
 * establishes clinical FACTS and this service reacts to them (§15 transitions
 * 6 and 7).
 *
 * DERIVE, DON'T STORE (CEO Directive #010). Every lifecycle state is computed
 * from facts that already exist and are owned elsewhere:
 *
 *   presented_at        Treatment Plan   → Draft vs Presented
 *   plan_decisions      Treatment Plan   → Decision Pending / Accepted / Deferred / Declined
 *   recorded work       Treatment Visit  → Treatment Started / Treatment Complete
 *
 * No new state column is introduced. `treatment_plans.status` is kept purely as
 * the legacy PROJECTION of the derived state, so every existing report, filter
 * and badge keeps working unchanged — see PlanLifecycleState::legacyStatus().
 *
 * Why the projection is persisted at all: `status` is read by reporting and
 * list queries that filter and group in SQL across thousands of rows. Deriving
 * it per row at query time is not practical, and removing the column would be a
 * breaking change. It is therefore a cache of a derived value, refreshed by
 * this service alone, and never a second source of truth.
 */
class PlanLifecycleService
{
    public function __construct(
        private readonly DerivedProgressService $progress,
        private readonly TreatmentPlanOpportunitySync $opportunitySync,
        private readonly ActivityEngine $activity,
    ) {
    }

    /**
     * The canonical lifecycle state of a plan, derived from facts.
     *
     * Pure — reads only, writes nothing.
     */
    public function derive(TreatmentPlan $plan): PlanLifecycleState
    {
        // Administratively cancelled is terminal and is the only state that
        // does not follow from a clinical or patient fact.
        if ($plan->status === 'cancelled') {
            return PlanLifecycleState::Closed;
        }

        $decision = $plan->currentDecision();

        // A recorded decision is read before presentation, so a plan accepted
        // chairside without a separate "presented" click is still Accepted.
        // Requiring presentation first would have silently downgraded those
        // plans, which is a workflow many clinics legitimately use.
        if ($decision) {
            if ($decision->decision === PlanDecision::REJECTED) {
                return PlanLifecycleState::Declined;
            }

            if ($decision->decision === PlanDecision::DEFERRED) {
                return PlanLifecycleState::Deferred;
            }
        }

        // Committed — by a decision row, or by the accepted_at mirror alone for
        // plans accepted before the decision ledger existed.
        $committed = ($decision && $decision->is_committing) || ! is_null($plan->accepted_at);

        if ($committed) {
            // The patient committed, so clinical facts decide how far the
            // treatment has progressed (§15 transitions 6 and 7).
            if ($this->progress->hasAllWorkRecorded($plan)) {
                return PlanLifecycleState::TreatmentComplete;
            }

            if ($this->progress->isTreatmentStarted($plan)) {
                return PlanLifecycleState::TreatmentStarted;
            }

            return PlanLifecycleState::Accepted;
        }

        // No live commitment: either never shown, or shown and awaiting an
        // answer (including an acceptance that was reversed).
        return is_null($plan->presented_at)
            ? PlanLifecycleState::Draft
            : PlanLifecycleState::DecisionPending;
    }

    /**
     * Refresh the legacy status projection from the derived state.
     *
     * Called by the Treatment Plan module after a decision changes, and by
     * Treatment Visit after clinical facts change. Idempotent: when the
     * projection already matches, nothing is written and no event fires.
     */
    public function sync(TreatmentPlan $plan, ?User $actor = null): PlanLifecycleState
    {
        return DB::transaction(function () use ($plan, $actor) {
            $plan->refresh();

            $state  = $this->derive($plan);
            $legacy = $state->legacyStatus();

            // The legacy column is coarser than the canonical lifecycle:
            // Accepted and Treatment Started both project onto 'ongoing'. So
            // an unchanged projection does NOT mean an unchanged state, and
            // returning early here would make the moment treatment actually
            // begins invisible to everything downstream.
            if ($plan->status !== $legacy) {
                $previous = $plan->status;
                $plan->update(['status' => $legacy]);
                $this->logTransition($plan, $previous, $state, $actor);
            }

            // Always evaluated against the derived state, never against the
            // projection. Idempotent — see mirrorToOpportunity().
            $this->mirrorToOpportunity($plan, $state, $actor);

            return $state;
        });
    }

    /**
     * React to a clinical fact recorded by Treatment Visit.
     *
     * Treatment Visit calls this after it has written what was actually
     * performed. The visit never writes plan state itself; it reports the fact
     * and the plan re-derives (§15 transitions 6 and 7).
     */
    public function reactToClinicalFact(TreatmentPlan $plan, ?User $actor = null): PlanLifecycleState
    {
        return $this->sync($plan, $actor);
    }

    /**
     * Mirror the derived state onto the sales pipeline (§6 — the Opportunity is
     * a mirror, never a master).
     *
     * Treatment Started is where an Opportunity finally becomes CONVERTED:
     * acceptance is commitment, actual treatment is conversion.
     */
    private function mirrorToOpportunity(TreatmentPlan $plan, PlanLifecycleState $state, ?User $actor): void
    {
        [$stage, $description] = match ($state) {
            PlanLifecycleState::TreatmentStarted,
            PlanLifecycleState::TreatmentComplete => [
                TreatmentOpportunity::CONVERTED,
                'Opportunity converted — treatment has begun on the accepted plan',
            ],
            // An acceptance that was reversed puts the estimate back on the
            // table, so the card must re-open and be chased again (§6).
            PlanLifecycleState::DecisionPending => [
                'quoted',
                'Opportunity re-opened — acceptance was reversed, estimate is live again',
            ],
            default => [null, null],
        };

        if ($stage === null) {
            return;
        }

        // Idempotent: the pipeline is a mirror, so re-deriving the same state
        // must not re-write the card or log a stage change that did not happen.
        $current = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status');

        if ($current === $stage) {
            return;
        }

        $this->opportunitySync->syncStage($plan, $stage, [
            'actor'       => $actor,
            'created_by'  => $actor?->id,
            'source'      => 'treatment_plan_lifecycle',
            'description' => $description,
        ]);
    }

    private function logTransition(TreatmentPlan $plan, ?string $previous, PlanLifecycleState $state, ?User $actor): void
    {
        $plan->loadMissing('patient');

        $this->activity->log(
            subject:        $plan,
            event:          'treatment_plan.lifecycle_changed',
            actor:          $actor,
            metadata:       [
                'patient_id'      => $plan->patient_id,
                'plan_id'         => $plan->id,
                'lifecycle_state' => $state->value,
                'legacy_from'     => $previous,
                'legacy_to'       => $state->legacyStatus(),
            ],
            relationshipId: $plan->patient?->relationship_id,
            description:    'Treatment plan is now ' . $state->label(),
        );
    }
}
