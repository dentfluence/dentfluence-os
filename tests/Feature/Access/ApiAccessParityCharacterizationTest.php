<?php

namespace Tests\Feature\Access;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.1 — CURRENT web↔API authorization parity reality.
 *
 * Three findings, each test-locked:
 *  1. The PRE API group (/api/v1/relationships/*) is auth:sanctum only —
 *     any token holder reads and mutates regardless of owner Settings.
 *  2. Where the module-form gate (api.role:module:x,action) IS applied,
 *     it correctly obeys owner Settings — the parity primitive works.
 *  3. Role-NAME-list gates (api.role:admin,front_desk — 55+ routes) ignore
 *     owner Settings entirely: a front_desk-string user with a ZERO-grant
 *     role passes; the same zero-grant role under another legacy string is
 *     denied. Authorization by job title, not by configuration.
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

    public function test_pre_api_reads_are_ungated_beyond_login(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);

        $this->getJson('/api/v1/relationships/today')
            ->assertOk(); // CURRENT: zero grants, full board over API
    }

    public function test_pre_api_mutations_are_ungated_beyond_login(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);

        $this->postJson('/api/v1/relationships/today/close', [
            'category' => 'recall_calls',
            'notes'    => 'closed via API by zero-perm user (characterization)',
        ])->assertOk(); // CURRENT: mutation allowed with zero grants
    }

    public function test_module_form_api_gate_obeys_owner_settings_where_applied(): void
    {
        $patient = $this->patient();

        // Zero grants → the module:patients,view-gated notes write is denied.
        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->postJson("/api/v1/patients/{$patient->id}/notes", ['note' => 'x'])
            ->assertForbidden();

        // Owner grants patients view → the same endpoint opens. Same code path.
        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false), ['*']);
        $this->postJson("/api/v1/patients/{$patient->id}/notes", ['note' => 'characterization note'])
            ->assertSuccessful();
    }

    public function test_role_name_list_gates_ignore_owner_settings(): void
    {
        $patient = $this->patient();
        $url     = "/api/v1/patients/{$patient->id}/membership-benefit-preview"; // api.role:admin,front_desk

        // Same ZERO-grant role configuration, two legacy strings:
        // 'assistant' → denied by the name list.
        Sanctum::actingAs($this->zeroPermUser('assistant'), ['*']);
        $this->getJson($url)->assertForbidden();

        // 'front_desk' → passes the name list despite zero owner grants.
        Sanctum::actingAs($this->zeroPermUser('front_desk'), ['*']);
        $this->assertNotSame(403, $this->getJson($url)->getStatusCode(),
            'front_desk legacy string passed a role-name-list gate with zero owner-configured grants (current reality)');
    }
}
