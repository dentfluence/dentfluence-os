<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Resources;

use App\Modules\Huddle\DTOs\HuddleBoardDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HuddleBoardResource extends JsonResource
{
    public function __construct(HuddleBoardDTO $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var HuddleBoardDTO $dto */
        $dto = $this->resource;

        $columns = [];
        foreach ($dto->columns as $slug => $cards) {
            $columns[$slug] = HuddleCardResource::collection(
                collect($cards)
            )->resolve($request);
        }

        return [
            'board' => [
                'id'        => $dto->board->id,
                'date' => $dto->board->date->toDateString(),
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
