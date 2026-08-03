<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 5 — permission gates on EVERY consultation write path.
 *
 * Extends the Phase 1 / Slice 1.2 principle (VIEW DOES NOT AUTHORIZE WRITE)
 * to the typed workflows added since: Same Issue, Minor Visit, Emergency and
 * COHA all carry module:patients,edit on their write routes.
 */
class ConsultationAccessTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Gate Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function consultation(Patient $patient, string $type = 'new'): Consultation
    {
        return Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => \App\Models\User::factory()->create(['branch_id' => 1])->id,
            'branch_id'         => 1,
            'consultation_type' => $type,
            'visit_type'        => $type === 'emergency' ? 'emergency' : 'routine',
            'status'            => 'completed',
            'consultation_date' => now()->subDay(),
            'chief_complaint'   => 'Gate fixture',
        ]);
    }

    public function test_view_only_role_cannot_use_any_consultation_write_path(): void
    {
        $viewer  = $this->userWithModulePerm('patients', true, false, false, 'View Only ' . uniqid());
        $patient = $this->patient();
        $consultation = $this->consultation($patient);
        $minorVisit   = $this->consultation($patient, 'minor_visit');
        $sameIssue    = $this->consultation($patient, 'same_issue');
        $emergency    = $this->consultation($patient, 'emergency');
        $coha         = $this->consultation($patient, 'coha');

        $writes = [
            ['postJson', route('patients.consultations.store', $patient)],
            ['putJson',  route('patients.consultations.update', [$patient, $consultation])],
            ['putJson',  route('consultations.update', $consultation)],
            ['postJson', route('patients.consultations.same-issue.store', $patient)],
            ['putJson',  route('patients.consultations.same-issue.update', [$patient, $sameIssue])],
            ['postJson', route('patients.consultations.minor-visit.store', $patient)],
            ['putJson',  route('patients.consultations.minor-visit.update', [$patient, $minorVisit])],
            ['postJson', route('patients.consultations.emergency.store', $patient)],
            ['putJson',  route('patients.consultations.emergency.update', [$patient, $emergency])],
            ['postJson', route('coha.store', $patient)],
            ['putJson',  route('coha.update', [$patient, $coha])],
        ];

        foreach ($writes as [$verb, $url]) {
            $this->actingAs($this->fresh($viewer))->{$verb}($url, [])
                ->assertForbidden();
        }

        // Deletes need the delete flag, which view-only certainly lacks.
        $this->actingAs($this->fresh($viewer))
            ->deleteJson(route('consultations.destroy', $consultation))
            ->assertForbidden();
    }

    public function test_edit_role_passes_the_gate_on_every_typed_write_path(): void
    {
        $editor  = $this->userWithModulePerm('patients', true, true, false, 'Edit Role ' . uniqid());
        $patient = $this->patient();
        $coha    = $this->consultation($patient, 'coha');

        // Gate check only: empty payloads mean validation may 422 — the
        // assertion is that the module gate no longer 403s.
        $writes = [
            ['postJson', route('patients.consultations.store', $patient)],
            ['postJson', route('patients.consultations.same-issue.store', $patient)],
            ['postJson', route('patients.consultations.minor-visit.store', $patient)],
            ['postJson', route('patients.consultations.emergency.store', $patient)],
            ['postJson', route('coha.store', $patient)],
            ['putJson',  route('coha.update', [$patient, $coha])],
        ];

        foreach ($writes as [$verb, $url]) {
            $this->assertNotSame(403,
                $this->actingAs($this->fresh($editor))->{$verb}($url, [])->getStatusCode(),
                "Edit-capable role was blocked on {$url}");
        }
    }

    public function test_edit_role_cannot_delete_consultations(): void
    {
        $editor  = $this->userWithModulePerm('patients', true, true, false, 'No Delete ' . uniqid());
        $patient = $this->patient();
        $consultation = $this->consultation($patient);

        $this->actingAs($editor)
            ->deleteJson(route('consultations.destroy', $consultation))
            ->assertForbidden();
    }
}
