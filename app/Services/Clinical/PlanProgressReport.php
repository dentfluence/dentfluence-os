<?php

namespace App\Services\Clinical;

use App\Enums\ClinicalProgress;
use App\Enums\PlanProgress;

/**
 * The canonical read model for one treatment plan's clinical progress.
 *
 * Slice 2.4d. Produced ONLY by DerivedProgressService. Read-only, computed on
 * demand, never persisted.
 *
 * It carries the out-of-scope information deliberately: work recorded against
 * a treatment the patient deferred, rejected or never decided on is real and
 * must not be hidden — but it must also never be counted as progress, because
 * that would let clinical work silently overwrite a patient decision.
 */
final class PlanProgressReport
{
    /**
     * @param  array<int,ClinicalProgress>  $items           in-scope: [treatment_plan_item_id => progress]
     * @param  array<int,ClinicalProgress>  $outOfScopeWork  work on items the patient did NOT agree to
     */
    public function __construct(
        public readonly int $treatmentPlanId,
        public readonly PlanProgress $progress,
        public readonly bool $started,
        public readonly array $items,
        public readonly array $outOfScopeWork = [],
        public readonly ?string $decision = null,
    ) {}

    /** In-scope items with any recorded work. */
    public function itemsWithWork(): int
    {
        return count(array_filter($this->items, fn (ClinicalProgress $p) => $p->hasWork()));
    }

    /** In-scope items whose latest valid fact says completed. */
    public function itemsCompleted(): int
    {
        return count(array_filter($this->items, fn (ClinicalProgress $p) => $p === ClinicalProgress::Completed));
    }

    public function itemsInScope(): int
    {
        return count($this->items);
    }

    public function hasAllWorkRecorded(): bool
    {
        return $this->progress === PlanProgress::AllWorkRecorded;
    }

    /**
     * Is there clinical work on treatments the patient did not agree to?
     * A real workflow signal (emergency treatment, or a decision recorded after
     * the fact) — surfaced, never folded into the progress figures.
     */
    public function hasWorkOutsideAcceptedPlan(): bool
    {
        return $this->outOfScopeWork !== [];
    }

    public function toArray(): array
    {
        return [
            'treatment_plan_id'   => $this->treatmentPlanId,
            'progress'            => $this->progress->value,
            'progress_label'      => $this->progress->label(),
            'started'             => $this->started,
            'decision'            => $this->decision,
            'items_in_scope'      => $this->itemsInScope(),
            'items_with_work'     => $this->itemsWithWork(),
            'items_completed'     => $this->itemsCompleted(),
            'items'               => array_map(fn (ClinicalProgress $p) => $p->value, $this->items),
            'work_outside_plan'   => array_map(fn (ClinicalProgress $p) => $p->value, $this->outOfScopeWork),
        ];
    }
}
