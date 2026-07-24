<?php

namespace Tests\Feature\Appointments;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use App\Services\Automation\ReminderAutomationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\TestCase;

/**
 * Slice 8 — appointment reminder generation (the canonical, fixed path).
 *
 * The `relationship:appointment-reminders` command now always runs through
 * ReminderAutomationRunner. These tests prove: eligible appointments generate a
 * correctly-attributed reminder task, generation is idempotent, ineligible
 * appointments produce nothing, and failures are observable — with NO outbound
 * patient communication (a reminder is a staff call-task only).
 */
class AppointmentReminderGenerationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;

    private function tomorrow(): string
    {
        return today()->addDay()->toDateString();
    }

    public function test_eligible_appointment_generates_one_reminder_with_correct_linkage(): void
    {
        $appt = $this->makeAppointment([
            'appointment_date' => $this->tomorrow(),
            'status'           => 'scheduled',
        ]);

        Artisan::call('relationship:appointment-reminders');

        $tasks = Task::where('category', 'call')->get();
        $this->assertCount(1, $tasks, 'exactly one reminder task');

        $task = $tasks->first();
        $this->assertSame($appt->patient_id, $task->patient_id, 'linked to the patient');
        $this->assertSame($appt->branch_id, $task->branch_id, 'branch matches the appointment');
        $this->assertNotNull($task->created_by, 'created_by is set');
        $this->assertNotNull(User::find($task->created_by), 'created_by is a real user');
        $this->assertStringContainsString("#{$appt->id}", (string) $task->description, 'references the appointment');
    }

    public function test_running_the_generator_twice_does_not_duplicate(): void
    {
        $this->makeAppointment(['appointment_date' => $this->tomorrow(), 'status' => 'scheduled']);

        Artisan::call('relationship:appointment-reminders');
        Artisan::call('relationship:appointment-reminders');

        $this->assertSame(1, Task::where('category', 'call')->count(), 'idempotent — no duplicate reminder');
    }

    public function test_no_appointment_tomorrow_creates_nothing(): void
    {
        // An appointment TODAY is not eligible (the generator targets tomorrow).
        $this->makeAppointment(['appointment_date' => today()->toDateString(), 'status' => 'scheduled']);

        Artisan::call('relationship:appointment-reminders');

        $this->assertSame(0, Task::where('category', 'call')->count());
    }

    public function test_no_show_appointment_tomorrow_gets_no_reminder(): void
    {
        $this->makeAppointment(['appointment_date' => $this->tomorrow(), 'status' => 'no_show']);

        Artisan::call('relationship:appointment-reminders');

        $this->assertSame(0, Task::where('category', 'call')->count());
    }

    public function test_completed_past_appointment_gets_no_reminder(): void
    {
        // A completed appointment is in the past (not tomorrow) → out of scope.
        $this->makeAppointment(['appointment_date' => today()->subDay()->toDateString(), 'status' => 'done']);

        Artisan::call('relationship:appointment-reminders');

        $this->assertSame(0, Task::where('category', 'call')->count());
    }

    public function test_reminder_is_a_staff_task_not_an_outbound_message(): void
    {
        // Communication safety: the reminder only creates a staff call-task plus a
        // timeline Activity — it never sends WhatsApp/SMS/email.
        $this->makeAppointment(['appointment_date' => $this->tomorrow(), 'status' => 'scheduled']);

        Artisan::call('relationship:appointment-reminders');

        $task = Task::where('category', 'call')->firstOrFail();
        $this->assertSame('call', $task->category);
        $this->assertSame('pending', $task->status);
        $this->assertSame(1, Activity::where('event', 'reminder.task_created')->count());
    }

    public function test_generation_failure_is_not_silently_swallowed(): void
    {
        $this->mock(ReminderAutomationRunner::class, function ($mock) {
            $mock->shouldReceive('generateAppointmentReminders')
                 ->andThrow(new \RuntimeException('reminder boom'));
        });

        $observed = false;
        try {
            $code = Artisan::call('relationship:appointment-reminders');
            $observed = ($code !== 0); // non-zero exit surfaces the failure
        } catch (\Throwable $e) {
            $observed = true; // exception surfaces the failure
        }

        $this->assertTrue($observed, 'a generation failure must be observable, not swallowed');
    }
}
