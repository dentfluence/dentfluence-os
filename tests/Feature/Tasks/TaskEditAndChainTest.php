<?php

namespace Tests\Feature\Tasks;

use App\Models\AppNotification;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing a task, chaining the next one, and the filters added on 22 Sep.
 *
 * The assertions that matter here are the ones about what editing CANNOT do.
 * A form that quietly lets someone move a due date walks around the reschedule
 * guards — the required reason, the stamped original_due_date, the counter —
 * and with them goes the only honest number on the screen.
 */
class TaskEditAndChainTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::ADMIN],
            ['name' => 'Clinic Owner', 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        $user = User::factory()->create([
            'role' => 'admin', 'role_id' => $role->id, 'branch_id' => 1, 'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function task(User $actor, array $extra = []): Task
    {
        return Task::create(array_merge([
            'title'       => 'Autoclave service',
            'category'    => 'maintenance',
            // Set explicitly rather than left to the column default: the DB
            // fills it, but the in-memory model does not know that, so
            // $task->priority reads null right after create() and a test that
            // echoes it back at the endpoint fails validation for a reason
            // that has nothing to do with the code under test.
            'priority'    => 'medium',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => 1,
            'created_by'  => $actor->id,
            'assigned_to' => $actor->id,
        ], $extra));
    }

    // ── edit ─────────────────────────────────────────────────────────────

    public function test_edit_changes_the_details_it_is_meant_to(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->putJson(route('tasks.update', $task), [
            'title'       => 'Autoclave service — annual',
            'description' => 'Vendor: Sterimax',
            'priority'    => 'urgent',
            'assigned_to' => $actor->id,
            'category'    => 'maintenance',
        ])->assertOk();

        $task->refresh();

        $this->assertSame('Autoclave service — annual', $task->title);
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('Vendor: Sterimax', $task->description);
    }

    public function test_edit_cannot_move_the_due_date_even_when_one_is_posted(): void
    {
        $actor   = $this->admin();
        $dueDate = today()->subDays(6);
        $task    = $this->task($actor, ['due_date' => $dueDate]);

        // Someone posts a due_date at the endpoint anyway — by hand, or from a
        // future form that grew a field it should not have.
        $this->putJson(route('tasks.update', $task), [
            'title'       => 'Autoclave service',
            'priority'    => 'medium',
            'assigned_to' => $actor->id,
            'category'    => 'maintenance',
            'due_date'    => today()->addDays(30)->toDateString(),
            'status'      => 'done',
        ])->assertOk();

        $task->refresh();

        // Both ignored. Moving a date is a Reschedule; closing is Done/Cancel.
        $this->assertSame($dueDate->toDateString(), $task->due_date->toDateString());
        $this->assertSame('pending', $task->status);
        $this->assertSame(6, $task->daysLate(), 'editing laundered the overdue count');
        $this->assertSame(0, (int) $task->reschedule_count);
    }

    public function test_handing_a_task_over_tells_the_new_owner(): void
    {
        $this->seed(\Database\Seeders\NotificationRuleSeeder::class);

        $actor = $this->admin();

        $assistantRole = Role::firstOrCreate(
            ['slug' => 'assistant'],
            ['name' => 'Assistant', 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );
        $newOwner = User::factory()->create([
            'role' => 'assistant', 'role_id' => $assistantRole->id,
            'branch_id' => 1, 'is_active' => true,
        ]);

        $task = $this->task($actor);

        $this->putJson(route('tasks.update', $task), [
            'title'       => $task->title,
            'priority'    => $task->priority,
            'assigned_to' => $newOwner->id,
            'category'    => $task->category,
        ])->assertOk();

        $this->assertSame($newOwner->id, $task->refresh()->assigned_to);
        $this->assertNotNull(
            AppNotification::where('user_id', $newOwner->id)->first(),
            'the person who now owns the work was never told',
        );
    }

    public function test_a_rename_does_not_notify_anyone(): void
    {
        $this->seed(\Database\Seeders\NotificationRuleSeeder::class);

        $actor = $this->admin();
        $task  = $this->task($actor);

        AppNotification::query()->delete();

        $this->putJson(route('tasks.update', $task), [
            'title'       => 'Autoclave service (clearer title)',
            'priority'    => 'medium',
            'assigned_to' => $actor->id,
            'category'    => 'maintenance',
        ])->assertOk();

        // A clearer title is already visible on the board. Buzzing a phone for
        // it is how staff learn to ignore the ones that matter.
        $this->assertSame(0, AppNotification::count());
    }

    // ── chaining the next piece of work ──────────────────────────────────

    public function test_closing_a_task_can_create_the_follow_up_in_the_same_step(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor, ['category' => 'call', 'title' => 'Ring Mrs Patil']);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key'   => 'spoke_done',
            'note'          => 'wants to decide after Diwali',
            'next'          => 'task',
            'next_title'    => 'Call again about the crown',
            'next_due_date' => today()->addDays(10)->toDateString(),
        ])->assertOk();

        $chained = Task::where('title', 'Call again about the crown')->first();

        $this->assertNotNull($chained, 'the follow-up was never created');
        $this->assertSame($actor->id, $chained->assigned_to, 'the next step lost its owner');
        $this->assertSame($task->patient_id, $chained->patient_id);
        $this->assertSame(today()->addDays(10)->toDateString(), $chained->due_date->toDateString());

        // It starts clean. Carrying the closed task's attempts or delay forward
        // would make a brand-new job look already late.
        $this->assertSame(0, (int) $chained->attempt_count);
        $this->assertSame(0, (int) $chained->reschedule_count);
        $this->assertNull($chained->original_due_date);
        $this->assertSame('pending', $chained->status);
    }

    public function test_no_follow_up_is_created_when_nothing_was_asked_for(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor);

        $this->postJson(route('tasks.done', $task), ['outcome_key' => 'completed'])->assertOk();

        // Default is Nothing. A prompt that manufactures busywork is worse than
        // no prompt at all.
        $this->assertSame(1, Task::count());
    }

    public function test_choosing_book_an_appointment_does_not_create_one_server_side(): void
    {
        $actor = $this->admin();
        $task  = $this->task($actor, ['category' => 'call']);

        $this->postJson(route('tasks.done', $task), [
            'outcome_key' => 'spoke_done',
            'next'        => 'appointment',
        ])->assertOk();

        // Booking belongs to AppointmentController@store, which the drawer calls
        // BEFORE this endpoint — so a refused slot leaves the task open instead
        // of closing work that was never scheduled. This endpoint must not
        // quietly book anything of its own.
        $this->assertSame(0, \App\Models\Appointment::count());
        $this->assertSame(1, Task::count());
    }

    // ── recurring service ────────────────────────────────────────────────

    public function test_a_recurring_service_books_its_own_next_visit_on_close(): void
    {
        $actor = $this->admin();
        $due   = today()->subDays(10);

        $task = $this->task($actor, [
            'title'               => 'AC servicing',
            'maintenance_type'    => 'ac_service',
            'due_date'            => $due,
            'is_recurring'        => true,
            'recurrence_interval' => 3,
            'recurrence_unit'     => 'months',
        ]);

        $res = $this->postJson(route('tasks.done', $task), ['outcome_key' => 'completed']);
        $res->assertOk();

        $next = Task::where('title', 'AC servicing')->where('id', '!=', $task->id)->first();

        $this->assertNotNull($next, 'the next service was never scheduled');

        // Measured from the DATE IT WAS DUE, not from today. Ten days late on
        // one service must not push the whole schedule ten days out.
        $this->assertSame(
            $due->copy()->addMonths(3)->toDateString(),
            $next->due_date->toDateString(),
        );
        $this->assertTrue((bool) $next->is_recurring);
        $this->assertNotNull($res->json('next_due_date'), 'the person was not told when the next one is');
    }

    // ── the filters added the same day ───────────────────────────────────

    public function test_picking_a_date_shows_that_day_including_finished_work(): void
    {
        $actor = $this->admin();
        $day   = today()->addDays(4);

        $open = $this->task($actor, ['title' => 'Pest control visit', 'due_date' => $day]);
        $this->task($actor, ['title' => 'Another day entirely', 'due_date' => $day->copy()->addDay()]);

        $closed = $this->task($actor, ['title' => 'Already handled', 'due_date' => $day]);
        app(TaskOutcomeService::class)->done($closed, 'completed');

        $res = $this->get(route('tasks.index', ['date' => $day->toDateString()]));

        $res->assertOk();
        $res->assertSee('Pest control visit');
        // On a chosen day you want the whole picture, not only what is left.
        $res->assertSee('Already handled');
        $res->assertDontSee('Another day entirely');
    }

    public function test_unassigned_work_can_be_found(): void
    {
        $actor = $this->admin();

        $this->task($actor, ['title' => 'Nobody owns this', 'assigned_to' => null]);
        $this->task($actor, ['title' => 'This one has an owner']);

        $res = $this->get(route('tasks.index', ['assigned_to' => 'none']));

        $res->assertOk();
        $res->assertSee('Nobody owns this');
        $res->assertDontSee('This one has an owner');
        $this->assertSame(1, $res->viewData('counts')['unassigned']);
    }

    // ── what was taken away ──────────────────────────────────────────────

    public function test_the_escalate_route_is_gone(): void
    {
        // It wrote is_escalated, a column that never existed, so every call
        // 500'd — and nothing ever called it. Removed rather than revived,
        // because "escalate" was never defined as a behaviour.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('tasks.escalate'),
            'the escalate route is back; it 500s on every call',
        );
    }
}
