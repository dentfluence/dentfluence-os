<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\CommActivityLog;
use App\Models\CommunicationQueue;
use App\Services\Relationship\YesterdayReviewService;
use App\Services\Whatsapp\OutboundMessageService;
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

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/missed-calls/bulk-whatsapp
    // ─────────────────────────────────────────────────────────────────────

    public function bulkWhatsapp(Request $request, OutboundMessageService $outbound): JsonResponse
    {
        $validated = $request->validate([
            'comm_ids'   => ['required', 'array', 'min:1'],
            'comm_ids.*' => ['integer', 'exists:communication_queue,id'],
            'message'    => ['required', 'string', 'max:1000'],
        ]);

        $items = CommunicationQueue::with('patient:id,name,phone')
            ->whereIn('id', $validated['comm_ids'])
            ->get();

        $sent   = 0;
        $failed = 0;

        foreach ($items as $item) {
            $phone = $item->patient?->phone ?? $item->phone;

            if (! $phone) {
                $failed++;
                continue;
            }

            $result = $outbound->sendText($phone, $validated['message'], [
                'category'     => 'service',
                'patient_id'   => $item->patient_id,
                'contact_name' => $item->patient?->name ?? $item->person_name,
            ]);

            if ($result['ok']) {
                $sent++;
                $item->logAttempt('Bulk WhatsApp sent from Missed Calls list');
            } else {
                $failed++;
                CommActivityLog::log($item->id, 'whatsapp_blocked', 'Bulk WhatsApp failed: ' . ($result['reason'] ?? 'unknown'));
            }
        }

        $message = "{$sent} message(s) sent.";
        if ($failed > 0) {
            $message .= " {$failed} skipped (no phone or consent not granted).";
        }

        return $this->success(['sent' => $sent, 'failed' => $failed], $message);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /api/v1/relationship/missed-calls/bulk-dismiss
    // ─────────────────────────────────────────────────────────────────────

    public function bulkDismiss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'comm_ids'   => ['required', 'array', 'min:1'],
            'comm_ids.*' => ['integer', 'exists:communication_queue,id'],
        ]);

        $items = CommunicationQueue::whereIn('id', $validated['comm_ids'])->get();

        foreach ($items as $item) {
            $item->dismiss($request->user()?->id);
        }

        return $this->success(null, count($items) . ' item(s) dismissed.');
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
