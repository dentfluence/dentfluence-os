<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE TASKS BOARD, AS DATA.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 * The board is rendered in two places now — its own page at /tasks and inline
 * on My Day — and the two must never disagree about what a person's open work
 * is. Two copies of this query would drift within a month: someone fixes the
 * visibility rule on one screen, reception sees a different list on the other,
 * and nobody can say which is right.
 *
 * So the query lives here once, and both screens ask it the same question.
 * TaskController::index() no longer owns the scope; it passes the request
 * through and renders what comes back.
 *
 * ── WHAT IT IS NOT ──────────────────────────────────────────────────────────
 * It does not write, and it does not decide what a task means. Every rule that
 * governs a task — who may see it, when it is late, what closing it does —
 * still lives on the model and in TaskController. This is a read.
 */
class TaskBoardData
{
    /**
     * Branch + reception-visibility + role scope, with no view filter applied.
     * Every count and every list starts here, so they can never drift apart.
     *
     * NOTE: Api\V1\TaskListController keeps its own copy on purpose — it
     * answers a different shape (a phone list, no pagination, no counts) and
     * coupling the two would make a change to either risky for both.
     */
    public function scope(User $user): Builder
    {
        $query = Task::query()
            ->where('branch_id', $user->branch_id)
            ->visibleToReception(); // hides Automation record-tasks (CEO rule, 6 Sep)

        // Staff-level roles see only their own work; admin / doctors see all.
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

    /**
     * @param  array<string, mixed>  $input  Request-shaped input: view, q,
     *                                       assigned_to, category, priority,
     *                                       date, source, status, per_page.
     * @return array<string, mixed>          Exactly the variables the board
     *                                       partial reads.
     */
    public function build(array $input, User $user): array
    {
        $base = $this->scope($user);

        // Counts are always for the WHOLE scope, never the current view —
        // otherwise clicking "Overdue" would rewrite every other number.
        $counts = [
            'open'       => (clone $base)->open()->count(),
            'overdue'    => (clone $base)->open()->whereDate('due_date', '<', today())->count(),
            'today'      => (clone $base)->open()->whereDate('due_date', today())->count(),
            'week'       => (clone $base)->open()
                                ->whereBetween('due_date', [today(), today()->copy()->endOfWeek()])
                                ->count(),
            'done'       => (clone $base)->where('status', 'done')->count(),
            'unassigned' => (clone $base)->open()->whereNull('assigned_to')->count(),
            'cancelled'  => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $filters = [
            'view'        => $input['view'] ?? 'open',
            'q'           => trim((string) ($input['q'] ?? '')),
            'assigned_to' => $input['assigned_to'] ?? null,
            'category'    => $input['category'] ?? null,
            'priority'    => $input['priority'] ?? null,
            // "What is on for the 4th?" — a single day, closed work included,
            // because on a chosen day you want the whole picture, not just
            // what is still outstanding.
            'date'        => $input['date'] ?? null,
        ];

        $query = $base->with(['assignedTo', 'patient', 'protocol.materials']);

        // A chosen date overrides the view entirely — the two answer different
        // questions and stacking them would show an empty screen and no reason.
        if ($filters['date']) {
            $query->whereDate('due_date', $filters['date']);
        } else {
            match ($filters['view']) {
                'overdue'   => $query->open()->whereDate('due_date', '<', today()),
                'today'     => $query->open()->whereDate('due_date', today()),
                'week'      => $query->open()->whereBetween('due_date', [today(), today()->copy()->endOfWeek()]),
                'done'      => $query->where('status', 'done'),
                'cancelled' => $query->where('status', 'cancelled'),
                'all'       => null,
                // My Day's window: still open, and due today OR earlier.
                // Overdue work is today's problem — a list that hid it would
                // let a week-old promise sit unseen behind a clean screen.
                // Only My Day passes this; the board's own chips never do.
                'due_now'   => $query->open()->whereDate('due_date', '<=', today()),
                default     => $query->open(),
            };
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        // 'none' is not a user id — it is the question "what does nobody own?".
        // A task with no owner is a task nobody does, and until now there was
        // no way to find one.
        if ($filters['assigned_to'] === 'none') {
            $query->whereNull('assigned_to');
        } elseif ($filters['assigned_to']) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if ($filters['category']) { $query->where('category', $filters['category']); }
        if ($filters['priority']) { $query->where('priority', $filters['priority']); }

        // Back-compat: HuddleController links here as ?status=escalated.
        // Keep that link working rather than silently landing on the open list.
        if (($input['status'] ?? null) === 'escalated') {
            $query->where('status', 'escalated');
        }

        // Practice Protocols filter, kept from the old screen.
        $source = $input['source'] ?? null;
        if ($source === 'protocol') {
            $query->whereNotNull('practice_protocol_id');
        }

        // Oldest promise first, then urgency. Closed views read newest first.
        if (in_array($filters['view'], ['done', 'cancelled'], true)) {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderBy('due_date')
                  ->orderByRaw("FIELD(priority,'urgent','high','medium','low')");
        }

        // Clamped, because build() is handed $request->all() on /tasks and a
        // page size is a cheap way to ask the database for everything.
        $perPage = max(1, min(100, (int) ($input['per_page'] ?? 50)));

        $tasks = $query->paginate($perPage)->withQueryString();

        $users = User::where('branch_id', $user->branch_id)->orderBy('name')->get();

        // For the inline "book an appointment" form in the close drawer. Same
        // definition of a doctor the appointments screen uses — copied rather
        // than invented, so the two lists cannot disagree.
        $doctors = User::where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return compact('tasks', 'counts', 'filters', 'users', 'doctors', 'source');
    }
}
