<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Repositories;

use App\Modules\Huddle\Models\HuddleComment;
use Illuminate\Support\Collection;

class HuddleCommentRepository
{
    public function forBoard(int $boardId): Collection
    {
        return HuddleComment::with('user')
            ->where('huddle_board_id', $boardId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // FIX #13: was filtering whereNull('resolved_at') — model uses boolean 'is_resolved'
    public function unresolvedForBoard(int $boardId): Collection
    {
        return HuddleComment::with('user')
            ->where('huddle_board_id', $boardId)
            ->where('is_resolved', false) // FIX #13
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findById(int $commentId): ?HuddleComment
    {
        return HuddleComment::find($commentId);
    }

    public function create(int $boardId, int $userId, string $body, ?int $cardId = null): HuddleComment
    {
        return HuddleComment::create([
            'huddle_board_id' => $boardId,
            'user_id'         => $userId,
            'huddle_card_id'  => $cardId,
            'body'            => $body,
            'type'            => 'comment',
            'is_resolved'     => false,
        ]);
    }

    // FIX #14: was only setting resolved_at — model primary field is boolean 'is_resolved'
    // Now sets all three fields for proper audit trail
    public function resolve(int $commentId, int $resolvedBy): bool
    {
        return (bool) HuddleComment::where('id', $commentId)
            ->update([
                'is_resolved' => true,       // FIX #14
                'resolved_by' => $resolvedBy,
                'resolved_at' => now(),
            ]);
    }

    public function delete(int $commentId): bool
    {
        return (bool) HuddleComment::where('id', $commentId)->delete();
    }
}
