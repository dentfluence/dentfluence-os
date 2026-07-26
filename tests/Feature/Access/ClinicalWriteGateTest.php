<?php

namespace Tests\Feature\Access;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.2 — VIEW DOES NOT AUTHORIZE WRITE.
 *
 * The audited hole: both `module:patients` web groups gated clinical creates,
 * edits and deletes on the VIEW flag, so a view-only role could write and
 * delete clinical records. Each action is now checked independently.
 */
class ClinicalWriteGateTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Clinical Gate Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    public function test_view_only_role_cannot_write_clinical_records(): void
    {
        $user    = $this->userWithModulePerm('patients', true, false, false, 'Quiet Observer ' . uniqid());
        $patient = $this->patient();

        // Reads still work…
        $this->actingAs($user)->get(route('patients.show', $patient))->assertOk();

        // …writes do not.
        $this->actingAs($user)
            ->postJson(route('patients.notes.store', $patient), ['note' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('consultations.store'), ['patient_id' => $patient->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('treatment-plans.store', $patient), [])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('visits.store', $patient), [])
            ->assertForbidden();
    }

    public function test_edit_role_can_write_but_not_delete(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false, 'Working Clinician ' . uniqid());
        $patient = $this->patient();

        $this->assertNotSame(403, $this->actingAs($user)
            ->postJson(route('patients.notes.store', $patient), ['note' => 'allowed'])
            ->getStatusCode());

        // Delete requires the delete flag, which this role does not hold.
        $this->actingAs($user)
            ->deleteJson(route('patients.destroy', $patient))
            ->assertForbidden();
    }

    public function test_delete_grant_authorizes_the_destructive_action(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, true);
        $patient = $this->patient();

        $this->assertNotSame(403, $this->actingAs($user)
            ->deleteJson(route('patients.destroy', $patient))
            ->getStatusCode());
    }
}
