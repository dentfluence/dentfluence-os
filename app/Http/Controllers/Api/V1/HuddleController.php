<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\FollowUp;
use App\Models\Task;
use App\Services\Huddle\HuddleBoardApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HuddleController (API v1)
 * ---------------------------------------------------------------------------
 * The mobile face of the Daily Huddle. Same "one brain" the web uses, exposed
 * as a small, predictable JSON API for the Flutter app and Tulip.
 *
 *   GET   /api/v1/huddle                       Full morning board (snapshot)
 *   GET   /api/v1/huddle/tasks                 Task layer (today / overdue / all)
 *   POST  /api/v1/huddle/tasks                 Create a task
 *   PATCH /api/v1/huddle/tasks/{task}/status   Mark done / pending / in_progress
 *   PATCH /api/v1/huddle/tasks/{task}/assign   Re-assign a task
 *
 * Everything is branch-scoped to the logged-in user. Writes are role-gated in
 * routes/api.php; reads are open to any logged-in staff member.
 */
class HuddleController extends ApiController
{
    public function __construct(private readonly HuddleBoardApiService $board) {}

    /**
     * GET /api/v1/huddle
     * One call that powers the whole mobile huddle screen: KPIs, today's
     * schedule, yesterday's flow (with visit-logged flags), critical alerts,
     * notes, and the task layer. Optional ?date=YYYY-MM-DD (defaults to today).
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $date     = $request->query('date');

        // Validate an explicit date if one was passed
        if ($date !== null) {
            try {
                Carbon::parse($date);
            } catch (\Throwable $e) {
                return $this->error('Invalid date. Use YYYY-MM-DD.', [], 422);
            }
        }

        $payload = $this->board->build($branchId, $date);

        return $this->success($payload, 'Daily huddle board');
    }

    /**
     * GET /api/v1/huddle/tasks
     * The task layer on its own (so a Tasks tab can refresh without the whole
     * board). ?scope=open|today|overdue|all  ·  ?date=YYYY-MM-DD
     */
    public function tasks(Request $request): JsonResponse
    {
        $branchId = (int) $request->user()->branch_id;

        $scope = $request->query('scope', 'open');
        if (! in_array($scope, ['open', 'today', 'overdue', 'all'], true)) {
            $scope = 'open';
        }

        $today = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : Carbon::today();

        $tasks = $this->board->tasks($branchId, $today, $scope);

        return $this->success([
            'scope' => $scope,
            'date'  => $today->toDateString(),
            'tasks' => $tasks->values()->all(),
        ], 'Huddle tasks');
    }

    /**
     * POST /api/v1/huddle/tasks
     * Quick-add a task from the huddle. Branch + creator are taken from the
     * logged-in user; the client only sends the human details.
     */
    public function storeTask(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'category'    => ['nullable', Rule::in(array_keys(Task::CATEGORIES))],
            'due_date'    => ['nullable', 'date'],
            'due_time'    => ['nullable', 'date_format:H:i'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'patient_id'  => ['nullable', 'integer', 'exists:patients,id'],
        ]);

