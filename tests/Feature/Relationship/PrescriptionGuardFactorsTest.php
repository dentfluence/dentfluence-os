<?php

namespace Tests\Feature\Relationship;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Prescription\Prescription;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — Prescription WhatsApp send also checks Do-Not-Contact + channel
 * eligibility (not the full Guard decide() pipeline — frequency/quiet-hours/
 * birthday rules are scoped to batch/automated contact, not a direct,
 * doctor-requested prescription send). Gated behind guard.full_8factor.
 *
 * Only runs at all when the patient is linked to a Master Relationship —
 * unlinked patients pass through unaffected (nothing to check).
 */
class PrescriptionGuardFactorsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function grantWhatsAppConsent(Patient $patient): void
    {
        $purpose = ConsentPurpose::create([
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

    /** A patient linked to a Master Relationship, with consent already granted. */
    private function linkedConsentedPatient(array $relationshipOverrides = []): Patient
    {
        $relationship = Relationship::create(array_merge([
            'name'  => 'Guard Factors Test',
            'phone' => '900' . random_int(1000, 9999),
        ], $relationshipOverrides));

        $patient = Patient::create([
            'name'      => 'Guard Factors Patient',
            'phone'     => $relationship->phone ?? ('900' . random_int(1000, 9999)),
            'branch_id' => 1,
        ]);
        $patient->relationship_id = $relationship->id;
        $patient->save();

        $this->grantWhatsAppConsent($patient);

        return $patient;
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

    public function test_send_still_works_when_flag_off_even_for_do_not_contact_patient(): void
    {
        $doctor       = $this->admin();
        $patient      = $this->linkedConsentedPatient(['do_not_contact' => true]);
        $prescription = $this->finalizedPrescription($patient, $doctor);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_send_blocked_when_flag_on_and_do_not_contact(): void
    {
        Feature::set('guard.full_8factor', true);

        $doctor       = $this->admin();
        $patient      = $this->linkedConsentedPatient(['do_not_contact' => true]);
        $prescription = $this->finalizedPrescription($patient, $doctor);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'This patient has asked not to be contacted.']);
    }

    public function test_send_allowed_when_flag_on_and_not_do_not_contact(): void
    {
        Feature::set('guard.full_8factor', true);

        $doctor       = $this->admin();
        $patient      = $this->linkedConsentedPatient();
        $prescription = $this->finalizedPrescription($patient, $doctor);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_unlinked_patient_is_unaffected_even_with_flag_on(): void
    {
        Feature::set('guard.full_8factor', true);

        $doctor  = $this->admin();
        $patient = Patient::create([
            'name' => 'No Relationship Patient', 'phone' => '9001112222', 'branch_id' => 1,
        ]); // relationship_id left null on purpose
        $this->grantWhatsAppConsent($patient);
        $prescription = $this->finalizedPrescription($patient, $doctor);

        $response = $this->actingAs($doctor)
            ->postJson(route('patients.prescriptions.whatsapp-send', [$patient, $prescription]));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }
}
