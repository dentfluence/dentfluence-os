<?php

namespace App\Services\Tasks;

use App\Models\ActionOptionList;
use App\Models\Task;
use App\Models\TaskOutcome;
use App\Services\Relationship\TodayActionOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Task Manager V2 — the ONE place a task changes state.
 *
 * Before this, TaskController::markDone() flipped status to 'done' inline and
 * the reason was lost. Every state change now goes through here so that:
 *   - an outcome row is always written (the trail is never optional),
 *   - the counters on tasks stay consistent with that trail,
 *   - the "did this work actually happen" rule lives in one place.
 *
 * Nothing here decides a due date or a status from thin air — the caller
 * supplies the outcome, this decides what that outcome MEANS.
 */
class TaskOutcomeService
{
    /**
     * Record an attempt: work happened, the task is NOT finished.
     *
     * The task stays `pending` on purpose — see the Slice 1a migration note.
     * Every board that queries open work keeps seeing it, which is the whole
     * point: "No answer" must leave the task on the list.
     */
    public function attempt(Task $task, ?string $outcomeKey, ?string $note = null): TaskOutcome
    {
        return DB::transaction(function () use ($task, $outcomeKey, $note) {
            $outcome = $this->log($task, 'attempted', $outcomeKey, $note);

            $task->forceFill([
                'attempt_count'    => (int) $task->attempt_count + 1,
                'last_outcome_key' => $outcomeKey,
                'last_outcome_at'  => now(),
            ])->save();

            return $outcome;
        });
    }

    /**
     * Move the task to a later date.
     *
     * original_due_date is stamped on the FIRST reschedule only and never
     * moves again, so overdue keeps being measured from when the work was
     * first promised. Without that, a backlog can be cleared by pushing dates
     * and the owner's red number becomes a lie.
     */
    public function reschedule(Task $task, string $newDueDate, ?string $note = null, ?string $outcomeKey = null): TaskOutcome
    {
        return DB::transaction(function () use ($task, $newDueDate, $note, $outcomeKey) {
            $before = $task->due_date;

            $outcome = $this->log($task, 'rescheduled', $outcomeKey, $note, [
                'due_date_before' => $before,
                'due_date_after'  => $newDueDate,
            ]);

            $task->forceFill([
                // Stamp once. ?? keeps any existing value untouched.
                'original_due_date' => $task->original_due_date ?? $before,
                'due_date'          => $newDueDate,
                'reschedule_count'  => (int) $task->reschedule_count + 1,
                'last_outcome_key'  => $outcomeKey ?? $task->last_outcome_key,
                'last_outcome_at'   => now(),
            ])->save();

            return $outcome;
        });
    }

    /**
     * Finish the task.
     *
     * Guard: an outcome that means the work did not actually happen cannot
     * close it. "No answer" is an attempt, not a completion — the same rule
     * the 12 Sep migration enforced on the PRE side. A caller that sends one
     * gets an attempt back instead of a silent lie in the database.
     */
    public function done(Task $task, ?string $outcomeKey = null, ?string $note = null): TaskOutcome
    {
        if ($task->outcomeLeavesOpen($outcomeKey)) {
            return $this->attempt($task, $outcomeKey, $note);
        }

        return DB::transaction(function () use ($task, $outcomeKey, $note) {
            $outcome = $this->log($task, 'done', $outcomeKey, $note);

            $task->forceFill([
                'status'           => 'done',
                'done_at'          => now(),
                'last_outcome_key' => $outcomeKey ?? $task->last_outcome_key,
                'last_outcome_at'  => now(),
            ])->save();

            return $outcome;
        });
    }

