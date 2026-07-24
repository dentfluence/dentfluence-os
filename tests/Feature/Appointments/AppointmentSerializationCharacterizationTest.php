<?php

namespace Tests\Feature\Appointments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: Appointment SERIALIZATION.
 *
 * Locks the EXACT key set of the two payload shapes the clients depend on:
 *   - web  : AppointmentController::formatAppointment()
 *   - API  : App\Http\Resources\AppointmentResource
 *
 * We compare the full sorted key list (not just presence) so that any slice
 * adding, removing, or renaming a key trips this test and forces an explicit,
 * approved decision.
 */
class AppointmentSerializationCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    /** The 30 keys formatAppointment() returns today. */
    private const WEB_KEYS = [
        'id', 'patient_id', 'patient_name', 'patient_phone', 'patient_age',
        'doctor_id', 'doctor_name', 'appointment_date', 'appointment_time',
        'duration_minutes', 'type', 'status', 'notes', 'chief_complaint',
        'treatment_category_id', 'treatment_category', 'treatment_id', 'treatment',
        'is_walkin', 'chair_number', 'operatory_id', 'operatory_name',
        'checked_in_at', 'in_chair_at', 'completed_at', 'cancel_reason',
        'cancelled_party', 'previous_status', 'treatment_color', 'doctor_color',
    ];

    /** The 28 keys AppointmentResource returns today. */
    private const API_KEYS = [
        'id', 'status', 'type', 'appointment_date', 'appointment_time',
        'duration_minutes', 'patient_id', 'patient_name', 'patient_phone',
        'doctor_id', 'doctor_name', 'doctor_color', 'treatment_category_id',
        'treatment_category', 'treatment_id', 'treatment', 'operatory_id',
        'operatory_name', 'chair_number', 'is_walkin', 'notes', 'chief_complaint',
        'cancel_reason', 'cancelled_party', 'checked_in_at', 'in_chair_at',
        'completed_at', 'created_at',
    ];

    public function test_web_format_appointment_key_set_is_stable(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $payload = $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'checkin'])
            ->assertOk()
            ->json('appointment');

        $expected = self::WEB_KEYS;
        sort($expected);
        $actual = array_keys($payload);
        sort($actual);

        $this->assertSame($expected, $actual, 'web formatAppointment() key set changed');
    }

    public function test_api_resource_key_set_is_stable(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();
        Sanctum::actingAs($admin, ['*']);

        $data = $this->getJson("/api/v1/appointments/{$appt->id}")
            ->assertOk()
            ->json('data');

        $expected = self::API_KEYS;
        sort($expected);
        $actual = array_keys($data);
        sort($actual);

        $this->assertSame($expected, $actual, 'AppointmentResource key set changed');
    }

    public function test_web_payload_formats_date_and_time_narrowly(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);

        $payload = $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'checkin'])
            ->json('appointment');

        $this->assertSame(today()->toDateString(), $payload['appointment_date']);
        $this->assertSame('10:00', $payload['appointment_time'], 'time is trimmed to HH:MM');
    }

    public function test_api_payload_formats_date_and_time_narrowly(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);
        Sanctum::actingAs($admin, ['*']);

        $data = $this->getJson("/api/v1/appointments/{$appt->id}")->json('data');

        $this->assertSame(today()->toDateString(), $data['appointment_date']);
        $this->assertSame('10:00', $data['appointment_time']);
    }
}
