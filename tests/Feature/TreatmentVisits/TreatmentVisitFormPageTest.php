<?php

namespace Tests\Feature\TreatmentVisits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Treatment Visits — dedicated form page (08-05, presentation-only refactor).
 *
 * Covers only the two new GET actions (create/edit) and the page-shaped
 * navigation contract. store()/update()/destroy() behaviour is unchanged and
 * already covered by TreatmentVisitCrudTest / TreatmentVisitClinicalWorkflowTest;
 * this file does not repeat that coverage.
 */
class TreatmentVisitFormPageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;
    use BuildsVisitFixtures;

    public function test_create_page_renders_with_the_expected_form_controls(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertViewIs('patients.treatment-visit-form');
        $response->assertViewHas('patient', fn ($p) => $p->id === $patient->id);
        $response->assertSee('dusk="visit-notes"', false);
        $response->assertSee('dusk="visit-save"', false);
        $response->assertSee($patient->name);
    }

    public function test_edit_page_renders_for_an_existing_visit(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload(['notes' => 'Existing note']));

        $response = $this->actingAs($user)->get(route('visits.edit', $visit));

        $response->assertOk();
        $response->assertViewIs('patients.treatment-visit-form');
        $response->assertViewHas('visit', fn ($v) => $v->id === $visit->id);
        $response->assertSee('Edit Visit');
    }

    public function test_create_page_prefills_appointment_and_plan_from_query_params(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();
        [$plan]  = $this->makeAcceptedPlanWithItem($patient);

        $response = $this->actingAs($user)->get(
            route('visits.create', $patient) . '?appointment_id=123&plan_id=' . $plan->id
        );

        $response->assertOk();
        $response->assertViewHas('prefillPlanId', (string) $plan->id);
    }

    public function test_view_only_role_cannot_open_the_create_or_edit_page(): void
    {
        $user    = $this->userWithModulePerm('patients', true, false, false);
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        // CheckModulePermission redirects (302) + flashes 'access_denied' for
        // non-JSON web requests rather than aborting 403 (RespondsWithAccessDenied
        // — see the same convention asserted in InventoryPermissionGatingTest).
        // These are plain get() calls, so that's the behaviour to expect here.
        $this->actingAs($user)->get(route('visits.create', $patient))
            ->assertSessionHas('access_denied');
        $this->actingAs($user)->get(route('visits.edit', $visit))
            ->assertSessionHas('access_denied');
    }

    public function test_edit_role_can_open_both_pages(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $this->actingAs($user)->get(route('visits.create', $patient))->assertOk();
        $this->actingAs($user)->get(route('visits.edit', $visit))->assertOk();
    }

    public function test_timeline_tab_links_to_the_dedicated_pages_not_a_modal(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->get(route('patients.tab', [$patient, 'visits']));

        $response->assertOk();
        $response->assertSee(route('visits.create', $patient), false);
        $response->assertDontSee('x-show="formOpen"', false);
    }
}
