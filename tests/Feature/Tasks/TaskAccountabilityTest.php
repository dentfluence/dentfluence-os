<?php

namespace Tests\Feature\Tasks;

use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\Task;
use App\Models\TaskOutcome;
use App\Models\User;
use App\Services\Tasks\TaskAccountabilityReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The weekly accountability read.
 *
 * Two things are being protected here. The first is arithmetic: if these
 * counts drift from the rows in task_outcomes, someone gets an unfair
 * conversation about their week. The second is the boundary behaviour —
 * a report that silently drops the last day's work, or quietly includes the
 * neighbouring clinic's, is worse than no report, because it looks right.
 */
class TaskAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $slug, int $branchId = 1): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'category' => Role::CATEGORY_STAFF, 'is_system' => true],
        );

        return User::factory()->create([
            'role' => $slug, 'role_id' => $role->id, 'branch_id' => $branchId, 'is_active' => true,
        ]);
    }

    /**
     * original_due_date is deliberately NOT in Task::$fillable — it is stamped
     * once by the reschedule path and must never arrive from a form. That guard
     * also applies to Task::create() here, which silently dropped it and made
     * the slipped assertion pass zero. forceFill is the correct way for a test
     * to set up a state the application creates through a different door.
     */
    private function task(User $actor, array $extra = []): Task
    {
        $guarded = array_intersect_key($extra, array_flip(['original_due_date']));
        $extra   = array_diff_key($extra, $guarded);

        $task = Task::create(array_merge([
            'title'       => 'Recall call',
            'category'    => 'call',
            'priority'    => 'medium',
            'task_type'   => 'human',
            'status'      => 'pending',
            'due_date'    => today(),
            'branch_id'   => $actor->branch_id,
            'created_by'  => $actor->id,
            'assigned_to' => $actor->id,
        ], $extra));

        if (! empty($guarded)) {
            $task->forceFill($guarded)->save();
        }

        return $task;
    }

    /**
     * Writes a trail row directly — the service is what is under test, not the
     * endpoints.
     *
     * created_at is set with forceFill for the same reason as above: the model
     * is append-only and does not expose the timestamp to mass assignment, so
     * a backdated row passed to create() quietly landed at now() and every
     * date-boundary assertion here was testing nothing.
     */
    private function outcome(Task $task, User $by, string $action, array $extra = []): TaskOutcome
    {
        $at    = $extra['created_at'] ?? now();
        $extra = array_diff_key($extra, ['created_at' => null]);

        $outcome = TaskOutcome::create(array_merge([
            'task_id'   => $task->id,
            'branch_id' => $task->branch_id,
            'user_id'   => $by->id,
            'action'    => $action,
        ], $extra));

        return $outcome->forceFill(['created_at' => $at])->save() ? $outcome->fresh() : $outcome;
    }

    // ── the arithmetic ───────────────────────────────────────────────────

    public function test_each_action_is_counted_under_its_own_heading(): void
    {
        $staff = $this->user('receptionist');
        $task  = $this->task($staff);

        $this->outcome($task, $staff, 'attempted');
        $this->outcome($task, $staff, 'attempted');
        $this->outcome($task, $staff, 'rescheduled');
        $this->outcome($task, $staff, 'done');

        $report = app(TaskAccountabilityReport::class)
            ->build(1, now()->subWeek(), now());

        $row = collect($report['staff'])->firstWhere('user_id', $staff->id);

        $this->assertSame(2, $row['attempted']);
        $this->assertSame(1, $row['rescheduled']);
        $this->assertSame(1, $row['done']);
        $this->assertSame(0, $row['cancelled']);

        // The four are never collapsed into one another — that distinction is
        // the whole reason the trail exists.
        $this->assertSame(1, $report['totals']['done']);
        $this->assertSame(2, $report['totals']['attempted']);
    }

    public function test_work_is_credited_to_whoever_logged_it_not_the_assignee(): void
    {
        $owner  = $this->user('admin');
        $helper = $this->user('receptionist');

        // Assigned to the owner, actually closed by the receptionist.
        $task = $this->task($owner);
        $this->outcome($task, $helper, 'done');

        $report = app(TaskAccountabilityReport::class)->build(1, now()->subWeek(), now());

        $helperRow = collect($report['staff'])->firstWhere('user_id', $helper->id);
        $ownerRow  = collect($report['staff'])->firstWhere('user_id', $owner->id);

        $this->assertSame(1, $helperRow['done']);
        $this->assertSame(0, $ownerRow['done'], 'Assignment says who owes it; the trail says who did it.');
    }

    public function test_slipped_is_measured_against_the_original_due_date(): void
    {
        $staff = $this->user('receptionist');

        // Due last Monday, rescheduled forward, finished today. due_date now
        // says it was on time; original_due_date says otherwise, and that is
        // the one that counts — otherwise lateness could be rescheduled away.
        $task = $this->task($staff, [
            'due_date'          => today()->addDays(2),
            'original_due_date' => today()->subDays(5),
        ]);

        $this->outcome($task, $staff, 'done');

        $report = app(TaskAccountabilityReport::class)->build(1, now()->subWeek(), now());
        $row    = collect($report['staff'])->firstWhere('user_id', $staff->id);

        $this->assertSame(1, $row['slipped']);
    }

    public function test_a_task_finished_on_time_does_not_count_as_slipped(): void
    {
        $staff = $this->user('receptionist');
        $task  = $this->task($staff, ['due_date' => today()->addDay()]);

        $this->outcome($task, $staff, 'done');

        $report = app(TaskAccountabilityReport::class)->build(1, now()->subWeek(), now());
        $row    = collect($report['staff'])->firstWhere('user_id', $staff->id);

        $this->assertSame(0, $row['slipped']);
    }

    public function test_open_and_overdue_are_current_state_and_ignore_the_range(): void
    {
        $staff = $this->user('receptionist');

        $this->task($staff, ['due_date' => today()->subDays(3)]);  // overdue
        $this->task($staff, ['due_date' => today()->addDays(3)]);  // open, not yet due
        $this->task($staff, ['due_date' => today(), 'status' => 'done']);

        // A range in which nothing at all was logged.
        $report = app(TaskAccountabilityReport::class)
            ->build(1, now()->subYear(), now()->subYear()->addDay());

        $row = collect($report['staff'])->firstWhere('user_id', $staff->id);

        $this->assertSame(2, $row['open'], 'Closed tasks are not open.');
        $this->assertSame(1, $row['overdue']);
        $this->assertSame(0, $row['done'], 'Nothing happened inside that range.');
    }

    // ── reasons ──────────────────────────────────────────────────────────

    public function test_blocking_reasons_come_from_attempts_only_and_are_ranked(): void
    {
        $staff = $this->user('receptionist');
        $task  = $this->task($staff);

        $this->outcome($task, $staff, 'attempted', ['outcome_label' => 'No answer']);
        $this->outcome($task, $staff, 'attempted', ['outcome_label' => 'No answer']);
        $this->outcome($task, $staff, 'attempted', ['outcome_label' => 'Switched off / busy']);
        // A completed outcome is a result, not an obstacle.
        $this->outcome($task, $staff, 'done',      ['outcome_label' => 'Spoke — handled']);

        $report  = app(TaskAccountabilityReport::class)->build(1, now()->subWeek(), now());
        $reasons = collect($report['reasons']);

        $this->assertSame('No answer', $reasons->first()['label']);
        $this->assertSame(2, $reasons->first()['count']);
        $this->assertNull($reasons->firstWhere('label', 'Spoke — handled'));
    }

    // ── boundaries and isolation ─────────────────────────────────────────

    public function test_work_logged_late_on_the_last_day_is_still_counted(): void
    {
        $staff = $this->user('receptionist');
        $task  = $this->task($staff);

        // 21:40 on the closing date. A range built from a bare date would
        // end at 00:00 and lose the entire evening's work.
        $this->outcome($task, $staff, 'done', ['created_at' => today()->setTime(21, 40)]);

        $report = app(TaskAccountabilityReport::class)->build(1, today(), today());
        $row    = collect($report['staff'])->firstWhere('user_id', $staff->id);

        $this->assertSame(1, $row['done']);
    }

    public function test_work_outside_the_range_is_excluded(): void
    {
        $staff = $this->user('receptionist');
        $task  = $this->task($staff);

        $this->outcome($task, $staff, 'done', ['created_at' => today()->subDays(30)]);

        $report = app(TaskAccountabilityReport::class)->build(1, today()->subDays(7), today());

        $this->assertSame(0, $report['totals']['done']);
    }

    public function test_another_branch_never_appears(): void
    {
        $mine     = $this->user('receptionist', 1);
        $theirs   = $this->user('receptionist', 2);
        $myTask   = $this->task($mine);
        $theirTask= $this->task($theirs);

        $this->outcome($myTask, $mine, 'done');
        $this->outcome($theirTask, $theirs, 'done');

        $report = app(TaskAccountabilityReport::class)->build(1, now()->subWeek(), now());

        $this->assertSame(1, $report['totals']['done']);
        $this->assertNull(collect($report['staff'])->firstWhere('user_id', $theirs->id));
    }

    // ── access ───────────────────────────────────────────────────────────

    public function test_the_owner_can_open_the_report(): void
    {
        $owner = $this->user('admin');

        $this->actingAs($owner)
            ->get(route('tasks.accountability'))
            ->assertOk()
            ->assertSee('Task Accountability');
    }

    public function test_somebody_with_the_board_but_not_reports_is_turned_away(): void
    {
        // Deliberately given FULL access to the task board. Without that, a
        // 403 here would prove nothing — the module:tasks gate on the route
        // group would have refused them first and the reports gate would
        // never have run.
        $module = Module::firstOrCreate(['slug' => 'tasks'], ['name' => 'Tasks']);

        $role = Role::create([
            'name'      => 'Board only',
            'slug'      => 'board_only_' . uniqid(),
            'category'  => Role::CATEGORY_STAFF,
            'is_system' => false,
        ]);

        RoleModulePermission::create([
            'role_id'   => $role->id,
            'module_id' => $module->id,
            'can_view'  => true,
            'can_edit'  => true,
        ]);

        $staff = User::factory()->create([
            'role' => 'receptionist', 'role_id' => $role->id, 'branch_id' => 1, 'is_active' => true,
        ]);

        // The board opens.
        $this->actingAs($staff)->get(route('tasks.index'))->assertOk();

        // The per-colleague breakdown does not. Asserted as JSON because
        // RespondsWithAccessDenied answers a browser request with a redirect
        // and only an expectsJson request with a 403 — both are the same
        // refusal, but only one of them is a status code.
        $this->actingAs($staff)->getJson(route('tasks.accountability'))->assertStatus(403);

        // And the same refusal through the browser door, so this test covers
        // the path a staff member would actually take.
        $this->actingAs($staff)->get(route('tasks.accountability'))->assertRedirect();
    }

    public function test_a_backwards_range_is_corrected_rather_than_returning_nothing(): void
    {
        $owner = $this->user('admin');
        $task  = $this->task($owner);
        $this->outcome($task, $owner, 'done');

        $this->actingAs($owner)
            ->get(route('tasks.accountability', [
                'from' => today()->toDateString(),
                'to'   => today()->subDays(3)->toDateString(),
            ]))
            ->assertOk();
    }

    public function test_the_report_does_not_touch_task_state(): void
    {
        $owner = $this->user('admin');
        $task  = $this->task($owner, ['due_date' => today()->subDays(4)]);
        $this->outcome($task, $owner, 'attempted');

        $before = $task->fresh()->toArray();

        $this->actingAs($owner)->get(route('tasks.accountability'))->assertOk();

        $this->assertEquals($before, $task->fresh()->toArray());
    }
}
