<?php

namespace Tests\Feature\Automation;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\ReminderAutomationRunner;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2, Slice 5 — reminders cutover (appointment reminder tasks).
 *
 * Proves the Automation path:
 *   - creates the reminder task with a VALID created_by (fixes the legacy null bug)
 *   - is idempotent (no double-contact on re-run)
 *   - skips cancelled appointments
 *   - is what the command runs when automation.engine is ON
 */
class ReminderCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
        Feature::set('automation.engine', true);
        Feature::flushCache();
    }

    private function makeTomorrowAppointment(string $status = 'scheduled', string $phone = '9000000501'): Patient
    {
        $doctor  = User::factory()->create(['branch_id' => 1]);
        $patient = Patient::create([
            'name'      => 'Reminder ' . $phone,
            'phone'     => $phone,
            'branch_id' => 1,
        ]);

        Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => $status,
        ]);

        return $patient;
    }

    public function test_automation_creates_reminder_task_with_valid_creator(): void
    {
        $patient = $this->makeTomorrowAppointment();

        $result = app(ReminderAutomationRunner::class)->generateAppointmentReminders();

        $this->assertSame(1, $result['created']);

        $task = Task::where('patient_id', $patient->id)->where('category', 'call')->first();
        $this->assertNotNull($task, 'A reminder task should be created.');
        $this->assertSame("Reminder call: {$patient->name}", $task->title);
        $this->assertNotNull($task->created_by, 'The created_by bug is fixed — never null now.');
    }

    public function test_automation_reminder_is_idempotent(): void
    {
        $patient = $this->makeTomorrowAppointment('scheduled', '9000000502');

        $runner = app(ReminderAutomationRunner::class);
        $runner->generateAppointmentReminders();
        $second = $runner->generateAppointmentReminders();

        $this->assertSame(0, $second['created'], 'No duplicate reminder on re-run.');
        $this->assertSame(
            1,
            Task::where('patient_id', $patient->id)->where('category', 'call')->count(),
            'Exactly one reminder task after two runs.'
        );
    }

    public function test_cancelled_appointment_gets_no_reminder(): void
    {
        $patient = $this->makeTomorrowAppointment('cancelled', '9000000503');

        $result = app(ReminderAutomationRunner::class)->generateAppointmentReminders();

        $this->assertSame(0, $result['created']);
        $this->assertDatabaseMissing('tasks', ['patient_id' => $patient->id, 'category' => 'call']);
    }

    public function test_command_routes_to_automation_when_flag_on(): void
    {
        $patient = $this->makeTomorrowAppointment('scheduled', '9000000504');

        // With the flag ON the command must use the Automation runner and NOT throw
        // (the legacy engine would throw on created_by null).
        $this->artisan('relationship:appointment-reminders')->assertSuccessful();

        $this->assertSame(
            1,
            Task::where('patient_id', $patient->id)->where('category', 'call')->count(),
            'The command created the reminder via Automation.'
        );
    }
}
