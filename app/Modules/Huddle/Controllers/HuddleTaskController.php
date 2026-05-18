<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Repositories\HuddleTaskRepository;
use App\Modules\Huddle\Requests\StoreHuddleTaskRequest;
use App\Modules\Huddle\Requests\UpdateTaskStatusRequest;
use App\Modules\Huddle\Requests\AssignTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HuddleTaskController extends Controller
{
    public function __construct(
        private readonly HuddleBoardRepository $boardRepository,
        private readonly HuddleTaskRepository  $taskRepository,
    ) {}

    /**
     * GET /huddle/tasks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // FIX #11: was findOrCreateToday() — method is findOrCreateForToday()
        $board = $this->boardRepository->findOrCreateForToday(
            branchId: $user->branch_id,
            role:     $user->role,
        );

        $logs = $this->taskRepository->forBoard($board->id);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id'              => $log->id,
                'task_id'         => $log->task_id,
                'title'           => $log->task->title ?? null,
                'task_type'       => $log->task->type ?? 'general',
                'status'          => $log->status,
                'assigned_to'     => $log->task->assignedTo->name ?? null,
                'due_date'        => $log->task->due_date ?? null,
                'requires_proof'  => $log->task->requires_proof ?? false,
                'carried_forward' => $log->carried_forward,
                'proof_path'      => $log->proof_path,
            ]),
        ]);
    }

    /**
     * POST /huddle/tasks
     */
    public function store(StoreHuddleTaskRequest $request): JsonResponse
    {
        $user = $request->user();

        // FIX #11: was findOrCreateToday()
        $board = $this->boardRepository->findOrCreateForToday(
            branchId: $user->branch_id,
            role:     $user->role,
        );

        $log = $this->taskRepository->create([
            'task_id'         => $request->validated('task_id'),
            'huddle_board_id' => $board->id,
            'status'          => 'pending',
            'carried_forward' => false,
        ]);

        return response()->json(['data' => ['id' => $log->id]], 201);
    }

    /**
     * PATCH /huddle/tasks/{taskId}/status
     */
    public function updateStatus(UpdateTaskStatusRequest $request, int $taskId): JsonResponse
    {
        $updated = $this->taskRepository->updateStatus(
            logId:  $taskId,
            status: $request->validated('status'),
        );

        if (! $updated) {
            return response()->json(['message' => 'Task log not found.'], 404);
        }

        return response()->json(['message' => 'Status updated.']);
    }

    /**
     * PATCH /huddle/tasks/{taskId}/assign
     */
    public function assign(AssignTaskRequest $request, int $taskId): JsonResponse
    {
        $user = $request->user();

        // FIX #11: was findOrCreateToday() and boardId was hardcoded 0
        $board = $this->boardRepository->findOrCreateForToday(
            branchId: $user->branch_id,
            role:     $user->role,
        );

        $log = $this->taskRepository->findByTaskAndBoard(
            taskId:  $taskId,
            boardId: $board->id,
        );

        if (! $log || ! $log->task) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        $log->task->update(['assigned_to' => $request->validated('user_id')]);

        return response()->json(['message' => 'Task assigned.']);
    }

    /**
     * POST /huddle/tasks/{taskId}/proof
     */
    public function uploadProof(Request $request, int $taskId): JsonResponse
    {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store(
            "huddle/proofs/{$taskId}",
            'local'
        );

        $stored = $this->taskRepository->storeProof(
            logId: $taskId,
            path:  $path,
        );

        if (! $stored) {
            Storage::delete($path);
            return response()->json(['message' => 'Task log not found.'], 404);
        }

        $this->taskRepository->updateStatus($taskId, 'done');

        return response()->json([
            'message'    => 'Proof uploaded.',
            'proof_path' => $path,
        ]);
    }

    /**
     * POST /huddle/tasks/{taskId}/carry-forward
     */
    public function carryForward(Request $request, int $taskId): JsonResponse
    {
        $marked = $this->taskRepository->markCarriedForward($taskId);

        if (! $marked) {
            return response()->json(['message' => 'Task log not found.'], 404);
        }

        return response()->json(['message' => 'Task marked for carry-forward.']);
    }
}
