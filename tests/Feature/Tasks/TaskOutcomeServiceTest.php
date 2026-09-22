<?php

namespace Tests\Feature\Tasks;

use App\Models\ActionOptionList;
use App\Models\Task;
use App\Models\TaskOutcome;
use App\Models\User;
use App\Services\Tasks\TaskOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task Manager V2 — the rules that decide what an outcome MEANS.
 *
 * These are the assertions worth having. The UI can be rebuilt in an
 * afternoon; the three rules locked here are the ones that, when they break,
 * put a lie in the database:
 *
 *  1. A task that was worked but not finished STAYS pending. It is not a new
 *     status — see the 2026_09_22_100001 migration for why. Forty files read
 *     `where('status','pending')`, and a task with its own "attempted" status
 *     would silently vanish off every one of those boards.
 *
 *  2. An outcome meaning the work never happened cannot close the task, even
 *     when the caller asked for done(). "No answer" marked as completed is
 *     exactly the bug the 12 Sep migration removed from the PRE board.
 *
 *  3. Rescheduling never moves the overdue clock. original_due_date is stamped
 *     once and never again, so a backlog cannot be cleared by pushing dates.
 *
 * @see \App\Services\Tasks\TaskOutcomeService
 */
class TaskOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create([
            'role'      => 'admin',
            'branch_id' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function task(User $actor, string $category = 'call', array $extra = []): Task
    {
        return Task::create(array_merge([
            'title'      => 'Ring Mrs Kulkarni',
            'category'   => $category,
            'task_type'  => 'human',
            'status'     => 'pending',
            'due_date'   => today(),
            'branch_id'  => 1,
            'created_by' => $actor->id,
        ], $extra));
    }

    private function service(): TaskOutcomeService
    {
        return app(TaskOutcomeService::class);
    }

    // ── attempts ─────────────────────────────────────────────────────────

    public function test_an_attempt_leaves_the_task_pending_and_counts_it(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor);

        $this->service()->attempt($task, 'no_answer', 'rang twice');
        $task->refresh();

        // The status column must NOT move. Every board that reads
        // where('status','pending') has to keep seeing this task.
        $this->assertSame('pending', $task->status);
        $this->assertTrue($task->isOpen());
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame('no_answer', $task->last_outcome_key);
        $this->assertSame('Attempted', $task->attemptLabel());

        $this->service()->attempt($task, 'no_answer', 'still nothing');
        $task->refresh();

        $this->assertSame(2, (int) $task->attempt_count);
        $this->assertSame('Attempted x2', $task->attemptLabel());
        $this->assertSame(2, TaskOutcome::where('task_id', $task->id)->count());
    }

    // ── the non-contact rule ─────────────────────────────────────────────

    public function test_done_with_a_non_contact_outcome_is_recorded_as_an_attempt(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'call');

        // The caller explicitly asked to COMPLETE the task.
        $this->service()->done($task, 'no_answer', 'nobody picked up');
        $task->refresh();

        // It must refuse: nobody was spoken to, so nothing was resolved.
        $this->assertSame('pending', $task->status);
        $this->assertTrue($task->isOpen());
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame(
            'attempted',
            TaskOutcome::where('task_id', $task->id)->value('action'),
            'a call that never connected was logged as a completion',
        );
    }

    public function test_the_clinic_cannot_configure_no_answer_into_closing_a_call(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'call');

        // A clinic ticks "Closes task" on No answer in Tasks > Settings.
        ActionOptionList::where('option_type', 'task_outcome')
            ->where('action_category', 'call')
            ->where('key', 'no_answer')
            ->update(['closes_task' => true]);

        $this->service()->done($task, 'no_answer');
        $task->refresh();

        // Labels and note rules are theirs to edit. This one is not.
        $this->assertSame('pending', $task->status);
    }

    public function test_a_clinic_configured_non_closing_outcome_keeps_a_work_task_open(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'lab', ['title' => 'Chase the crown']);

        // Seeded with closes_task = false.
        $this->service()->done($task, 'awaiting_lab', 'lab says Thursday');
        $task->refresh();

        $this->assertSame('pending', $task->status);
        $this->assertSame(1, (int) $task->attempt_count);
    }

    public function test_a_closing_outcome_completes_the_task(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'lab');

        $this->service()->done($task, 'completed', 'crown fitted');
        $task->refresh();

        $this->assertSame('done', $task->status);
        $this->assertFalse($task->isOpen());
        $this->assertNotNull($task->done_at);
        $this->assertSame('done', TaskOutcome::where('task_id', $task->id)->value('action'));
    }

    // ── reschedule ───────────────────────────────────────────────────────

    public function test_reschedule_stamps_the_original_due_date_once_and_never_again(): void
    {
        $actor = $this->actor();
        $first = today()->subDays(5);
        $task  = $this->task($actor, 'admin', ['due_date' => $first]);

        $this->service()->reschedule($task, today()->addDays(2)->toDateString(), 'vendor unreachable');
        $task->refresh();

        $this->assertSame($first->toDateString(), $task->original_due_date->toDateString());
        $this->assertSame(1, (int) $task->reschedule_count);

        $this->service()->reschedule($task, today()->addDays(9)->toDateString(), 'still unreachable');
        $task->refresh();

        // The second move must NOT re-stamp it — that is what would launder
        // the backlog.
        $this->assertSame($first->toDateString(), $task->original_due_date->toDateString());
        $this->assertSame(2, (int) $task->reschedule_count);
        $this->assertTrue($task->wasRescheduled());
    }

    public function test_overdue_is_measured_from_the_first_promise_not_the_new_date(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'admin', ['due_date' => today()->subDays(10)]);

        $this->assertSame(10, $task->daysLate());

        // Push it a fortnight into the future.
        $this->service()->reschedule($task, today()->addDays(14)->toDateString(), 'parts on order');
        $task->refresh();

        // Still ten days late. Moving the date does not erase the delay.
        $this->assertSame(10, $task->daysLate());
        $this->assertTrue($task->isOverdue());
    }

    public function test_a_closed_task_is_never_reported_as_late(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'admin', ['due_date' => today()->subDays(30)]);

        $this->service()->done($task, 'completed');
        $task->refresh();

        $this->assertSame(0, $task->daysLate());
    }

    // ── cancel / reopen ──────────────────────────────────────────────────

    public function test_cancel_closes_the_task_without_claiming_the_work_was_done(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'admin');

        $this->service()->cancel($task, 'patient moved to Pune');
        $task->refresh();

        $this->assertSame('cancelled', $task->status);
        $this->assertFalse($task->isOpen());
        // The distinction is the whole point: done would be a claim that
        // somebody did this work.
        $this->assertNull($task->done_at);
        $this->assertSame('patient moved to Pune', $task->cancel_reason);
    }

    public function test_reopen_puts_a_closed_task_back_on_the_list_and_keeps_its_counters(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'call');

        $this->service()->attempt($task, 'no_answer');
        $this->service()->done($task->refresh(), 'spoke_done');
        $this->service()->reopen($task->refresh(), 'wrong patient closed');
        $task->refresh();

        $this->assertSame('pending', $task->status);
        $this->assertNull($task->done_at);
        // History is not rewritten by reopening — the attempt still happened.
        $this->assertSame(1, (int) $task->attempt_count);
        $this->assertSame(3, TaskOutcome::where('task_id', $task->id)->count());
    }

    // ── the vocabulary ───────────────────────────────────────────────────

    public function test_each_category_gets_its_own_short_list_not_one_pooled_list(): void
    {
        $actor = $this->actor();

        $lab  = $this->service()->optionsFor($this->task($actor, 'lab'));
        $call = $this->service()->optionsFor($this->task($actor, 'call'));

        $this->assertArrayHasKey('awaiting_lab', $lab);
        $this->assertArrayNotHasKey('awaiting_lab', $call);
        $this->assertArrayHasKey('no_answer', $call);
        $this->assertArrayNotHasKey('no_answer', $lab);

        // Short enough that a person under time pressure reads them all. The
        // first cut of this drawer offered forty.
        $this->assertLessThanOrEqual(10, count($lab));
        $this->assertLessThanOrEqual(10, count($call));
    }

    public function test_renaming_an_outcome_in_settings_does_not_rewrite_history(): void
    {
        $actor = $this->actor();
        $task  = $this->task($actor, 'lab');

        $this->service()->attempt($task, 'awaiting_lab', 'called the lab');

        ActionOptionList::where('option_type', 'task_outcome')
            ->where('action_category', 'lab')
            ->where('key', 'awaiting_lab')
            ->update(['label' => 'Pending at lab (renamed)']);

        // The trail must still show what the staff member actually saw.
        $this->assertSame(
            'Waiting on lab',
            TaskOutcome::where('task_id', $task->id)->value('outcome_label'),
        );
    }
}
