<?php

namespace App\Services\Relationship;

use App\Models\Lead;
use App\Models\RelationshipJourney;
use App\Models\TreatmentOpportunity;

/**
 * JourneyService — Phase 1 · Sprint 3 (Workstream C).
 *
 * Keeps relationship journeys in sync with the legacy pipeline state
 * (`leads.stage`, `treatment_opportunities.status`) — IN SHADOW. Journeys are
 * dual-written to mirror the legacy columns; nothing reads journeys as
 * authoritative yet (that cutover is Blueprint Phase 4, behind the
 * `journey.authoritative` flag). This lets us measure divergence and prove the
 * journey state machine matches reality before anything depends on it.
 *
 * Shadow reconcile uses a DIRECT state set (not RelationshipJourney::transition())
 * because reconciling to current reality may legitimately "jump" states — the
 * strict transition graph is for live, step-by-step changes, not backfill.
 */
class JourneyService
{
    /** leads.stage  →  lead journey state. */
    public const LEAD_STAGE_MAP = [
        'new_lead'     => RelationshipJourney::LEAD_NEW_ENQUIRY,
        'contacted'    => RelationshipJourney::LEAD_CONTACTED,
        'appointment'  => RelationshipJourney::LEAD_APPOINTMENT_BOOKED,
        'consultation' => RelationshipJourney::LEAD_CONSULTATION,
        'plan_given'   => RelationshipJourney::LEAD_TREATMENT_PLANNED,
        'converted'    => RelationshipJourney::LEAD_CLOSED,
        'lost'         => RelationshipJourney::LEAD_LOST,
    ];

    /** treatment_opportunities.status  →  opportunity journey state. */
    public const OPPORTUNITY_STATUS_MAP = [
        'prospect'  => RelationshipJourney::OPPORTUNITY_IDENTIFIED,
        'discussed' => RelationshipJourney::OPPORTUNITY_PRESENTED,
        'quoted'    => RelationshipJourney::OPPORTUNITY_QUOTED,
        'accepted'  => RelationshipJourney::OPPORTUNITY_ACCEPTED,
        'declined'  => RelationshipJourney::OPPORTUNITY_DECLINED,
        'completed' => RelationshipJourney::OPPORTUNITY_COMPLETED,
    ];

    private const TERMINAL = ['closed', 'lost', 'completed', 'declined'];

    public function mapLeadStage(?string $stage): string
    {
        return self::LEAD_STAGE_MAP[$stage] ?? RelationshipJourney::LEAD_NEW_ENQUIRY;
    }

    public function mapOpportunityStatus(?string $status): string
    {
        return self::OPPORTUNITY_STATUS_MAP[$status] ?? RelationshipJourney::OPPORTUNITY_IDENTIFIED;
    }

    // ── Lead ───────────────────────────────────────────────────────────────────

    /**
     * Shadow-reconcile a lead's journey to its current stage.
     *
     * @return array{status:string, from?:?string, to?:string, diverged?:bool}
     */
    public function syncLeadJourney(Lead $lead): array
    {
        if (! $lead->relationship_id) {
            return ['status' => 'skipped_no_relationship'];
        }

        $target = $this->mapLeadStage($lead->stage);

        $journey = RelationshipJourney::where('relationship_id', $lead->relationship_id)
            ->where('type', RelationshipJourney::TYPE_LEAD)
            ->first();

        if (! $journey) {
            RelationshipJourney::create([
                'relationship_id' => $lead->relationship_id,
                'type'            => RelationshipJourney::TYPE_LEAD,
                'state'           => $target,
                'metadata'        => ['lead_id' => $lead->id, 'shadow' => true],
                'started_at'      => now(),
                'closed_at'       => $this->isTerminal($target) ? now() : null,
            ]);
            return ['status' => 'created', 'to' => $target];
        }

        if ($journey->state === $target) {
            return ['status' => 'in_sync', 'to' => $target];
        }

        $from = $journey->state;
        $journey->update([
            'state'     => $target,
            'closed_at' => $this->isTerminal($target) ? now() : null,
        ]);

        return ['status' => 'reconciled', 'from' => $from, 'to' => $target, 'diverged' => true];
    }

    // ── Opportunity ─────────────────────────────────────────────────────────────

