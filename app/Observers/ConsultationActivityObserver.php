<?php

namespace App\Observers;

use App\Models\Consultation;
use App\Services\Relationship\ActivityEngine;

/**
 * ConsultationActivityObserver — Backend Orchestration (docs/backend-orchestration-plan.md §2.3)
 *
 * Records 'consultation.completed' on the Activity/Timeline stream. There are
 * 5 separate places a Consultation gets created (ConsultationController::store,
 * sameIssueStore, minorVisitStore, emergencyStore, cohaStore) — a model
 * observer is the single choke point that covers all of them without
 * touching any of that already-working, validated controller code.
 *
 * Purely additive: no RulesEngine rule is currently keyed to this event, so
 * this creates zero Tasks/Notifications today. It only makes the event exist
 * in the Activity/ActivityRecorded stream for Insights (once that flag is on)
 * and for any future rule.
 */
class ConsultationActivityObserver
{
    public function created(Consultation $consultation): void
    {
        $consultation->loadMissing('patient');

        app(ActivityEngine::class)->log(
            subject:        $consultation,
            event:          'consultation.completed',
            actor:          null,
            metadata:       ['patient_id' => $consultation->patient_id],
            relationshipId: $consultation->patient?->relationship_id,
            description:    'Consultation recorded',
        );
    }
}
