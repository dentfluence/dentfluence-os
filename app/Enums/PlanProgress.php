<?php

namespace App\Enums;

/**
 * Derived clinical progress of a whole treatment plan.
 *
 * Slice 2.4d. Derived, never stored.
 *
 * NOTE THE CEILING. The highest state is ALL WORK RECORDED, never "Completed".
 *
 * "All work recorded" is a statement about DATA: every treatment the patient
 * agreed to carries a clinical fact whose latest valid value is
 * completed_today. The derivation can prove that from what was captured.
 *
 * "Completed" would be a statement about CLINICAL JUDGEMENT — that the
 * clinician is satisfied with the result, that lab work is delivered and
 * fitted, that post-operative review has happened where the procedure requires
 * it. None of that is recorded anywhere, so claiming it would assert
 * professional judgement on the clinician's behalf. That is precisely the
 * failure mode Phase 2 exists to remove (billing completing a plan because
 * every item happened to be invoiced).
 *
 * When clinical completion is introduced it must be its own CAPTURED
 * assertion — a fact, not an inference.
 */
enum PlanProgress: string
{
    case NotStarted       = 'not_started';
    case InProgress       = 'in_progress';
    case AllWorkRecorded  = 'all_work_recorded';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted      => 'Not Started',
            self::InProgress      => 'In Progress',
            self::AllWorkRecorded => 'All Work Recorded',
        };
    }
}
