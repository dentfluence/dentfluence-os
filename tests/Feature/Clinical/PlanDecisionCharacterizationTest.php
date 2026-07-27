<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.3a — PLAN DECISION CHARACTERIZATION (read-only).
 *
 * Locks how a patient's DECISION on a treatment plan is recorded TODAY, before
 * Slice 2.3b introduces an append-only decision record. Every assertion here
 * describes current reality — several describe reality we intend to change, and
 * those are marked FINDING so the intent is not mistaken for approval.
 */
class PlanDecisionCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        return Patient::create([
            'name' => 'Decision Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
    }

    private function planWithItems(Patient $patient): TreatmentPlan
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Implant Plan',
            'status' => 'pending', 'rows' => [], 'total' => 45000,
        ]);
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id, 'treatment_name' => 'Implant',
            'unit_price' => 45000, 'units' => 1, 'total' => 45000,
        ]);

        return $plan->fresh('items');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    // ── 1. There is no decision record at all ────────────────────────────────

    /**
     * SUPERSEDED BY SLICE 2.3b. This originally asserted that no decision
     * record existed and that the entire vocabulary of a patient decision was
     * one nullable timestamp. That WAS the state being characterized, and 2.3b
     * deliberately ended it.
     *
     * What is retained is the half still worth guarding: the decision truth
     * lives in its own ledger, and no parallel decision columns were smuggled
     * onto treatment_plans alongside it.
     */
    public function test_decision_truth_lives_in_a_ledger_not_in_columns_on_the_plan(): void
    {
        $this->assertTrue(Schema::hasTable('plan_decisions'));
        $this->assertTrue(Schema::hasTable('plan_decision_items'));

        // accepted_at survives ONLY as the compatibility mirror…
        $this->assertTrue(Schema::hasColumn('treatment_plans', 'accepted_at'));

        // …and no sibling decision columns were added beside it. Rejection and
        // deferral are ledger rows, not plan attributes.
        $this->assertFalse(Schema::hasColumn('treatment_plans', 'rejected_at'));
        $this->assertFalse(Schema::hasColumn('treatment_plans', 'deferred_at'));
        $this->assertFalse(Schema::hasColumn('treatment_plans', 'defer_until'));
    }

    // ── 2. Acceptance is disciplined; everything else is not ─────────────────

    public function test_accepted_at_is_written_only_by_the_acceptance_service(): void
    {
        $plan  = $this->planWithItems($this->patient());
        $actor = $this->admin();

        $this->actingAs($actor);   // see the revert-without-auth finding below

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, 'clinic');
        $plan->refresh();

        $this->assertNotNull($plan->accepted_at);
        $this->assertSame('ongoing', $plan->status);
        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.accepted']);

        app(TreatmentPlanAcceptanceService::class)->revert($plan->fresh(), 'Wrong plan', $actor, 'clinic');
        $plan->refresh();

        $this->assertNull($plan->accepted_at);
        $this->assertSame('pending', $plan->status);
    }

    // FINDING F-3 (characterized here, FIXED in 2.3b) — revert() died on a NOT
    // NULL constraint whenever there was no authenticated session, because
    // StaffActivityLog::record() fills performed_by from auth()->id() no matter
    // what $actor is passed. That broke any queue job, console command or
    // service-to-service call. The green-path regression now lives in
    // PlanDecisionLedgerTest::test_revert_no_longer_dies_without_an_authenticated_session().

    /**
     * FINDING — the plan's clinical status is FREELY writable through the
     * ordinary update endpoint on BOTH web and API. Any user with patients.edit
     * can declare a plan cancelled or completed without passing a service,
     * without an activity row, and without any audit trail. This is the
     * uncontrolled writer Slice 2.3 must close.
     */
    public function test_plan_status_can_be_set_freely_through_the_update_endpoint(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        $admin   = $this->admin();

        $payload = [
            'consultation_id' => $plan->consultation_id,
            'plan_name'       => $plan->plan_name,
            'status'          => 'cancelled',
        ];

        $this->actingAs($admin)
            ->putJson(route('treatment-plans.update', $plan), $payload)
            ->assertOk();

        $this->assertSame('cancelled', $plan->fresh()->status,
            'FINDING: a plan can be cancelled with no decision record and no event');

        // …and nothing was written to the ledger to say who decided this or why.
        $this->assertDatabaseMissing('activities', ['event' => 'treatment_plan.cancelled']);
        $this->assertDatabaseMissing('activities', ['event' => 'treatment_plan.rejected']);

        // The same freedom exists on the API surface.
        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/v1/treatment-plans/{$plan->id}", array_merge($payload, ['status' => 'completed']))
            ->assertOk();

        $this->assertSame('completed', $plan->fresh()->status,
            'FINDING: clinical completion is also freely settable, bypassing the completion rule');
    }

    // ── 3. Rejection has a commercial record but no clinical one ─────────────

    /**
     * FINDING — when a patient declines through the Case Acceptance journey,
     * the journey and the Opportunity both record it. The treatment plan — the
     * clinical record — records nothing at all. There is no clinical rejection.
     */
    public function test_a_patient_decline_never_reaches_the_clinical_record(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);

        TreatmentOpportunity::create([
            'patient_id' => $plan->patient_id, 'treatment_plan_id' => $plan->id,
            'type' => 'other', 'label' => 'Implant', 'status' => 'quoted',
        ]);

        app(\App\Services\TreatmentPlan\TreatmentPlanOpportunitySync::class)
            ->syncStage($plan, 'declined', ['source' => 'case_acceptance']);

        // Commercial layer knows.
        $this->assertSame('declined', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // Clinical layer does not.
        $plan->refresh();
        $this->assertSame('pending', $plan->status, 'FINDING: the plan is untouched by a decline');
        $this->assertNull($plan->accepted_at);
        $this->assertDatabaseMissing('activities', ['event' => 'treatment_plan.rejected']);
    }

    // ── 4. Deferral does not exist in any form ───────────────────────────────

    public function test_there_is_no_way_to_record_that_a_patient_is_thinking_about_it(): void
    {
        $plan = $this->planWithItems($this->patient());

        // No verb, no column, no event. A presented-but-undecided plan is
        // indistinguishable from a plan the patient actively deferred.
        $this->assertFalse(method_exists(TreatmentPlanAcceptanceService::class, 'defer'));
        $this->assertFalse(method_exists(TreatmentPlanAcceptanceService::class, 'reject'));
        $this->assertSame('pending', $plan->status);
    }

    // ── 5. Acceptance maps straight to the board's "Converted" ───────────────

    /**
     * FINDING — contradiction C-1 of the frozen integration contract. The
     * contract requires Accepted → COMMITTED and only actual treatment start →
     * CONVERTED. Today acceptance writes opportunity status 'completed', which
     * the PRE UI renders as "✓ Converted".
     */
    public function test_acceptance_marks_the_opportunity_completed_which_the_board_calls_converted(): void
    {
        $plan = $this->planWithItems($this->patient());

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $this->admin(), 'clinic');

        $this->assertSame('completed', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'),
            'FINDING C-1: accepted and converted are the same stored value today');

        // KEY DISCOVERY: the vocabulary already exists. The enum carries a
        // dedicated 'accepted' value whose display label is literally
        // "Committed" — and no code path has ever written it. C-1 is therefore
        // a wrong-value bug, NOT a missing-schema problem.
        $this->assertSame('Committed', TreatmentOpportunity::STAGES['accepted']['label']);
        $this->assertSame('Converted', TreatmentOpportunity::STAGES['completed']['label']);
        $this->assertSame('Nurturing', TreatmentOpportunity::STAGES['discussed']['label']);

        $column = collect(\Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM treatment_opportunities LIKE 'status'"))->first();
        $this->assertStringContainsString("'accepted'", $column->Type ?? '',
            'the Committed slot already exists in the enum and is simply unused');
    }

    /**
     * FINDING — the blast radius of fixing C-1. "Open opportunity" is defined
     * in 14 places as NOT IN ('completed','declined'). If acceptance starts
     * writing 'accepted' (Committed), every one of those treats an accepted
     * plan as still open, so patients who already said yes re-enter the
     * sales-chase pool. The fix needs a canonical closed-set, not just a
     * changed value.
     */
    public function test_open_opportunity_is_defined_by_excluding_only_completed_and_declined(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);

        $opp = TreatmentOpportunity::create([
            'patient_id' => $plan->patient_id, 'treatment_plan_id' => $plan->id,
            'type' => 'other', 'label' => 'Implant', 'status' => 'accepted',
        ]);

        // Today an 'accepted' (Committed) card counts as OPEN everywhere.
        $this->assertSame(1, TreatmentOpportunity::open()->count(),
            'FINDING: Committed is currently indistinguishable from still-being-chased');

        $opp->update(['status' => 'completed']);
        $this->assertSame(0, TreatmentOpportunity::open()->count());
    }

    // ── 6. Treatment start cannot be attributed to a plan ────────────────────

    /**
     * CORRECTION TO C-5 — I previously reported that treatment_visits had no
     * treatment_plan_id and called it a structural blocker. That was WRONG: I
     * read only the create-table migration and missed
     * 2026_05_27_000003_add_treatment_plan_id_to_visits.php.
     *
     * The link EXISTS, is fillable, is validated in TreatmentVisitService, and
     * already drives completePlanAndQueueRecall(). It is NULLABLE, so the real
     * constraint is coverage (how many production visits actually carry it),
     * not expressibility. Converted remains out of scope for this slice per the
     * directive — but it is not blocked by schema.
     */
    public function test_a_treatment_visit_can_be_attributed_to_a_treatment_plan(): void
    {
        $this->assertTrue(Schema::hasTable('treatment_visits'));
        $this->assertTrue(Schema::hasColumn('treatment_visits', 'treatment_plan_id'),
            'CORRECTION: the visit-to-plan link exists and is usable');

        $this->assertContains('treatment_plan_id', (new \App\Models\TreatmentVisit)->getFillable());

        foreach (['patient_id', 'appointment_id', 'consultation_id', 'doctor_id'] as $col) {
            $this->assertTrue(Schema::hasColumn('treatment_visits', $col));
        }
    }
}