    /**
     * Close the task WITHOUT claiming the work was done.
     *
     * This is the honest exit for "not required any more" / "patient
     * cancelled". Marking such a task `done` would report work nobody did —
     * the same mistake as closing the 137 orphan reminder rows as done on
     * 7 Sep. A reason is required.
     */
    public function cancel(Task $task, string $reason, ?string $outcomeKey = null): TaskOutcome
    {
        return DB::transaction(function () use ($task, $reason, $outcomeKey) {
            $outcome = $this->log($task, 'cancelled', $outcomeKey, $reason);

            $task->forceFill([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $outcome;
        });
    }

    /** Put a closed task back on the list. Counters are deliberately kept. */
    /**
     * The follow-up task created from the one just closed.
     *
     * Lives here, not in a controller, because BOTH the web drawer and the
     * phone's outcome sheet create it. The last time a task rule lived in two
     * controllers the two disagreed for months about what "No answer" meant.
     *
     * The patient and the branch are carried forward and are NOT negotiable:
     * the follow-up is about the same case by definition, and a task that
     * crosses branches has no owner anyone can find. The owner, type and
     * priority ARE negotiable and come from the form, falling back to the
     * closing task's values.
     *
     * Returns null when no follow-up was asked for — callers treat that as
     * the ordinary case, not a failure.
     */
    public function chainFollowUp(Task $task, array $data): ?Task
    {
        if (($data['next'] ?? null) !== 'task' || empty($data['next_title'])) {
            return null;
        }

        $assignee = $data['next_assigned_to'] ?? $task->assigned_to;

        // A follow-up cannot be handed to someone in another branch; that
        // would create work nobody in either branch sees on their board.
        if ($assignee && (int) $assignee !== (int) $task->assigned_to) {
            $ok = \App\Models\User::where('id', $assignee)
                ->where('branch_id', $task->branch_id)
                ->exists();
            if (! $ok) {
                $assignee = $task->assigned_to;
            }
        }

        $chained = Task::create([
            'title'       => $data['next_title'],
            'description' => $data['note'] ?? null,
            'assigned_to' => $assignee,
            'created_by'  => Auth::id(),
            'branch_id'   => $task->branch_id,
            'patient_id'  => $task->patient_id,
            'due_date'    => $data['next_due_date'] ?? today()->addDay(),
            'priority'    => $data['next_priority'] ?? $task->priority,
            'category'    => $data['next_category'] ?? $task->category,
            'status'      => 'pending',
        ]);

        // Reassignment is a notification event everywhere else in the module;
        // a chained task handed to someone else is no different.
        if ($chained->assigned_to && (int) $chained->assigned_to !== (int) Auth::id()) {
            try {
                app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.assigned', [
                    'title'        => 'Follow-up task assigned to you',
                    'message'      => "\"{$chained->title}\" — due {$chained->due_date->format('d M Y')}.",
                    'source'       => $chained,
                    'branch_id'    => $chained->branch_id,
                    'owner'        => $chained->assigned_to,
                    'action_url'   => route('tasks.index'),
                    'action_label' => 'View Tasks',
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Chained task notify failed: ' . $e->getMessage());
            }
        }

        return $chained;
    }

    /**
     * THE SAME FOLLOW-UP, CHAINED FROM A CALL INSTEAD OF A TASK.
     *
     * ── WHY THIS EXISTS (CEO, 23 Sep 2026) ──────────────────────────────────
     * "Samiksha ne ek call kela patient la, tyane sangitla me Saturday la
     * karto X-ray… pan Saturday cha kay? Call response madhye note kela pan
     * pudhcha task ready nahi jhala."
     *
     * Logging a call could only push follow_up_date by a fixed +2 days on
     * queue-backed rows. Not Saturday, not owned by anyone, and not on any
     * list a person works down. The outcome was recorded; the WORK was not.
     *
     * ── WHY IT IS A HUMAN TASK ──────────────────────────────────────────────
     * TaskEngine::autoCreate() tags everything 'system' and
     * Task::scopeVisibleToReception() hides those — the 6 Sep rule that keeps
     * PRE automation out of the staff board. A follow-up a person PROMISED on
     * a call is not automation: it is that person's word, and it belongs where
     * it can be seen, chased and counted. So this does not go through
     * TaskEngine, and the task is left at its default 'human' type.
     *
     * ── WHY NOT MERGE THE BOARDS INSTEAD ────────────────────────────────────
     * Today's Actions rebuilds itself every morning from patient data and is
     * never "finished"; Tasks are finite promises worked to zero. Merging
     * makes the finite list infinite, and a list nobody can finish is a list
     * nobody starts. The hand-off is the fix, not the merge.
     *
     * @param  array<string, mixed>  $data  next_title, next_due_date,
     *                                      next_assigned_to, next_category,
     *                                      next_priority, notes
     */
    public function chainFromCall(array $data, ?int $patientId, int $branchId): ?Task
    {
        if (empty($data['next_title']) || empty($data['next_due_date'])) {
            return null;
        }

        $assignee = $data['next_assigned_to'] ?? Auth::id();

        // Same branch guard as chainFollowUp(): work handed across branches
        // appears on nobody's board. Falls back to the person who made the
        // call, who at least knows it exists.
        if ($assignee) {
            $ok = \App\Models\User::where('id', $assignee)
                ->where('branch_id', $branchId)
                ->exists();
            if (! $ok) {
                $assignee = Auth::id();
            }
        }

        $chained = Task::create([
            'title'       => $data['next_title'],
            'description' => $data['notes'] ?? null,
            'assigned_to' => $assignee,
            'created_by'  => Auth::id(),
            'branch_id'   => $branchId,
            'patient_id'  => $patientId,
            'due_date'    => $data['next_due_date'],
            'priority'    => $data['next_priority'] ?? 'medium',
            'category'    => $data['next_category'] ?? 'call',
            'status'      => 'pending',
        ]);

        if ($chained->assigned_to && (int) $chained->assigned_to !== (int) Auth::id()) {
            try {
                app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.assigned', [
                    'title'        => 'Follow-up task assigned to you',
                    'message'      => "\"{$chained->title}\" — due {$chained->due_date->format('d M Y')}.",
                    'source'       => $chained,
                    'branch_id'    => $chained->branch_id,
                    'owner'        => $chained->assigned_to,
                    'action_url'   => route('tasks.index'),
                    'action_label' => 'View Tasks',
                ]);
            } catch (\Throwable $e) {
                \Log::warning('Call follow-up notify failed: ' . $e->getMessage());
            }
        }

        return $chained;
    }

