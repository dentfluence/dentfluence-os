<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\BillingPrompt;
use App\Models\Task;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Treatment Visits — clinical workflow side-effects.
 *
 * Covers everything TreatmentVisitService::create()/update() is supposed to
 * fan out to: lab cases, billing prompts, implant placement + stock, plan
 * linkage, work_outcome, repeat-work tracking, activity events, and the
 * 6-month recall task.
 */
class TreatmentVisitClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    // ── Plan-linked vs. ad-hoc ───────────────────────────────────────────────

    public function test_visit_linked_to_a_treatment_plan_persists_the_link(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [$plan, ] = $this->makeAcceptedPlanWithItem($patient);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visits', [
            'patient_id'        => $patient->id,
            'treatment_plan_id' => $plan->id,
        ]);
    }

    public function test_adhoc_visit_without_a_treatment_plan_can_be_created(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'Emergency Pain Relief',
            'visit_items'    => [[
                // No treatment_plan_item_id — pure walk-in work.
                'treatment_name' => 'Emergency Pain Relief',
            ]],
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('treatment_visits', [
            'patient_id'        => $patient->id,
            'treatment_plan_id' => null,
        ]);
        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_name'         => 'Emergency Pain Relief',
            'treatment_plan_item_id' => null,
        ]);
    }

    // ── Implant ──────────────────────────────────────────────────────────────

    public function test_implant_visit_creates_an_implant_placement_record(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'Implant',
            'tooth_number'   => '36',
            'impl_brand'     => 'Nobel Biocare',
            'impl_size'      => '4.3x10',
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseHas('implant_placements', [
            'treatment_visit_id'     => $visit->id,
            'patient_id'             => $patient->id,
            'implant_brand_freetext' => 'Nobel Biocare',
            'status'                 => 'placed',
        ]);
    }

    public function test_implant_stock_is_deducted_once_and_not_duplicated_on_resave(): void
    {
        $user       = $this->makeUser();
        $patient    = $this->makePatient();
        $item       = $this->makeInventoryItem();
        $catalog    = $this->makeImplantCatalogEntry($item);
        $this->makeInventoryLocation(); // implant_drawer location the service will pick up

        $payload = $this->baseVisitPayload([
            'treatment_name'              => 'Implant',
            'implant_fixture_catalog_id'  => $catalog->id,
        ]);

        $create = $this->actingAs($user)->postJson(route('visits.store', $patient), $payload);
        $create->assertOk();
        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'reference_type'    => TreatmentVisit::class,
            'reference_id'      => $visit->id,
            'qty'               => -1,
        ]);

        // Re-saving the SAME visit with the SAME implant component must not
        // deduct stock a second time (idempotency guard in
        // recordImplantPlacementAndStock()).
        $this->actingAs($user)->putJson(route('visits.update', $visit), $payload)->assertOk();

        $this->assertDatabaseCount('stock_movements', 1);
    }

    // ── Lab case ─────────────────────────────────────────────────────────────

    public function test_visit_with_lab_section_creates_a_draft_lab_case(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $vendor  = $this->makeLabVendor();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'Crown',
            'tooth_number'   => '11,12',
            'lab_case'       => [
                'enabled'       => true,
                'lab_vendor_id' => $vendor->id,
                'work_category' => 'Crown',
                'priority'      => 'routine',
            ],
        ]))->assertOk();

        $this->assertDatabaseHas('lab_cases', [
            'patient_id'    => $patient->id,
            'lab_vendor_id' => $vendor->id,
            'status'        => 'draft',
        ]);
        $this->assertDatabaseCount('lab_case_items', 2); // one per tooth, inherited from tooth_number
    }

    // ── Billing prompt ───────────────────────────────────────────────────────

    public function test_saving_visit_items_creates_a_pending_billing_prompt(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [[
                'treatment_name' => 'Scaling',
                'tooth_number'   => 'Full mouth',
            ]],
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseHas('billing_prompts', [
            'patient_id'   => $patient->id,
            'trigger_type' => 'treatment_visit',
            'trigger_id'   => $visit->id,
            'status'       => 'pending',
        ]);
    }

    public function test_updating_visit_items_dismisses_the_old_prompt_and_creates_a_new_one(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [['treatment_name' => 'Scaling']],
        ]))->assertOk();

        $visit      = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();
        $firstPrompt = BillingPrompt::where('trigger_id', $visit->id)->firstOrFail();

        $this->actingAs($user)->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [['treatment_name' => 'Polishing']],
        ]))->assertOk();

        $this->assertDatabaseHas('billing_prompts', [
            'id'     => $firstPrompt->id,
            'status' => 'dismissed',
        ]);
        $this->assertDatabaseHas('billing_prompts', [
            'trigger_id'  => $visit->id,
            'status'      => 'pending',
            'description' => 'Bill for: Polishing',
        ]);
        $this->assertDatabaseCount('billing_prompts', 2);
    }

    // ── work_outcome ─────────────────────────────────────────────────────────

    public function test_work_outcome_is_persisted_for_planned_items_and_nulled_for_adhoc_items(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [$plan, $planItem] = $this->makeAcceptedPlanWithItem($patient, 'RCT');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'visit_items'       => [
                [
                    'treatment_plan_item_id' => $planItem->id,
                    'treatment_name'         => 'RCT',
                    'work_outcome'           => 'completed_today',
                ],
                [
                    // Ad-hoc item — no plan link. Per TreatmentVisitService::
                    // saveVisitItems(), work_outcome must be forced to null
                    // here even though the client sent one.
                    'treatment_name' => 'Fluoride Application',
                    'work_outcome'   => 'started',
                ],
            ],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_plan_item_id' => $planItem->id,
            'work_outcome'           => 'completed_today',
        ]);
        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_name'         => 'Fluoride Application',
            'treatment_plan_item_id' => null,
            'work_outcome'           => null,
        ]);
    }

    // ── Repeat work ──────────────────────────────────────────────────────────

    public function test_repeat_treatment_is_flagged_with_its_reason_and_source_item(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [['treatment_name' => 'RCT', 'tooth_number' => '26']],
        ]))->assertOk();
        $originalItem = TreatmentVisitItem::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [[
                'treatment_name'          => 'RCT',
                'tooth_number'            => '26',
                'is_repeat'               => true,
                'repeat_reason'           => 'Persistent pain after first attempt',
                'repeat_of_visit_item_id' => $originalItem->id,
            ]],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', [
            'is_repeat'               => true,
            'repeat_reason'           => 'Persistent pain after first attempt',
            'repeat_of_visit_item_id' => $originalItem->id,
        ]);
    }

    // ── guardPlanItemOwnership() ─────────────────────────────────────────────

    public function test_visit_item_cannot_reference_a_plan_item_from_a_different_plan(): void
    {
        $user     = $this->makeUser();
        $patientA = $this->makePatient();
        $patientB = $this->makePatient();
        [$planA, ] = $this->makeAcceptedPlanWithItem($patientA, 'RCT');
        [, $itemB] = $this->makeAcceptedPlanWithItem($patientB, 'Crown');

        $response = $this->actingAs($user)->postJson(route('visits.store', $patientA), $this->baseVisitPayload([
            'treatment_plan_id' => $planA->id,
            'visit_items'       => [[
                'treatment_plan_item_id' => $itemB->id,
                'treatment_name'         => 'Crown',
            ]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_items']);
    }

    public function test_visit_item_referencing_a_plan_item_with_no_plan_linked_to_the_visit_is_rejected(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [, $planItem] = $this->makeAcceptedPlanWithItem($patient, 'RCT');

        // treatment_plan_id intentionally omitted on the visit itself.
        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [[
                'treatment_plan_item_id' => $planItem->id,
                'treatment_name'         => 'RCT',
            ]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_items']);
    }

    // ── Activity log ─────────────────────────────────────────────────────────

    public function test_recording_planned_work_logs_an_activity_event(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [$plan, $planItem] = $this->makeAcceptedPlanWithItem($patient, 'RCT');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'visit_items'       => [[
                'treatment_plan_item_id' => $planItem->id,
                'treatment_name'         => 'RCT',
                'work_outcome'           => 'completed_today',
            ]],
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseHas('activities', [
            'subject_type' => TreatmentVisit::class,
            'subject_id'   => $visit->id,
            'event'        => 'treatment_visit.work_recorded',
        ]);
    }

    public function test_adhoc_visit_with_no_planned_work_does_not_log_a_work_recorded_event(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [['treatment_name' => 'Consultation only']],
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->assertDatabaseMissing('activities', [
            'subject_type' => TreatmentVisit::class,
            'subject_id'   => $visit->id,
            'event'        => 'treatment_visit.work_recorded',
        ]);
    }

    // ── Recall task ──────────────────────────────────────────────────────────

    public function test_marking_treatment_complete_creates_a_six_month_recall_task(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [$plan, ] = $this->makeAcceptedPlanWithItem($patient);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id'        => $plan->id,
            'mark_treatment_complete'  => true,
        ]))->assertOk();

        $this->assertDatabaseHas('tasks', [
            'patient_id' => $patient->id,
            'category'   => 'follow_up',
            'status'     => 'pending',
        ]);
        $task = Task::where('patient_id', $patient->id)->firstOrFail();
        $this->assertStringContainsString('recall', strtolower($task->title));

        $this->assertDatabaseHas('activities', [
            'subject_type' => \App\Models\TreatmentPlan::class,
            'subject_id'   => $plan->id,
            'event'        => 'treatment.completed',
        ]);
    }

    public function test_marking_treatment_complete_twice_does_not_duplicate_the_recall_task(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        [$plan, ] = $this->makeAcceptedPlanWithItem($patient);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id'       => $plan->id,
            'mark_treatment_complete' => true,
        ]))->assertOk();

        // A second, separate visit against the same plan, also marked complete.
        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id'       => $plan->id,
            'mark_treatment_complete' => true,
        ]))->assertOk();

        $this->assertDatabaseCount('tasks', 1);
    }
}