        $task = Task::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'priority'    => $data['priority'] ?? 'medium',
            'category'    => $data['category'] ?? 'other',
            'due_date'    => $data['due_date'] ?? Carbon::today()->toDateString(),
            'due_time'    => $data['due_time'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'patient_id'  => $data['patient_id'] ?? null,
            'status'      => 'pending',
            'branch_id'   => $user->branch_id,
            'created_by'  => $user->id,
        ]);

        return $this->success(['id' => $task->id], 'Task created.', 201);
    }

    /**
     * PATCH /api/v1/huddle/tasks/{task}/status
     * Toggle a task between done / pending / in_progress. Sets done_at when
     * completed and clears it when re-opened.
     */
    public function updateTaskStatus(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'done'])],
        ]);

        $task->update([
            'status'  => $data['status'],
            'done_at' => $data['status'] === 'done' ? now() : null,
        ]);

        return $this->success(['id' => $task->id, 'status' => $task->status], 'Task status updated.');
    }

    /**
     * PATCH /api/v1/huddle/tasks/{task}/assign
     * Re-assign a task to another staff member in the same branch.
     */
    public function assignTask(Request $request, Task $task): JsonResponse
    {
        if ($denied = $this->guardBranch($request, $task)) {
            return $denied;
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $task->update(['assigned_to' => $data['assigned_to']]);

        return $this->success(['id' => $task->id, 'assigned_to' => (int) $task->assigned_to], 'Task assigned.');
    }

    /**
     * GET /api/v1/huddle/staff
     * Active staff in the caller's branch — used by the task assign picker.
     */
    public function staff(Request $request): JsonResponse
    {
        $staff = \App\Models\User::where('branch_id', $request->user()->branch_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn ($u) => [
                'id'   => (int) $u->id,
                'name' => $u->name,
                'role' => $u->role,
            ]);

        return $this->success($staff, 'Branch staff');
    }

    /**
     * POST /api/v1/huddle/comms/push
     * Mirrors the web huddle "Add to Comm List": creates CommunicationQueue
     * rows for the chosen reminders / follow-ups so they appear in PRM.
     * PRM items (already queued) and items without a patient are skipped.
     */
    public function pushComms(Request $request): JsonResponse
    {
        $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.patient_id' => ['nullable', 'integer'],
            'items.*.comm_type'  => ['nullable', 'string'],
            'items.*.note'       => ['nullable', 'string', 'max:500'],
        ]);

        $user    = $request->user();
        $today   = Carbon::today();
        $created = 0;

        foreach ($request->input('items') as $item) {
            $patientId = isset($item['patient_id']) ? (int) $item['patient_id'] : null;
            $commType  = $item['comm_type'] ?? 'reminder';
            $note      = $item['note'] ?? null;

            // Skip PRM items (already queued) and items with no patient
            if ($commType === 'prm' || ! $patientId) {
                continue;
            }

            $sourceEngine = $commType === 'reminder' ? 'huddle_reminder' : 'huddle_followup';

            // De-dupe: skip if this patient already has a pending huddle item today
            $exists = \App\Models\CommunicationQueue::where('patient_id', $patientId)
                ->where('source_engine', $sourceEngine)
                ->where('status', 'pending')
                ->whereDate('created_at', $today->toDateString())
                ->exists();
            if ($exists) {
                continue;
            }

            $patient = \Illuminate\Support\Facades\DB::table('patients')
                ->where('id', $patientId)
                ->select('name', 'phone')
                ->first();

            \App\Models\CommunicationQueue::create([
                'patient_id'     => $patientId,
                'person_name'    => $patient?->name ?? 'Unknown',
                'phone'          => $patient?->phone ?? null,
                'channel'        => 'call',
                'comm_type'      => 'existing_patient',
                'purpose'        => $commType === 'reminder' ? 'appointment' : 'other',
                'direction'      => 'outbound',
                'next_action'    => 'call_back',
                'status'         => 'pending',
                'priority'       => $commType === 'reminder' ? 'high' : 'medium',
                'note'           => $note,
                'source_engine'  => $sourceEngine,
                'follow_up_date' => $today->toDateString(),
                'due_at'         => $today->toDateString() . ' ' . ($commType === 'reminder' ? '09:00:00' : '10:00:00'),
                'assigned_to'    => $user->name,
                'created_by'     => $user->id,
            ]);

            $created++;
        }

        return $this->success(['created' => $created], $created . ' item(s) added to the communication list.');
    }

    /**
     * POST /api/v1/huddle/yesterday-flow/log
     * Mirrors the web huddle's "Yesterday's Flow" quick-action modal
     * (resources/views/partials/yesterday-followup-card.blade.php ->
     * huddle.yesterday-flow.log): logs a task and/or books a follow-up call
     * for a patient from yesterday's flow, assigned to a chosen staff
     * member, instead of navigating straight to their profile.
     *
     * At least one of `task` or `book_followup_call` must be provided.
     */
    public function logYesterdayFollowUp(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id'         => ['required', 'integer', 'exists:patients,id'],
            'task'               => ['nullable', 'string', 'max:255'],
            'book_followup_call' => ['required', 'boolean'],
            'date'               => ['required_if:book_followup_call,1', 'nullable', 'date'],
            'reason'             => ['nullable', 'string', 'max:500'],
            'assigned_to'        => ['required', 'integer', 'exists:users,id'],
        ]);

        $task   = trim((string) $request->input('task', ''));
        $bookIt = $request->boolean('book_followup_call');

        if ($task === '' && ! $bookIt) {
            return $this->error("Add a task or tick 'Book Follow-up call' before saving.", [], 422);
        }

        $user       = $request->user();
        $assignedTo = (int) $request->input('assigned_to');
        $date       = $request->input('date') ?: now()->toDateString();
        $reason     = $request->input('reason');

        [$createdTask, $createdFollowUp] = DB::transaction(function () use ($request, $task, $bookIt, $user, $assignedTo, $date, $reason) {
            $createdTask     = null;
            $createdFollowUp = null;

            if ($task !== '') {
                $createdTask = Task::create([
                    'title'       => $task,
                    'description' => $reason,
                    'assigned_to' => $assignedTo,
                    'created_by'  => $user->id,
                    'branch_id'   => $user->branch_id,
                    'patient_id'  => (int) $request->input('patient_id'),
                    'due_date'    => $date,
                    'priority'    => 'medium',
                    'category'    => 'follow_up',
                    'task_type'   => 'human',
                    'status'      => 'pending',
                ]);
            }

            if ($bookIt) {
                $createdFollowUp = FollowUp::create([
                    'patient_id'   => (int) $request->input('patient_id'),
                    'label'        => "Follow-up call — logged from Yesterday's Flow",
                    'due_date'     => $date,
                    'due_time'     => '10:00',
                    'channel'      => 'call',
                    'priority'     => 'medium',
                    'note'         => $reason,
                    'trigger_type' => 'manual',
                    'auto_created' => false,
                    'assigned_to'  => $assignedTo,
                    'status'       => 'pending',
                ]);
            }

            return [$createdTask, $createdFollowUp];
        });

        $parts = [];
        if ($createdTask)     $parts[] = 'Task assigned';
        if ($createdFollowUp) $parts[] = 'Follow-up call scheduled';

        return $this->success([
            'task_id'      => $createdTask?->id,
            'follow_up_id' => $createdFollowUp?->id,
        ], implode(' · ', $parts) . '.');
    }

    /**
     * Make sure a task belongs to the caller's branch before mutating it.
     * Returns a 404 JsonResponse if not, or null when the task is in-scope.
     */
    private function guardBranch(Request $request, Task $task): ?JsonResponse
    {
        if ((int) $task->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Task not found.', [], 404);
        }
        return null;
    }
}
