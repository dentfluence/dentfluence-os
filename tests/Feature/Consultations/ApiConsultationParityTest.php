<?php

namespace Tests\Feature\Consultations;

use App\Models\BillingPrompt;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Consultations · Slice 10 — API ↔ web business-rule parity.
 *
 * The invariant: one rule set governs both surfaces. Pins the three parity
 * fixes of 2026-08-03:
 *  1. Future dates rejected on every API consultation write (was: none).
 *  2. The retired consultations.prescriptions/.instructions JSON columns can
 *     no longer be written via the API (mobile Rx silently vanished into
 *     columns with zero read paths).
 *  3. Minor Visit charges fire the same BillingPrompt as the web.
 */
class ApiConsultationParityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function apiClinician(): User
    {
        $user = $this->userWithModulePerm('patients', true, true, false, 'API Clinician ' . uniqid());
        Sanctum::actingAs($this->fresh($user), ['*']);
        return $user;
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'API Parity Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    public function test_api_rejects_future_dates_on_every_consultation_write(): void
    {
        $this->apiClinician();
        $patient  = $this->patient();
        $tomorrow = now()->addDay()->format('Y-m-d');

        $writes = [
            ["/api/v1/patients/{$patient->id}/consultations",
                ['chief_complaint' => 'x', 'consultation_date' => $tomorrow]],
            ["/api/v1/patients/{$patient->id}/consultations/same-issue",
                ['update_notes' => 'x', 'consultation_date' => $tomorrow]],
            ["/api/v1/patients/{$patient->id}/consultations/minor-visit",
                ['procedure_performed' => 'x', 'consultation_date' => $tomorrow]],
            ["/api/v1/patients/{$patient->id}/consultations/emergency",
                ['chief_complaint' => 'x', 'emergency_treatment_rendered' => 'x', 'consultation_date' => $tomorrow]],
        ];

        foreach ($writes as [$url, $payload]) {
            $this->postJson($url, $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors('consultation_date');
        }

        $this->assertSame(0, Consultation::count());
    }

    public function test_api_backdated_store_still_works(): void
    {
        $user    = $this->apiClinician();
        $patient = $this->patient();

        $this->postJson("/api/v1/patients/{$patient->id}/consultations", [
            'chief_complaint'   => 'Backdated mobile entry',
            'consultation_date' => now()->subDays(3)->format('Y-m-d'),
        ])->assertCreated();

        $consultation = Consultation::latest('id')->first();
        $this->assertSame(now()->subDays(3)->toDateString(), $consultation->consultation_date->toDateString());
        // No doctor sent → attributed to the API user, same as web fallback.
        $this->assertSame($user->id, $consultation->doctor_id);
    }

    public function test_api_no_longer_writes_the_retired_prescription_json_columns(): void
    {
        $this->apiClinician();
        $patient = $this->patient();

        $this->postJson("/api/v1/patients/{$patient->id}/consultations/emergency", [
            'chief_complaint'              => 'Swelling',
            'emergency_treatment_rendered' => 'I&D done',
            'prescriptions'                => [['drug' => 'Amoxicillin', 'dose' => '500mg']],
            'instructions'                 => ['Soft diet'],
        ])->assertCreated();

        $consultation = Consultation::latest('id')->first();
        // The dead columns must stay empty — this data used to vanish here.
        $this->assertEmpty($consultation->prescriptions);
        $this->assertEmpty($consultation->instructions);
    }

    public function test_api_minor_visit_charges_fire_a_billing_prompt(): void
    {
        $user    = $this->apiClinician();
        $patient = $this->patient();

        $this->postJson("/api/v1/patients/{$patient->id}/consultations/minor-visit", [
            'procedure_performed' => 'Suture removal',
            'charges'             => 300,
        ])->assertCreated();

        $consultation = Consultation::latest('id')->first();
        $prompt = BillingPrompt::where('trigger_type', 'consultation')
            ->where('trigger_id', $consultation->id)->first();

        $this->assertNotNull($prompt, 'API Minor Visit charges did not fire a BillingPrompt.');
        $this->assertSame('pending', $prompt->status);
        $this->assertSame($user->id, $prompt->created_by);
    }
}
