<?php

namespace Tests\Feature\Relationship;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Prescription\Prescription;
use App\Models\User;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — closing the Prescription "Send WhatsApp" consent bypass.
 *
 * Before this, sendWhatsApp() built a wa.me link with zero consent check.
 * It now runs CommunicationGuard::hasWhatsAppConsent() first. Gated behind
 * guard.consent_required (default off) so today's behaviour is unchanged
 * unless the flag is deliberately flipped — the block is always LOGGED,
 * only ENFORCED when the flag is on.
 */
class PrescriptionConsentGateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Consent Test Patient',
            'phone'     => '900' . random_int(1000, 9999),
            'branch_id' => 1,
        ]);
    }

    private function finalizedPrescription(Patient $patient, User $doctor): Prescription
    {
        return Prescription::create([
            'prescription_number' => 'RX-TEST-' . random_int(10000, 99999),
            'patient_id'           => $patient->id,
            'prescribed_by'        => $doctor->id,
            'status'               => Prescription::STATUS_ISSUED,
        ]);
    }

    private function grantWhatsAppConsent(Patient $patient): void
    {
        $purpose = ConsentPurpose::where('key', 'whatsapp_comms')->first()
            ?? ConsentPurpose::create([
                'key' => 'whatsapp_comms', 'name' => 'WhatsApp messages', 'category' => 'communication',
                'is_mandatory' => false, 'requires_explicit' => true, 'version' => 1, 'active' => true,
            ]);

        PatientConsent::create([
            'patient_id'         => $patient->id,
            'consent_purpose_id' => $purpose->id,
            'status'             => PatientConsent::GRANTED,
            'granted_at'         => now(),
        ]);
    }

    public function test_send_still_works_when_flag_off_even_without_consent(): void
    {
        $doctor       = $this->admin();
        $patient      = $this->patient();
        $prescription = $this->finalizedPrescription($patient, $doctor);

        // No consent purpose/record exists at all — but the flag is off, so
        // today's behaviour (send goes through) must be unchanged.
        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_send_blocked_when_flag_on_and_no_consent(): void
    {
        Feature::set('guard.consent_required', true);

        $doctor       = $this->admin();
        $patient      = $this->patient();
        $prescription = $this->finalizedPrescription($patient, $doctor);
        // Seed the purpose but grant no consent for this patient.
        ConsentPurpose::create([
            'key' => 'whatsapp_comms', 'name' => 'WhatsApp messages', 'category' => 'communication',
            'is_mandatory' => false, 'requires_explicit' => true, 'version' => 1, 'active' => true,
        ]);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_send_allowed_when_flag_on_and_consent_granted(): void
    {
        Feature::set('guard.consent_required', true);

        $doctor       = $this->admin();
        $patient      = $this->patient();
        $prescription = $this->finalizedPrescription($patient, $doctor);
        $this->grantWhatsAppConsent($patient);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }
}
