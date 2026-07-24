<?php

namespace Tests\Feature\Appointments;

use App\Models\Activity;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 2 — Characterization: TIMELINE (activity) behaviour.
 *
 * Appointment lifecycle side-effects are written to the `activities` table via
 * AppointmentActivityLogger → ActivityEngine. This test captures WHEN a row is
 * written. Slice 2 pinned the gap where reschedule / revert / delete emitted
 * nothing (audit F1); Slice 7 closed it, so those now emit one event each. The
 * row is written even when the patient has no relationship_id (it simply won't
 * surface on a Timeline yet).
 */
class AppointmentTimelineCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    private function activityCount(Appointment $appt, ?string $event = null): int
    {
        $q = Activity::where('subject_type', Appointment::class)
            ->where('subject_id', $appt->id);

        if ($event) {
            $q->where('event', $event);
        }

        return $q->count();
    }

    // ── Events that DO write a timeline row ───────────────────────────────

    public function test_booking_writes_appointment_booked(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient();
        $doctor  = $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '11:00',
            'type'             => 'consultation',
        ])->assertOk();

        $appt = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(1, $this->activityCount($appt, 'appointment.booked'));
    }

    public function test_checkin_done_cancel_noshow_each_write_a_row(): void
    {
        $admin = $this->adminUser();

        $a = $this->makeAppointment(['status' => 'scheduled']);
        $this->actingAs($admin)->patchJson(route('appointments.updateStatus', $a), ['status' => 'checkin'])->assertOk();
        $this->assertSame(1, $this->activityCount($a, 'appointment.checked_in'));

        $b = $this->makeAppointment(['status' => 'in_chair']);
        $this->actingAs($admin)->patchJson(route('appointments.updateStatus', $b), ['status' => 'done'])->assertOk();
        $this->assertSame(1, $this->activityCount($b, 'appointment.completed'));

        $c = $this->makeAppointment(['status' => 'scheduled']);
        $this->actingAs($admin)->patchJson(route('appointments.updateStatus', $c), ['status' => 'no_show'])->assertOk();
        $this->assertSame(1, $this->activityCount($c, 'appointment.missed'));

        $d = $this->makeAppointment(['status' => 'scheduled']);
        $this->actingAs($admin)->patchJson(route('appointments.cancel', $d), [
            'cancel_reason' => 'x', 'cancelled_party' => 'clinic',
        ])->assertOk();
        $this->assertSame(1, $this->activityCount($d, 'appointment.cancelled'));
    }

    public function test_booking_row_is_written_even_without_a_relationship_id(): void
    {
        $admin   = $this->adminUser();
        $patient = $this->newPatient(); // no relationship_id
        $doctor  = $this->doctorUser();

        $this->actingAs($admin)->postJson(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '09:30',
            'type'             => 'consultation',
        ])->assertOk();

        $appt = Appointment::where('patient_id', $patient->id)->firstOrFail();
        $row  = Activity::where('subject_id', $appt->id)->where('event', 'appointment.booked')->first();

        $this->assertNotNull($row);
        $this->assertNull($row->relationship_id, 'row is written even with a null relationship_id');
    }

    // ── Events CLOSED in Slice 7 ──────────────────────────────────────────
    // Slice 2 pinned these three as emitting NO lifecycle row (audit F1 gap).
    // Slice 7 is the approved slice that closes the gap: each now emits exactly
    // one canonical event via AppointmentActivityLogger (the single producer).

    public function test_reschedule_writes_appointment_rescheduled(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);

        $this->actingAs($admin)->patchJson(route('appointments.reschedule', $appt), [
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '16:30',
        ])->assertOk();

        // OLD (Slice 2): asserted 0 rows. NEW (Slice 7): exactly one rescheduled.
        $this->assertSame(1, $this->activityCount($appt), 'reschedule emits exactly one lifecycle event');
        $this->assertSame(1, $this->activityCount($appt, 'appointment.rescheduled'));
    }

    public function test_revert_writes_appointment_reverted(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        // in_chair emits nothing; revert now emits appointment.reverted.
        $this->actingAs($admin)->patchJson(route('appointments.updateStatus', $appt), ['status' => 'in_chair'])->assertOk();
        $this->actingAs($admin)->patchJson(route('appointments.revert', $appt))->assertOk();

        // OLD (Slice 2): asserted 0 rows. NEW (Slice 7): exactly one reverted.
        $this->assertSame(1, $this->activityCount($appt), 'revert emits exactly one lifecycle event');
        $this->assertSame(1, $this->activityCount($appt, 'appointment.reverted'));
    }

    public function test_delete_writes_appointment_deleted(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $this->actingAs($admin)->deleteJson(route('appointments.destroy', $appt), ['json' => true])->assertOk();

        // OLD (Slice 2): asserted 0 rows. NEW (Slice 7): exactly one deleted.
        $this->assertSame(1, $this->activityCount($appt), 'delete emits exactly one lifecycle event');
        $this->assertSame(1, $this->activityCount($appt, 'appointment.deleted'));
    }
}
