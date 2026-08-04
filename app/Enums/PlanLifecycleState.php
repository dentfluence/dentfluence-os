<?php

namespace App\Enums;

/**
 * The canonical Treatment Plan lifecycle (Canonical Treatment Lifecycle V1 §15).
 *
 * Every state here is DERIVED from facts that already exist:
 *
 *   Draft            no presentation recorded
 *   Presented        presented, decision not yet recorded  → Decision Pending
 *   DecisionPending  presented, awaiting the patient's answer
 *   Accepted         patient committed (fully or partly)
 *   TreatmentStarted accepted AND at least one clinical fact recorded
 *   TreatmentComplete accepted work has all been performed
 *   Deferred         patient asked for time
 *   Declined         patient rejected
 *   Closed           administratively cancelled
 *
 * Nothing in this enum is stored. `treatment_plans.status` remains as the
 * legacy projection of these states for backward compatibility, and is written
 * by exactly one door (PlanLifecycleService).
 */
enum PlanLifecycleState: string
{
    case Draft             = 'draft';
    case DecisionPending   = 'decision_pending';
    case Accepted          = 'accepted';
    case TreatmentStarted  = 'treatment_started';
    case TreatmentComplete = 'treatment_complete';
    case Deferred          = 'deferred';
    case Declined          = 'declined';
    case Closed            = 'closed';

    /**
     * The legacy `treatment_plans.status` value this state projects onto.
     *
     * BACKWARD COMPATIBILITY: the four legacy values (pending / ongoing /
     * completed / cancelled) are unchanged, so every existing report, filter
     * and UI badge keeps reading exactly what it read before. What changes is
     * WHO writes them and WHY — not the vocabulary.
     */
    public function legacyStatus(): string
    {
        return match ($this) {
            self::Draft,
            self::DecisionPending,
            self::Deferred,
            self::Declined          => 'pending',
            self::Accepted,
            self::TreatmentStarted  => 'ongoing',
            self::TreatmentComplete => 'completed',
            self::Closed            => 'cancelled',
        };
    }

    /** Human label for UI and audit descriptions. */
    public function label(): string
    {
        return match ($this) {
            self::Draft             => 'Draft',
            self::DecisionPending   => 'Decision Pending',
            self::Accepted          => 'Accepted',
            self::TreatmentStarted  => 'Treatment Started',
            self::TreatmentComplete => 'Treatment Complete',
            self::Deferred          => 'Deferred',
            self::Declined          => 'Declined',
            self::Closed            => 'Closed',
        };
    }

    /** Does this state authorize new Treatment Visits? (Contract §7, §12) */
    public function authorizesTreatment(): bool
    {
        return in_array($this, [self::Accepted, self::TreatmentStarted], true);
    }
}
