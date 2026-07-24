<?php

namespace Tests\Feature\Appointments;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 7 — lifecycle events for the actions that used to emit nothing
 * (reschedule / revert / delete): actor + context capture, exactly-one
 * production (no duplicates), and transaction-rollback safety.
 */
class AppointmentLifecycleEventTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    private function event(Appointment $appt, string $event): ?Activity
    {
        return Activity::where('subject_type', Appointment::class)
            ->where('subject_id', $appt->id)
            ->where('event', $event)
            ->first();
    }

    private function meta(Activity $a): array
    {
        return is_array($a->metadata) ? $a->metadata : (json_decode((string) $a->metadata, true) ?: []);
    }

    public function test_reschedule_event_records_actor_and_old_and_new_slot(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);

        $this->actingAs($admin)->patchJson(route('appointments.reschedule', $appt), [
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '16:30',
        ])->assertOk();

        $row = $this->event($appt, 'appointment.rescheduled');
        $this->assertNotNull($row);
        $this->assertSame($admin->id, $row->actor_id);
        $this->assertSame(User::class, $row->actor_type);

        $meta = $this->meta($row);
        $this->assertSame('10:00', substr((string) $meta['from_time'], 0, 5));
        $this->assertSame('16:30', substr((string) $meta['to_time'], 0, 5));
    }

    public function test_reverted_event_records_from_and_to_status(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['status' => 'scheduled']);

        $this->actingAs($admin)->patchJson(route('appointments.updateStatus', $appt), ['status' => 'in_chair'])->assertOk();
        $this->actingAs($admin)->patchJson(route('appointments.revert', $appt))->assertOk();

        $row = $this->event($appt, 'appointment.reverted');
        $this->assertNotNull($row);
        $this->assertSame($admin->id, $row->actor_id);

        $meta = $this->meta($row);
        $this->assertSame('in_chair', $meta['from_status']);
        $this->assertSame('scheduled', $meta['to_status']);
    }

    public function test_delete_event_records_actor(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment();

        $this->actingAs($admin)->deleteJson(route('appointments.destroy', $appt), ['json' => true])->assertOk();

        $row = $this->event($appt, 'appointment.deleted');
        $this->assertNotNull($row);
        $this->assertSame($admin->id, $row->actor_id);
    }

    public function test_single_reschedule_produces_exactly_one_event_no_duplicate(): void
    {
        $admin = $this->adminUser();
        $appt  = $this->makeAppointment(['appointment_time' => '10:00']);

        $this->actingAs($admin)->patchJson(route('appointments.reschedule', $appt), [
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '16:30',
        ])->assertOk();

        // Exactly one producer — no controller + service + observer triple-log.
        $this->assertSame(1, Activity::where('subject_id', $appt->id)
            ->where('event', 'appointment.rescheduled')->count());
    }

    public function test_failed_reschedule_leaves_no_lifecycle_event(): void
    {
        // Transaction-rollback safety: a rejected reschedule (overlap) must not
        // leave an appointment.rescheduled row, and must not move the slot.
        $admin   = $this->adminUser();
        $doctor  = $this->doctorUser();
        $moving  = $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '10:00', 'duration_minutes' => 30]);
        $this->makeAppointment(['doctor_id' => $doctor->id, 'appointment_time' => '14:00', 'duration_minutes' => 30]);

        $this->actingAs($admin)->patchJson(route('appointments.reschedule', $moving), [
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '14:00', // collides with the other appointment
        ])->assertStatus(422);

        $this->assertSame(0, Activity::where('subject_id', $moving->id)
            ->where('event', 'appointment.rescheduled')->count(), 'no event on a rejected reschedule');
        $this->assertSame('10:00:00', $moving->fresh()->appointment_time, 'slot unchanged after rejection');
    }
}
