<?php

namespace Tests\Feature\Tasks;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task -> Appointment linking.
 *
 * The point of this column is a question the clinic owner asks out loud:
 * "did the recall calls actually fill chairs?" Every assertion below protects
 * the honesty of that answer — a link that pointed at another branch's
 * appointment, or another patient's, would turn the conversion figure into a
 * number that reads well and means nothing.
 */
class TaskAppointmentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(int $branchId = 1): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Clinic Owner', 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        $user = User::factory()->create([
            'role' => 'admin', 'role_id' => $role->id, 'branch_id' => $branchId, 'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function patient(int $branchId = 1): Patient
    {
        return Patient::create([
            'name'      => 'Recall Patient',
            'phone'     => '9' . fake()->numerify('#########'),
            'branch_id' => $branchId,
        ]);
    }

    private function appointment(User $actor, Patient $patient, int $branchId = 1): Appointment
    {
        return Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $actor->id,
            'branch_id'        => $branchId,
            'created_by'       => $actor->id,
            'appointment_date' => today()->addDays(3)->toDateString(),
            'appointment_time' => '11:30',
            'duration_minutes' => 30,
            'type'             => 'follow-up',
            'status'           => 'scheduled',
        ]);
    }

    /**
     * appointment_id is deliberately NOT in Task::$fillable — the link is
     * observed by markDone(), never posted. Task::create() therefore drops it,
     * so a test that needs an already-linked task sets it with forceFill
     * rather than weakening the model to suit the test.
     */
    private function task(User $actor, array $extra = []): Task
    {
        $guarded = array_intersect_key($extra, array_flip(['appointment_id']));
        $extra   = array_diff_key($extra, $guarded);

        $task = Task::create(array_merge([
            'title'       => 'Call about the crown',
            'category'    => 'call',
            'priority'    => 'medium',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $actor->id,
            'assigned_to' => $actor->id,
        ], $extra));

        if (! empty($guarded)) {
            $task->forceFill($guarded)->save();
        }

        return $task;
    }

    // ── the happy path ───────────────────────────────────────────────────

    public function test_closing_a_task_stores_the_appointment_it_produced(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $task    = $this->task($actor, ['patient_id' => $patient->id]);
        $appt    = $this->appointment($actor, $patient);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key'    => 'spoke_done',
            'appointment_id' => $appt->id,
        ])->assertOk()->assertJsonPath('appointment_id', $appt->id);

        $task->refresh();

        $this->assertSame($appt->id, $task->appointment_id);
        $this->assertTrue($task->converted());
        $this->assertSame($appt->id, $task->appointment->id);
    }

    public function test_a_task_with_no_booking_is_simply_not_linked(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.done', $task), ['outcome_key' => 'spoke_done'])->assertOk();

        $task->refresh();

        $this->assertNull($task->appointment_id);
        $this->assertFalse($task->converted());
    }

    // ── the guards ───────────────────────────────────────────────────────

    public function test_an_appointment_from_another_branch_is_never_linked(): void
    {
        $actor   = $this->admin(1);
        $patient = $this->patient(1);
        $task    = $this->task($actor, ['patient_id' => $patient->id]);

        // A real second branch. appointments.branch_id is a foreign key, so
        // an invented id fails at the database before the guard under test
        // ever runs.
        $otherBranch = Branch::create(['name' => 'Second Branch']);

        // Same patient id, but the appointment belongs to the other branch.
        $foreign = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $actor->id,
            'branch_id'        => $otherBranch->id,
            'created_by'       => $actor->id,
            'appointment_date' => today()->addDay()->toDateString(),
            'appointment_time' => '09:00',
            'duration_minutes' => 30,
            'type'             => 'follow-up',
            'status'           => 'scheduled',
        ]);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key'    => 'spoke_done',
            'appointment_id' => $foreign->id,
        ])->assertOk();

        $task->refresh();

        // The close still stands — the work genuinely happened. Only the
        // link is refused.
        $this->assertNull($task->appointment_id);
        $this->assertFalse($task->isOpen());
    }

    public function test_an_appointment_for_a_different_patient_is_never_linked(): void
    {
        $actor    = $this->admin();
        $mine     = $this->patient();
        $somebody = $this->patient();
        $task     = $this->task($actor, ['patient_id' => $mine->id]);
        $wrong    = $this->appointment($actor, $somebody);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key'    => 'spoke_done',
            'appointment_id' => $wrong->id,
        ])->assertOk();

        $this->assertNull($task->fresh()->appointment_id);
    }

    public function test_an_appointment_id_that_does_not_exist_is_rejected_outright(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key'    => 'spoke_done',
            'appointment_id' => 999999,
        ])->assertStatus(422);

        // Nothing was closed, because validation ran before any of it.
        $this->assertTrue($task->fresh()->isOpen());
    }

    public function test_a_task_left_open_by_its_outcome_is_never_marked_converted(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $task    = $this->task($actor, ['patient_id' => $patient->id]);
        $appt    = $this->appointment($actor, $patient);

        // 'no_answer' is a non-contact outcome: the call never connected, so
        // the task stays open and the early return fires before any linking.
        $this->postJson(route('tasks.done', $task), [
            'outcome_key'    => 'no_answer',
            'appointment_id' => $appt->id,
        ])->assertOk()->assertJsonPath('closed', false);

        $task->refresh();

        $this->assertTrue($task->isOpen());
        $this->assertNull($task->appointment_id);
    }

    // ── it shows up where staff look ─────────────────────────────────────

    public function test_the_drawer_reports_the_linked_appointment(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $appt    = $this->appointment($actor, $patient);
        $task    = $this->task($actor, [
            'patient_id'     => $patient->id,
            'appointment_id' => $appt->id,
        ]);

        $this->getJson(route('tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('task.appointment.id', $appt->id)
            ->assertJsonPath('task.appointment.doctor', $actor->name);
    }

    public function test_deleting_the_appointment_leaves_the_task_standing(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $appt    = $this->appointment($actor, $patient);
        $task    = $this->task($actor, [
            'patient_id'     => $patient->id,
            'appointment_id' => $appt->id,
        ]);

        $appt->forceDelete();

        $task->refresh();

        $this->assertNotNull($task->id, 'The task must survive its appointment.');
        $this->assertNull($task->appointment_id);
    }
}
