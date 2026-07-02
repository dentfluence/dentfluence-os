<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patient;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Appointments module — status workflow (the daily front-desk heartbeat)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  Moving a patient through the day must stamp the right times and remember
 *  the previous status so the desk can undo a mis-tap:
 *    1. Marking "in chair" records the in_chair_at time and saves the old
 *       status as previous_status.
 *    2. Marking "done" records the completed_at time.
 *    3. "Revert" puts the appointment back to its previous status.
 */
class AppointmentStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_changes_stamp_times_and_can_be_reverted(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $doctor  = User::factory()->create(['branch_id' => 1]);
        $patient = Patient::create([
            'name'      => 'Dusk Patient',
            'phone'     => '9999900000',
            'branch_id' => 1,
        ]);

        $appt = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);

        // 1. Move to "in chair"
        $r1 = $this->actingAs($doctor)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'in_chair']);
        $r1->assertOk()->assertJson(['ok' => true, 'status' => 'in_chair']);

        $appt->refresh();
        $this->assertSame('in_chair', $appt->status);
        $this->assertNotNull($appt->in_chair_at, 'in_chair_at should be stamped');
        $this->assertSame('scheduled', $appt->previous_status, 'previous status should be remembered');

        // 2. Move to "done"
        $this->actingAs($doctor)
            ->patchJson(route('appointments.updateStatus', $appt), ['status' => 'done'])
            ->assertOk();
        $appt->refresh();
        $this->assertSame('done', $appt->status);
        $this->assertNotNull($appt->completed_at, 'completed_at should be stamped');

        // 3. Revert — back to the previous status ("in_chair")
        $this->actingAs($doctor)
            ->patchJson(route('appointments.revert', $appt))
            ->assertOk();
        $appt->refresh();
        $this->assertSame('in_chair', $appt->status, 'revert should restore previous status');
    }
}
