<?php

namespace App\Enums;

/**
 * Derived clinical progress of ONE treatment plan item.
 *
 * Slice 2.4d. These states are DERIVED and never stored. They are computed
 * from captured clinical facts (treatment_visit_items.work_outcome) by
 * DerivedProgressService, which is the only component permitted to produce
 * them.
 *
 * COMPLETED here means "the clinician recorded that this item was finished at
 * a visit". It does not assert clinical completion of the treatment course —
 * see PlanProgress::AllWorkRecorded for why that distinction is kept.
 */
enum ClinicalProgress: string
{
    case NotStarted = 'not_started';
    case Started    = 'started';
    case InProgress = 'in_progress';
    case Completed  = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Started    => 'Started',
            self::InProgress => 'In Progress',
            self::Completed  => 'Completed',
        };
    }

    /** Has any clinical work been recorded against this item at all? */
    public function hasWork(): bool
    {
        return $this !== self::NotStarted;
    }
}
