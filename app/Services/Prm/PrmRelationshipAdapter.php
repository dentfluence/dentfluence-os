<?php

namespace App\Services\Prm;

use App\Models\Lead;
use App\Services\Relationship\ActivityEngine;
use App\Services\Relationship\JourneyService;
use Illuminate\Database\Eloquent\Model;

/**
 * PrmRelationshipAdapter — Phase 1 · Workstream F (slice F1).
 *
 * Backward-compatibility bridge: the legacy PRM board keeps working exactly as
 * before, but every PRM write is now ALSO reflected into the relationship spine
 * so PRE is a faithful, primary view of the same activity.
 *
 * Two things happen on each PRM write:
 *   1. The lead's RelationshipJourney is shadow-synced to the lead's current
 *      stage — using JourneyService's correct stage→state mapping (the old
 *      inline PrmController code compared raw lead stages against journey
 *      states, so the sync silently no-op'd for most stages).
 *   2. A relationship Activity is recorded via ActivityEngine, so the PRM action
 *      appears on the unified timeline.
 *
 * Everything here is additive and fault-tolerant: a failure in journey sync or
 * activity logging must NEVER block the PRM action the user just took. Journeys
 * remain shadow (nothing reads them as truth until the Phase 4 cutover).
 */
class PrmRelationshipAdapter
{
    public function __construct(
        private readonly JourneyService $journeys,
        private readonly ActivityEngine $activity,
    ) {}

    /**
     * A lead moved to a new pipeline stage on the PRM board.
     * The lead must already be saved with the new stage before calling this.
     */
    public function onStageChanged(Lead $lead, ?string $oldStage, string $newStage, ?Model $actor = null): void
    {
        $this->syncJourney($lead);

        $this->record(
            $lead,
            'lead.stage_changed',
            $actor,
            ['from' => $oldStage, 'to' => $newStage, 'source' => 'prm'],
            "Lead stage: {$oldStage} → {$newStage}",
        );
    }

    /**
     * A staff member logged an activity (call, note, WhatsApp, …) on a lead.
     */
    public function onActivityLogged(Lead $lead, string $type, string $label, ?string $note = null, ?string $outcome = null, ?Model $actor = null): void
    {
        $this->record(
            $lead,
            'lead.activity_logged',
            $actor,
            ['type' => $type, 'label' => $label, 'outcome' => $outcome, 'source' => 'prm'],
            $note ?: $label,
        );
    }

    /**
     * A lead was converted to a patient on the PRM board.
     * The lead must already be saved as converted before calling this.
     */
    public function onConverted(Lead $lead, ?Model $actor = null): void
    {
        $this->syncJourney($lead);

        $this->record(
            $lead,
            'lead.converted',
            $actor,
            ['source' => 'prm'],
            'Lead converted to patient',
        );
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** Shadow-sync the lead journey; never throws into the caller. */
    private function syncJourney(Lead $lead): void
    {
        if (! $lead->relationship_id) {
            return;
        }

        try {
            $this->journeys->syncLeadJourney($lead);
        } catch (\Throwable) {
            // Journey sync failure must never block the PRM action.
        }
    }

    /** Record a relationship Activity; ActivityEngine already swallows failures. */
    private function record(Lead $lead, string $event, ?Model $actor, array $metadata, string $description): void
    {
        if (! $lead->relationship_id) {
            return;
        }

        $this->activity->log(
            subject:        $lead,
            event:          $event,
            actor:          $actor,
            metadata:       $metadata,
            relationshipId: $lead->relationship_id,
            description:    $description,
        );
    }
}
