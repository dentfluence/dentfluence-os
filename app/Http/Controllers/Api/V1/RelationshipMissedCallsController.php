<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\CommunicationQueue;
use App\Services\Relationship\YesterdayReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RelationshipMissedCallsController (API v1)
 * -------------------------------------------
 * Mobile face of the "Missed Calls" backlog page (web:
 * App\Http\Controllers\Relationship\MissedCallsController). Same source of
 * truth — YesterdayReviewService::missedCallsQuery() — and the same
 * bulk-WhatsApp / bulk-dismiss / ignore / unignore model helpers, so
 * behaviour never drifts between web and mobile.
 *
 *   GET  /api/v1/relationship/missed-calls                     → paginated list
 *   POST /api/v1/relationship/missed-calls/bulk-whatsapp        → send WhatsApp to selected rows
 *   POST /api/v1/relationship/missed-calls/bulk-dismiss         → close selected rows
 *   POST /api/v1/relationship/missed-calls/{id}/ignore          → exclude one row from the queue
 *   POST /api/v1/relationship/missed-calls/{id}/unignore        → restore one row to the queue
 */
class RelationshipMissedCallsController extends ApiController
{
    public function __construct(
        private readonly YesterdayReviewService $yesterdayReview,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /api/v1/relationship/missed-calls
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $showIgnored = $request->boolean('show_ignored');

        $query = $this->yesterdayReview->missedCallsQuery($showIgnored);

        // Filters — mirrors the web MissedCallsController::index() convention.
        $filters = $request->only(['search', 'purpose', 'priority']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('person_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $page  = $query->paginate($limit)->withQueryString();

        $items = $page->getCollection()->map(fn (CommunicationQueue $c) => [
            'id'             => $c->id,
            'person_name'    => $c->person_name,
            'patient_id'     => $c->patient_id,
            'patient_name'   => $c->patient?->name,
            'phone'          => $c->patient?->phone ?? $c->phone,
            'purpose'        => $c->purpose,
            'purpose_label'  => $c->purpose_label ?? $c->purpose,
            'priority'       => $c->priority,
            'status'         => $c->status,
            'channel'        => $c->channel,
            'attempt_count'  => $c->attempt_count,
            'follow_up_date' => $c->follow_up_date?->toDateString(),
            'due_at'         => $c->due_at?->toIso8601String(),
            'is_overdue'     => (bool) $c->is_overdue,
            'is_ignored'     => $c->ignored_at !== null,
        ]);

        return $this->success($items, '', 200, [
            'current_page'    => $page->currentPage(),
            'per_page'        => $page->perPage(),
            'total'           => $page->total(),
            'last_page'       => $page->lastPage(),
            'purpose_options' => CommunicationQueue::PURPOSES,
        ]);
    }

    // NOTE: bulkWhatsapp() removed 2026-07-14 — the route was orphaned (web
    // removed bulk WhatsApp on 07-06 and mobile followed; nothing called it).
    // Individual consent-gated sends now live in Api\V1\WhatsappController.

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/missed-calls/bulk-dismiss
    // ─────────────────────────────────────────────────────────────────────

    public function bulkDismiss(Request $request): JsonResponse
    {
        // select_all mode (2026-07-14 web parity): re-applies the same
        // filters server-side and dismisses EVERY matching row — this is
        // what lets the 1,800+ item backlog get cleared in one pass instead
        // of one loaded page at a time. Mirrors web MissedCallsController.
        if ($request->boolean('select_all')) {
            $showIgnored = $request->boolean('show_ignored');
            $filters     = $request->only(['search', 'purpose', 'priority']);

            $query = $this->yesterdayReview->missedCallsQuery($showIgnored);

            if (! empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('person_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
                });
            }
            if (! empty($filters['purpose'])) {
                $query->where('purpose', $filters['purpose']);
            }
            if (! empty($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }

            $count  = 0;
            $userId = $request->user()?->id;
            $query->chunkById(200, function ($chunk) use (&$count, $userId) {
                foreach ($chunk as $item) {
                    $item->dismiss($userId);
                    $count++;
                }
            });

            return $this->success(['dismissed' => $count], "{$count} item(s) dismissed.");
        }

        $validated = $request->validate([
            'comm_ids'   => ['required', 'array', 'min:1'],
            'comm_ids.*' => ['integer', 'exists:communication_queue,id'],
        ]);

        $items = CommunicationQueue::whereIn('id', $validated['comm_ids'])->get();

        foreach ($items as $item) {
            $item->dismiss($request->user()?->id);
        }

        return $this->success(['dismissed' => count($items)], count($items) . ' item(s) dismissed.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/missed-calls/{id}/ignore
    // POST /api/v1/relationship/missed-calls/{id}/unignore
    // ─────────────────────────────────────────────────────────────────────

    public function ignore(Request $request, CommunicationQueue $missedCall): JsonResponse
    {
        $missedCall->ignore($request->user()?->id);

        return $this->success(null, 'Removed from the missed-calls queue. You can restore it from "Show ignored".');
    }

    public function unignore(CommunicationQueue $missedCall): JsonResponse
    {
        $missedCall->unignore();

        return $this->success(null, 'Restored to the missed-calls queue.');
    }
}