    /**
     * Stamp the appointment a closed task produced.
     *
     * Both guards matter: an appointment from another branch would leak a
     * patient across clinics, and one for a different patient would make the
     * conversion figure a lie. Either way the link is simply not made — the
     * close still stands, because the work did happen.
     *
     * Returns true when the link was made.
     */
    public function linkAppointment(Task $task, ?int $appointmentId): bool
    {
        if (! $appointmentId || $task->isOpen()) {
            return false;
        }

        $appointment = \App\Models\Appointment::find($appointmentId);

        if (! $appointment
            || (int) $appointment->branch_id !== (int) $task->branch_id
            || ($task->patient_id && (int) $appointment->patient_id !== (int) $task->patient_id)
        ) {
            \Log::warning('Task ' . $task->id . ' closed with an appointment link that did not match branch/patient; link skipped.');
            return false;
        }

        $task->appointment_id = $appointment->id;
        $task->save();

        return true;
    }

    public function reopen(Task $task, ?string $note = null): TaskOutcome
    {
        return DB::transaction(function () use ($task, $note) {
            $outcome = $this->log($task, 'reopened', null, $note);

            $task->forceFill([
                'status'        => 'pending',
                'done_at'       => null,
                'cancelled_at'  => null,
                'cancel_reason' => null,
            ])->save();

            return $outcome;
        });
    }

    // ── Outcome vocabulary ────────────────────────────────────────────────

    /**
     * The outcome list this task's drawer should show: [key => label].
     *
     * Read from action_option_lists (option_type = 'task_outcome') scoped to
     * the TASK'S OWN CATEGORY, so a lab task offers lab words and a call task
     * offers call words — six or so each, not the forty that came from
     * flattening every PRE category together.
     *
     * Falls back to Task::WORK_OUTCOMES only if a clinic has deactivated every
     * row for a category; an empty dropdown would be worse than a generic one.
     */
    public function optionsFor(Task $task): array
    {
        $rows = ActionOptionList::query()->taskOutcomesFor($task->category)->get();

        return $rows->isNotEmpty()
            ? ActionOptionList::labelMap($rows)
            : Task::WORK_OUTCOMES;
    }

    /**
     * Keys that must NOT close the task.
     *
     * Two sources, and the order matters:
     *  1. whatever the clinic has set closes_task = false on, and
     *  2. for communication tasks, the non-contact keys — which override the
     *     stored value entirely. A clinic may rename "No answer" but may not
     *     make it complete a call that never connected. Same rule, same
     *     reason, as migration 2026_09_12_000001 on the PRE side.
     */
    public function nonClosingKeysFor(Task $task): array
    {
        $keys = ActionOptionList::query()
            ->taskOutcomesFor($task->category)
            ->where('closes_task', false)
            ->pluck('key')
            ->all();

        if ($task->isCommTask()) {
            $keys = array_merge($keys, TodayActionOptions::nonContactKeys());
        }

        return array_values(array_unique($keys));
    }

    /** Outcome keys the clinic marked "a note is required before saving". */
    public function requiresNoteKeysFor(Task $task): array
    {
        return ActionOptionList::query()
            ->taskOutcomesFor($task->category)
            ->where('requires_notes', true)
            ->pluck('key')
            ->all();
    }

    // ── internals ─────────────────────────────────────────────────────────

    private function log(Task $task, string $action, ?string $outcomeKey, ?string $note, array $extra = []): TaskOutcome
    {
        return TaskOutcome::create([
            'task_id'       => $task->id,
            'branch_id'     => $task->branch_id,
            'user_id'       => Auth::id(),
            'action'        => $action,
            'outcome_key'   => $outcomeKey,
            // Snapshot the label so a later rename in Settings cannot rewrite
            // history. See the task_outcomes migration.
            'outcome_label' => $outcomeKey ? ($this->optionsFor($task)[$outcomeKey] ?? null) : null,
            'note'          => $note,
            ...$extra,
        ]);
    }
}
