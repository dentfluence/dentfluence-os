<?php

namespace Tests\Feature\Phase3;

use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\JourneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Sprint 3 (Workstream C) — JourneyService shadow reconcile.
 */
class JourneyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(): Relationship
    {
        return Relationship::create([
            'name' => 'J', 'phone' => '111', 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    private function leadFor(Relationship $rel, string $stage): Lead
    {
        return Lead::withoutEvents(function () use ($rel, $stage) {
            $l = new Lead(['name' => 'L', 'phone' => '111']);
            $l->relationship_id = $rel->id;
            $l->stage = $stage;
            $l->save();
            return $l;
        });
    }

    // ── mapping (pure) ──────────────────────────────────────────────────────

    public function test_stage_and_status_mappings(): void
    {
        $svc = app(JourneyService::class);

        $this->assertSame('closed', $svc->mapLeadStage('converted'));
        $this->assertSame('treatment_planned', $svc->mapLeadStage('plan_given'));
        $this->assertSame('new_enquiry', $svc->mapLeadStage('something_unknown'));

        $this->assertSame('presented', $svc->mapOpportunityStatus('discussed'));
        $this->assertSame('completed', $svc->mapOpportunityStatus('completed'));
        $this->assertSame('identified', $svc->mapOpportunityStatus('unknown'));
    }

    // ── lead journeys ─────────────────────────────────────────────────────────

    public function test_reconciles_existing_lead_journey_to_stage(): void
    {
        $rel  = $this->relationship();
        $lead = $this->leadFor($rel, 'plan_given');

        // Simulate the journey linkLead created at new_enquiry.
        $journey = RelationshipJourney::create([
            'relationship_id' => $rel->id,
            'type'            => RelationshipJourney::TYPE_LEAD,
            'state'           => RelationshipJourney::LEAD_NEW_ENQUIRY,
            'started_at'      => now(),
        ]);

        $result = app(JourneyService::class)->syncLeadJourney($lead);

        $this->assertSame('reconciled', $result['status']);
        $this->assertTrue($result['diverged']);
        $this->assertSame('treatment_planned', $journey->fresh()->state);
    }

    public function test_creates_lead_journey_when_missing(): void
    {
        $rel  = $this->relationship();
        $lead = $this->leadFor($rel, 'contacted');

        $result = app(JourneyService::class)->syncLeadJourney($lead);

        $this->assertSame('created', $result['status']);
        $this->assertDatabaseHas('relationship_journeys', [
            'relationship_id' => $rel->id,
            'type'            => 'lead',
            'state'           => 'contacted',
        ]);
    }

    public function test_lead_sync_is_idempotent(): void
    {
        $rel  = $this->relationship();
        $lead = $this->leadFor($rel, 'contacted');
        $svc  = app(JourneyService::class);

        $svc->syncLeadJourney($lead);
        $second = $svc->syncLeadJourney($lead);

        $this->assertSame('in_sync', $second['status']);
        $this->assertSame(1, RelationshipJourney::where('relationship_id', $rel->id)->where('type', 'lead')->count());
    }

    public function test_skips_lead_without_relationship(): void
    {
        $lead = Lead::withoutEvents(fn () => Lead::create(['name' => 'L', 'phone' => '111']));
        $this->assertSame('skipped_no_relationship', app(JourneyService::class)->syncLeadJourney($lead)['status']);
    }

    // ── opportunity journeys ────────────────────────────────────────────────

    public function test_creates_and_reuses_opportunity_journey(): void
    {
        $rel     = $this->relationship();
        $patient = Patient::create(['name' => 'P', 'phone' => '222']);

        $opp = new TreatmentOpportunity(['type' => 'implant', 'status' => 'quoted']);
        $opp->patient_id      = $patient->id;
        $opp->relationship_id = $rel->id;
        $opp->save();

        $svc = app(JourneyService::class);

        $first  = $svc->syncOpportunityJourney($opp);
        $second = $svc->syncOpportunityJourney($opp); // idempotent — must find via metadata

        $this->assertSame('created', $first['status']);
        $this->assertSame('in_sync', $second['status']);
        $this->assertDatabaseHas('relationship_journeys', [
            'relationship_id' => $rel->id,
            'type'            => 'opportunity',
            'state'           => 'quoted',
        ]);
        $this->assertSame(1, RelationshipJourney::where('type', 'opportunity')->count());
    }
}
