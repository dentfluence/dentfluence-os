<?php

namespace Tests\Feature\Insights;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\User;
use App\Services\Insights\InsightsProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 · Slice 1 — Insights Engine (Health/LTV/Risk).
 *
 * Insights is net-new, so there is no legacy 3-signal system to diff against.
 * These tests prove: (1) each calculator produces correct, bounded values on
 * known fixture data, (2) rebuild is idempotent, (3) parity() catches drift
 * between what's stored and a fresh recompute — the same self-check
 * discipline TodayActionsProjector already proved for its projection.
 */
class InsightsProjectorTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(array $overrides = []): Relationship
    {
        return Relationship::create(array_merge([
            'name' => 'Insights Test Person', 'phone' => '9' . random_int(100000000, 999999999),
            'status' => 'active', 'score' => 0, 'relationship_since' => now()->toDateString(),
        ], $overrides));
    }

    private function patient(Relationship $relationship, array $overrides = []): Patient
    {
        $patient = Patient::create(array_merge([
            'name' => $relationship->name, 'phone' => $relationship->phone, 'branch_id' => 1,
        ], $overrides));

        $patient->relationship_id = $relationship->id;
        $patient->save();

        return $patient;
    }

    private function doctor(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    public function test_rebuild_for_computes_and_stores_all_three_signals(): void
    {
        $rel     = $this->relationship();
        $patient = $this->patient($rel);
        $doctor  = $this->doctor();

        DB::table('appointments')->insert([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => 1,
            'appointment_date' => now()->subDays(10)->toDateString(), 'appointment_time' => '10:00:00',
            'type' => 'treatment', 'status' => 'done', 'created_by' => $doctor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->invoiceFor($patient)->id, 'patient_id' => $patient->id,
            'amount' => 5000, 'payment_mode' => 'cash', 'payment_date' => now()->toDateString(),
        ]);

        $projector = app(InsightsProjector::class);
        $result    = $projector->rebuildFor($rel->id);

        $this->assertTrue($result['found']);
        $this->assertSame(3, $result['rows']);

        $stored = $projector->signalsFor($rel->id);

        $this->assertArrayHasKey('health', $stored);
        $this->assertArrayHasKey('ltv', $stored);
        $this->assertArrayHasKey('risk', $stored);

        // Recent completed visit (10 days ago, well within the 180-day ideal
        // window) ⇒ high health score.
        $this->assertGreaterThanOrEqual(40, $stored['health']['score']);

        // Realized LTV must reflect exactly the one payment recorded.
        $this->assertEqualsWithDelta(5000.0, $stored['ltv']['value_realized'], 0.01);

        // Recent visit ⇒ low dormancy-driven risk.
        $this->assertLessThan(60, $stored['risk']['score']);

        foreach (['health', 'risk'] as $signal) {
            $this->assertGreaterThanOrEqual(0, $stored[$signal]['score']);
            $this->assertLessThanOrEqual(100, $stored[$signal]['score']);
        }
    }

    public function test_rebuild_is_idempotent_no_duplicate_rows(): void
    {
        $rel = $this->relationship();
        $this->patient($rel);

        $projector = app(InsightsProjector::class);
        $projector->rebuildFor($rel->id);
        $projector->rebuildFor($rel->id);

        $this->assertSame(3, DB::table('insight_signals')->where('relationship_id', $rel->id)->count());
    }

    public function test_rebuild_all_covers_every_relationship(): void
    {
        $relA = $this->relationship();
        $relB = $this->relationship();
        $this->patient($relA);
        $this->patient($relB);

        $result = app(InsightsProjector::class)->rebuildAll();

        $this->assertGreaterThanOrEqual(2, $result['relationships']);
        $this->assertSame(3, DB::table('insight_signals')->where('relationship_id', $relA->id)->count());
        $this->assertSame(3, DB::table('insight_signals')->where('relationship_id', $relB->id)->count());
    }

    public function test_parity_matches_immediately_after_rebuild(): void
    {
        $rel = $this->relationship();
        $this->patient($rel);

        $projector = app(InsightsProjector::class);
        $projector->rebuildFor($rel->id);

        $parity = $projector->parity(onlyRelationshipId: $rel->id);

        $this->assertTrue($parity['match'], json_encode($parity['diffs']));
        $this->assertSame(1, $parity['checked']);
    }

    public function test_parity_detects_drift_when_source_data_changes(): void
    {
        $rel     = $this->relationship();
        $patient = $this->patient($rel);
        $doctor  = $this->doctor();

        $projector = app(InsightsProjector::class);
        $projector->rebuildFor($rel->id); // stored while patient has no visits yet

        // Now a completed visit appears, but we do NOT rebuild — parity should
        // notice the stored health/risk rows no longer match a fresh compute.
        DB::table('appointments')->insert([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => 1,
            'appointment_date' => now()->subDays(5)->toDateString(), 'appointment_time' => '09:00:00',
            'type' => 'treatment', 'status' => 'done', 'created_by' => $doctor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $parity = $projector->parity(onlyRelationshipId: $rel->id);

        $this->assertFalse($parity['match']);
        $this->assertArrayHasKey($rel->id, $parity['diffs']);
    }

    private function invoiceFor(Patient $patient): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-TEST-' . random_int(100000, 999999),
            'patient_id'     => $patient->id,
            'invoice_date'   => now()->toDateString(),
            'total_amount'   => 5000,
            'paid_amount'    => 5000,
            'status'         => 'paid',
        ]);
    }
}
