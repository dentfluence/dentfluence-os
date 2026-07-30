<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\PlanDecision;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.4e — THE SCREENS READ THE CANONICAL SERVICE.
 *
 * The plan screen and the visit screen now show clinical progress that came
 * from DerivedProgressService, and nothing else. These tests assert what the
 * user actually sees, because that is where the previous slices' defects hid:
 * the write path was right and the read path lied.
 */
class ProgressReadModelUiTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function acceptedPlan(int $items = 1): TreatmentPlan
    {
        $patient = Patient::create([
            'name' => 'UI Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Course',
            'status' => 'pending', 'rows' => [], 'total' => 10000,
            'accepted_at' => now(),
        ]);

        for ($i = 1; $i <= $items; $i++) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id, 'treatment_name' => 'Treatment ' . $i,
                'unit_price' => 5000, 'units' => 1, 'total' => 5000,
            ]);
        }

        PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::ACCEPTED,
            'source'            => 'clinic',
        ]);

        return $plan->fresh('items');
    }

    private function fact(TreatmentPlan $plan, TreatmentPlanItem $item, string $outcome, bool $deleted = false): void
    {
        $visit = TreatmentVisit::create([
            'patient_id' => $plan->patient_id, 'doctor_id' => $this->admin()->id,
            'treatment_plan_id' => $plan->id, 'visit_date' => now()->toDateString(),
            'treatment_name' => $item->treatment_name, 'created_by' => $this->admin()->id,
        ]);

        TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id, 'patient_id' => $plan->patient_id,
            'treatment_plan_item_id' => $item->id, 'treatment_name' => $item->treatment_name,
            'work_outcome' => $outcome,
        ]);

        if ($deleted) {
            $visit->delete();
        }
    }

    private function planTab(TreatmentPlan $plan): string
    {
        return $this->actingAs($this->admin())
            ->get(route('patients.tab', ['patient' => $plan->patient_id, 'tab' => 'treatment-plan']))
            ->assertOk()->getContent();
    }

    private function visitTab(TreatmentPlan $plan): string
    {
        return $this->actingAs($this->admin())
            ->get(route('patients.tab', ['patient' => $plan->patient_id, 'tab' => 'visits']))
            ->assertOk()->getContent();
    }

    // ── The plan screen ──────────────────────────────────────────────────────

    public function test_the_plan_screen_no_longer_carries_the_legacy_status_column(): void
    {
        $plan = $this->acceptedPlan();

        $html = $this->planTab($plan);

        // The payload used to carry plan.status even though nothing rendered it.
        // Leaving it there is an invitation to read a lifecycle column as progress.
        $this->assertMatchesRegularExpression('/"id":' . $plan->id . ',.*?"progress":/s', $html);
        $this->assertDoesNotMatchRegularExpression('/"id":' . $plan->id . ',[^}]*"status":/s', $html);
    }

    public function test_the_plan_screen_shows_progress_derived_from_recorded_work(): void
    {
        $plan = $this->acceptedPlan(2);
        [$a, $b] = $plan->items->all();

        $this->fact($plan, $a, TreatmentVisitItem::WORK_COMPLETED_TODAY);
        $this->fact($plan, $b, TreatmentVisitItem::WORK_STARTED);

        $this->assertStringContainsString('"progress":"In Progress"', $this->planTab($plan));
    }

    public function test_the_plan_screen_ceiling_reads_all_work_recorded_never_completed(): void
    {
        $plan = $this->acceptedPlan(1);
        $this->fact($plan, $plan->items->first(), TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $html = $this->planTab($plan);

        $this->assertStringContainsString('"progress":"All Work Recorded"', $html);
        $this->assertStringNotContainsString('"progress":"Completed"', $html);
    }

    // ── Legacy columns cannot move the display ───────────────────────────────

    public function test_setting_every_legacy_status_to_completed_does_not_move_the_display(): void
    {
        $plan = $this->acceptedPlan(1);

        $plan->forceFill(['status' => 'completed'])->save();
        $plan->items->first()->forceFill([
            'status' => 'completed', 'billing_progress' => 'invoiced',
        ])->save();

        // Billing and the legacy lifecycle both scream "done"; no clinical work
        // was ever recorded, so the screen must say Not Started.
        $this->assertStringContainsString('"progress":"Not Started"', $this->planTab($plan));
    }

    // ── Soft-deleted work stays invisible on screen ──────────────────────────

    public function test_work_on_a_deleted_visit_never_reaches_the_screen(): void
    {
        $plan = $this->acceptedPlan(1);
        $this->fact($plan, $plan->items->first(), TreatmentVisitItem::WORK_COMPLETED_TODAY, deleted: true);

        $this->assertStringContainsString('"progress":"Not Started"', $this->planTab($plan));
    }

    // ── Rejected work stays out of the displayed figure ──────────────────────

    public function test_work_on_a_rejected_item_does_not_appear_as_plan_progress(): void
    {
        $patient = Patient::create([
            'name' => 'Partial Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Course',
            'status' => 'pending', 'rows' => [], 'total' => 10000, 'accepted_at' => now(),
        ]);
        $accepted = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id, 'treatment_name' => 'Accepted work',
            'unit_price' => 5000, 'units' => 1, 'total' => 5000,
        ]);
        $rejected = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id, 'treatment_name' => 'Rejected work',
            'unit_price' => 5000, 'units' => 1, 'total' => 5000,
        ]);

        $decision = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::PARTIALLY_ACCEPTED, 'source' => 'clinic',
        ]);
        \App\Models\PlanDecisionItem::create(['plan_decision_id' => $decision->id,
            'treatment_plan_item_id' => $accepted->id, 'decision' => 'accepted']);
        \App\Models\PlanDecisionItem::create(['plan_decision_id' => $decision->id,
            'treatment_plan_item_id' => $rejected->id, 'decision' => 'rejected']);

        // Work happened on the treatment the patient declined.
        $this->fact($plan->fresh('items'), $rejected, TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertStringContainsString('"progress":"Not Started"', $this->planTab($plan),
            'work the patient never agreed to must not read as plan progress');
    }

    // ── Repeat work ──────────────────────────────────────────────────────────

    public function test_repeat_work_reopens_the_displayed_progress(): void
    {
        $plan = $this->acceptedPlan(1);
        $item = $plan->items->first();

        $this->fact($plan, $item, TreatmentVisitItem::WORK_COMPLETED_TODAY);
        $this->assertStringContainsString('"progress":"All Work Recorded"', $this->planTab($plan));

        $this->fact($plan, $item, TreatmentVisitItem::WORK_WORKED_ON);
        $this->assertStringContainsString('"progress":"In Progress"', $this->planTab($plan));
    }

    // ── The visit screen ─────────────────────────────────────────────────────

    public function test_the_visit_plan_picker_shows_canonical_progress_not_legacy_status(): void
    {
        $plan = $this->acceptedPlan(1);

        // Legacy column says the plan is under way; no work has been recorded.
        $plan->forceFill(['status' => 'ongoing'])->save();

        $html = $this->visitTab($plan);

        $this->assertStringContainsString('"progress":"Not Started"', $html);
        $this->assertStringNotContainsString('"status":"ongoing"', $html,
            'the picker used to label an untouched plan "(Ongoing)"');
    }

    public function test_the_visit_plan_picker_follows_recorded_work(): void
    {
        $plan = $this->acceptedPlan(1);
        $this->fact($plan, $plan->items->first(), TreatmentVisitItem::WORK_STARTED);

        $this->assertStringContainsString('"progress":"In Progress"', $this->visitTab($plan));
    }

    // ── The guard still holds after the wiring ───────────────────────────────

    public function test_wiring_the_screens_did_not_introduce_a_second_derivation(): void
    {
        $this->artisan('progress:invariant-check')->assertExitCode(0);
    }
}
