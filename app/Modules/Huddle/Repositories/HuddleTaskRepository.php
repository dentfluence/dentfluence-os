<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Repositories;

use App\Modules\Huddle\Models\HuddleTaskLog;
use Illuminate\Support\Collection;

class HuddleTaskRepository
{
    public function forBoard(int $boardId): Collection
    {
        return HuddleTaskLog::with('task', 'task.assignedTo')
            ->where('huddle_board_id', $boardId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function overdueForBranch(int $branchId): Collection
    {
        return HuddleTaskLog::with('task')
            ->whereHas('huddleBoard', fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'overdue')
            ->get();
    }

    public function findByTaskAndBoard(int $taskId, int $boardId): ?HuddleTaskLog
    {
        return HuddleTaskLog::where('task_id', $taskId)
            ->where('huddle_board_id', $boardId)
            ->first();
    }

    public function create(array $data): HuddleTaskLog
    {
        return HuddleTaskLog::create([
            'task_id'         => $data['task_id'],
            'huddle_board_id' => $data['huddle_board_id'],
            'status'          => $data['status'] ?? 'pending',
            'carried_forward' => $data['carried_forward'] ?? false,
            'proof_path'      => $data['proof_path'] ?? null,
        ]);
    }

    public function updateStatus(int $logId, string $status): bool
    {
        return (bool) HuddleTaskLog::where('id', $logId)
            ->update(['status' => $status]);
    }

    public function storeProof(int $logId, string $path): bool
    {
        return (bool) HuddleTaskLog::where('id', $logId)
            ->update(['proof_path' => $path]);
    }

    public function markCarriedForward(int $logId): bool
    {
        return (bool) HuddleTaskLog::where('id', $logId)
            ->update(['carried_forward' => true]);
    }
}
