<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Repositories;

use App\Modules\Huddle\Models\HuddleComment;
use Illuminate\Support\Collection;

class HuddleCommentRepository
{
    // ── Reads ────────────────────────────────────────────────────────────────

    /**
     * All comments for a board, newest first, with author.
     */
    public function forBoard(int $boardId): Collection
    {
        return HuddleComment::with('user')
            ->where('huddle_board_id', $boardId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Unresolved comments only — used for the Comments column card list.
     */
    public function unresolvedForBoard(int $boardId): Collection
    {
        return HuddleComment::with('user')
            ->where('huddle_board_id', $boardId)
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findById(int $commentId): ?HuddleComment
    {
        return HuddleComment::find($commentId);
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    public function create(int $boardId, int $userId, string $body, ?int $cardId = null): HuddleComment
    {
        return HuddleComment::create([
            'huddle_board_id' => $boardId,
            'user_id'         => $userId,
            'huddle_card_id'  => $cardId,
            'body'            => $body,
            'resolved_at'     => null,
        ]);
    }

    public function resolve(int $commentId): bool
    {
        return (bool) HuddleComment::where('id', $commentId)
            ->update(['resolved_at' => now()]);
    }

    public function delete(int $commentId): bool
    {
        return (bool) HuddleComment::where('id', $commentId)->delete();
    }
}