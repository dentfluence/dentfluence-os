<?php

namespace Tests\Feature\Appointments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: BOOKING / ACTION RESPONSES.
 *
 * Locks the exact HTTP status, redirect target, flash message, and JSON shape
 * each endpoint returns today, for both the browser (redirect + flash) and
 * XHR/JSON (ok:true) styles the calendar uses.
 */
class AppointmentResponseCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    // ── WEB browser POST → redirect + flash ───────────────────────────────

    public function test_web_full_form_browser_post_redirects_with_success_flash(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $resp = $this->actingAs($admin)->post(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:30',
            'type'             => 'consultation',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success', 'Appointment booked successfully.');
    }

    public function test_web_walkin_browser_post_flashes_walkin_message(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $this->doctorUser();

        $resp = $this->actingAs($admin)->post(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'is_walkin'        => true,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '13:15',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success', 'Walk-in booked successfully.');
    }

    // ── WEB JSON action responses ─────────────────────────────────────────

    public function test_web_reschedule_returns_ok_json(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.reschedule', $appt), [
                'appointment_date' => today()->toDateString(),
                'appointment_time' => '16:00',
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'appointment' => ['id', 'appointment_time']])
            // The JSON payload trims the time to HH:MM (the client contract)…
            ->assertJsonPath('appointment.appointment_time', '16:00');

        // …while the raw TIME column stores/returns HH:MM:SS (current behaviour).
        $this->assertSame('16:00:00', $appt->fresh()->appointment_time);
    }

    public function test_web_hide_returns_ok_json_and_persists(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'cancelled']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.hide', $appt))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue((bool) $appt->fresh()->hidden_from_calendar);
    }

    public function test_web_destroy_json_returns_ok_and_soft_deletes(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $this->actingAs($admin)
            ->deleteJson(route('appointments.destroy', $appt), ['json' => true])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSoftDeleted('appointments', ['id' => $appt->id]);
    }

    public function test_web_destroy_browser_redirects_with_flash(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $resp = $this->actingAs($admin)->delete(route('appointments.destroy', $appt));

        $resp->assertRedirect(route('appointments.index'));
        $resp->assertSessionHas('success', 'Appointment deleted.');
    }

    public function test_web_check_conflict_returns_boolean_and_list(): void
    {
        $admin  = $this->adminUser();
        $doctor = $this->doctorUser();
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'duration_minutes' => 30]);

        $this->actingAs($admin)
            ->getJson(route('appointments.check.conflict', [
                'doctor_id'        => $doctor->id,
                'appointment_date' => today()->toDateString(),
                'appointment_time' => '10:15',
                'duration_minutes' => 30,
            ]))
            ->assertOk()
            ->assertJsonPath('has_conflict', true)
            ->assertJsonStructure(['has_conflict', 'conflicts' => [['patient_name', 'time', 'duration']]]);
    }

    // ── API envelope messages ─────────────────────────────────────────────

    public function test_api_action_messages_are_stable(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin, ['*']);

        $appt = $this->makeAppointment(['status' => 'scheduled']);

        $this->patchJson("/api/v1/appointments/{$appt->id}/status", ['status' => 'checkin'])
            ->assertOk()->assertJsonPath('message', 'Status updated.');

        $this->patchJson("/api/v1/appointments/{$appt->id}/cancel", [
            'cancel_reason'   => 'x',
            'cancelled_party' => 'clinic',
        ])->assertOk()->assertJsonPath('message', 'Appointment cancelled.');

        $this->deleteJson("/api/v1/appointments/{$appt->id}")
            ->assertOk()->assertJsonPath('message', 'Appointment deleted.');
    }
}
