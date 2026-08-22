<?php

namespace App\Services\Clinical;

use App\Enums\ClinicalProgress;
use App\Enums\PlanProgress;
use App\Models\PlanDecision;
use App\Models\PlanDecisionItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisitItem;
use Illuminate\Support\Collection;

/**
 * DerivedProgressService — THE canonical reader of clinical progress.
 *
 * Slice 2.4d, implementing the frozen Slice 2.4c contract
 * (docs/patient-journey-v1_1-slice-2_4c-derivation-contract.md).
 *
 * ══════════════════════════════════════════════════════════════════════════
 * FROZEN ARCHITECTURAL INVARIANT
 *
 *   There shall be exactly one canonical derivation model for clinical
 *   progress. Captured facts remain canonical. Derived progress remains
 *   read-only. No future feature may derive clinical progress independently,
 *   or read legacy status fields as substitutes.
 *
 * Timeline, Dashboard, PRE, Analytics, Reports, the future patient microsite,
 * mobile/API and any AI capability ask THIS service. None of them computes
 * progress for itself. A second implementation is a second truth, and the whole
 * of Phase 2 exists because this system had several.
 * ══════════════════════════════════════════════════════════════════════════
 *
 * READS ONLY:
 *   treatment_visit_items.work_outcome        (the captured clinical fact)
 *   treatment_visit_items.treatment_name / tooth_number  (repeat-work lookup only)
 *   treatment_visit_items.treatment_plan_item_id
 *   treatment_visits.deleted_at               (validity)
 *   plan_decisions / plan_decision_items      (scope — what the patient agreed to)
 *
 * NEVER READS — these are not progress, whatever they look like:
 *   treatment_plans.status            legacy lifecycle; two completion writers
 *   treatment_plan_items.status       inert; no writer since inception
 *   billing_progress / invoiced_units money
 *   current_stage / completed_stages  per-visit detail, cumulative, misleading
 *   TreatmentPlanItemTooth.status     billing ('invoiced'); 'completed' never written
 */
class DerivedProgressService
{
    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Has treatment on this plan actually STARTED?
     *
     * True when at least one valid clinical fact exists against any of the
     * plan's items. Note this is deliberately NOT scoped by patient decision:
     * if a clinician treated something, treatment started — even if the plan
     * was never accepted (emergency work) or was later cancelled.
     */
    public function isTreatmentStarted(TreatmentPlan $plan): bool
    {
        return $this->validFactsQuery()
            ->whereIn('treatment_visit_items.treatment_plan_item_id',
                TreatmentPlanItem::where('treatment_plan_id', $plan->id)->select('id'))
            ->exists();
    }

    /** Derived progress of ONE plan item. */
    public function deriveTreatmentPlanItemProgress(TreatmentPlanItem $item): ClinicalProgress
    {
        return $this->progressFromFacts(
            $this->validFactsFor([$item->id])->get($item->id, collect())
        );
    }

    /** Derived progress of a whole plan, scoped by what the patient agreed to. */
    public function deriveTreatmentPlanProgress(TreatmentPlan $plan): PlanProgressReport
    {
        $decision  = $plan->currentDecision();
        $allItems  = TreatmentPlanItem::where('treatment_plan_id', $plan->id)->pluck('id')->all();
        $inScope   = $this->itemsInScope($plan, $decision);
        $outScope  = array_values(array_diff($allItems, $inScope));

        $facts = $this->validFactsFor($allItems);

        $items = [];
        foreach ($inScope as $id) {
            $items[$id] = $this->progressFromFacts($facts->get($id, collect()));
        }

        // Work on treatments the patient did NOT agree to — surfaced, never counted.
        $outOfScopeWork = [];
        foreach ($outScope as $id) {
            $progress = $this->progressFromFacts($facts->get($id, collect()));
            if ($progress->hasWork()) {
                $outOfScopeWork[$id] = $progress;
            }
        }

        return new PlanProgressReport(
            treatmentPlanId: $plan->id,
            progress:        $this->rollUp($items),
            started:         $facts->contains(fn (Collection $f) => $f->isNotEmpty()),
            items:           $items,
            outOfScopeWork:  $outOfScopeWork,
            decision:        $decision?->decision,
        );
    }

