<?php

namespace Tests\Feature\Characterization;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use App\Services\Relationship\AppointmentReminderEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERIZATION TEST — pins the CURRENT behaviour of AppointmentReminderEngine
 * so Phase 2 (Automation Engine) cannot change it by accident. These assertions
 * describe how the engine behaves TODAY. Safety net, not a target spec.
 *
 * ⚠️ KNOWN LATENT BUG PINNED HERE (discovered 2026-07-02, Sprint 1):
 * AppointmentReminderEngine::generateReminders() hardcodes `created_by => null`
 * when creating the "Reminder call" task, but `tasks.created_by` is NOT NULL with
 * an FK to users (see 2025_01_01_000001_create_tasks_table). So for a real
 * appointment tomorrow the task INSERT throws a QueryException — the engine
 * currently cannot create reminder tasks. The "cancelled" path is safe only
 * because it returns before the insert.
 *
 * When Phase 2 fixes this (system tasks need a system actor / nullable creator),
 * flip test_reminder_task_creation_* below from expecting a throw to expecting
 * a successfully-created, deduplicated task. See docs/phase-2/automation-inventory.md §4.
 */
class AppointmentReminderCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    /** Creates the reminder-call fixture: an appointment for tomorrow. */
    private function makeTomorrowAppointment(string $status = 'scheduled', string $phone = '9000000201'): array
    {
        $doctor  = User::factory()->create(['branch_id' => 1]);
        $patient = Patient::create([
            'name'      => 'Reminder Patient ' . $phone,
            'phone'     => $phone,
            'branch_id' => 1,
        ]);

        $appt = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => $status,
        ]);

        return [$doctor, $patient, $appt];
    }

    /**
     * CURRENT behaviour: with a real appointment tomorrow the engine throws when
     * it tries to insert a task with a null created_by. Pins the latent bug so a
     * future fix is a deliberate, visible change to this test.
     */
    public function test_reminder_task_creation_currently_throws_on_null_created_by(): void
    {
        $this->makeTomorrowAppointment();

        $this->expectException(QueryException::class);

        (new AppointmentReminderEngine())->generateReminders();
    }

    /** No reminder task is ever persisted under today's behaviour. */
    public function test_no_reminder_task_is_persisted_today(): void
    {
        $this->makeTomorrowAppointment('scheduled', '9000000202');

        try {
            (new AppointmentReminderEngine())->generateReminders();
        } catch (QueryException) {
            // expected today — see class docblock
        }

        $this->assertSame(0, Task::where('category', 'call')->count());
    }

    /** A cancelled appointment tomorrow short-circuits before any insert — no throw, no task. */
    public function test_cancelled_appointment_gets_no_reminder(): void
    {
        [, $patient] = $this->makeTomorrowAppointment('cancelled', '9000000203');

        $result = (new AppointmentReminderEngine())->generateReminders();

        $this->assertSame(0, $result['created'], 'Cancelled appointments must not generate reminders.');

        $this->assertDatabaseMissing('tasks', [
            'patient_id' => $patient->id,
            'category'   => 'call',
        ]);
    }
}
