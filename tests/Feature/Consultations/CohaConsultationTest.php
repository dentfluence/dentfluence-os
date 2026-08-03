<?php

namespace Tests\Feature\Consultations;

use App\Models\Consultation;
use App\Models\ConsultationCohaReport;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 5 — behavioural coverage for the COHA workflow.
 *
 * Regression anchor — Slice 3 (2026-08-03): cohaStore()/cohaUpdate() had ZERO
 * validation (raw $request->input() straight into Consultation::create). These
 * tests pin the new cohaRules() contract: doctor required, future dates
 * rejected, section payloads accepted as arrays OR JSON strings, junk rejected.
 */
class CohaConsultationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function clinician(): User
    {
        return $this->userWithModulePerm('patients', true, true, false, 'COHA Clinician ' . uniqid());
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'COHA Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function payload(User $doctor, array $overrides = []): array
    {
        return array_merge([
            'doctor_id'         => $doctor->id,
            'consultation_date' => now()->format('Y-m-d'),
            'doctor_notes'      => 'Overall oral health fair.',
            'extraoral'         => ['tmj' => 'normal'],
            'soft_tissue'       => ['tongue' => 'normal'],
            'tooth_assessment'  => ['36' => 'deep caries'],
            'monitoring_teeth'  => ['36', '47'],
        ], $overrides);
    }

    public function test_coha_store_creates_consultation_and_linked_report(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('coha.store', $patient), $this->payload($user))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame('coha', $consultation->consultation_type);
        $this->assertSame($user->id, $consultation->doctor_id);

        $report = ConsultationCohaReport::where('consultation_id', $consultation->id)->first();
        $this->assertNotNull($report);
        // The back-link written after report creation.
        $this->assertSame($report->id, $consultation->fresh()->coha_report_id);
        $this->assertSame(['tmj' => 'normal'], $report->extraoral);
        $this->assertSame(['36' => 'deep caries'], $report->tooth_assessment);
        $this->assertSame(['36', '47'], $report->monitoring_teeth);
    }

    public function test_coha_store_accepts_sections_posted_as_json_strings(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('coha.store', $patient), $this->payload($user, [
                'extraoral'   => json_encode(['lymph_nodes' => 'not palpable']),
                'soft_tissue' => json_encode(['palate' => 'normal']),
            ]))
            ->assertSessionHasNoErrors();

        $report = ConsultationCohaReport::latest('id')->first();
        $this->assertSame(['lymph_nodes' => 'not palpable'], $report->extraoral);
    }

    public function test_coha_store_requires_doctor(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $payload = $this->payload($user);
        unset($payload['doctor_id']);

        // Slice 3 regression: pre-fix this passed silently with a fallback.
        $this->actingAs($user)
            ->post(route('coha.store', $patient), $payload)
            ->assertSessionHasErrors('doctor_id');

        $this->assertSame(0, Consultation::count());
    }

    public function test_coha_store_rejects_future_date_and_junk_section(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('coha.store', $patient), $this->payload($user, [
                'consultation_date' => now()->addDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('consultation_date');

        $this->actingAs($user)
            ->post(route('coha.store', $patient), $this->payload($user, [
                'extraoral' => 'this is not valid json {{{',
            ]))
            ->assertSessionHasErrors('extraoral');

        $this->assertSame(0, Consultation::count());
    }

    public function test_coha_update_revises_report_sections(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('coha.store', $patient), $this->payload($user))
            ->assertSessionHasNoErrors();

        $consultation = Consultation::latest('id')->first();

        $this->actingAs($user)
            ->put(route('coha.update', [$patient, $consultation]), $this->payload($user, [
                'tooth_assessment' => ['36' => 'restored — monitor'],
                'doctor_notes'     => 'Reviewed after restoration.',
            ]))
            ->assertSessionHasNoErrors();

        $report = ConsultationCohaReport::where('consultation_id', $consultation->id)->first();
        $this->assertSame(['36' => 'restored — monitor'], $report->tooth_assessment);
        $this->assertSame('Reviewed after restoration.', $report->doctor_notes);
    }

    public function test_coha_update_rejects_non_coha_consultation(): void
    {
        $user    = $this->clinician();
        $patient = $this->patient();

        $standard = Consultation::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $user->id,
            'branch_id'         => 1,
            'consultation_type' => 'new',
            'visit_type'        => 'routine',
            'status'            => 'completed',
            'consultation_date' => now()->subDay(),
            'chief_complaint'   => 'Not a COHA',
        ]);

        $this->actingAs($user)
            ->put(route('coha.update', [$patient, $standard]), $this->payload($user))
            ->assertNotFound();
    }
}
