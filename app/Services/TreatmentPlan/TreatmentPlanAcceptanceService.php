<?php

namespace App\Services\TreatmentPlan;

use App\Models\PlanDecision;
use App\Models\PlanDecisionItem;
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
 *   1. Appends a PlanDecision row — the canonical, append-only patient
 *      decision truth (Slice 2.3).
 *   2. Stamps accepted_at + status = ongoing. accepted_at is now a READ-MODEL
 *      MIRROR of the decision ledger, kept because a large amount of existing
 *      code reads it; the ledger is the source.
 *   3. Logs treatment_plan.accepted on the Timeline.
 *   4. Creates the follow-up TreatmentOpportunity (guarded — re-accepting a
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
            // Slice 2.3 — the decision ledger is written FIRST. accepted_at
            // below is a mirror of this row, not an independent truth.
            \App\Models\PlanDecision::create([
                'treatment_plan_id' => $plan->id,
                'decision'          => \App\Models\PlanDecision::ACCEPTED,
                'source'            => $via,
                'recorded_by'       => $actor?->id,
            ]);

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

            // Reflect the acceptance onto the plan's single linked Opportunity
            // — created earlier if the plan was already presented (Estimate
            // Given), or created now if it was accepted straight away.
            //
            // Slice 2.3c (C-1 fix): acceptance maps to COMMITTED, not Converted.
            // The patient has agreed to proceed; treatment has NOT started.
            // Converted is reserved for actual treatment start and is
            // deliberately not written anywhere yet.
            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, TreatmentOpportunity::COMMITTED, [
                'actor'       => $actor,
                'created_by'  => $createdBy,
                'source'      => 'treatment_plan_accepted',
                'description' => 'Opportunity committed — patient accepted the treatment plan (' . $via . ')',
            ]);

            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    /**
     * Un-accept a plan — the single revert door (added 2026-07-14; the API
     * copy previously flipped the status with NO billing guard and NO audit,
     * so a billed plan could be reverted from mobile with no trail).
     *
     * Guards (throw \RuntimeException with the user-facing message):
     *   - plan must currently be accepted
     *   - plan must have NO invoices (can't un-accept something billed)
     *
     * Writes the StaffActivityLog audit row with the mandatory reason.
     */
    public function revert(
        TreatmentPlan $plan,
        string $reason,
        ?User $actor = null,
        string $via = 'clinic'
    ): TreatmentPlan {
        if (is_null($plan->accepted_at)) {
            throw new \RuntimeException('This plan is not accepted, so there is nothing to revert.');
        }

        if ($plan->invoices()->exists()) {
            throw new \RuntimeException('Cannot revert: this plan already has invoices/billing against it.');
        }

        return DB::transaction(function () use ($plan, $reason, $actor, $via) {
            $plan->load('patient');

            $plan->update([
                'accepted_at' => null,
                'status'      => 'pending',
            ]);

            // Staff activity log (note column is varchar(255) — cap it).
            $note = sprintf(
                'Reverted treatment plan #%d (%s) for patient %s. Reason: %s%s',
                $plan->id,
                $plan->plan_name,
                $plan->patient?->name ?? ('#' . $plan->patient_id),
                $reason,
                $via === 'mobile' ? ' [mobile]' : ''
            );

            // F-3: StaffActivityLog::record() fills performed_by from auth()->id(),
            // which is NOT NULL — so a revert from any context without a session
            // (queue job, console command, future service-to-service call that
            // passes $actor explicitly) died on an integrity constraint. The
            // staff log is a convenience record; it must never be the reason a
            // clinical revert fails. The Activity ledger above is the audit.
            // record() sets performed_by from auth()->id() internally, so the
            // guard must be on the SESSION, not on $actor.
            if (auth()->id()) {
                \App\Models\StaffActivityLog::record(
                    $actor?->id ?? auth()->id(),
                    'tp_reverted',
                    'accepted',
                    'pending',
                    mb_substr($note, 0, 255)
                );
            }

            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    /**
     * PARTIAL ACCEPTANCE — the patient said yes to some of the plan.
     *
     * @param  array<int,string>  $itemDecisions  [treatment_plan_item_id => accepted|deferred|rejected|not_yet_decided]
     */
    public function acceptPartially(
        TreatmentPlan $plan,
        array $itemDecisions,
        ?User $actor = null,
        string $via = 'clinic',
        ?string $notes = null,
    ): TreatmentPlan {
        $this->guardDecidable($plan);

        if (empty($itemDecisions)) {
            throw new \RuntimeException('A partial acceptance must say what the patient decided about each treatment.');
        }

        return DB::transaction(function () use ($plan, $itemDecisions, $actor, $via, $notes) {
            $decision = $this->appendDecision($plan, PlanDecision::PARTIALLY_ACCEPTED, $actor, $via, $notes);

            $validItemIds = $plan->items()->pluck('id')->all();

            foreach ($itemDecisions as $itemId => $verdict) {
                if (! in_array((int) $itemId, $validItemIds, true)) {
                    throw new \RuntimeException('Treatment item #' . $itemId . ' does not belong to this plan.');
                }
                if (! array_key_exists($verdict, PlanDecisionItem::DECISIONS)) {
                    throw new \RuntimeException('Unknown item decision "' . $verdict . '".');
                }

                PlanDecisionItem::create([
                    'plan_decision_id'       => $decision->id,
                    'treatment_plan_item_id' => (int) $itemId,
                    'decision'               => $verdict,
                ]);
            }

            // The patient committed to at least part of the plan, so the
            // acceptance mirror is set exactly as it is for a full acceptance.
            $plan->update(['accepted_at' => now(), 'status' => 'ongoing']);

            $this->logDecision($plan, 'treatment_plan.partially_accepted', $actor,
                'Treatment plan partially accepted by patient', [
                    'via'            => $via,
                    'item_decisions' => $itemDecisions,
                ]);

            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, TreatmentOpportunity::COMMITTED, [
                'actor'       => $actor,
                'created_by'  => $actor?->id,
                'source'      => 'treatment_plan_partially_accepted',
                'description' => 'Opportunity committed — patient accepted part of the treatment plan (' . $via . ')',
            ]);

            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    /**
     * REJECTED — the patient explicitly declined the proposed treatment.
     *
     * Only an explicit patient decision may land here. No answer, a missed
     * call, or an unreachable patient are communication outcomes, NOT clinical
     * rejections, and must never reach this verb.
     */
    public function reject(
        TreatmentPlan $plan,
        ?string $reason = null,
        ?User $actor = null,
        string $via = 'clinic',
    ): TreatmentPlan {
        $this->guardDecidable($plan);

        return DB::transaction(function () use ($plan, $reason, $actor, $via) {
            $this->appendDecision($plan, PlanDecision::REJECTED, $actor, $via, $reason);

            $this->logDecision($plan, 'treatment_plan.rejected', $actor,
                'Treatment plan rejected by patient', ['via' => $via, 'reason' => $reason]);

            // Rejection closes the commercial journey.
            app(TreatmentPlanOpportunitySync::class)->syncStage($plan, TreatmentOpportunity::DECLINED, [
                'actor'           => $actor,
                'created_by'      => $actor?->id,
                'source'          => 'treatment_plan_rejected',
                'description'     => 'Opportunity declined — patient rejected the treatment plan (' . $via . ')',
                'declined_reason' => $reason,
            ]);

            // The plan itself is NOT cancelled. Cancelled is an administrative
            // state; rejection is a patient decision. Never collapse the two.
            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    /**
     * DEFERRED — "not now, later." Still a live opportunity.
     *
     * With a date: the opportunity stays open but is SUPPRESSED from chase,
     * nudge and due-action behaviour until that date, by moving the follow-up
     * date forward. With no date: nothing is invented — no date is written and
     * the opportunity simply remains as it was.
     */
    public function defer(
        TreatmentPlan $plan,
        ?string $deferUntil = null,
        ?string $reason = null,
        ?User $actor = null,
        string $via = 'clinic',
    ): TreatmentPlan {
        $this->guardDecidable($plan);

        return DB::transaction(function () use ($plan, $deferUntil, $reason, $actor, $via) {
            $this->appendDecision($plan, PlanDecision::DEFERRED, $actor, $via, $reason, $deferUntil);

            $this->logDecision($plan, 'treatment_plan.deferred', $actor,
                $deferUntil
                    ? 'Treatment plan deferred by patient until ' . $deferUntil
                    : 'Treatment plan deferred by patient (no review date agreed)',
                ['via' => $via, 'defer_until' => $deferUntil, 'reason' => $reason]);

            // Deferred is NOT closed — the patient may still say yes. The card
            // stays in the pipeline; only its due date moves, and only if the
            // patient actually named one.
            if ($deferUntil) {
                TreatmentOpportunity::where('treatment_plan_id', $plan->id)
                    ->update(['follow_up_date' => $deferUntil]);
            }

            return $plan->fresh(['items', 'creator', 'patient']);
        });
    }

    // ── shared decision plumbing ─────────────────────────────────────────────

    private function guardDecidable(TreatmentPlan $plan): void
    {
        if ($plan->status === 'cancelled') {
            throw new \RuntimeException('A cancelled treatment plan cannot carry a patient decision.');
        }
        if (! $plan->patient_id) {
            throw new \RuntimeException('This plan is not linked to a patient, so no decision can be recorded.');
        }
    }

    private function appendDecision(
        TreatmentPlan $plan,
        string $decision,
        ?User $actor,
        string $via,
        ?string $notes = null,
        ?string $deferUntil = null,
    ): PlanDecision {
        return PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => $decision,
            'defer_until'       => $deferUntil,
            'notes'             => $notes,
            'source'            => $via,
            'recorded_by'       => $actor?->id,
        ]);
    }

    private function logDecision(TreatmentPlan $plan, string $event, ?User $actor, string $description, array $meta): void
    {
        $plan->loadMissing('patient');

        app(ActivityEngine::class)->log(
            subject:        $plan,
            event:          $event,
            actor:          $actor,
            metadata:       array_merge(['patient_id' => $plan->patient_id, 'plan_id' => $plan->id], $meta),
            relationshipId: $plan->patient?->relationship_id,
            description:    $description,
        );
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
