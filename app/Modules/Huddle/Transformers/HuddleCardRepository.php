<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Repositories;

use App\Modules\Huddle\Models\HuddleCard;
use Illuminate\Database\Eloquent\Collection;

class HuddleCardRepository
{
    public function __construct(
        private readonly HuddleCard $model
    ) {}

    /**
     * All cards for a given board, ordered by position.
     * This is the primary read used by the Kanban renderer.
     */
    public function getByBoard(int $boardId): Collection
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->orderBy('position')
            ->get();
    }

    /**
     * Cards for a board filtered by column slug
     * (e.g. 'today_flow', 'tasks', 'lab').
     */
    public function getByBoardAndColumn(int $boardId, string $column): Collection
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('column', $column)
            ->orderBy('position')
            ->get();
    }

    /**
     * Find a single card — used before updates to verify ownership.
     */
    public function findById(int $id): ?HuddleCard
    {
        return $this->model->find($id);
    }

    /**
     * Find the huddle card that wraps a specific appointment.
     * Prevents duplicate cards being created for the same appointment.
     */
    public function findByAppointment(int $boardId, int $appointmentId): ?HuddleCard
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('source_type', 'appointment')
            ->where('source_id', $appointmentId)
            ->first();
    }

    /**
     * Find the huddle card that wraps a specific task.
     */
    public function findByTask(int $boardId, int $taskId): ?HuddleCard
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('source_type', 'task')
            ->where('source_id', $taskId)
            ->first();
    }

    /**
     * Create a new huddle card from a validated data array.
     * Caller (transformer) is responsible for building this array.
     */
    public function create(array $data): HuddleCard
    {
        return $this->model->create($data);
    }

    /**
     * Upsert pattern: find by source, create if missing.
     * Used by AggregationService so re-runs are idempotent.
     */
    public function firstOrCreateFromSource(
        int $boardId,
        string $sourceType,
        int $sourceId,
        array $defaults
    ): HuddleCard {
        return $this->model->firstOrCreate(
            [
                'huddle_board_id' => $boardId,
                'source_type'     => $sourceType,
                'source_id'       => $sourceId,
            ],
            $defaults
        );
    }

    /**
     * Sync the card's cached payload JSON from the live source record.
     * Called by the UpdateHuddleCard listener (Phase 3).
     */
    public function updatePayload(int $cardId, array $payload): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->update(['payload' => $payload]);
    }

    /**
     * Update position and/or column — called by drag-and-drop API (Phase 2).
     */
    public function move(int $cardId, string $column, int $position): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->update([
                'column'   => $column,
                'position' => $position,
            ]);
    }

    /**
     * Soft-delete a card (huddle_cards uses SoftDeletes).
     */
    public function delete(int $cardId): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->delete();
    }

    /**
     * Bulk-update positions after a column reorder.
     * Accepts: [['id' => 1, 'position' => 0], ...]
     */
    public function bulkUpdatePositions(array $positions): void
    {
        foreach ($positions as $item) {
            $this->model
                ->where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }
    }
}