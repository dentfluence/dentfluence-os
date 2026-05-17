<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Resources;

use App\Modules\Huddle\DTOs\HuddleBoardDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a HuddleBoardDTO into the full board JSON response.
 *
 * Shape:
 * {
 *   "board": { id, date, branch_id, is_locked },
 *   "role":  "doctor",
 *   "date":  "2025-01-15",
 *   "stats": { total_appointments, confirmed, ... },
 *   "columns": {
 *     "today_flow": [ ...HuddleCardResource ],
 *     "tasks":      [ ...HuddleCardResource ],
 *     ...
 *   }
 * }
 */
class HuddleBoardResource extends JsonResource
{
    /**
     * @param  HuddleBoardDTO  $resource
     */
    public function __construct(HuddleBoardDTO $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var HuddleBoardDTO $dto */
        $dto = $this->resource;

        // Transform each column's cards through HuddleCardResource
        $columns = [];
        foreach ($dto->columns as $slug => $cards) {
            $columns[$slug] = HuddleCardResource::collection(
                collect($cards)
            )->resolve($request);
        }

        return [
            'board' => [
                'id'        => $dto->board->id,
                'date'      => $dto->board->date,
                'branch_id' => $dto->board->branch_id,
                'is_locked' => (bool) $dto->board->is_locked,
            ],
            'role'    => $dto->role,
            'date'    => $dto->date,
            'stats'   => $dto->stats->toArray(),
            'columns' => $columns,
        ];
    }
}
