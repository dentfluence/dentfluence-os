<?php

namespace Tests\Feature\Phase2;

use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\RelationshipBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Sprint 2 (Workstream G) — identity backfill.
 *
 * Verifies: dry-run writes nothing; apply links leads + patients; apply is
 * idempotent; duplicates are QUEUED (never merged).
 */
class RelationshipBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name, string $phone): Patient
    {
        return Patient::create(['name' => $name, 'phone' => $phone]);
    }

    private function lead(string $name, string $phone): Lead
    {
        // Lead has an observer (enrichment/routing) — bypass it in tests.
        return Lead::withoutEvents(fn () => Lead::create(['name' => $name, 'phone' => $phone]));
    }

    private function relationship(string $name, string $phone): Relationship
    {
        return Relationship::create([
            'name'               => $name,
            'phone'              => $phone,
            'status'             => 'active',
            'score'              => 0,
            'relationship_since' => now()->toDateString(),
        ]);
    }

    public function test_analyze_is_read_only(): void
    {
        $this->patient('A', '111');
        $this->patient('B', '222');
        $this->lead('C', '333');

        $report = app(RelationshipBackfillService::class)->analyze();

        $this->assertSame(0, Relationship::count(), 'Dry run must not create relationships.');
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(2, $report['patients']['unlinked']);
        $this->assertSame(1, $report['leads']['unlinked']);
    }

    public function test_apply_links_leads_and_patients(): void
    {
        $p1 = $this->patient('A', '111');
        $p2 = $this->patient('B', '222');
        $l  = $this->lead('C', '333');

        app(RelationshipBackfillService::class)->apply();

        $this->assertNotNull($p1->fresh()->relationship_id);
        $this->assertNotNull($p2->fresh()->relationship_id);
        $this->assertNotNull($l->fresh()->relationship_id);
        $this->assertSame(3, Relationship::count()); // 3 distinct phones → 3 relationships
    }

    public function test_apply_is_idempotent(): void
    {
        $this->patient('A', '111');
        $this->lead('C', '333');

        $svc = app(RelationshipBackfillService::class);
        $svc->apply();
        $after = Relationship::count();
        $svc->apply(); // re-run

        $this->assertSame($after, Relationship::count(), 'Re-running must not create duplicates.');
    }

    public function test_apply_links_opportunities_via_their_patient(): void
    {
        $patient = $this->patient('A', '111');

        $opp = new TreatmentOpportunity(['type' => 'implant', 'status' => 'quoted']);
        $opp->patient_id = $patient->id;
        $opp->save();

        app(RelationshipBackfillService::class)->apply();

        // Patient got a relationship; the opportunity inherited it via the patient.
        $patientRelId = $patient->fresh()->relationship_id;
        $this->assertNotNull($patientRelId);
        $this->assertSame($patientRelId, $opp->fresh()->relationship_id);
    }

    public function test_dedup_queues_shared_contacts_without_merging(): void
    {
        $a = $this->relationship('A', '999');
        $b = $this->relationship('B', '999'); // same phone → potential duplicate

        $queued = app(RelationshipBackfillService::class)->queueDedupCandidates();

        $this->assertSame(1, $queued);
        $this->assertDatabaseHas('dedup_candidates', [
            'relationship_id'           => min($a->id, $b->id),
            'candidate_relationship_id' => max($a->id, $b->id),
            'match_reason'              => 'phone',
            'status'                    => 'pending',
        ]);

        // NEVER auto-merged — both relationships still exist.
        $this->assertSame(2, Relationship::count());
    }
}