    /**
     * Every in-scope item's latest valid fact says completed.
     *
     * This is NOT "the treatment is complete" — see PlanProgress::AllWorkRecorded.
     * A plan with nothing in scope is not "all recorded"; there is nothing to record.
     */
    public function hasAllWorkRecorded(TreatmentPlan $plan): bool
    {
        return $this->deriveTreatmentPlanProgress($plan)->hasAllWorkRecorded();
    }

    /**
     * Latest derived progress for every (procedure, tooth) pair this patient
     * has on record — plan-linked AND ad-hoc work alike.
     *
     * This is precisely the question repeat-work detection asks: "is the
     * previous instance of this procedure on this tooth actually FINISHED?"
     * It is answered HERE, in the one service permitted to derive clinical
     * progress, so the visit form never re-implements latest-fact-wins in
     * JavaScript. A second derivation would be a second truth.
     *
     * Keys are "<lowercased treatment name>|<tooth token>". An item recorded
     * against several teeth ("16, 17") registers under each tooth separately,
     * because a treatment is finished per tooth, not per line. Items carrying
     * no tooth register under an empty tooth token.
     *
     * A procedure with NO recorded outcome does not appear in the map at all.
     * Absence therefore reads as "not finished", which is the safe answer:
     * unfinished treatment is a continuation, never repeat work.
     *
     * @param  int|null  $excludeVisitId  the visit being edited — its own rows
     *                                    are today's work, not history.
     * @return array<string,string>  key => ClinicalProgress value
     */
    public function deriveProcedureProgressForPatient(int $patientId, ?int $excludeVisitId = null): array
    {
        $query = $this->baseValidFactsQuery()
            ->where('treatment_visit_items.patient_id', $patientId)
            ->orderBy('treatment_visits.visit_date')
            ->orderBy('treatment_visit_items.id');

        if ($excludeVisitId !== null) {
            $query->where('treatment_visit_items.treatment_visit_id', '!=', $excludeVisitId);
        }

        $rows = $query->get([
            'treatment_visit_items.id',
            'treatment_visit_items.treatment_name',
            'treatment_visit_items.tooth_number',
            'treatment_visit_items.work_outcome',
        ]);

        $grouped = [];

        foreach ($rows as $row) {
            foreach (self::teethTokens($row->tooth_number) as $tooth) {
                $grouped[self::procedureKey($row->treatment_name, $tooth)][] = $row;
            }
        }

        $out = [];

        foreach ($grouped as $key => $facts) {
            $out[$key] = $this->progressFromFacts(collect($facts))->value;
        }

        return $out;
    }

    /**
     * "RCT" + "46" -> "rct|46". The ONE place this key is built; the visit
     * form mirrors it in JS and the tests pin the two together.
     */
    public static function procedureKey(?string $treatmentName, ?string $tooth): string
    {
        return mb_strtolower(trim((string) $treatmentName)) . '|' . trim((string) $tooth);
    }

    /** "16, 17" -> ['16','17']; blank -> ['']. */
    private static function teethTokens(?string $toothNumber): array
    {
        $tokens = array_values(array_filter(
            array_map('trim', preg_split('/[,\s]+/', (string) $toothNumber) ?: []),
            fn ($t) => $t !== ''
        ));

        return $tokens === [] ? [''] : $tokens;
    }

    // ── Validity — encapsulated. No caller may know these rules. ─────────────

    /**
     * A clinical fact is VALID when it is currently in force.
     *
     * Today that means: an outcome was recorded, and the visit it belongs to
     * still exists. When correction / void concepts arrive, this method is the
     * ONLY place that changes, and every consumer inherits it.
     *
     * ⚠ treatment_visits soft-deletes; treatment_visit_items does NOT cascade.
     * Querying visit items without this join silently counts deleted work.
     */
    private function validFactsQuery()
    {
        return $this->baseValidFactsQuery()
            ->whereNotNull('treatment_visit_items.treatment_plan_item_id');
    }

