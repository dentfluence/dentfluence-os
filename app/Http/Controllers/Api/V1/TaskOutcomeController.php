<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Task;
use App\Services\Tasks\TaskOutcomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TaskOutcomeController (API v1) — the phone's half of Task Manager V2.
 *
 *   GET  /api/v1/tasks/{task}/outcome     what happened + the choices + history
 *   POST /api/v1/tasks/{task}/done
 *   POST /api/v1/tasks/{task}/attempt
 *   POST /api/v1/tasks/{task}/reschedule
 *   POST /api/v1/tasks/{task}/cancel
 *
 * EVERY route delegates to TaskOutcomeService — the same object the web
 * controller uses. That is deliberate and is the whole point of the file: the
 * last time task rules lived in two places (web board and mobile board), the
 * phone and the web disagreed about what "No answer" meant for months. A rule
 * written twice is a rule that will drift.
 *
 * The outcome vocabulary is served BY THE SERVER, never hard-coded in Dart.
 * A clinic editing its list in Tasks > Settings must see the change on the
 * phone without shipping an APK.
 */
class TaskOutcomeController extends ApiController
{
    public function __construct(private readonly TaskOutcomeService $outcomes) {}

    public function show(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $task->load(['assignedTo', 'patient', 'outcomes.user']);

        return $this->success([
            'task' => [
                'id'               => $task->id,
                'title'            => $task->title,
                'description'      => $task->description,
                'category'         => $task->category,
                'category_label'   => $task->categoryLabel(),
                'priority'         => $task->priority,
                'status'           => $task->status,
                'is_open'          => $task->isOpen(),
                'due_date'         => $task->due_date->toDateString(),
                'due_date_label'   => $task->due_date->format('d M Y'),
                'days_late'        => $task->daysLate(),
                'attempt_label'    => $task->attemptLabel(),
                'reschedule_count' => (int) $task->reschedule_count,
                'assigned_to'      => $task->assignedTo?->name,
                'patient_name'     => $task->patient?->name,
            ],
            // key => label, scoped to this task's category. Served, not baked in.
            'options'           => $this->outcomes->optionsFor($task),
            // Choosing one of these leaves the task OPEN.
            'non_closing_keys'  => $this->outcomes->nonClosingKeysFor($task),
            // The app must block submit until a note is typed for these.
            'requires_note_keys'=> $this->outcomes->requiresNoteKeysFor($task),
            'trail' => $task->outcomes->map(fn ($o) => [
                'action'  => $o->action,
                'summary' => $o->summary(),
                'user'    => $o->user?->name,
                'at'      => $o->created_at->format('d M, h:i A'),
            ])->values()->all(),
        ], 'Task outcome');
    }

    public function done(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'outcome_key' => ['nullable', 'string', 'max:60'],
            'note'        => ['nullable', 'string', 'max:1000'],

            // "What happens next", captured at the moment the work closes —
            // the same fields the web drawer sends, validated the same way
            // and handed to the same service. Asked here it costs one line;
            // asked tomorrow it never gets asked and the follow-up dies.
            'next'             => ['nullable', 'in:task'],
            'next_title'       => ['nullable', 'string', 'max:255'],
            'next_due_date'    => ['nullable', 'date', 'after_or_equal:today'],
            'next_assigned_to' => ['nullable', 'exists:users,id'],
            'next_category'    => ['nullable', 'in:' . implode(',', array_keys(Task::CATEGORIES))],
            'next_priority'    => ['nullable', 'in:urgent,high,medium,low'],

            // Sent AFTER the phone has booked through the appointments
            // endpoint, so by the time it arrives the appointment provably
            // exists. The task never creates one.
            'appointment_id'   => ['nullable', 'exists:appointments,id'],
        ]);

        $this->outcomes->done($task, $data['outcome_key'] ?? null, $data['note'] ?? null);
        $task->refresh();

        // Both of these no-op while the task is still open, which is the
        // point: an outcome meaning the work never happened must not leave a
        // booking attached or a follow-up spawned behind it.
        $this->outcomes->linkAppointment($task, $data['appointment_id'] ?? null);
        $chained = $task->isOpen() ? null : $this->outcomes->chainFollowUp($task, $data);

        // The service turns a "the work never happened" outcome into an
        // attempt. The phone must be told which of the two it got, or it will
        // strike the row through and the task will reappear on the next
        // refresh looking like a bug.
        return $this->success([
            'id'              => $task->id,
            'status'          => $task->status,
            'closed'          => ! $task->isOpen(),
            'attempt_label'   => $task->attemptLabel(),
            'chained_task_id' => $chained?->id,
            'appointment_id'  => $task->appointment_id,
        ], $task->isOpen()
            ? 'Logged as attempted — the task stays on the list.'
            : ($chained ? 'Done, and the follow-up task is on the list.' : 'Task completed.'));
    }

    public function attempt(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'outcome_key' => ['nullable', 'string', 'max:60'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        $this->outcomes->attempt($task, $data['outcome_key'] ?? null, $data['note'] ?? null);
        $task->refresh();

        return $this->success([
            'id'            => $task->id,
            'status'        => $task->status,
            'attempt_label' => $task->attemptLabel(),
        ], 'Attempt logged.');
    }

    public function reschedule(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'due_date'    => ['required', 'date', 'after_or_equal:today'],
            'note'        => ['required', 'string', 'max:1000'],
            'outcome_key' => ['nullable', 'string', 'max:60'],
        ]);

        $this->outcomes->reschedule($task, $data['due_date'], $data['note'], $data['outcome_key'] ?? null);
        $task->refresh();

        return $this->success([
            'id'               => $task->id,
            'due_date'         => $task->due_date->toDateString(),
            'reschedule_count' => (int) $task->reschedule_count,
            'days_late'        => $task->daysLate(),
        ], 'Task rescheduled.');
    }

    public function cancel(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->outcomes->cancel($task, $data['reason']);

        return $this->success(['id' => $task->id, 'status' => 'cancelled'], 'Task cancelled.');
    }

    private function guardBranch(Request $request, Task $task): ?JsonResponse
    {
        if ((int) $task->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Task not found.', [], 404);
        }

        return null;
    }
}
