<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Models\User;
use App\Services\Patient\PatientJourneyService;
use App\Services\TreatmentVisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.4b — CLINICAL WORK CAPTURE.
 *
 * Records what the dentist did TODAY to a planned treatment. Facts only:
 * nothing here completes a plan, starts treatment, converts an opportunity or
 * queues a recall. 2.4a proved the system could not know what work was done;
 * this slice asks the clinician instead of inferring.
 */
class ClinicalWorkCaptureTest extends TestCase
{
    use RefreshDatabase;

    private ?User $doctor = null;

    private function doctor(): User
    {
        return $this->doctor ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name' => 'Work Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
    }

    private function planWithItems(Patient $patient): TreatmentPlan
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'RCT + Crown',
            'status' => 'pending', 'rows' => [], 'total' => 20000,
        ]);
        foreach ([['Root Canal Treatment', 12000], ['Crown', 8000]] as [$n, $p]) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id, 'treatment_name' => $n,
                'unit_price' => $p, 'units' => 1, 'total' => $p,
            ]);
        }

        return $plan->fresh('items');
    }

    /** Record a visit through the real service, the way the screen does. */
    private function recordVisit(Patient $patient, ?TreatmentPlan $plan, array $visitItems): TreatmentVisit
    {
        $this->actingAs($this->doctor());

        return app(TreatmentVisitService::class)->create($patient, [
            'doctor_id'         => $this->doctor()->id,
            'visit_date'        => now()->toDateString(),
            'treatment_name'    => 'Root Canal Treatment',
            'treatment_plan_id' => $plan?->id,
            'visit_items'       => $visitItems,
        ]);
    }

    // ── Capture ──────────────────────────────────────────────────────────────

    public function test_a_dentist_can_record_what_they_did_today_to_a_planned_treatment(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        [$rct, $crown] = $plan->items->all();

        $visit = $this->recordVisit($patient, $plan, [
            ['treatment_plan_item_id' => $rct->id,   'treatment_name' => 'Root Canal Treatment',
             'work_outcome' => TreatmentVisitItem::WORK_STARTED],
            ['treatment_plan_item_id' => $crown->id, 'treatment_name' => 'Crown',
             'work_outcome' => TreatmentVisitItem::WORK_WORKED_ON],
        ]);

        $items = TreatmentVisitItem::where('treatment_visit_id', $visit->id)->get();

        $this->assertCount(2, $items);
        $this->assertSame(TreatmentVisitItem::WORK_STARTED,
            $items->firstWhere('treatment_plan_item_id', $rct->id)->work_outcome);
        $this->assertSame(TreatmentVisitItem::WORK_WORKED_ON,
            $items->firstWhere('treatment_plan_item_id', $crown->id)->work_outcome);
    }

    public function test_the_same_treatment_can_be_worked_on_across_several_visits(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        $rct     = $plan->items->first();

        foreach ([
            TreatmentVisitItem::WORK_STARTED,
            TreatmentVisitItem::WORK_WORKED_ON,
            TreatmentVisitItem::WORK_COMPLETED_TODAY,
        ] as $outcome) {
            $this->recordVisit($patient, $plan, [[
                'treatment_plan_item_id' => $rct->id,
                'treatment_name'         => 'Root Canal Treatment',
                'work_outcome'           => $outcome,
            ]]);
        }

        $rows = TreatmentVisitItem::where('treatment_plan_item_id', $rct->id)
            ->orderBy('id')->pluck('work_outcome')->all();

        // Three visits, three separate facts, all still true.
        $this->assertSame([
            TreatmentVisitItem::WORK_STARTED,
            TreatmentVisitItem::WORK_WORKED_ON,
            TreatmentVisitItem::WORK_COMPLETED_TODAY,
        ], $rows);
    }

    // ── The critical rule: capture is not completion ─────────────────────────

    public function test_recording_completed_today_does_not_complete_the_plan_or_touch_anything_downstream(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        $rct     = $plan->items->first();

        $this->recordVisit($patient, $plan, [[
            'treatment_plan_item_id' => $rct->id,
            'treatment_name'         => 'Root Canal Treatment',
            'work_outcome'           => TreatmentVisitItem::WORK_COMPLETED_TODAY,
        ]]);

        $plan->refresh();

        // The plan is untouched…
        $this->assertSame('pending', $plan->status, 'capture must never complete a plan');
        $this->assertNull($plan->accepted_at);

        // …the plan ITEM is untouched (item.status stays inert)…
        $this->assertSame('pending', $rct->fresh()->status);

        // …no opportunity was created or converted…
        $this->assertSame(0, TreatmentOpportunity::count());

        // …and no recall was queued.
        $this->assertDatabaseMissing('tasks', ['category' => 'follow_up']);
    }

    // ── Ownership validation ─────────────────────────────────────────────────

    public function test_work_cannot_be_recorded_against_another_plans_treatment(): void
    {
        $patient   = $this->patient();
        $ownPlan   = $this->planWithItems($patient);
        $otherPlan = $this->planWithItems($this->patient());

        $this->expectException(ValidationException::class);

        $this->recordVisit($patient, $ownPlan, [[
            'treatment_plan_item_id' => $otherPlan->items->first()->id,
            'treatment_name'         => 'Root Canal Treatment',
            'work_outcome'           => TreatmentVisitItem::WORK_STARTED,
        ]]);
    }

    public function test_planned_work_cannot_be_recorded_on_a_visit_with_no_plan(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);

        $this->expectException(ValidationException::class);

        $this->recordVisit($patient, null, [[
            'treatment_plan_item_id' => $plan->items->first()->id,
            'treatment_name'         => 'Root Canal Treatment',
            'work_outcome'           => TreatmentVisitItem::WORK_STARTED,
        ]]);
    }

    // ── Visits without plans are untouched ───────────────────────────────────

    public function test_an_ad_hoc_visit_still_works_exactly_as_before(): void
    {
        $patient = $this->patient();

        $visit = $this->recordVisit($patient, null, [[
            'treatment_name' => 'Emergency pain relief',
            'tooth_number'   => '26',
        ]]);

        $item = TreatmentVisitItem::where('treatment_visit_id', $visit->id)->firstOrFail();

        $this->assertNull($item->treatment_plan_item_id);
        $this->assertNull($item->work_outcome, 'ad-hoc work has no planned outcome to record');
    }

    public function test_an_outcome_sent_for_unplanned_work_is_ignored_not_stored(): void
    {
        $patient = $this->patient();

        $visit = $this->recordVisit($patient, null, [[
            'treatment_name' => 'Scaling',
            'work_outcome'   => TreatmentVisitItem::WORK_COMPLETED_TODAY,   // nonsense without a plan item
        ]]);

        $this->assertNull(
            TreatmentVisitItem::where('treatment_visit_id', $visit->id)->value('work_outcome'),
        );
    }

    // ── Journey ──────────────────────────────────────────────────────────────

    public function test_todays_work_appears_once_on_the_journey_not_once_per_treatment(): void
    {
        $patient = $this->patient();
        $plan    = $this->planWithItems($patient);
        [$rct, $crown] = $plan->items->all();

        $this->recordVisit($patient, $plan, [
            ['treatment_plan_item_id' => $rct->id,   'treatment_name' => 'Root Canal Treatment',
             'work_outcome' => TreatmentVisitItem::WORK_STARTED],
            ['treatment_plan_item_id' => $crown->id, 'treatment_name' => 'Crown',
             'work_outcome' => TreatmentVisitItem::WORK_WORKED_ON],
        ]);

        $this->assertSame(1, \App\Models\Activity::where('event', 'treatment_visit.work_recorded')->count(),
            'one visit is one piece of work, not one event per treatment');

        $activity = \App\Models\Activity::where('event', 'treatment_visit.work_recorded')->firstOrFail();
        $this->assertStringContainsString('Root Canal Treatment — Started', $activity->description);
        $this->assertStringContainsString('Crown — Worked On', $activity->description);
        $this->assertCount(2, $activity->metadata['work']);
    }

    public function test_an_ad_hoc_visit_writes_no_clinical_work_event(): void
    {
        $this->recordVisit($this->patient(), null, [['treatment_name' => 'Emergency pain relief']]);

        $this->assertDatabaseMissing('activities', ['event' => 'treatment_visit.work_recorded']);
    }

    // ── Vocabulary ───────────────────────────────────────────────────────────

    public function test_the_dentist_only_ever_sees_three_plain_words(): void
    {
        $this->assertSame(
            ['Started', 'Worked On', 'Completed Today'],
            array_values(TreatmentVisitItem::WORK_OUTCOMES),
        );
    }
}
