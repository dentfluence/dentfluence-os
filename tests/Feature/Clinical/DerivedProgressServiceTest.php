<?php

namespace Tests\Feature\Clinical;

use App\Enums\ClinicalProgress;
use App\Enums\PlanProgress;
use App\Models\Patient;
use App\Models\PlanDecision;
use App\Models\PlanDecisionItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Models\User;
use App\Services\Clinical\DerivedProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.4d — THE CANONICAL DERIVED PROGRESS SERVICE.
 *
 * Implements the frozen 2.4c contract. Progress is derived from captured
 * clinical facts, latest VALID fact wins, nothing is stored, and the ceiling
 * is "All Work Recorded" — never "Completed".
 */
class DerivedProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?User $doctor = null;

    private function doctor(): User
    {
        return $this->doctor ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function service(): DerivedProgressService
    {
        return app(DerivedProgressService::class);
    }

    private function plan(int $itemCount = 2): TreatmentPlan
    {
        $patient = Patient::create([
            'name' => 'Progress Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Course',
            'status' => 'pending', 'rows' => [], 'total' => 10000,
        ]);

        for ($i = 1; $i <= $itemCount; $i++) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id, 'treatment_name' => 'Treatment ' . $i,
                'unit_price' => 5000, 'units' => 1, 'total' => 5000,
            ]);
        }

        return $plan->fresh('items');
    }

    /** Record a clinical fact directly — bypassing the writer keeps this a unit of the READER. */
    private function fact(
        TreatmentPlan $plan,
        TreatmentPlanItem $item,
        ?string $outcome,
        string $date = 'today',
        bool $deleted = false,
    ): TreatmentVisitItem {
        $visit = TreatmentVisit::create([
            'patient_id'        => $plan->patient_id,
            'doctor_id'         => $this->doctor()->id,
            'treatment_plan_id' => $plan->id,
            'visit_date'        => $date === 'today' ? now()->toDateString() : $date,
            'treatment_name'    => $item->treatment_name,
            'created_by'        => $this->doctor()->id,
        ]);

        $vi = TreatmentVisitItem::create([
            'treatment_visit_id'     => $visit->id,
            'patient_id'             => $plan->patient_id,
            'treatment_plan_item_id' => $item->id,
            'treatment_name'         => $item->treatment_name,
            'work_outcome'           => $outcome,
        ]);

        if ($deleted) {
            $visit->delete();   // soft delete — the item row REMAINS readable
        }

        return $vi;
    }

    private function accept(TreatmentPlan $plan): void
    {
        PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::ACCEPTED,
            'source'            => 'clinic',
        ]);
    }

    // ── STARTED ──────────────────────────────────────────────────────────────

    public function test_a_plan_with_no_recorded_work_has_not_started(): void
    {
        $this->assertFalse($this->service()->isTreatmentStarted($this->plan()));
    }

    public function test_one_recorded_fact_starts_the_plan(): void
    {
        $plan = $this->plan();
        $this->fact($plan, $plan->items->first(), TreatmentVisitItem::WORK_STARTED);

        $this->assertTrue($this->service()->isTreatmentStarted($plan));
    }

    /**
     * Linkage alone is a billing convenience, not evidence of work. A visit
     * item picked off the plan but never given an outcome — every pre-2.4b row
     * looks like this — must NOT start the plan.
     */
    public function test_a_linked_item_with_no_outcome_does_not_start_the_plan(): void
    {
        $plan = $this->plan();
        $this->fact($plan, $plan->items->first(), null);

        $this->assertFalse($this->service()->isTreatmentStarted($plan));
        $this->assertSame(ClinicalProgress::NotStarted,
            $this->service()->deriveTreatmentPlanItemProgress($plan->items->first()));
    }

    public function test_ad_hoc_work_with_no_plan_item_is_invisible_to_the_plan(): void
    {
        $plan  = $this->plan();
        $visit = TreatmentVisit::create([
            'patient_id' => $plan->patient_id, 'doctor_id' => $this->doctor()->id,
            'visit_date' => now()->toDateString(), 'treatment_name' => 'Emergency',
            'created_by' => $this->doctor()->id,
        ]);
        TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id, 'patient_id' => $plan->patient_id,
            'treatment_name' => 'Emergency', 'work_outcome' => TreatmentVisitItem::WORK_COMPLETED_TODAY,
        ]);

        $this->assertFalse($this->service()->isTreatmentStarted($plan));
    }

    // ── THE SOFT-DELETE TRAP ─────────────────────────────────────────────────

    /**
     * treatment_visits soft-deletes; treatment_visit_items does NOT cascade.
     * A derivation that queries visit items directly silently counts deleted
     * work. This is the defect the 2.4c contract flagged as most likely.
     */
    public function test_work_on_a_deleted_visit_is_ignored_entirely(): void
    {
        $plan = $this->plan();
        $item = $plan->items->first();

        $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY, deleted: true);

        // The row still exists…
        $this->assertDatabaseCount('treatment_visit_items', 1);

        // …and the service must not see it.
        $this->assertFalse($this->service()->isTreatmentStarted($plan));
        $this->assertSame(ClinicalProgress::NotStarted,
            $this->service()->deriveTreatmentPlanItemProgress($item));
    }

    public function test_deleting_the_latest_visit_falls_back_to_the_previous_valid_fact(): void
    {
        $plan = $this->plan();
        $item = $plan->items->first();

        $this->fact($plan, $item, TreatmentVisitItem::WORK_STARTED, '2026-07-01');
        $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY, '2026-07-20', deleted: true);

        $this->assertSame(ClinicalProgress::Started,
            $this->service()->deriveTreatmentPlanItemProgress($item),
            'the completed fact was withdrawn, so the earlier one is again the latest valid fact');
    }

    // ── LATEST VALID FACT WINS ───────────────────────────────────────────────

    public function test_the_multi_visit_course_progresses_exactly_as_designed(): void
    {
        $plan = $this->plan(1);
        $item = $plan->items->first();
        $this->accept($plan);

        $this->assertSame(ClinicalProgress::NotStarted, $this->service()->deriveTreatmentPlanItemProgress($item));
        $this->assertSame(PlanProgress::NotStarted, $this->service()->deriveTreatmentPlanProgress($plan)->progress);

        $this->fact($plan, $item, TreatmentVisitItem::WORK_STARTED, '2026-07-01');
        $this->assertSame(ClinicalProgress::Started, $this->service()->deriveTreatmentPlanItemProgress($item));
        $this->assertSame(PlanProgress::InProgress, $this->service()->deriveTreatmentPlanProgress($plan)->progress);

        $this->fact($plan, $item, TreatmentVisitItem::WORK_WORKED_ON, '2026-07-10');
        $this->assertSame(ClinicalProgress::InProgress, $this->service()->deriveTreatmentPlanItemProgress($item));

        $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY, '2026-07-20');
        $this->assertSame(ClinicalProgress::Completed, $this->service()->deriveTreatmentPlanItemProgress($item));
        $this->assertSame(PlanProgress::AllWorkRecorded, $this->service()->deriveTreatmentPlanProgress($plan)->progress);
    }

    /**
     * Repeat work reopens an item. "Any completed ever" would freeze it as
     * Completed and hide the redo — which is precisely why latest-wins.
     */
    public function test_repeat_work_after_completion_reopens_the_item(): void
    {
        $plan = $this->plan(1);
        $item = $plan->items->first();
        $this->accept($plan);

        $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY, '2026-03-01');
        $this->assertSame(ClinicalProgress::Completed, $this->service()->deriveTreatmentPlanItemProgress($item));

        $this->fact($plan, $item, TreatmentVisitItem::WORK_WORKED_ON, '2026-07-01');

        $this->assertSame(ClinicalProgress::InProgress, $this->service()->deriveTreatmentPlanItemProgress($item));
        $this->assertSame(PlanProgress::InProgress, $this->service()->deriveTreatmentPlanProgress($plan)->progress);
    }

    // ── PLAN ROLL-UP ─────────────────────────────────────────────────────────

    public function test_mixed_completed_and_in_progress_items_read_as_in_progress(): void
    {
        $plan = $this->plan(2);
        [$a, $b] = $plan->items->all();
        $this->accept($plan);

        $this->fact($plan, $a, TreatmentVisitItem::WORK_COMPLETED_TODAY);
        $this->fact($plan, $b, TreatmentVisitItem::WORK_WORKED_ON);

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        $this->assertSame(PlanProgress::InProgress, $report->progress);
        $this->assertSame(2, $report->itemsInScope());
        $this->assertSame(2, $report->itemsWithWork());
        $this->assertSame(1, $report->itemsCompleted());
        $this->assertFalse($report->hasAllWorkRecorded());
    }

    public function test_all_accepted_items_completed_reads_as_all_work_recorded_not_completed(): void
    {
        $plan = $this->plan(2);
        $this->accept($plan);

        foreach ($plan->items as $item) {
            $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY);
        }

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        $this->assertSame(PlanProgress::AllWorkRecorded, $report->progress);
        $this->assertTrue($this->service()->hasAllWorkRecorded($plan));

        // The wording is the point — the ceiling is never "Completed".
        $this->assertSame('All Work Recorded', $report->progress->label());

        // …and nothing was stored.
        $this->assertSame('pending', $plan->fresh()->status);
    }

    // ── DECISION SCOPE (Slice 2.3 interaction) ───────────────────────────────

    public function test_a_plan_with_no_decision_has_nothing_in_scope(): void
    {
        $plan = $this->plan(2);
        $this->fact($plan, $plan->items->first(), TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        $this->assertSame(0, $report->itemsInScope(), 'the patient agreed to nothing');
        $this->assertSame(PlanProgress::NotStarted, $report->progress);

        // But treatment DID start, and the work is surfaced rather than hidden.
        $this->assertTrue($report->started);
        $this->assertTrue($report->hasWorkOutsideAcceptedPlan());
    }

    public function test_partial_acceptance_scopes_progress_to_the_accepted_items_only(): void
    {
        $plan = $this->plan(3);
        [$accepted, $deferred, $rejected] = $plan->items->all();

        $decision = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::PARTIALLY_ACCEPTED,
            'source'            => 'clinic',
        ]);
        foreach ([
            [$accepted, PlanDecisionItem::ACCEPTED],
            [$deferred, PlanDecisionItem::DEFERRED],
            [$rejected, PlanDecisionItem::REJECTED],
        ] as [$item, $verdict]) {
            PlanDecisionItem::create([
                'plan_decision_id' => $decision->id,
                'treatment_plan_item_id' => $item->id,
                'decision' => $verdict,
            ]);
        }

        $this->fact($plan, $accepted, TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        // Only the accepted item counts — otherwise a rejected crown would make
        // every partially accepted plan permanently incomplete.
        $this->assertSame(1, $report->itemsInScope());
        $this->assertSame(PlanProgress::AllWorkRecorded, $report->progress);
        $this->assertArrayHasKey($accepted->id, $report->items);
        $this->assertArrayNotHasKey($deferred->id, $report->items);
        $this->assertArrayNotHasKey($rejected->id, $report->items);
    }

    public function test_work_on_a_rejected_item_is_surfaced_but_never_counted(): void
    {
        $plan = $this->plan(2);
        [$accepted, $rejected] = $plan->items->all();

        $decision = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::PARTIALLY_ACCEPTED,
            'source'            => 'clinic',
        ]);
        PlanDecisionItem::create(['plan_decision_id' => $decision->id,
            'treatment_plan_item_id' => $accepted->id, 'decision' => PlanDecisionItem::ACCEPTED]);
        PlanDecisionItem::create(['plan_decision_id' => $decision->id,
            'treatment_plan_item_id' => $rejected->id, 'decision' => PlanDecisionItem::REJECTED]);

        // Emergency treatment happened on the rejected item anyway.
        $this->fact($plan, $rejected, TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        $this->assertSame(1, $report->itemsInScope());
        $this->assertSame(0, $report->itemsWithWork(), 'rejected work never counts as progress');
        $this->assertTrue($report->hasWorkOutsideAcceptedPlan());
        $this->assertArrayHasKey($rejected->id, $report->outOfScopeWork);

        // Crucially: clinical work has NOT overwritten the patient's decision.
        $this->assertSame(PlanDecisionItem::REJECTED,
            $decision->items()->where('treatment_plan_item_id', $rejected->id)->value('decision'));
    }

    public function test_a_deferred_plan_has_nothing_in_scope(): void
    {
        $plan = $this->plan(2);
        PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::DEFERRED,
            'source'            => 'clinic',
        ]);

        $this->assertSame(0, $this->service()->deriveTreatmentPlanProgress($plan)->itemsInScope());
    }

    // ── FORBIDDEN INPUTS ─────────────────────────────────────────────────────

    /**
     * The legacy columns must have no influence whatsoever. Setting every one
     * of them to "completed" must not move the derivation a millimetre.
     */
    public function test_legacy_status_columns_have_zero_influence_on_derived_progress(): void
    {
        $plan = $this->plan(1);
        $this->accept($plan);

        $plan->forceFill(['status' => 'completed'])->save();
        $plan->items->first()->forceFill([
            'status' => 'completed', 'billing_progress' => 'invoiced',
        ])->save();

        $report = $this->service()->deriveTreatmentPlanProgress($plan);

        $this->assertSame(PlanProgress::NotStarted, $report->progress,
            'billing and legacy status say completed; no clinical work was ever recorded');
        $this->assertFalse($this->service()->isTreatmentStarted($plan));
    }

    // ── ARCHITECTURAL GUARD ──────────────────────────────────────────────────

    public function test_no_component_outside_the_canonical_service_derives_progress(): void
    {
        $this->artisan('progress:invariant-check')->assertExitCode(0);
    }

    /**
     * A guard that can only ever pass proves nothing. This points the same
     * command at a fixture that deliberately reads the captured fact outside
     * the canonical service, and asserts it FAILS.
     *
     * The fixture also mentions the column inside a docblock, so this doubles
     * as proof that documentation is not punished — a guard that flags accurate
     * comments teaches people to write vague ones.
     */
    public function test_the_guard_actually_catches_a_violation(): void
    {
        $this->artisan('progress:invariant-check --path=tests/Fixtures/ProgressGuard')
            ->assertExitCode(1);
    }
}
