<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentCategory;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 6 — Booking Parity & Validation.
 *
 * Proves the canonical rules now hold across every booking path:
 *   P1 walk-in patients are minted through PatientService::register()
 *   P2 one canonical duration rule (bounds + default)
 *   P3 `follow-up` accepted everywhere
 *   P4 invalid duration / type rejected consistently on web and API
 */
class AppointmentBookingParityTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    // ── P1: patient registration invariant ────────────────────────────────

    public function test_no_direct_patient_minting_remains_in_appointments(): void
    {
        // The Appointments files are no longer whitelisted in the invariant
        // guard; this passes only because they mint via PatientService::register.
        $this->artisan('patients:invariant-check')->assertExitCode(0);
    }

    public function test_web_walkin_new_patient_is_registered_with_a_tdc_number(): void
    {
        $admin = $this->adminUser();
        $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'first_name'       => 'Parity',
            'last_name'        => 'Walkin',
            'mobile'           => '9700011122',
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:15',
        ])->assertOk();

        $patient = Patient::where('name', 'Parity Walkin')->firstOrFail();
        // register() omits patient_id so the model boot assigns a TDC number.
        $this->assertNotNull($patient->patient_id, 'walk-in patient should get a TDC via register()');
        $this->assertSame('Parity', $patient->first_name);
    }

    // ── P2: canonical duration rule ───────────────────────────────────────

    public function test_resolve_duration_is_the_single_source(): void
    {
        $svc = app(AppointmentService::class);

        $this->assertSame(120, $svc->resolveDuration(120, null), 'explicit value wins');
        $this->assertSame(30, $svc->resolveDuration(null, null), 'no hint → 30');

        $implant = TreatmentCategory::create(['name' => 'Implant Surgery', 'is_active' => true]);
        $this->assertSame(90, $svc->resolveDuration(null, $implant->id), 'category default (implant → 90)');
    }

    public function test_api_booking_defaults_duration_from_the_category(): void
    {
        // Previously the API used a flat 30-min fallback; now it shares the
        // web's category-based default via resolveDuration().
        $admin    = $this->adminUser();
        $patient  = $this->newPatient();
        $doctor   = $this->doctorUser();
        $category = TreatmentCategory::create(['name' => 'Implant', 'is_active' => true]);
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/appointments', [
            'patient_id'            => $patient->id,
            'doctor_id'             => $doctor->id,
            'appointment_date'      => today()->toDateString(),
            'appointment_time'      => '09:00',
            'type'                  => 'treatment',
            'treatment_category_id' => $category->id,
        ])->assertStatus(201)->assertJsonPath('data.duration_minutes', 90);
    }

    public function test_web_now_accepts_a_long_duration_up_to_480(): void
    {
        // Web store/update used to cap at 240; the canonical max is 480 (matching
        // reschedule + API). 300 was previously rejected, now accepted.
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'treatment',
            'duration_minutes' => 300,
        ])->assertOk();
    }

    public function test_duration_below_10_is_rejected_on_web_and_api(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        // Web
        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'duration_minutes' => 9,
        ])->assertStatus(422)->assertJsonValidationErrors('duration_minutes');

        // API (previously allowed a 5-min booking; now min 10 like the web)
        Sanctum::actingAs($admin, ['*']);
        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '12:00',
            'type'             => 'consultation',
            'duration_minutes' => 9,
        ])->assertStatus(422);
    }

    public function test_duration_above_480_is_rejected_on_web_and_api(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'duration_minutes' => 481,
        ])->assertStatus(422);

        Sanctum::actingAs($admin, ['*']);
        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '12:00',
            'type'             => 'consultation',
            'duration_minutes' => 481,
        ])->assertStatus(422);
    }

    // ── P3: follow-up type parity ─────────────────────────────────────────

    public function test_follow_up_type_is_accepted_on_web_and_api(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        // Web (already accepted follow-up)
        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'follow-up',
        ])->assertOk();

        // API (previously rejected follow-up — the P3 fix)
        Sanctum::actingAs($admin, ['*']);
        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '13:00',
            'type'             => 'follow-up',
        ])->assertStatus(201)->assertJsonPath('data.type', 'follow-up');
    }

    public function test_invalid_type_is_rejected_on_web_and_api(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'banana',
        ])->assertStatus(422)->assertJsonValidationErrors('type');

        Sanctum::actingAs($admin, ['*']);
        $this->postJson('/api/v1/appointments', [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '13:00',
            'type'             => 'banana',
        ])->assertStatus(422);
    }
}
