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
     * "Today" is scoped to date only — one board per branch/role per day.
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
                'meta'      => null,
            ]
        );
    }

    /**
     * Find a specific board by its primary key.
     */
    public function findById(int $id): ?HuddleBoard
    {
        return $this->model->find($id);
    }

    /**
     * Find a board scoped to a branch, role, and specific date.
     */
    public function findByDateAndRole(int $branchId, string $role, string $date): ?HuddleBoard
    {
        return $this->model
            ->where('branch_id', $branchId)
            ->where('role', $role)
            ->where('date', $date)
            ->first();
    }

    /**
     * Return all boards for a branch within a date range.
     * Used by the report generator in Phase 4.
     */
    public function getByDateRange(int $branchId, string $from, string $to): Collection
    {
        return $this->model
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Lock a board so no further edits are allowed (end-of-day action).
     */
    public function lock(int $boardId): bool
    {
        return (bool) $this->model
            ->where('id', $boardId)
            ->update(['is_locked' => true]);
    }

    /**
     * Persist arbitrary meta JSON onto the board (e.g. summary stats snapshot).
     */
    public function updateMeta(int $boardId, array $meta): bool
    {
        return (bool) $this->model
            ->where('id', $boardId)
            ->update(['meta' => $meta]);
    }
}