    /**
     * Shadow-reconcile a treatment opportunity's journey to its current status.
     * One opportunity journey per opportunity, keyed via metadata.opportunity_id.
     *
     * @return array{status:string, from?:?string, to?:string, diverged?:bool}
     */
    public function syncOpportunityJourney(TreatmentOpportunity $opp): array
    {
        if (! $opp->relationship_id) {
            return ['status' => 'skipped_no_relationship'];
        }

        $target = $this->mapOpportunityStatus($opp->status);

        $journey = RelationshipJourney::where('relationship_id', $opp->relationship_id)
            ->where('type', RelationshipJourney::TYPE_OPPORTUNITY)
            ->where('metadata->opportunity_id', $opp->id)
            ->first();

        if (! $journey) {
            RelationshipJourney::create([
                'relationship_id' => $opp->relationship_id,
                'type'            => RelationshipJourney::TYPE_OPPORTUNITY,
                'state'           => $target,
                'metadata'        => ['opportunity_id' => $opp->id, 'shadow' => true],
                'started_at'      => now(),
                'closed_at'       => $this->isTerminal($target) ? now() : null,
            ]);
            return ['status' => 'created', 'to' => $target];
        }

        if ($journey->state === $target) {
            return ['status' => 'in_sync', 'to' => $target];
        }

        $from = $journey->state;
        $journey->update([
            'state'     => $target,
            'closed_at' => $this->isTerminal($target) ? now() : null,
        ]);

        return ['status' => 'reconciled', 'from' => $from, 'to' => $target, 'diverged' => true];
    }

    // ── Bulk operations (used by the sync command) ───────────────────────────────

    /**
     * Read-only: how many journeys would be created / reconciled / are in sync.
     *
     * @return array<string,mixed>
     */
    public function analyze(): array
    {
        $leads = ['create' => 0, 'reconcile' => 0, 'in_sync' => 0, 'skipped' => 0];
        Lead::orderBy('id')->chunkById(300, function ($rows) use (&$leads) {
            foreach ($rows as $lead) {
                $bucket = $this->classifyLead($lead);
                $leads[$bucket]++;
            }
        });

        $opps = ['create' => 0, 'reconcile' => 0, 'in_sync' => 0, 'skipped' => 0];
        TreatmentOpportunity::orderBy('id')->chunkById(300, function ($rows) use (&$opps) {
            foreach ($rows as $opp) {
                $bucket = $this->classifyOpportunity($opp);
                $opps[$bucket]++;
            }
        });

        return ['mode' => 'dry-run', 'leads' => $leads, 'opportunities' => $opps];
    }

    /**
     * Apply the shadow sync to all leads + opportunities. Idempotent.
     *
     * @return array<string,mixed>
     */
    public function applyAll(): array
    {
        $leads = ['created' => 0, 'reconciled' => 0, 'in_sync' => 0, 'skipped' => 0];
        Lead::orderBy('id')->chunkById(200, function ($rows) use (&$leads) {
            foreach ($rows as $lead) {
                $leads[$this->tally($this->syncLeadJourney($lead))]++;
            }
        });

        $opps = ['created' => 0, 'reconciled' => 0, 'in_sync' => 0, 'skipped' => 0];
        TreatmentOpportunity::orderBy('id')->chunkById(200, function ($rows) use (&$opps) {
            foreach ($rows as $opp) {
                $opps[$this->tally($this->syncOpportunityJourney($opp))]++;
            }
        });

        return ['mode' => 'applied', 'leads' => $leads, 'opportunities' => $opps];
    }

    // ── helpers ──────────────────────────────────────────────────────────────────

    private function classifyLead(Lead $lead): string
    {
        if (! $lead->relationship_id) {
            return 'skipped';
        }
        $target  = $this->mapLeadStage($lead->stage);
        $journey = RelationshipJourney::where('relationship_id', $lead->relationship_id)
            ->where('type', RelationshipJourney::TYPE_LEAD)->first();

        if (! $journey) {
            return 'create';
        }
        return $journey->state === $target ? 'in_sync' : 'reconcile';
    }

    private function classifyOpportunity(TreatmentOpportunity $opp): string
    {
        if (! $opp->relationship_id) {
            return 'skipped';
        }
        $target  = $this->mapOpportunityStatus($opp->status);
        $journey = RelationshipJourney::where('relationship_id', $opp->relationship_id)
            ->where('type', RelationshipJourney::TYPE_OPPORTUNITY)
            ->where('metadata->opportunity_id', $opp->id)->first();

        if (! $journey) {
            return 'create';
        }
        return $journey->state === $target ? 'in_sync' : 'reconcile';
    }

    /** Map a sync result to an applyAll tally bucket. */
    private function tally(array $result): string
    {
        return match ($result['status']) {
            'created'    => 'created',
            'reconciled' => 'reconciled',
            'in_sync'    => 'in_sync',
            default      => 'skipped',
        };
    }

    private function isTerminal(string $state): bool
    {
        return in_array($state, self::TERMINAL, true);
    }
}
