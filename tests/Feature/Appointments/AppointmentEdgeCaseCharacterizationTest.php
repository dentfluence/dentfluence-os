<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: EDGE CASES.
 *
 * Double-booking, cancelled/no-show slots freeing up, hidden appointments,
 * a soft-deleted patient, and the server timezone that the M2 date bug class
 * hinges on. Behaviour as-is today.
 */
class AppointmentEdgeCaseCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    public function test_exact_same_slot_double_booking_is_rejected(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'duration_minutes' => 30]);

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
        ])->assertStatus(422);
    }

    public function test_a_cancelled_appointment_frees_the_slot(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();
        $existing = $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'status' => 'cancelled']);

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
        ])->assertOk();
    }

    public function test_a_no_show_appointment_frees_the_slot(): void
    {
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $patient = $this->newPatient();
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'status' => 'no_show']);

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
        ])->assertOk();
    }

    public function test_hidden_appointment_persists_and_is_excluded_by_the_calendar_filter(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'cancelled']);

        $this->actingAs($admin)->patchJson(route('appointments.hide', $appt))->assertOk();

        // Row still exists, flag persisted (M1)…
        $this->assertTrue((bool) $appt->fresh()->hidden_from_calendar);
        // …and the calendar's "not hidden" filter now excludes it.
        $this->assertNull(
            Appointment::where('hidden_from_calendar', false)->whereKey($appt->id)->first(),
            'hidden appointment should drop out of the calendar query'
        );
    }

    public function test_soft_deleted_patient_serializes_with_placeholder_name(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $appt    = $this->makeAppointment(['patient_id' => $patient->id]);

        $patient->delete(); // soft delete

        Sanctum::actingAs($admin, ['*']);
        $this->getJson("/api/v1/appointments/{$appt->id}")
            ->assertOk()
            ->assertJsonPath('data.patient_name', '—');
    }

    public function test_server_timezone_is_asia_kolkata(): void
    {
        // The M2 date bug class exists precisely because the app runs in a
        // +5:30 timezone. Lock it so a config change is a conscious decision.
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
    }
}
