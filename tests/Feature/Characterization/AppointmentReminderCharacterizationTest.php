<?php

namespace Tests\Feature\Characterization;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Appointment reminder generation — canonical behaviour.
 *
 * ⚠️ SLICE 8 CHANGE (approved defect fix): this file previously PINNED a latent
 * bug — the legacy AppointmentReminderEngine hardcoded `created_by => null`
 * (tasks.created_by is NOT NULL), so it THREW and created nothing. The original
 * docblock explicitly said: "When Phase 2 fixes this … flip test_reminder_task_
 * creation_* from expecting a throw to expecting a successfully-created,
 * deduplicated task."
 *
 * Slice 8 is that fix. The canonical producer is now ReminderAutomationRunner
 * (valid created_by + branch_id), run unconditionally by the
 * `relationship:appointment-reminders` command. These tests now characterise the
 * FIXED behaviour via that command.
 *
 *   OLD → engine threw QueryException, 0 tasks persisted.
 *   NEW → command creates exactly one valid, deduplicated reminder task.
 *   WHY → the reminder defect (R1) is the approved target of Slice 8.
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

    /** NEW: reminder generation now creates a valid task (no throw). */
    public function test_reminder_generation_creates_a_valid_task(): void
    {
        [, $patient] = $this->makeTomorrowAppointment();

        $this->artisan('relationship:appointment-reminders')->assertSuccessful();

        $task = Task::where('patient_id', $patient->id)->where('category', 'call')->first();
        $this->assertNotNull($task, 'a reminder task is now created');
        $this->assertNotNull($task->created_by, 'created_by is a valid actor, never null');
    }

    /** NEW: exactly one reminder task is persisted (was 0 under the old throw). */
    public function test_reminder_task_is_persisted_exactly_once(): void
    {
        $this->makeTomorrowAppointment('scheduled', '9000000202');

        $this->artisan('relationship:appointment-reminders')->assertSuccessful();

        $this->assertSame(1, Task::where('category', 'call')->count());
    }

    /** UNCHANGED: a cancelled appointment tomorrow generates no reminder. */
    public function test_cancelled_appointment_gets_no_reminder(): void
    {
        [, $patient] = $this->makeTomorrowAppointment('cancelled', '9000000203');

        $this->artisan('relationship:appointment-reminders')->assertSuccessful();

        $this->assertDatabaseMissing('tasks', [
            'patient_id' => $patient->id,
            'category'   => 'call',
        ]);
    }
}
