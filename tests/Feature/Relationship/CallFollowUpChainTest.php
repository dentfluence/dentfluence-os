<?php

namespace Tests\Feature\Relationship;

use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A CALL THAT PROMISES SATURDAY MUST PRODUCE SATURDAY'S WORK.
 *
 * ── THE FAILURE (CEO, 23 Sep 2026) ──────────────────────────────────────────
 * "Samiksha ne ek call kela patient la, tyane sangitla me Saturday la karto
 * X-ray… pan Saturday cha kay? Call response madhye note kela pan pudhcha task
 * ready nahi jhala."
 *
 * Logging a call recorded the OUTCOME and, on queue-backed rows only, pushed
 * follow_up_date by a fixed +2 days. Not Saturday. Not owned by anyone. Not on
 * any list a person works down. So Saturday came and nobody rang him.
 *
 * ── WHY A TASK AND NOT A MERGED BOARD ───────────────────────────────────────
 * The CEO's first instinct was to merge Today's Actions into Tasks. That
 * reverses his own 6 Sep rule ("PRE engine che task vegle, task manager che
 * vegle") and makes a finite list infinite: Today's Actions regenerates itself
 * from patient data every morning and is never finished, while Tasks are
 * promises worked to zero. The hand-off is the fix; the merge is not.
 *
 * Hence: the chained task is a HUMAN task. TaskEngine tags its own 'system'
 * and visibleToReception() hides those — a promise a person made on a call
 * must be visible to the people who work the board.
 */
class CallFollowUpChainTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Clinic Owner', 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        $user = User::factory()->create([
            'role'      => 'admin',
            'role_id'   => $role->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function patient(): Patient
    {
        return Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'CallChain',
            'name'       => 'Test CallChain',
            'gender'     => 'male',
            'phone'      => '9000000055',
            'branch_id'  => 1,
        ]);
    }

    private function followUp(Patient $patient): FollowUp
    {
        return FollowUp::create([
            'patient_id' => $patient->id,
            'label'      => 'Follow-up call',
            'due_date'   => today()->toDateString(),
            'status'     => 'pending',
            'channel'    => 'call',
            'priority'   => 'medium',
        ]);
    }

    private function logCall(Patient $patient, FollowUp $fu, array $extra = [])
    {
        return $this->postJson(route('relationship.today.log-call'), array_merge([
            'patient_id' => $patient->id,
            'response'   => 'will_call_back',
            'notes'      => 'Patient will come Saturday for the X-ray.',
            'items'      => [
                ['category' => 'follow_up_calls', 'subject_id' => $fu->id],
            ],
        ], $extra));
    }

    // ── the fix ──────────────────────────────────────────────────────────

    public function test_a_call_can_create_saturdays_task(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $saturday = today()->next('Saturday');

        $res = $this->logCall($patient, $fu, [
            'next_title'    => 'Call again about the X-ray',
            'next_due_date' => $saturday->toDateString(),
            'next_priority' => 'high',
            'next_category' => 'call',
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        $task = Task::where('title', 'Call again about the X-ray')->first();

        $this->assertNotNull($task, 'The call promised Saturday and produced nothing.');
        $this->assertSame($saturday->toDateString(), $task->due_date->toDateString(),
            'The follow-up did not land on the day the patient actually named.');
        $this->assertSame($patient->id, $task->patient_id);
        $this->assertSame($actor->id, (int) $task->assigned_to);
        $this->assertSame('high', $task->priority);
    }

    public function test_the_chained_task_is_human_work_not_automation(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $this->logCall($patient, $fu, [
            'next_title'    => 'Ring about the X-ray',
            'next_due_date' => today()->addDays(3)->toDateString(),
        ]);

        $task = Task::where('title', 'Ring about the X-ray')->firstOrFail();

        // 'system' is what TaskEngine tags, and visibleToReception() hides it.
        // A promise a person made must be on the board they work.
        $this->assertNotSame('system', $task->task_type);
        $this->assertTrue(
            Task::query()->visibleToReception()->whereKey($task->id)->exists(),
            'The follow-up was created hidden from the staff board — which is '
            . 'the same as not creating it.',
        );
    }

    public function test_a_call_with_no_follow_up_creates_nothing(): void
    {
        $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $before = Task::count();

        $this->logCall($patient, $fu)->assertOk();

        // Default is "Nothing". A prompt that manufactured a task on every
        // call would fill the board with busywork nobody promised.
        $this->assertSame($before, Task::count());
    }

    public function test_a_title_without_a_date_is_refused(): void
    {
        $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        // A dated promise with no date is the exact failure being fixed.
        $this->logCall($patient, $fu, ['next_title' => 'Call again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('next_due_date');
    }

    public function test_the_call_is_still_logged_even_though_a_task_followed(): void
    {
        $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $res = $this->logCall($patient, $fu, [
            'next_title'    => 'Ring Saturday',
            'next_due_date' => today()->addDays(3)->toDateString(),
        ]);

        // The chain runs AFTER the outcome is recorded, so it can never cost
        // the clinic the call itself. Whether the FollowUp row closes depends
        // on that outcome's closes_task rule — seeded clinic data, and not
        // what this test is about.
        $res->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($res->json('next_task_id'));
        $this->assertNotNull($res->json('next_action_label'));
    }

    public function test_a_follow_up_cannot_be_handed_to_another_branch(): void
    {
        $actor   = $this->admin();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        // Branch 2 must exist — assigned_to/branch_id are real foreign keys.
        \App\Models\Branch::firstOrCreate(
            ['id' => 2],
            ['name' => 'Second Branch', 'is_active' => true],
        );

        $outsider = User::factory()->create(['branch_id' => 2, 'is_active' => true]);

        $this->logCall($patient, $fu, [
            'next_title'       => 'Ring Saturday',
            'next_due_date'    => today()->addDays(3)->toDateString(),
            'next_assigned_to' => $outsider->id,
        ])->assertOk();

        $task = Task::where('title', 'Ring Saturday')->firstOrFail();

        // Work handed across branches appears on nobody's board. It falls
        // back to the person who made the call, who at least knows it exists.
        $this->assertSame($actor->id, (int) $task->assigned_to);
    }
}
