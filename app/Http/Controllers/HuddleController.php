<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Huddle\Resources\HuddleBoardResource;
use App\Modules\Huddle\Services\HuddleAggregationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HuddleController — SESSION 3 UPDATE
 *
 * IMPORTANT: This file EXTENDS the existing controller.
 * Copy only the NEW/UPDATED methods below into your existing
 * app/Http/Controllers/HuddleController.php (or wherever it lives).
 *
 * Do NOT replace the existing file wholesale —
 * accountability(), updateInstruction(), storeNote() stay untouched.
 */
class HuddleController extends Controller
{
    public function __construct(
        private readonly HuddleAggregationService $aggregationService,
    ) {}

    /**
     * GET /huddle
     * GET /huddle?date=2025-01-15  ← optional date param for historical view
     *
     * Returns the full role-based board payload.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $date = $request->has('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $boardDTO = $this->aggregationService->buildBoardForUser($user, $date);

        return (new HuddleBoardResource($boardDTO))
            ->response()
            ->setStatusCode(200);
    }

    // -------------------------------------------------------------------------
    // Keep existing methods below — do not remove them:
    //   accountability()
    //   updateInstruction()
    //   storeNote()
    // -------------------------------------------------------------------------
}
