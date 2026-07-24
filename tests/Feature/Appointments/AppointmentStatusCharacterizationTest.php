<?php

namespace Tests\Feature\Appointments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: Appointment STATUS FLOW.
 *
 * Captures every transition exactly as it behaves today, including the fact
 * that "invalid" transitions (e.g. done -> scheduled) are CURRENTLY ALLOWED
 * because there is no state machine — only an `in:` list. That is a known gap
 * (see audit E3); this test locks the present behaviour, it does not endorse it.
 */
class AppointmentStatusCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    // ── WEB status transitions stamp timestamps ───────────────────────────

    public function test_web_checkin_stamps_checked_in_at_and_saves_previous_status(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'checkin'])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => 'checkin']);

        $appt->refresh();
        $this->assertSame('checkin', $appt->status);
        $this->assertNotNull($appt->checked_in_at);
        $this->assertSame('scheduled', $appt->previous_status);
    }

    public function test_web_in_chair_then_done_stamp_their_timestamps(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'checkin']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'in_chair'])
            ->assertOk();
        $appt->refresh();
        $this->assertNotNull($appt->in_chair_at);

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'done'])
            ->assertOk();
        $appt->refresh();
        $this->assertSame('done', $appt->status);
        $this->assertNotNull($appt->completed_at);
    }

    public function test_web_no_show_via_status_dropdown(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'no_show'])
            ->assertOk()
            ->assertJson(['status' => 'no_show']);

        $this->assertSame('no_show', $appt->fresh()->status);
    }

    public function test_web_cancel_with_reason_records_party_and_reason(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.cancel', $appt), [
                'cancel_reason'   => 'Patient requested',
                'cancelled_party' => 'patient',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $appt->refresh();
        $this->assertSame('cancelled', $appt->status);
        $this->assertSame('Patient requested', $appt->cancel_reason);
        $this->assertSame('patient', $appt->cancelled_party);
        $this->assertSame('scheduled', $appt->previous_status);
    }

    public function test_web_revert_restores_previous_status_and_clears_it(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        // move to in_chair (previous_status becomes 'scheduled')
        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'in_chair'])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('appointments.revert', $appt))
            ->assertOk();

        $appt->refresh();
        $this->assertSame('scheduled', $appt->status);
        $this->assertNull($appt->previous_status);
    }

    public function test_web_revert_without_previous_status_returns_422(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled', 'previous_status' => null]);

        $this->actingAs($admin)
            ->patchJson(route('appointments.revert', $appt))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    // ── Invalid transition is CURRENTLY ALLOWED (no state machine) ────────

    public function test_web_backwards_transition_done_to_scheduled_is_currently_allowed(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'done']);

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'scheduled'])
            ->assertOk()
            ->assertJson(['status' => 'scheduled']);

        $this->assertSame('scheduled', $appt->fresh()->status);
    }

    public function test_web_invalid_status_value_is_rejected(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $this->actingAs($admin)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ── API status transition parity ──────────────────────────────────────

    public function test_api_status_update_advances_and_returns_counts(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/v1/appointments/{$appt->id}/status", ['status' => 'checkin'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'checkin')
            ->assertJsonStructure(['meta' => ['counts']]);

        $this->assertNotNull($appt->fresh()->checked_in_at);
    }
}