    /**
     * Validity WITHOUT the plan-item requirement.
     *
     * Plan progress is only ever about plan items, so validFactsQuery() above
     * keeps that constraint. Repeat-work detection is not: a walk-in RCT is
     * still a real clinical fact about a real tooth, and asking "was the
     * previous one finished?" must see it.
     */
    private function baseValidFactsQuery()
    {
        return TreatmentVisitItem::query()
            ->join('treatment_visits', 'treatment_visits.id', '=', 'treatment_visit_items.treatment_visit_id')
            ->whereNull('treatment_visits.deleted_at')
            ->whereNotNull('treatment_visit_items.work_outcome');
    }

    /**
     * Valid facts for the given plan items, grouped by item and ordered oldest
     * → newest, so the LAST element is the latest valid clinical fact.
     *
     * @param  array<int,int>  $itemIds
     * @return Collection<int,Collection>
     */
    private function validFactsFor(array $itemIds): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return $this->validFactsQuery()
            ->whereIn('treatment_visit_items.treatment_plan_item_id', $itemIds)
            ->orderBy('treatment_visits.visit_date')
            ->orderBy('treatment_visit_items.id')
            ->get([
                'treatment_visit_items.id',
                'treatment_visit_items.treatment_plan_item_id',
                'treatment_visit_items.work_outcome',
            ])
            ->groupBy('treatment_plan_item_id');
    }

    // ── Derivation ───────────────────────────────────────────────────────────

    /**
     * LATEST VALID CLINICAL FACT WINS.
     *
     * Deliberately not "any completed_today ever": that would freeze an item as
     * Completed and hide repeat work. An item finished in March and redone in
     * July reads In Progress again, because the newest valid fact says so.
     */
    private function progressFromFacts(Collection $facts): ClinicalProgress
    {
        if ($facts->isEmpty()) {
            return ClinicalProgress::NotStarted;
        }

        $latest = $facts->last()->work_outcome;

        return match (true) {
            $latest === TreatmentVisitItem::WORK_COMPLETED_TODAY => ClinicalProgress::Completed,
            $latest === TreatmentVisitItem::WORK_WORKED_ON       => ClinicalProgress::InProgress,
            // 'started' as the only fact means started; with earlier facts
            // behind it, work is under way again.
            $facts->count() === 1                               => ClinicalProgress::Started,
            default                                             => ClinicalProgress::InProgress,
        };
    }

    /** @param  array<int,ClinicalProgress>  $items */
    private function rollUp(array $items): PlanProgress
    {
        if ($items === []) {
            return PlanProgress::NotStarted;   // nothing agreed to — nothing to progress
        }

        $withWork  = array_filter($items, fn (ClinicalProgress $p) => $p->hasWork());
        $completed = array_filter($items, fn (ClinicalProgress $p) => $p === ClinicalProgress::Completed);

        return match (true) {
            $withWork === []                     => PlanProgress::NotStarted,
            count($completed) === count($items)  => PlanProgress::AllWorkRecorded,
            default                              => PlanProgress::InProgress,
        };
    }

    // ── Scope — decided by the patient, not by the item list ─────────────────

    /**
     * Which of the plan's items count toward progress.
     *
     * Deferred / rejected / not-yet-decided items are excluded from the
     * denominator: counting a rejected crown would make every partially
     * accepted plan permanently incomplete.
     *
     * @return array<int,int>
     */
    private function itemsInScope(TreatmentPlan $plan, ?PlanDecision $decision): array
    {
        if (! $decision) {
            return [];   // nothing agreed to yet
        }

        if ($decision->decision === PlanDecision::ACCEPTED) {
            return TreatmentPlanItem::where('treatment_plan_id', $plan->id)->pluck('id')->all();
        }

        if ($decision->decision === PlanDecision::PARTIALLY_ACCEPTED) {
            return PlanDecisionItem::where('plan_decision_id', $decision->id)
                ->where('decision', PlanDecisionItem::ACCEPTED)
                ->pluck('treatment_plan_item_id')
                ->all();
        }

        // Deferred or rejected — the patient agreed to nothing.
        return [];
    }
}
