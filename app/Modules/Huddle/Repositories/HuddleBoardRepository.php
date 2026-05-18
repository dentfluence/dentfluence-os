<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Repositories;

use App\Modules\Huddle\Models\HuddleBoard;
use Illuminate\Database\Eloquent\Collection;

class HuddleBoardRepository
{
    public function __construct(
        private readonly HuddleBoard $model
    ) {}

    /**
     * Find or create today's board for the given branch and role.
     * One board per branch/role per day.
     */
    public function findOrCreateForToday(int $branchId, string $role): HuddleBoard
    {
        return $this->model->firstOrCreate(
            [
                'branch_id' => $branchId,
                'role'      => $role,
                'date'      => now()->toDateString(),
            ],
            [
                'is_locked' => false,
            ]
        );
    }

    public function findById(int $id): ?HuddleBoard
    {
        return $this->model->find($id);
    }

    public function findByDateAndRole(int $branchId, string $role, string $date): ?HuddleBoard
    {
        return $this->model
            ->where('branch_id', $branchId)
            ->where('role', $role)
            ->where('date', $date)
            ->first();
    }

    public function getByDateRange(int $branchId, string $from, string $to): Collection
    {
        return $this->model
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date', 'desc')
            ->get();
    }

    public function lock(int $boardId): bool
    {
        return (bool) $this->model
            ->where('id', $boardId)
            ->update(['is_locked' => true]);
    }

    public function updateMeta(int $boardId, array $meta): bool
    {
        return (bool) $this->model
            ->where('id', $boardId)
            ->update(['meta' => $meta]);
    }
}
