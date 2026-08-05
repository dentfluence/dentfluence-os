<?php

namespace Tests\Feature\TreatmentVisits;

use App\Models\TreatmentVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Treatment Visits — authorization and web/API parity.
 *
 * Unlike TreatmentVisitCrudTest / TreatmentVisitClinicalWorkflowTest, this
 * file does NOT disable module-permission middleware — access control is
 * exactly what it exercises. Personas are built through the real
 * roles / role_module_permissions tables via BuildsAccessPersonas, matching
 * the existing ClinicalWriteGateTest convention.
 */
class TreatmentVisitAuthorizationAndParityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;
    use BuildsVisitFixtures;

    // ── Web authorization ────────────────────────────────────────────────────

    public function test_view_only_role_cannot_create_or_delete_a_visit(): void
    {
        $user    = $this->userWithModulePerm('patients', true, false, false);
        $patient = $this->makePatient();

        $this->actingAs($user)
            ->postJson(route('visits.store', $patient), $this->baseVisitPayload())
            ->assertForbidden();

        $visit = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $this->actingAs($user)
            ->deleteJson(route('visits.destroy', $visit))
            ->assertForbidden();
    }

    public function test_edit_role_can_create_a_visit_but_not_delete_it(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $this->actingAs($user)
            ->postJson(route('visits.store', $patient), $this->baseVisitPayload())
            ->assertOk();

        $visit = TreatmentVisit::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)
            ->deleteJson(route('visits.destroy', $visit))
            ->assertForbidden();
    }

    public function test_delete_role_can_delete_a_visit(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, true);
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $this->actingAs($user)
            ->deleteJson(route('visits.destroy', $visit))
            ->assertOk();
    }

    public function test_zero_permission_role_cannot_read_or_write_visits(): void
    {
        $user    = $this->zeroPermUser();
        $patient = $this->makePatient();

        $this->actingAs($user)
            ->postJson(route('visits.store', $patient), $this->baseVisitPayload())
            ->assertForbidden();
    }

    // ── API authorization ────────────────────────────────────────────────────

    public function test_api_create_requires_patients_edit_permission(): void
    {
        $viewOnly = $this->userWithModulePerm('patients', true, false, false);
        $patient  = $this->makePatient();

        Sanctum::actingAs($viewOnly, ['*']);

        $this->postJson("/api/v1/patients/{$patient->id}/visits", $this->baseVisitPayload())
            ->assertForbidden();
    }

    public function test_api_delete_requires_patients_delete_permission(): void
    {
        $editOnly = $this->userWithModulePerm('patients', true, true, false);
        $patient  = $this->makePatient();
        $visit    = $patient->treatmentVisits()->create($this->baseVisitPayload());

        Sanctum::actingAs($editOnly, ['*']);

        $this->deleteJson("/api/v1/visits/{$visit->id}")->assertForbidden();
    }

    /**
     * EXPECTED TO FAIL against current code.
     *
     * Per the 2026-08-05 audit (§J, §7 P0 finding "Missing API authorization
     * on PHI reads") and independently re-verified at routes/api.php:253-254:
     * `GET /patients/{patient}/visits/form-options` and `GET /visits/{visit}`
     * carry only the blanket `auth:sanctum` middleware — no
     * `api.role:module:patients,view` gate like every comparable PHI read in
     * the same file. This test encodes the CORRECT/expected behavior (a user
     * with no patients-module permission should be forbidden) so it fails
     * loudly instead of silently passing. Do not "fix" this by editing
     * production code — report it as failing.
     */
    public function test_api_visit_reads_are_expected_to_require_patients_view_permission(): void
    {
        $noPatientsAccess = $this->zeroPermUser();
        $patient          = $this->makePatient();
        $visit            = $patient->treatmentVisits()->create($this->baseVisitPayload());

        Sanctum::actingAs($noPatientsAccess, ['*']);

        $this->getJson("/api/v1/patients/{$patient->id}/visits/form-options")
            ->assertForbidden();

        $this->getJson("/api/v1/visits/{$visit->id}")
            ->assertForbidden();
    }

    // ── Web / API parity ─────────────────────────────────────────────────────

    /**
     * Both controllers delegate to the same TreatmentVisitService, so a visit
     * created through either surface should produce identical persisted
     * side-effects: the visit row, its items, and the resulting billing
     * prompt.
     */
    public function test_web_and_api_create_produce_identical_side_effects(): void
    {
        $webUser = $this->userWithModulePerm('patients', true, true, true);
        $apiUser = $this->userWithModulePerm('patients', true, true, true);

        $webPatient = $this->makePatient();
        $apiPatient = $this->makePatient();

        $payload = $this->baseVisitPayload([
            'treatment_name' => 'Scaling',
            'visit_items'    => [[
                'treatment_name'  => 'Scaling',
                'suggested_price' => 800,
            ]],
        ]);

        $this->actingAs($webUser)
            ->postJson(route('visits.store', $webPatient), $payload)
            ->assertOk();

        Sanctum::actingAs($apiUser, ['*']);
        // API store() returns 201 Created (explicit status arg), unlike the
        // web controller which returns the default 200.
        $this->postJson("/api/v1/patients/{$apiPatient->id}/visits", $payload)
            ->assertCreated();

        $webVisit = TreatmentVisit::where('patient_id', $webPatient->id)->firstOrFail();
        $apiVisit = TreatmentVisit::where('patient_id', $apiPatient->id)->firstOrFail();

        // Same clinical fields persisted.
        $this->assertSame($webVisit->treatment_name, $apiVisit->treatment_name);
        $this->assertSame($webVisit->status, $apiVisit->status);

        // Same fan-out: one visit item, one pending billing prompt, each.
        $this->assertSame(
            $webVisit->visitItems()->count(),
            $apiVisit->visitItems()->count()
        );
        $this->assertSame(
            $webVisit->billingPrompts()->where('status', 'pending')->count(),
            $apiVisit->billingPrompts()->where('status', 'pending')->count()
        );
    }

    public function test_api_store_defaults_doctor_id_to_the_authenticated_user_when_omitted(): void
    {
        $apiUser = $this->userWithModulePerm('patients', true, true, true);
        $patient = $this->makePatient();

        Sanctum::actingAs($apiUser, ['*']);

        // doctor_id intentionally omitted from the payload.
        $this->postJson("/api/v1/patients/{$patient->id}/visits", $this->baseVisitPayload())
            ->assertCreated();

        $this->assertDatabaseHas('treatment_visits', [
            'patient_id' => $patient->id,
            'doctor_id'  => $apiUser->id,
        ]);
    }

    /**
     * The web controller does NOT default doctor_id — this documents the
     * asymmetry noted in the audit (§F) rather than asserting parity that
     * doesn't exist here by design.
     */
    public function test_web_store_leaves_doctor_id_null_when_omitted(): void
    {
        $webUser = $this->userWithModulePerm('patients', true, true, true);
        $patient = $this->makePatient();

        $this->actingAs($webUser)
            ->postJson(route('visits.store', $patient), $this->baseVisitPayload())
            ->assertOk();

        $this->assertDatabaseHas('treatment_visits', [
            'patient_id' => $patient->id,
            'doctor_id'  => null,
        ]);
    }
}
