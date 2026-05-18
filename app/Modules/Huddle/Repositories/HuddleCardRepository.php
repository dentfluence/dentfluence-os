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

    public function getByBoard(int $boardId): Collection
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->orderBy('position')
            ->get();
    }

    // FIX #7: was filtering on 'column' — correct column is 'column_key'
    public function getByBoardAndColumn(int $boardId, string $column): Collection
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('column_key', $column) // FIX #7
            ->orderBy('position')
            ->get();
    }

    public function findById(int $id): ?HuddleCard
    {
        return $this->model->find($id);
    }

    public function findByAppointment(int $boardId, int $appointmentId): ?HuddleCard
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('source_type', 'appointment')
            ->where('source_id', $appointmentId)
            ->first();
    }

    public function findByTask(int $boardId, int $taskId): ?HuddleCard
    {
        return $this->model
            ->where('huddle_board_id', $boardId)
            ->where('source_type', 'task')
            ->where('source_id', $taskId)
            ->first();
    }

    public function create(array $data): HuddleCard
    {
        return $this->model->create($data);
    }

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

    // FIX #5: was updating column 'payload' — actual column is 'snapshot'
    public function updateSnapshot(int $cardId, array $snapshot): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->update(['snapshot' => $snapshot]); // FIX #5
    }

    // Kept as alias so any legacy callers don't break
    public function updatePayload(int $cardId, array $payload): bool
    {
        return $this->updateSnapshot($cardId, $payload);
    }

    // FIX #6: was updating column 'column' — correct column is 'column_key'
    public function move(int $cardId, string $column, int $position): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->update([
                'column_key' => $column,   // FIX #6
                'position'   => $position,
            ]);
    }

    public function delete(int $cardId): bool
    {
        return (bool) $this->model
            ->where('id', $cardId)
            ->delete();
    }

    public function bulkUpdatePositions(array $positions): void
    {
        foreach ($positions as $item) {
            $this->model
                ->where('id', $item['id'])
                ->update(['position' => $item['position']]);
        }
    }
}
