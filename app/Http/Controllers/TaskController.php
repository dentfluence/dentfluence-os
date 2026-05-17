<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    // ── index ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $branchId = Auth::user()->branch_id;

        $query = Task::with(['assignedTo', 'patient'])
            ->where('branch_id', $branchId)
            ->orderBy('due_date');

        if ($request->filled('date'))        $query->whereDate('due_date', $request->date);
        if ($request->filled('priority'))    $query->where('priority', $request->priority);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('status'))      $query->where('status', $request->status);

        $tasks = $query->get();

        $overdue  = $tasks->filter(fn($t) => $t->status === 'pending' && $t->due_date->lt(today()) && !$t->due_date->isToday());
        $today    = $tasks->filter(fn($t) => $t->due_date->isToday()  && $t->status !== 'done');
        $upcoming = $tasks->filter(fn($t) => $t->due_date->isFuture() && $t->status !== 'done');
        $done     = $tasks->filter(fn($t) => $t->status === 'done');

        $users = User::where('branch_id', $branchId)->orderBy('name')->get();

        return view('tasks.index', compact('overdue', 'today', 'upcoming', 'done', 'users'));
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
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'assigned_to' => 'required|exists:users,id',
            'due_date'    => 'required|date',
            'priority'    => 'required|in:urgent,high,medium,low',
            'category'    => 'required|in:clinical,admin,lab,follow_up,other',
            'patient_id'  => 'nullable|exists:patients,id',
        ]);

        $task = Task::create([
            ...$data,
            'branch_id'  => Auth::user()->branch_id,
            'created_by' => Auth::id(),
            'status'     => 'pending',
        ]);

        $task->load(['assignedTo', 'patient']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'   => true,
                'task' => [
                    'id'           => $task->id,
                    'title'        => $task->title,
                    'description'  => $task->description,
                    'priority'     => $task->priority,
                    'due_date'     => $task->due_date->format('d M Y'),
                    'due_date_ts'  => $task->due_date->toDateString(),
                    'assigned_to'  => $task->assignedTo->name,
                    'patient_name' => $task->patient?->name,
                    'category'     => $task->category,
                    'status'       => $task->status,
                ],
            ]);
        }

        return redirect()->route('tasks.index')->with('success', 'Task created.');
    }

    // ── markDone ──────────────────────────────────────────────────────────────
    public function markDone(Task $task)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);
        $task->update(['status' => 'done', 'done_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // ── escalate ──────────────────────────────────────────────────────────────
    public function escalate(Task $task, Request $request)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'escalation_note' => 'required|string|max:500',
        ]);

        $task->update([
            'status'           => 'escalated',
            'escalated_at'     => now(),
            'escalation_note'  => $data['escalation_note'],
        ]);

        return response()->json(['ok' => true]);
    }

    // ── overdue ───────────────────────────────────────────────────────────────
    public function overdue()
    {
        $tasks = Task::with(['assignedTo', 'patient'])
            ->where('branch_id', Auth::user()->branch_id)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->get();

        return view('tasks.overdue', compact('tasks'));
    }

    // ── myTasks ───────────────────────────────────────────────────────────────
    public function myTasks()
    {
        $tasks = Task::with(['assignedTo', 'patient'])
            ->where('branch_id', Auth::user()->branch_id)
            ->where('assigned_to', Auth::id())
            ->where('status', 'pending')
            ->whereDate('due_date', today())
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->get();

        return view('tasks.mine', compact('tasks'));
    }
}