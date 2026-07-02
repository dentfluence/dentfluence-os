<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Modules\Huddle\Repositories\HuddleCommentRepository;
use App\Modules\Huddle\Requests\StoreHuddleCommentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HuddleCommentController extends Controller
{
    public function __construct(
        private readonly HuddleBoardRepository   $boardRepository,
        private readonly HuddleCommentRepository $commentRepository,
    ) {}

    /**
     * GET /huddle/comments
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // FIX #12: was findOrCreateToday() — method is findOrCreateForToday()
        $board = $this->boardRepository->findOrCreateForToday(
            branchId: $user->branch_id,
            role:     $user->role,
        );

        $comments = $this->commentRepository->unresolvedForBoard($board->id);

        return response()->json([
            'data' => $comments->map(fn ($c) => [
                'id'         => $c->id,
                'author'     => $c->user->name ?? 'Unknown',
                'body'       => $c->body,
                'type'       => $c->type,
                'created_at' => $c->created_at->toISOString(),
                'resolved'   => (bool) $c->is_resolved, // FIX: was $c->resolved_at !== null
            ]),
        ]);
    }

    /**
     * POST /huddle/comments
     */
    public function store(StoreHuddleCommentRequest $request): JsonResponse
    {
        $user = $request->user();

        // FIX #12: was findOrCreateToday()
        $board = $this->boardRepository->findOrCreateForToday(
            branchId: $user->branch_id,
            role:     $user->role,
        );

        $comment = $this->commentRepository->create(
            boardId: $board->id,
            userId:  $user->id,
            body:    $request->validated('body'),
            cardId:  $request->validated('huddle_card_id'),
        );

        return response()->json([
            'data' => [
                'id'         => $comment->id,
                'author'     => $user->name,
                'body'       => $comment->body,
                'type'       => $comment->type,
                'created_at' => $comment->created_at->toISOString(),
                'resolved'   => false,
            ],
        ], 201);
    }

    /**
     * PATCH /huddle/comments/{commentId}/resolve
     */
    public function resolve(Request $request, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);

        if (! $comment) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        // FIX #12: pass resolver's user ID for audit trail
        $this->commentRepository->resolve($commentId, $request->user()->id);

        return response()->json(['message' => 'Comment resolved.']);
    }

    /**
     * DELETE /huddle/comments/{commentId}
     */
    public function destroy(Request $request, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);

        if (! $comment) {
            return response()->json(['message' => 'Comment not found.'], 404);
        }

        $user = $request->user();

        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->commentRepository->delete($commentId);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
