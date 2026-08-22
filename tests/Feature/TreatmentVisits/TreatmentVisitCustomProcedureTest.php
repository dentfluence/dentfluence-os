<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\BillingPrompt;
use App\Models\LabCase;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Treatment Visits — CUSTOM PROCEDURES ("+ Add Custom Treatment").
 *
 * The contract this file pins down (2026-08-19 sprint):
 *
 *   Today's Procedures has exactly TWO doors — the selected Treatment Plan,
 *   and + Add Custom Treatment. Past the door, a custom procedure is NOT a
 *   different kind of thing: it is a treatment_visit_items row like any
 *   other, and it flows through the same Recorded Items → Billing Preview →
 *   Lab Case → Treatment Progress path.
 *
 * So these tests deliberately assert the ABSENCE of a separate custom-
 * treatment code path as much as the presence of the feature:
 *   • no Material / Option on a procedure line (that is Lab Case's job)
 *   • tooth is OPTIONAL and never blocks a save
 *   • suggested price is optional, editable, and reaches billing
 *   • Lab Required ON reuses the ONE existing visit LabCase
 *   • plan-sourced procedures behave exactly as they did before
 */
class TreatmentVisitCustomProcedureTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    /** A custom procedure line exactly as the rebuilt form now sends it. */
    private function customItem(array $overrides = []): array
    {
        return array_merge([
            'treatment_plan_item_id' => null,
            'work_outcome'           => null,
            'treatment_name'         => 'Emergency Pain Relief',
            'material_option'        => null,
            'tooth_number'           => null,
            'suggested_price'        => null,
            'notes'                  => null,
        ], $overrides);
    }

    // ── H. Tooth is optional ─────────────────────────────────────────────────

    public function test_custom_procedure_saves_with_no_tooth_at_all(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'Scaling',
            'visit_items'    => [$this->customItem(['treatment_name' => 'Scaling'])],
        ]))->assertOk();

        $item = TreatmentVisitItem::where('treatment_name', 'Scaling')->firstOrFail();

        $this->assertNull($item->treatment_plan_item_id, 'A custom procedure carries no plan item link.');
        $this->assertNull($item->tooth_number, 'Tooth must be optional — a blank tooth is not an error.');
    }

    public function test_custom_procedure_saves_with_a_tooth_picked_from_the_chart(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        // The shared FDI picker writes a comma-joined string, same shape the
        // visit-level chart and the Lab module already produce.
        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem(['tooth_number' => '16, 17'])],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_name' => 'Emergency Pain Relief',
            'tooth_number'   => '16, 17',
        ]);
    }

    // ── C / F. Suggested price → Billing ─────────────────────────────────────

    public function test_custom_procedure_price_is_persisted_and_reaches_the_billing_prompt(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem([
                'treatment_name'  => 'Composite Filling',
                'tooth_number'    => '26',
                'suggested_price' => 2500,
            ])],
        ]))->assertOk();

        $item = TreatmentVisitItem::where('treatment_name', 'Composite Filling')->firstOrFail();
        $this->assertEquals('2500.00', (string) $item->suggested_price);
        $this->assertSame('pending', $item->billing_status);

        $prompt = BillingPrompt::where('trigger_type', 'treatment_visit')->firstOrFail();
        $this->assertStringContainsString('Composite Filling', $prompt->description);
        $this->assertStringContainsString('Tooth 26', $prompt->description);
    }

    public function test_custom_procedure_without_a_price_is_stored_as_zero_not_rejected(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem(['suggested_price' => null])],
        ]))->assertOk();

        $item = TreatmentVisitItem::where('treatment_name', 'Emergency Pain Relief')->firstOrFail();
        $this->assertEquals('0.00', (string) $item->suggested_price);
    }

    // ── C. Material / Option is gone ─────────────────────────────────────────

    public function test_custom_procedure_carries_no_material_option(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem()],
        ]))->assertOk();

        $item = TreatmentVisitItem::where('treatment_name', 'Emergency Pain Relief')->firstOrFail();

        // The column still exists (legacy rows keep their data) but nothing
        // in the UI can write it any more — material/subtype is Lab Case's.
        $this->assertNull($item->material_option);
    }

    // ── G. Recorded Items ────────────────────────────────────────────────────

    public function test_custom_procedure_comes_back_in_recorded_items_like_a_plan_procedure(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem([
                'treatment_name'  => 'Denture Repair',
                'tooth_number'    => '11',
                'suggested_price' => 800,
            ])],
        ]))->assertOk();

        $items = $response->json('visit.visit_items');

        $this->assertCount(1, $items);
        $this->assertSame('Denture Repair', $items[0]['treatment_name']);
        $this->assertSame('11', $items[0]['tooth_number']);
        // assertEquals, not assertSame: the service casts to (float), but a
        // whole number serialises to JSON as 800 and decodes back as an int.
        $this->assertEquals(800.0, (float) $items[0]['suggested_price']);
        $this->assertNull($items[0]['treatment_plan_item_id']);
    }

    // ── E. Lab Required ──────────────────────────────────────────────────────

    public function test_lab_required_on_creates_the_one_existing_draft_lab_case(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $vendor  = $this->makeLabVendor();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'PFM Crown',
            'tooth_number'   => '17',
            'visit_items'    => [$this->customItem([
                'treatment_name'  => 'PFM Crown',
                'tooth_number'    => '17',
                'suggested_price' => 6000,
            ])],
            // The per-procedure toggle drives THIS existing payload — there is
            // no second lab channel and no per-item lab columns.
            'lab_case' => [
                'enabled'              => true,
                'lab_vendor_id'        => $vendor->id,
                'work_category'        => 'Crown & Bridge',
                'work_subtype'         => 'PFM',
                'priority'             => 'routine',
                'expected_return_date' => now()->addDays(5)->format('Y-m-d'),
                'instructions'         => 'Shade A2.',
            ],
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();
        $case  = LabCase::where('treatment_visit_id', $visit->id)->firstOrFail();

        $this->assertSame('draft', $case->status);
        $this->assertSame($vendor->id, $case->lab_vendor_id);
        $this->assertSame('Crown & Bridge', $case->work_category);
        // The teeth the doctor already picked are inherited, not re-entered.
        $this->assertSame(1, $case->items()->count());
        $this->assertSame('17', $case->items()->first()->tooth_number);
    }

    public function test_lab_required_off_creates_no_lab_case(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->customItem(['treatment_name' => 'Scaling'])],
            'lab_case'    => null,
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(0, LabCase::where('treatment_visit_id', $visit->id)->count());
    }

    // ── B. Same model, two origins ───────────────────────────────────────────

    public function test_a_visit_can_record_a_plan_procedure_and_a_custom_procedure_together(): void
    {
        $user          = $this->makeUser();
        $patient       = $this->makePatient();
        [$plan, $line] = $this->makeAcceptedPlanWithItem($patient, 'RCT');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'RCT',
            'visit_items'       => [
                [
                    'treatment_plan_item_id' => $line->id,
                    'work_outcome'           => TreatmentVisitItem::WORK_COMPLETED_TODAY,
                    'treatment_name'         => 'RCT',
                    'tooth_number'           => '46',
                    'suggested_price'        => 5000,
                ],
                $this->customItem([
                    'treatment_name'  => 'Fluoride Application',
                    'suggested_price' => 500,
                ]),
            ],
        ]))->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(2, $visit->visitItems()->count());

        $planned = $visit->visitItems()->whereNotNull('treatment_plan_item_id')->firstOrFail();
        $custom  = $visit->visitItems()->whereNull('treatment_plan_item_id')->firstOrFail();

        $this->assertSame(TreatmentVisitItem::WORK_COMPLETED_TODAY, $planned->work_outcome);
        // Ad-hoc work has no plan item to report an outcome against, so the
        // service leaves it null rather than inventing one — unchanged rule.
        $this->assertNull($custom->work_outcome);

        // Both lines reach the front desk as one bill.
        $prompt = BillingPrompt::where('trigger_id', $visit->id)->firstOrFail();
        $this->assertStringContainsString('RCT', $prompt->description);
        $this->assertStringContainsString('Fluoride Application', $prompt->description);
    }

    // ── A / I. Treatment Plan regression ─────────────────────────────────────

    public function test_plan_procedure_recording_is_unchanged(): void
    {
        $user          = $this->makeUser();
        $patient       = $this->makePatient();
        [$plan, $line] = $this->makeAcceptedPlanWithItem($patient, 'Crown');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Crown',
            'visit_items'       => [[
                'treatment_plan_item_id' => $line->id,
                'work_outcome'           => TreatmentVisitItem::WORK_STARTED,
                'treatment_name'         => 'Crown',
                'tooth_number'           => '36',
                'suggested_price'        => 7000,
            ]],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', [
            'treatment_plan_item_id' => $line->id,
            'treatment_name'         => 'Crown',
            'tooth_number'           => '36',
            'work_outcome'           => TreatmentVisitItem::WORK_STARTED,
        ]);
    }

    public function test_a_plan_item_from_another_plan_is_still_refused(): void
    {
        $user     = $this->makeUser();
        $patient  = $this->makePatient();
        [$plan]   = $this->makeAcceptedPlanWithItem($patient, 'RCT');

        $otherPlan = TreatmentPlan::create([
            'patient_id'  => $this->makePatient()->id,
            'plan_type'   => 'best',
            'accepted_at' => now(),
        ]);
        $foreign = TreatmentPlanItem::create([
            'treatment_plan_id' => $otherPlan->id,
            'treatment_name'    => 'Extraction',
        ]);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'visit_items'       => [[
                'treatment_plan_item_id' => $foreign->id,
                'treatment_name'         => 'Extraction',
            ]],
        ]))->assertStatus(422);
    }

    // ── View contract ────────────────────────────────────────────────────────

    public function test_form_page_no_longer_offers_a_page_level_procedure_search(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertDontSee('Search a procedure');
        $response->assertDontSee('Material / Option');
        $response->assertDontSee('toggleOtherTreatment');
    }

    public function test_form_page_renders_the_custom_procedure_controls(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertSee('Add Custom Treatment');
        $response->assertSee('Select or type a procedure', false);
        $response->assertSee('Suggested Price', false);
        $response->assertSee('Lab Required?', false);
        // The optional tooth control is the SHARED odontogram, not a new one.
        $response->assertSee('toothChartMixin()', false);
        $response->assertSee('tp-tooth-popup', false);
    }

    public function test_form_page_publishes_the_procedure_catalogue_with_prices(): void
    {
        $user     = $this->userWithModulePerm('patients', true, true, false);
        $patient  = $this->makePatient();
        $category = TreatmentCategory::create(['name' => 'Ops', 'billing_basis' => 'gross']);

        Treatment::create([
            'treatment_category_id'    => $category->id,
            'name'                     => 'Zirconia Crown',
            'default_duration_minutes' => 45,
            'default_price'            => 9000,
            'gst_pct'                  => 0,
            'is_active'                => true,
        ]);

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertSee('TV_TREATMENT_CATALOG', false);
        $response->assertSee('Zirconia Crown', false);
        $response->assertSee('9000', false);
    }
}
