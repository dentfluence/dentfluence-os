<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * T-1 — the Treatment master id must survive the visit.
 *
 * Every case here sits on a way the link could later be quietly undone, and
 * three of them assert that NOTHING is linked. That is the point: the cheap
 * version of this feature is a fuzzy name match that "usually works", and a
 * treatment id that is usually right is worse than none at all, because every
 * revenue and provider report downstream reads it as fact.
 */
class VisitTreatmentLinkTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    /**
     * Deliberately an INSTANCE property, not a `static`.
     *
     * A static survives between test methods in the same PHP process, but
     * RefreshDatabase rolls its row back after every test — so from the second
     * test onward the cached object would point at a category id that no longer
     * exists, and every insert would die on the foreign key. PHPUnit builds a
     * fresh instance per test method, so an instance property resets with the
     * database and the two stay in step.
     */
    private ?TreatmentCategory $category = null;

    private function makeTreatment(string $name, bool $active = true): Treatment
    {
        $this->category ??= TreatmentCategory::create(['name' => 'Ops', 'billing_basis' => 'gross']);

        return Treatment::create([
            'treatment_category_id'    => $this->category->id,
            'name'                     => $name,
            'default_duration_minutes' => 30,
            'default_price'            => 5000,
            'gst_pct'                  => 0,
            'is_active'                => $active,
        ]);
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            'treatment_plan_item_id' => null,
            'work_outcome'           => null,
            'treatment_name'         => 'Zirconia Crown',
            'tooth_number'           => null,
            'suggested_price'        => null,
            'notes'                  => null,
        ], $overrides);
    }

    // ── 1. the catalogue picker sends the id ────────────────────────────────

    public function test_an_explicit_treatment_id_from_the_picker_is_stored(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment('Zirconia Crown');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->item(['treatment_id' => $treatment->id])],
        ]))->assertOk();

        $this->assertSame(
            $treatment->id,
            TreatmentVisitItem::firstOrFail()->treatment_id
        );
    }

    // ── 2. planned work links itself, with no client change at all ──────────

    public function test_a_plan_item_supplies_the_treatment_id_when_the_client_sends_none(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment('Root Canal Treatment');

        $plan = TreatmentPlan::create([
            'patient_id'  => $patient->id,
            'plan_type'   => 'best',
            'accepted_at' => now(),
        ]);
        $planItem = TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_id'      => $treatment->id,
            'treatment_name'    => 'Root Canal Treatment',
        ]);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_plan_id' => $plan->id,
            'visit_items'       => [$this->item([
                'treatment_plan_item_id' => $planItem->id,
                'treatment_name'         => 'Root Canal Treatment',
                // deliberately NO treatment_id — this is exactly what the phone
                // and any pre-T-1 browser tab send.
            ])],
        ]))->assertOk();

        $this->assertSame(
            $treatment->id,
            TreatmentVisitItem::firstOrFail()->treatment_id,
            'Work done against a plan item must inherit that item\'s treatment.'
        );
    }

    // ── 3. an exact, unambiguous name still links ───────────────────────────

    public function test_an_exact_name_match_links_even_though_no_id_was_sent(): void
    {
        $user      = $this->makeUser();
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment('Scaling and Polishing');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->item(['treatment_name' => '  scaling AND polishing '])],
        ]))->assertOk();

        $this->assertSame(
            $treatment->id,
            TreatmentVisitItem::firstOrFail()->treatment_id,
            'Matching is trimmed and case-insensitive — but still EXACT, never fuzzy.'
        );
    }

    // ── 4-6. the three ways it must refuse to guess ─────────────────────────

    public function test_a_procedure_the_master_does_not_have_is_recorded_with_no_link(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $this->makeTreatment('Zirconia Crown');

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->item(['treatment_name' => 'Emergency Pain Relief'])],
        ]))->assertOk();

        $item = TreatmentVisitItem::firstOrFail();
        $this->assertNull($item->treatment_id, 'The catalogue is a shortcut, not a gate.');
        $this->assertSame('Emergency Pain Relief', $item->treatment_name, 'The typed name is still the record.');
    }

    public function test_a_name_matching_two_master_rows_links_to_neither(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $this->makeTreatment('Crown');
        $this->makeTreatment('Crown');   // duplicate names are legal in the master

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->item(['treatment_name' => 'Crown'])],
        ]))->assertOk();

        $this->assertNull(
            TreatmentVisitItem::firstOrFail()->treatment_id,
            'Ambiguity is a question for a human. Picking one would poison every report that reads it.'
        );
    }

    public function test_a_retired_treatment_does_not_capture_new_work(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $this->makeTreatment('Amalgam Filling', active: false);

        $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [$this->item(['treatment_name' => 'Amalgam Filling'])],
        ]))->assertOk();

        $this->assertNull(
            TreatmentVisitItem::firstOrFail()->treatment_id,
            'A treatment switched off is off; only active master rows may claim new work.'
        );
    }

    // ── 7. the catalogue the form publishes must carry the id ───────────────

    public function test_the_form_publishes_the_catalogue_id_not_only_the_name(): void
    {
        $user      = $this->userWithModulePerm('patients', true, true, false);
        $patient   = $this->makePatient();
        $treatment = $this->makeTreatment('Zirconia Crown');

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertSee('TV_TREATMENT_CATALOG', false);

        // The id was being SELECTed and dropped before it reached the browser,
        // which is why the picker had nothing but a label to send back.
        //
        // The catalogue is printed through Illuminate\Support\Js::from(), which
        // encodes with JSON_HEX_QUOT — every `"` reaches the page as `\u0022`.
        // So a raw assertSee('"id":8') can never match even when the id is
        // there. Undo that one escape first, then look for the real JSON.
        $page = str_replace('\u0022', '"', $response->getContent());

        $this->assertStringContainsString(
            '"id":' . $treatment->id,
            $page,
            'The picker must publish the Treatment master id alongside the name.'
        );
        $this->assertStringContainsString('"name":"Zirconia Crown"', $page);
    }
}
