<?php

namespace Tests\Feature\Access;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.4 — web↔API authorization PARITY (flipped from the 1.1
 * characterization, which recorded the PRE API and several money/PHI routes
 * as auth-only).
 *
 * The invariant: one owner-configured truth (role_module_permissions) governs
 * both surfaces. There is no separate mobile permission system.
 */
class ApiAccessParityCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Parity Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    public function test_pre_api_reads_require_the_relationship_view_grant(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->getJson('/api/v1/relationships/today')->assertForbidden();

        Sanctum::actingAs($this->userWithModulePerm('relationship', true, false, false), ['*']);
        $this->getJson('/api/v1/relationships/today')->assertOk();
    }

    public function test_pre_api_mutations_require_the_relationship_edit_grant(): void
    {
        $payload = ['category' => 'recall_calls', 'notes' => 'api parity attempt'];

        // View-only → denied on the API exactly as on the web.
        Sanctum::actingAs($this->userWithModulePerm('relationship', true, false, false), ['*']);
        $this->postJson('/api/v1/relationships/today/close', $payload)->assertForbidden();

        // Edit → allowed.
        Sanctum::actingAs($this->userWithModulePerm('relationship', true, true, false), ['*']);
        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationships/today/close', $payload)->getStatusCode());
    }

    public function test_phi_reads_require_the_patients_view_grant(): void
    {
        $patient = $this->patient();

        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->getJson("/api/v1/patients/{$patient->id}/consultations")->assertForbidden();

        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false), ['*']);
        $this->getJson("/api/v1/patients/{$patient->id}/consultations")->assertOk();
    }

    public function test_money_reads_and_mutations_require_finance_grants(): void
    {
        $patient = $this->patient();

        // Previously ungated entirely (1.1 finding).
        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->getJson("/api/v1/patients/{$patient->id}/membership-benefit-preview")->assertForbidden();
        $this->getJson('/api/v1/billing/invoices')->assertForbidden();

        // Finance view opens the reads but NOT the enrollment money-chain write.
        Sanctum::actingAs($this->userWithModulePerm('finance', true, false, false), ['*']);
        $this->getJson('/api/v1/billing/invoices')->assertOk();
        $this->postJson("/api/v1/patients/{$patient->id}/membership/enroll", [])->assertForbidden();
    }

    public function test_clinical_writes_follow_patients_edit_not_a_dentist_role_name(): void
    {
        $patient = $this->patient();

        // An arbitrarily-named role with patients edit may write clinically…
        Sanctum::actingAs($this->userWithModulePerm('patients', true, true, false, 'Night Clinician ' . uniqid()), ['*']);
        $this->assertNotSame(403,
            $this->postJson("/api/v1/patients/{$patient->id}/consultations", [])->getStatusCode());

        // …while a view-only role may not, whatever it is called.
        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false, 'Night Clinician ' . uniqid()), ['*']);
        $this->postJson("/api/v1/patients/{$patient->id}/consultations", [])->assertForbidden();
    }

    public function test_no_api_route_is_authorized_by_a_role_name_list_except_the_admin_selftest(): void
    {
        $offenders = [];

        foreach (\Illuminate\Support\Facades\Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (! is_string($mw) || ! str_starts_with($mw, 'api.role:')) {
                    continue;
                }
                if (str_contains($mw, 'module:')) {
                    continue; // owner-configured permission — correct
                }
                if ($route->uri() === 'api/v1/auth/admin-check') {
                    continue; // documented system self-test exception
                }
                $offenders[] = $route->uri() . ' [' . $mw . ']';
            }
        }

        $this->assertSame([], $offenders,
            "API routes still authorized by job title instead of owner Settings:\n" . implode("\n", $offenders));
    }
}
