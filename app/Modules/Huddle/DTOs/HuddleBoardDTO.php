<?php

declare(strict_types=1);

namespace App\Modules\Huddle\DTOs;

use App\Modules\Huddle\Models\HuddleBoard;

/**
 * Top-level DTO wrapping the full board payload:
 * board model + stats + role-filtered columns of cards.
 */
final class HuddleBoardDTO
{
    /**
     * @param  HuddleCardDTO[][]  $columns  keyed by column slug
     *                                       e.g. ['today_flow' => [...], 'tasks' => [...]]
     */
    public function __construct(
        public readonly HuddleBoard    $board,
        public readonly HuddleStatsDTO $stats,
        public readonly array          $columns,
        public readonly string         $role,
        public readonly string         $date,
    ) {}
}
