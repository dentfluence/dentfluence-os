<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: Appointment CREATION.
 *
 * Captures exactly what the booking paths do TODAY (web full-form, web walk-in
 * new/existing patient, API store, API walk-in, overlap detection,
 * allow_overlap override, validation failures). This is a behavioural
 * contract, not an assertion of what booking SHOULD do.
 */
class AppointmentCreationCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    // ── WEB: full-form booking, existing patient ──────────────────────────

    public function test_web_full_form_books_a_scheduled_appointment(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:00',
            'type'             => 'consultation',
        ]);

        $resp->assertOk()
            ->assertJson(['success' => true, 'ok' => true]);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id'  => $doctor->id,
            'status'     => 'scheduled',
            'is_walkin'  => false,
        ]);
    }

    // ── WEB: walk-in with a NEW patient ───────────────────────────────────

    public function test_web_walkin_new_patient_mints_patient_and_checks_in(): void
    {
        $admin = $this->adminUser();
        $this->doctorUser(); // a default active doctor for the branch fallback

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'first_name'       => 'Walkin',
            'last_name'        => 'Newby',
            'mobile'           => '9800012345',
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '12:00',
        ]);

        $resp->assertOk()->assertJson(['success' => true, 'ok' => true]);

        $patient = Patient::where('name', 'Walkin Newby')->first();
        $this->assertNotNull($patient, 'walk-in mints a new patient');

        $appt = Appointment::where('patient_id', $patient->id)->first();
        $this->assertSame('checkin', $appt->status, 'walk-in starts checked in');
        $this->assertTrue((bool) $appt->is_walkin);
        $this->assertNotNull($appt->checked_in_at);
    }

    // ── WEB: walk-in with an EXISTING patient ─────────────────────────────

    public function test_web_walkin_existing_patient_checks_in(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $this->doctorUser();

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'is_walkin'        => true,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '12:30',
        ]);

        $resp->assertOk()->assertJson(['success' => true, 'ok' => true]);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'status'     => 'checkin',
            'is_walkin'  => true,
        ]);
    }

    // ── WEB: overlap detection + allow_overlap override ───────────────────

    public function test_web_overlap_is_rejected_with_422_and_conflict_payload(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'duration_minutes' => 30]);

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:15',
            'type'             => 'consultation',
        ]);

        $resp->assertStatus(422)
            ->assertJsonPath('has_conflict', true)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['success', 'ok', 'has_conflict', 'message', 'errors' => ['appointment_time']]);
    }

    public function test_web_allow_overlap_lets_the_double_booking_through(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'duration_minutes' => 30]);

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:15',
            'type'             => 'consultation',
            'allow_overlap'    => true,
        ]);

        $resp->assertOk()->assertJson(['success' => true]);
    }

    // ── WEB: validation failure (malformed time) — 422, not 500 ───────────

    public function test_web_malformed_time_returns_422_validation_error(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();

        $resp = $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '99:99',
            'type'             => 'consultation',
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors('appointment_time');
    }

    // ── API: booking an existing patient ──────────────────────────────────

    public function test_api_store_books_and_returns_201_envelope(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();
        Sanctum::actingAs($admin, ['*']);

        $resp = $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '14:00',
            'type'             => 'consultation',
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'scheduled');
    }

    // ── API: walk-in ──────────────────────────────────────────────────────

    public function test_api_walkin_checks_in_a_new_patient(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin, ['*']);

        $resp = $this->postJson('/api/v1/appointments/walk-in', [
            'first_name'       => 'Api',
            'last_name'        => 'Walkin',
            'phone'            => '9811122233',
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '15:00',
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'checkin')
            ->assertJsonPath('data.is_walkin', true);
    }

    // ── API: malformed time is caught (M3 guard) as a 422, not a 500 ──────

    public function test_api_malformed_time_is_caught_as_422(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();
        Sanctum::actingAs($admin, ['*']);

        $resp = $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => 'abc',
            'type'             => 'consultation',
        ]);

        $resp->assertStatus(422);
    }
}
