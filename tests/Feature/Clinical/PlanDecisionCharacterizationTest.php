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

    /**
     * SUPERSEDED BY SLICE 2.3d. This originally recorded that there was no way
     * at all to say a patient was thinking about it — no verb, no column, no
     * event — so a presented-but-undecided plan was indistinguishable from one
     * the patient had actively deferred.
     *
     * All four verbs now exist. What is retained is the distinction that made
     * the gap worth closing: NOT YET DECIDED and DEFERRED are different truths,
     * and silence must never be recorded as a decision.
     */
    public function test_undecided_and_deferred_remain_different_truths(): void
    {
        foreach (['accept', 'acceptPartially', 'defer', 'reject'] as $verb) {
            $this->assertTrue(method_exists(TreatmentPlanAcceptanceService::class, $verb),
                "SLICE 2.3d: {$verb}() is part of the one decision service");
        }

        // A presented plan nobody has decided on carries NO decision row.
        // Not-yet-decided is the absence of a decision, never a recorded one.
        $plan = $this->planWithItems($this->patient());
        app(\App\Services\TreatmentPlan\TreatmentPlanPresentationService::class)
            ->markPresented($plan, $this->admin(), 'clinic');

        $this->assertNull($plan->fresh()->currentDecision());
        $this->assertTrue($plan->fresh()->is_decision_pending);
        $this->assertDatabaseCount('plan_decisions', 0);

        // Deferring is an explicit act that DOES record one.
        $this->actingAs($this->admin());
        app(TreatmentPlanAcceptanceService::class)->defer($plan->fresh(), null, 'Will think about it', $this->admin());

        $this->assertSame(\App\Models\PlanDecision::DEFERRED, $plan->fresh()->currentDecision()->decision);
        $this->assertFalse($plan->fresh()->is_decision_pending);
    }

    // ── 5. Acceptance maps straight to the board's "Converted" ───────────────

    /**
     * FINDING — contradiction C-1 of the frozen integration contract. The
     * contract requires Accepted → COMMITTED and only actual treatment start →
     * CONVERTED. Today acceptance writes opportunity status 'completed', which
     * the PRE UI renders as "✓ Converted".
     */
    public function test_acceptance_marks_the_opportunity_committed_never_converted(): void
    {
        $plan = $this->planWithItems($this->patient());

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $this->admin(), 'clinic');

        // C-1 IS NOW FIXED (Slice 2.3c). This test originally recorded the
        // defect: acceptance stored 'completed', which the board labels
        // "Converted". Retained to prove the fix holds and never regresses.
        $this->assertSame(TreatmentOpportunity::COMMITTED, TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'),
            'C-1 fixed: acceptance stores Committed, not Converted');

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
     * The blast radius of the C-1 fix, now closed. "Open opportunity" used to
     * be spelled out as NOT IN ('completed','declined') in 14 separate places,
     * so an accepted card counted as open everywhere. There is now ONE
     * definition, and Committed sits inside it.
     */
    public function test_open_is_defined_once_and_a_committed_card_is_not_open(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);

        $opp = TreatmentOpportunity::create([
            'patient_id' => $plan->patient_id, 'treatment_plan_id' => $plan->id,
            'type' => 'other', 'label' => 'Implant', 'status' => TreatmentOpportunity::COMMITTED,
        ]);

        $this->assertSame(0, TreatmentOpportunity::open()->count(),
            'Committed is decided work, not sales work');

        // …but it is still a live, visible card — not hidden.
        $this->assertSame(1, TreatmentOpportunity::committed()->count());

        // A still-undecided card remains open.
        $opp2 = TreatmentOpportunity::create([
            'patient_id' => $plan->patient_id, 'type' => 'other',
            'label' => 'Crown', 'status' => 'quoted',
        ]);
        $this->assertSame(1, TreatmentOpportunity::open()->count());
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
