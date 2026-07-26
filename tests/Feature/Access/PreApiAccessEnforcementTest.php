<?php

namespace Tests\Feature\Access;

use App\Models\Lead;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Slice M1 (2026-07-26) — API authorization mirror, PRE domain.
 *
 * Covers the four PRE API groups converted in this slice: /leads/*,
 * /relationship/missed-calls/*, /relationship/recall-settings/*, /templates/*.
 * (The /relationships/* group itself is covered by
 * ApiAccessParityCharacterizationTest + SettingsControlAcceptanceTest.)
 *
 * Semantics are copied route-for-route from web routes/relationship.php
 * (Slice 1.3): group = relationship,view · operational mutations = ',edit' ·
 * clinic-wide config + bulk-dismiss = ',delete' · template destroy = ',edit'
 * (deliberate web semantic, mirrored — NOT delete) · lead conversion =
 * relationship,edit AND patients,edit via two stacked api.role gates.
 *
 * Denial assertions send no meaningful payload on purpose: middleware runs
 * before validation, so 403 isolates authorization. Grant assertions use
 * "not 403" (a 404/422 past the gate still proves the gate opened).
 */
class PreApiAccessEnforcementTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    /**
     * A real missed-call row. `ignore`/`unignore` use route-MODEL binding, and
     * Laravel resolves bindings (SubstituteBindings) BEFORE route middleware —
     * so a nonexistent id returns 404 before the gate is consulted, which would
     * test nothing. Using a real row makes the authorization gate the only
     * thing under test. (No data is exposed either way.)
     */
    private function missedCall(): \App\Models\CommunicationQueue
    {
        return \App\Models\CommunicationQueue::create([
            'person_name' => 'M1 Missed Call',
            'phone'       => '9' . random_int(100000000, 999999999),
            'channel'     => 'call',
            'comm_type'   => 'existing_patient',
            'direction'   => 'inbound',
            'purpose'     => 'missed_call',
            'status'      => 'pending',
            'priority'    => 'medium',
        ]);
    }

    private function lead(): Lead
    {
        return Lead::create([
            'name'  => 'M1 Convert Lead',
            'phone' => '9' . random_int(100000000, 999999999),
            'stage' => 'new_lead',
        ]);
    }

    /* ── zero-grant: every M1 group is closed, reads and writes alike ────── */

    public function test_zero_grant_token_is_denied_every_m1_group(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);

        // Reads (group view gate)
        $this->getJson('/api/v1/templates')->assertForbidden();
        $this->getJson('/api/v1/relationship/recall-settings')->assertForbidden();
        $this->getJson('/api/v1/relationship/missed-calls')->assertForbidden();
        $this->getJson('/api/v1/leads/1/detail')->assertForbidden();

        // Mutations
        $this->postJson('/api/v1/leads/quick-add', [])->assertForbidden();
        $this->postJson('/api/v1/leads/1/move', [])->assertForbidden();
        $this->postJson('/api/v1/leads/1/convert', [])->assertForbidden();
        $this->postJson('/api/v1/templates', [])->assertForbidden();
        $this->putJson('/api/v1/templates/1', [])->assertForbidden();
        $this->deleteJson('/api/v1/templates/1')->assertForbidden();
        $this->postJson('/api/v1/relationship/recall-settings/general', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/recall-settings/birthday', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/missed-calls/bulk-dismiss', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/missed-calls/' . $this->missedCall()->id . '/ignore', [])->assertForbidden();
    }

    /* ── view-only: reads open, no mutation right is implied ─────────────── */

    public function test_view_grant_opens_reads_but_no_mutations(): void
    {
        Sanctum::actingAs(
            $this->userWithModulePerm('relationship', true, false, false,
                'Orange Lantern ' . uniqid()), // arbitrary name — must not matter
            ['*']
        );

        $this->assertNotSame(403, $this->getJson('/api/v1/templates')->getStatusCode());
        $this->assertNotSame(403, $this->getJson('/api/v1/relationship/recall-settings')->getStatusCode());
        $this->assertNotSame(403, $this->getJson('/api/v1/relationship/missed-calls')->getStatusCode());

        $this->postJson('/api/v1/leads/quick-add', [])->assertForbidden();
        $this->postJson('/api/v1/templates', [])->assertForbidden();
        $this->deleteJson('/api/v1/templates/1')->assertForbidden();
        $this->postJson('/api/v1/relationship/missed-calls/' . $this->missedCall()->id . '/ignore', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/recall-settings/general', [])->assertForbidden();
    }

    /* ── edit: operational mutations open; delete-tier stays closed ──────── */

    public function test_edit_grant_allows_operational_mutations_but_not_delete_tier(): void
    {
        Sanctum::actingAs(
            $this->userWithModulePerm('relationship', true, true, false), ['*']
        );

        // Operational mutations pass the gate (404/422 beyond it is fine).
        $this->assertNotSame(403, $this->postJson('/api/v1/leads/quick-add', [])->getStatusCode());
        $this->assertNotSame(403, $this->postJson('/api/v1/leads/999999/move', [])->getStatusCode());
        $this->assertNotSame(403, $this->postJson('/api/v1/templates', [])->getStatusCode());
        $this->assertNotSame(403, $this->postJson('/api/v1/relationship/missed-calls/999999/ignore', [])->getStatusCode());

        // Template DESTROY rides the EDIT grant — deliberate web semantic
        // (routes/relationship.php:395-396), mirrored exactly.
        $this->assertNotSame(403, $this->deleteJson('/api/v1/templates/999999')->getStatusCode());

        // Delete-tier stays closed to edit-only.
        $this->postJson('/api/v1/relationship/recall-settings/general', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/recall-settings/treatment/1', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/recall-settings/birthday', [])->assertForbidden();
        $this->postJson('/api/v1/relationship/missed-calls/bulk-dismiss', [])->assertForbidden();
    }

    public function test_delete_grant_opens_the_delete_tier(): void
    {
        Sanctum::actingAs(
            $this->userWithModulePerm('relationship', true, true, true), ['*']
        );

        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationship/recall-settings/general', [])->getStatusCode());
        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationship/missed-calls/bulk-dismiss', ['ids' => []])->getStatusCode());
    }

    /* ── lead conversion: the CEO-approved AND semantic ──────────────────── */

    public function test_lead_conversion_requires_both_relationship_edit_and_patients_edit(): void
    {
        $before = Patient::count();

        // relationship,edit alone → denied by the stacked patients,edit gate.
        Sanctum::actingAs($this->userWithModulePerm('relationship', true, true, false), ['*']);
        $this->postJson('/api/v1/leads/' . $this->lead()->id . '/convert')->assertForbidden();
        $this->assertSame($before, Patient::count(), 'a patient was minted without patients,edit');

        // patients,edit alone → denied by the relationship gates.
        Sanctum::actingAs($this->userWithModulePerm('patients', true, true, false), ['*']);
        $this->postJson('/api/v1/leads/' . $this->lead()->id . '/convert')->assertForbidden();
        $this->assertSame($before, Patient::count(), 'a patient was minted without relationship,edit');

        // Both grants on one arbitrarily-named role → conversion succeeds and
        // mints exactly through PatientService::register (the invariant path).
        Sanctum::actingAs($this->userWithTwoModulePerms(
            'relationship', [true, true, false],
            'patients',     [true, true, false],
            'Green Coordinator ' . uniqid()
        ), ['*']);

        $this->postJson('/api/v1/leads/' . $this->lead()->id . '/convert')->assertOk();
        $this->assertGreaterThan($before, Patient::count());
    }

    /* ── admin behavior unchanged ────────────────────────────────────────── */

    public function test_admin_passes_every_m1_gate_unconditionally(): void
    {
        Sanctum::actingAs($this->legacyAdminUser(), ['*']);

        $this->assertNotSame(403, $this->getJson('/api/v1/templates')->getStatusCode());
        $this->assertNotSame(403, $this->getJson('/api/v1/relationship/missed-calls')->getStatusCode());
        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationship/recall-settings/general', [])->getStatusCode());
        $this->assertNotSame(403,
            $this->postJson('/api/v1/relationship/missed-calls/bulk-dismiss', ['ids' => []])->getStatusCode());
    }
}
