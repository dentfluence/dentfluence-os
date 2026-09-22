<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TaskListController (API v1) — the phone's task board.
 *
 *   GET /api/v1/tasks?view=today|week|overdue|open|done&mine=1
 *
 * WHY THIS EXISTS, given the 4 Sep ruling that mobile has no Tasks screen:
 * the huddle board is bound to one date and is closed by mid-morning. "What
 * is still on me at four o'clock" had nowhere to live on the phone, so it
 * lived nowhere — staff went back to the web app or forgot. This endpoint
 * answers that one question and nothing else.
 *
 * READ ONLY. Every write still goes through TaskOutcomeController, which
 * delegates to TaskOutcomeService — the same object the web board uses. The
 * rule about a rule written twice being a rule that drifts has not changed;
 * this file deliberately contains no business logic to drift.
 *
 * The scope below is a deliberate copy of TaskController::scopedQuery(). It
 * is copied rather than shared because extracting it now would mean touching
 * the web board, which was frozen yesterday. If a third caller appears, move
 * it to the model as a scope — not before.
 */
class TaskListController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'view' => 'nullable|in:today,week,overdue,open,done',
            'mine' => 'nullable|boolean',
            'q'    => 'nullable|string|max:100',
        ]);

        $view = $data['view'] ?? 'today';
        $user = $request->user();

        $query = $this->scopedQuery($user);

        // "Mine" is a filter the phone offers and the web board does not,
        // because a phone is a personal device — the question asked on it is
        // almost always "what is on ME", not "what is on the clinic".
        if ($request->boolean('mine')) {
            $query->where('assigned_to', $user->id);
        }

        if (! empty($data['q'])) {
            $term = '%' . $data['q'] . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)
                                       ->orWhere('description', 'like', $term));
        }

        $this->applyView($query, $view);

        // Oldest promise first, then urgency — the same ordering as the web
        // board, so a staff member does not learn two different lists.
        if ($view === 'done') {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderBy('due_date')
                  ->orderByRaw("FIELD(priority,'urgent','high','medium','low')");
        }

        $tasks = $query->with(['assignedTo:id,name', 'patient:id,name'])
            ->limit(100)
            ->get();

        return $this->success([
            'view'   => $view,
            'counts' => $this->counts($user, $request->boolean('mine')),
            'tasks'  => $tasks->map(fn (Task $t) => $this->row($t))->values(),
        ]);
    }

    /**
     * Branch + reception visibility + role scope.
     *
     * visibleToReception() is what keeps PRE automation record-tasks off this
     * list (CEO rule, 6 Sep: automation tasks and staff tasks are different
     * things). Without it the phone would show hundreds of rows nobody works.
     */
    private function scopedQuery(User $user): Builder
    {
        $query = Task::query()
            ->where('branch_id', $user->branch_id)
            ->visibleToReception();

        $staffRoles = [
            User::ROLE_ASSISTANT,
            User::ROLE_FRONT_DESK,
            User::ROLE_ACCOUNTS,
        ];

        if (in_array($user->role, $staffRoles, true)) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private function applyView(Builder $query, string $view): void
    {
        match ($view) {
            'overdue' => $query->open()->whereDate('due_date', '<', today()),
            'today'   => $query->open()->whereDate('due_date', '<=', today()),
            'week'    => $query->open()->whereBetween('due_date', [today(), today()->copy()->endOfWeek()]),
            'done'    => $query->where('status', 'done')->whereDate('updated_at', '>=', today()->subDays(7)),
            default   => $query->open(),
        };
    }

    /**
     * The chip numbers. Cloned from the same scope as the list so a chip can
     * never promise a count the list does not deliver.
     *
     * NOTE: 'today' is <= today, not = today. An overdue task is still today's
     * problem, and a "Today" chip that hid yesterday's unfinished work is how
     * a task quietly disappears — which is the failure this whole module was
     * rebuilt to stop.
     */
    private function counts(User $user, bool $mineOnly): array
    {
        $base = fn () => tap($this->scopedQuery($user), function ($q) use ($mineOnly, $user) {
            if ($mineOnly) {
                $q->where('assigned_to', $user->id);
            }
        });

        return [
            'today'   => (clone $base())->open()->whereDate('due_date', '<=', today())->count(),
            'week'    => (clone $base())->open()->whereBetween('due_date', [today(), today()->copy()->endOfWeek()])->count(),
            'overdue' => (clone $base())->open()->whereDate('due_date', '<', today())->count(),
            'open'    => (clone $base())->open()->count(),
        ];
    }

    /**
     * One row, shaped for a list cell. Deliberately thin — the phone opens
     * the outcome sheet for detail, which calls /tasks/{id}/outcome and gets
     * the full picture including the trail. Sending it all twice would make
     * this list slow on a clinic's 4G for data nothing renders.
     */
    private function row(Task $task): array
    {
        return [
            'id'             => $task->id,
            'title'          => $task->title,
            'category'       => $task->category,
            'category_label' => $task->categoryLabel(),
            'priority'       => $task->priority,
            'status'         => $task->status,
            'is_open'        => $task->isOpen(),
            'due_date'       => $task->due_date->toDateString(),
            'due_date_label' => $task->due_date->format('d M'),
            'days_late'      => $task->daysLate(),
            'attempt_label'  => $task->attemptLabel(),
            'assigned_to'    => $task->assignedTo?->name,
            'patient_name'   => $task->patient?->name,
        ];
    }
}
