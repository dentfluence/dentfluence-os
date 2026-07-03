<?php

namespace App\Http\Controllers\Communication;

use App\Models\AppNotification;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use App\Modules\Huddle\Models\HuddleTaskLog;
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class TaskController extends Controller
{
    // ── index ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Task::with(['assignedTo', 'patient', 'protocol.materials'])
            ->where('branch_id', $branchId)
            ->visibleToReception() // Phase 3: hides System (Automation-record) tasks once tasks.human_system_split is on
            ->orderBy('due_date');

        // ── Role-based visibility ───────────────────────────────────────────
        // Staff-level roles (assistant, front_desk, accounts) see only their
        // own tasks. Admin / doctors see everything.
        $staffRoles = [
            \App\Models\User::ROLE_ASSISTANT,
            \App\Models\User::ROLE_FRONT_DESK,
            \App\Models\User::ROLE_ACCOUNTS,
        ];
        if (in_array(Auth::user()->role, $staffRoles)) {
            $query->where('assigned_to', Auth::id());
        }
        // ────────────────────────────────────────────────────────────────────

        if ($request->filled('date'))        $query->whereDate('due_date', $request->date);
        if ($request->filled('priority'))    $query->where('priority', $request->priority);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('status'))      $query->where('status', $request->status);

        // Practice Protocols filter: ?source=protocol → only protocol-generated tasks.
        $source = $request->get('source');
        if ($source === 'protocol') $query->whereNotNull('practice_protocol_id');

        $tasks = $query->get();

        $overdue  = $tasks->filter(fn($t) => $t->status === 'pending' && $t->due_date->lt(today()) && !$t->due_date->isToday());
        $today    = $tasks->filter(fn($t) => $t->due_date->isToday()  && $t->status !== 'done');
        $upcoming = $tasks->filter(fn($t) => $t->due_date->isFuture() && $t->status !== 'done');
        $done     = $tasks->filter(fn($t) => $t->status === 'done');

        $users = User::where('branch_id', $branchId)->orderBy('name')->get();

        return view('tasks.index', compact('overdue', 'today', 'upcoming', 'done', 'users', 'source'));
    }

    // ── create (fallback page) ────────────────────────────────────────────────
    public function create()
    {
        $users = User::where('branch_id', Auth::user()->branch_id)->orderBy('name')->get();
        return view('tasks.create', compact('users'));
    }

    // ── store ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string|max:1000',
            'assigned_to'         => 'required|exists:users,id',
            'due_date'            => 'required|date',
            'priority'            => 'required|in:urgent,high,medium,low',
            'category'            => 'required|in:clinical,admin,lab,follow_up,call,whatsapp,maintenance,other',
            'patient_id'          => 'nullable|exists:patients,id',
            // Maintenance / recurring fields
            'maintenance_type'    => 'nullable|in:ac_service,pest_control,deep_cleaning,autoclave,dental_chair,xray_machine,water_purifier,fire_safety,generator,other',
            'is_recurring'        => 'boolean',
            'recurrence_interval' => 'nullable|integer|min:1|max:365',
            'recurrence_unit'     => 'nullable|in:days,weeks,months,years',
        ]);

        // Normalise: only save recurring fields when category=maintenance
        if (($data['category'] ?? '') !== 'maintenance') {
            $data['is_recurring']        = false;
            $data['recurrence_interval'] = null;
            $data['recurrence_unit']     = null;
            $data['maintenance_type']    = null;
        }

        $task = Task::create([
            ...$data,
            'branch_id'  => Auth::user()->branch_id,
            'created_by' => Auth::id(),
            'status'     => 'pending',
        ]);

        // ── Wire to Daily Huddle board ──────────────────────────────────────
        try {
            $assignedUser = User::find($task->assigned_to);
            if ($assignedUser) {
                $boardRepo = app(HuddleBoardRepository::class);
                $board = $boardRepo->findOrCreateForToday(
                    branchId: $task->branch_id,
                    role:     $assignedUser->role ?? 'staff',
                );
                HuddleTaskLog::firstOrCreate(
                    ['task_id' => $task->id, 'huddle_board_id' => $board->id],
                    ['status' => 'pending', 'carried_forward' => false]
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('HuddleTaskLog sync failed: ' . $e->getMessage());
        }
        // ───────────────────────────────────────────────────────────────────

        // ── In-app notification → assigned user ────────────────────────────────
        if ($task->assigned_to && $task->assigned_to !== Auth::id()) {
            $assigner = Auth::user()->name;
            $due      = $task->due_date->format('d M Y');
            AppNotification::notify(
                userId:      $task->assigned_to,
                type:        'task_assigned',
                title:       'New task assigned to you',
                message:     "\"{$task->title}\" — due {$due}. Assigned by {$assigner}.",
                actionUrl:   route('tasks.index'),
                actionLabel: 'View Tasks',
            );
        }
        // ────────────────────────────────────────────────────────────────────

        $task->load(['assignedTo', 'patient']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'   => true,
                'task' => [
                    'id'               => $task->id,
                    'title'            => $task->title,
                    'description'      => $task->description,
                    'priority'         => $task->priority,
                    'due_date'         => $task->due_date->format('d M Y'),
                    'due_date_ts'      => $task->due_date->toDateString(),
                    'assigned_to'      => $task->assignedTo->name,
                    'patient_name'     => $task->patient?->name,
                    'category'         => $task->category,
                    'status'           => $task->status,
                    'is_recurring'     => $task->is_recurring,
                    'recurrence_label' => $task->recurrenceLabel(),
                ],
            ]);
        }

        return redirect()->route('tasks.index')->with('success', 'Task created.');
    }

    // ── markDone ──────────────────────────────────────────────────────────────
    public function markDone(Task $task)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        // ── Evidence gate ────────────────────────────────────────────────────
        // Protocol tasks flagged "requires_evidence" cannot be completed until
        // proof has been attached. The board should instead call uploadEvidence.
        if ($task->requires_evidence) {
            $hasProof = HuddleTaskLog::where('task_id', $task->id)
                ->whereNotNull('proof_path')
                ->exists();

            if (! $hasProof) {
                return response()->json([
                    'ok'             => false,
                    'needs_evidence' => true,
                    'message'        => 'Please attach evidence before completing this task.',
                ], 422);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        $task->update(['status' => 'done', 'done_at' => now()]);

        // Sync status to any HuddleTaskLog entries for this task
        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'done']);

        // ── Auto-spawn next occurrence for recurring/AMC tasks ───────────────
        $nextTask = null;
        if ($task->is_recurring && $task->recurrence_interval && $task->recurrence_unit) {
            try {
                $nextTask = $task->spawnNext();
            } catch (\Throwable $e) {
                \Log::warning('Task auto-spawn failed: ' . $e->getMessage());
            }
        }
        // ────────────────────────────────────────────────────────────────────

        return response()->json([
            'ok'            => true,
            'is_recurring'  => $task->is_recurring,
            'next_due_date' => $nextTask?->due_date->format('d M Y'),
            'next_task_id'  => $nextTask?->id,
        ]);
    }

    // ── uploadEvidence ──────────────────────────────────────────────────────────
    // Attach proof to a task and complete it in one step. Used by protocol tasks
    // that require evidence. Reuses the Huddle proof store (huddle_task_logs).
    public function uploadEvidence(Task $task, Request $request)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store("tasks/evidence/{$task->id}", 'local');

        // Ensure a HuddleTaskLog exists for this task (protocol tasks may not have one yet).
        try {
            $assignedUser = User::find($task->assigned_to);
            $board = app(HuddleBoardRepository::class)->findOrCreateForToday(
                branchId: $task->branch_id,
                role:     $assignedUser->role ?? 'staff',
            );
            $log = HuddleTaskLog::firstOrCreate(
                ['task_id' => $task->id, 'huddle_board_id' => $board->id],
                ['status' => 'pending', 'carried_forward' => false],
            );
            $log->update([
                'proof_path'        => $path,
                'proof_uploaded_at' => now(),
                'status'            => 'done',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Task evidence log sync failed: ' . $e->getMessage());
        }

        $task->update(['status' => 'done', 'done_at' => now()]);
        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'done']);

        // Recurring protocols still auto-spawn their next occurrence.
        $nextTask = null;
        if ($task->is_recurring && $task->recurrence_interval && $task->recurrence_unit) {
            try {
                $nextTask = $task->spawnNext();
            } catch (\Throwable $e) {
                \Log::warning('Task auto-spawn failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'ok'            => true,
            'is_recurring'  => $task->is_recurring,
            'next_due_date' => $nextTask?->due_date->format('d M Y'),
        ]);
    }

    // ── escalate ──────────────────────────────────────────────────────────────
    public function escalate(Task $task, \Illuminate\Http\Request $request)
    {
        $task->update(['is_escalated' => true]);

        return response()->json(['ok' => true]);
    }

    public function myTasks(\Illuminate\Http\Request $request)
    {
        // Always scoped to the logged-in user — regardless of role.
        // Admin on the main dashboard sees all; here they see only their own.
        $tasks = Task::with(['assignedTo', 'patient', 'protocol.materials'])
            ->where('assigned_to', Auth::id())
            ->visibleToReception() // Phase 3: hides System (Automation-record) tasks once tasks.human_system_split is on
            ->orderBy('due_date')
            ->get();

        $overdue  = $tasks->filter(fn($t) => $t->status === 'pending' && $t->due_date->lt(today()) && !$t->due_date->isToday());
        $today    = $tasks->filter(fn($t) => $t->due_date->isToday()  && $t->status !== 'done');
        $upcoming = $tasks->filter(fn($t) => $t->due_date->isFuture() && $t->status !== 'done');
        $done     = $tasks->filter(fn($t) => $t->status === 'done');

        return view('tasks.mine', compact('overdue', 'today', 'upcoming', 'done'));
    }

    public function overdue()
    {
        return view('tasks.overdue');
    }
}
