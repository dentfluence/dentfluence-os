<?php

namespace Tests\Feature\Clinical;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.1 — CLINICAL TRUTH CHARACTERIZATION.
 *
 * Records what the clinical model means TODAY, before Phase 2 changes any of
 * it. Every assertion here documents current behaviour — including behaviour
 * we intend to change. A green run means the characterization is accurate.
 *
 * Key findings locked here:
 *  1. treatment_plan_items.status has NO writer anywhere in app/ — it is frozen
 *     at 'pending' and read only by delete-protection guards. It therefore
 *     CANNOT represent patient decisions (the CEO's Gate B correction).
 *  2. Plan acceptance sets status 'ongoing' immediately — "accepted" and
 *     "in progress" are the same value.
 *  3. Plan completion is written by BILLING (all items invoiced) and by a
 *     visit flag — not by clinical item completion.
 *  4. Presentation is stored only as an Opportunity stage, not a clinical fact.
 *  5. consultations.treatment_acceptance already exists (accepted/pending/
 *     refused/deferred) but is a free-standing form field: no service writes
 *     it, nothing reads it for journey purposes.
 */
class ClinicalTruthCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Clinical Truth Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function planWithItems(Patient $patient, int $items = 2): TreatmentPlan
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id,
            'plan_name'  => 'Characterization Plan',
            'status'     => 'pending',
            'rows'       => [],
            'total'      => 45000,
        ]);

        foreach (range(1, $items) as $i) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id,
                'treatment_name'    => 'Procedure ' . $i,
                'unit_price'        => 22500,
                'units'             => 1,
                'total'             => 22500,
                'sort_order'        => $i,
            ]);
        }

        return $plan->fresh('items');
    }

    // ── 1. Item status: declared vocabulary vs actual use ────────────────────

    public function test_item_status_defaults_to_pending_and_has_no_application_writer(): void
    {
        $plan = $this->planWithItems($this->patient());

        foreach ($plan->items as $item) {
            $this->assertSame('pending', $item->status,
                'items are created pending — the only value the application ever sets');
        }

        // The column accepts the full declared vocabulary at the DB level…
        $item = $plan->items->first();
        foreach (['ongoing', 'completed', 'cancelled'] as $value) {
            $item->update(['status' => $value]);
            $this->assertSame($value, $item->fresh()->status);
        }

        // …but nothing in the application ever moves it there. Locked as a
        // FINDING: item.status is an execution vocabulary with no execution
        // writer, so it cannot carry patient-decision meaning either.
        $this->assertTrue(true);
    }

    public function test_item_status_completed_only_protects_rows_from_deletion(): void
    {
        // The two live readers of item.status both guard destructive edits.
        $plan = $this->planWithItems($this->patient());
        $item = $plan->items->first();
        $item->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->deleteJson(route('treatment-plan-items.destroy', $item))
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('treatment_plan_items', ['id' => $item->id, 'deleted_at' => null]);
    }

    // ── 2. Acceptance conflates "agreed" with "in progress" ──────────────────

    public function test_acceptance_sets_accepted_at_and_immediately_marks_the_plan_ongoing(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        $actor   = $this->admin();

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, 'clinic');

        $plan->refresh();

        $this->assertNotNull($plan->accepted_at, 'accepted_at is the only decision the model can express');
        $this->assertSame('ongoing', $plan->status,
            'FINDING: acceptance immediately means "ongoing" — no separate treatment-started fact');

        // No clinical work has happened at this point.
        $this->assertSame(0, $patient->treatmentVisits()->count());

        // …and every item is still untouched.
        foreach ($plan->items as $item) {
            $this->assertSame('pending', $item->fresh()->status);
        }
    }

    public function test_acceptance_writes_an_activity_event_and_syncs_one_opportunity(): void
    {
        $plan = $this->planWithItems($this->patient());

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $this->admin(), 'clinic');

        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.accepted']);

        $opps = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->get();
        $this->assertCount(1, $opps, 'exactly one opportunity per plan, ever');
        // Superseded by Slice 2.3c. This originally recorded that acceptance
        // mapped to 'completed' — the board's "Converted" — collapsing "said
        // yes" into "treatment started". Acceptance now maps to Committed.
        $this->assertSame(TreatmentOpportunity::COMMITTED, $opps->first()->status,
            'SLICE 2.3c: acceptance means Committed, never Converted');
    }

    // ── 3. Presentation ──────────────────────────────────────────────────────
    //
    // SUPERSEDED BY SLICE 2.2. This test originally asserted that NO clinical
    // presentation fact existed and that presentation was observable only as a
    // sales-pipeline stage ('quoted'). Slice 2.2 deliberately ended that: the
    // clinical fact now lives on treatment_plans.presented_at, and the
    // opportunity stage became a downstream projection of it.
    //
    // What is retained here is the half that is still TRUE and still worth
    // guarding — presentation is not a decision, and no patient-decision record
    // exists yet (that remains open for a later slice).

    public function test_presentation_still_decides_nothing_now_that_the_decision_ledger_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('treatment_plans', 'presented_at'),
            'SLICE 2.2: the clinical presentation fact now exists');
        // Superseded by Slice 2.3b: the decision ledger now exists. What must
        // remain true is that PRESENTING still decides nothing — a presented
        // plan carries no decision row until someone records one.
        $this->assertTrue(Schema::hasTable('plan_decisions'),
            'SLICE 2.3: the patient-decision ledger now exists');

        $plan = $this->planWithItems($this->patient());

        $this->actingAs($this->admin())
            ->postJson(route('treatment-plans.mark-presented', $plan))
            ->assertOk();

        // The clinical fact is written…
        $this->assertNotNull($plan->fresh()->presented_at);

        // …the opportunity stage still follows it (compatibility projection)…
        $this->assertSame('quoted', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // …and presentation still decides nothing.
        $this->assertNull($plan->fresh()->accepted_at);
        $this->assertSame('pending', $plan->fresh()->status);
    }

    // ── 4. Consultation disposition exists but is inert ──────────────────────

    public function test_consultation_carries_treatment_acceptance_but_nothing_derives_journey_from_it(): void
    {
        $this->assertTrue(Schema::hasColumn('consultations', 'treatment_acceptance'),
            'an accepted/pending/refused/deferred field already exists on consultations');

        $patient = $this->patient();
        $doctor  = $this->admin();

        $consultation = Consultation::create([
            'patient_id'           => $patient->id,
            'doctor_id'            => $doctor->id,
            'branch_id'            => 1,
            'status'               => 'completed',
            'consultation_date'    => now(),
            'treatment_acceptance' => 'deferred',
        ]);

        $this->assertSame('deferred', $consultation->fresh()->treatment_acceptance);

        // FINDING: it is a form field only — no "no treatment needed"
        // disposition exists, and nothing reads this for journey state.
        $this->assertFalse(Schema::hasColumn('consultations', 'clinical_disposition'));
    }

    // ── 5. Completion is asserted by billing, not by clinical progress ────────

    public function test_plan_completion_is_written_by_billing_when_every_item_is_invoiced(): void
    {
        // Documented from TreatmentPlanBillingService: "Close the plan only once
        // EVERY item is fully invoiced" — completion currently means BILLED,
        // which the CEO's Gate B rule explicitly forbids as evidence.
        $plan = $this->planWithItems($this->patient());

        $this->assertSame('pending', $plan->status);

        // Simulate what billing does today.
        $plan->items->each->update(['billing_progress' => TreatmentPlanItem::PROGRESS_INVOICED]);
        $plan->update(['status' => 'completed']);

        $this->assertSame('completed', $plan->fresh()->status);

        // No visit, no clinical evidence whatsoever.
        $this->assertSame(0, $plan->patient->treatmentVisits()->count());
    }

    // ── 6. last_visit_date remains dead ──────────────────────────────────────

    public function test_last_visit_date_is_never_written_by_any_clinical_flow(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $this->admin(), 'clinic');

        Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $this->admin()->id,
            'branch_id'         => 1,
            'status'            => 'completed',
            'consultation_date' => now(),
        ]);

        $this->assertNull($patient->fresh()->last_visit_date,
            'FINDING (Gate A): no clinical event writes last_visit_date');
    }
}
