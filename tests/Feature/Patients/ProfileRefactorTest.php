<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\User;
use App\Services\PatientProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Patients Phase 4 · Slice 1 — Patient Profile refactor.
 *
 * Covers: thin orchestrator renders, lazy tab fragments serve every tab,
 * guards (unknown tab / merged patient), the permission fix on note &
 * opportunity writes, and the perf guarantee that opening the profile no
 * longer loads lazy-tab data.
 */
class ProfileRefactorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['branch_id' => 1, 'role' => 'admin', 'role_id' => null]);
    }

    private function patient(string $name = 'Profile Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    // ── Orchestrator ──────────────────────────────────────────────────────────

    public function test_profile_page_renders_with_journey_timeline_and_tabs(): void
    {
        $patient = $this->patient();

        $resp = $this->actingAs($this->admin)->get(route('patients.show', $patient));

        $resp->assertOk();
        $resp->assertSee('Journey Timeline');                 // Slice 2 card replaced the Visit Log
        $resp->assertSee('Patient Details &amp; Rapport', false);
        foreach (PatientProfileService::LAZY_TABS as $tab) {
            $resp->assertSee('dusk="tab-' . $tab . '"', false);     // tab nav intact
            $resp->assertSee('id="tab-panel-' . $tab . '"', false); // lazy container present
        }
    }

    public function test_profile_open_does_not_query_lazy_tab_tables(): void
    {
        $patient = $this->patient();

        $tables = [];
        DB::listen(function ($q) use (&$tables) {
            $tables[] = $q->sql;
        });

        $this->actingAs($this->admin)->get(route('patients.show', $patient))->assertOk();

        $sql = implode("\n", $tables);
        // Data owned by lazy tabs must NOT load on the eager page (Phase 4 perf contract).
        foreach (['prescriptions', 'clinical_files', 'implant_catalog', 'membership_benefit_logs', 'billing_prompts', 'lab_vendors'] as $lazyTable) {
            $this->assertStringNotContainsString($lazyTable, $sql, "Eager profile page unexpectedly queried [{$lazyTable}]");
        }
    }

    // ── Lazy fragments ────────────────────────────────────────────────────────

    public function test_every_lazy_tab_fragment_renders(): void
    {
        $patient = $this->patient();

        foreach (PatientProfileService::LAZY_TABS as $tab) {
            $this->actingAs($this->admin)
                ->get(route('patients.tab', [$patient, $tab]))
                ->assertOk();
        }
    }

    public function test_unknown_tab_is_404(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->admin)
            ->get(route('patients.tab', [$patient, 'nonsense']))
            ->assertNotFound();
    }

    public function test_fragment_for_merged_patient_is_404(): void
    {
        $master = $this->patient('Master');
        $merged = $this->patient('Merged Away');
        $merged->forceFill(['merged_into_id' => $master->id])->save();

        $this->actingAs($this->admin)
            ->get(route('patients.tab', [$merged, 'billing']))
            ->assertNotFound();
    }

    // ── Permission fix (Phase 4): note/opportunity writes need patients,edit ──

    public function test_note_and_opportunity_writes_denied_without_edit_permission(): void
    {
        // role_id-less non-admin has no module permissions at all; module:patients,edit
        // denies via redirect (302), not 403 — same convention as the Phase 3 tests.
        $readonly = User::factory()->create(['branch_id' => 1, 'role' => 'front_desk', 'role_id' => null]);
        $patient  = $this->patient();

        $this->actingAs($readonly)
            ->from(route('patients.index'))
            ->post(route('patients.relationship-notes.store', $patient), ['note' => 'should be blocked'])
            ->assertRedirect();

        $this->actingAs($readonly)
            ->from(route('patients.index'))
            ->post(route('patients.opportunities.store', $patient), ['type' => 'implant'])
            ->assertRedirect();

        $this->assertDatabaseCount('patient_relationship_notes', 0);
        $this->assertDatabaseCount('treatment_opportunities', 0);
    }

    public function test_note_write_works_for_admin(): void
    {
        $patient = $this->patient();

        $this->actingAs($this->admin)
            ->postJson(route('patients.relationship-notes.store', $patient), [
                'note' => 'Prefers morning appointments',
                'type' => 'internal',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('patient_relationship_notes', [
            'patient_id' => $patient->id,
            'note'       => 'Prefers morning appointments',
        ]);
    }
}
