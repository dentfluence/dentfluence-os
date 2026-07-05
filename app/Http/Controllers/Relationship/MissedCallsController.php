<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\CommActivityLog;
use App\Models\CommunicationQueue;
use App\Services\Relationship\YesterdayReviewService;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MissedCallsController — full paginated "Missed Calls" list (Relationship
 * Engine · Today's Actions, Yesterday's Missed Calls widget).
 *
 * The /relationship/today dashboard widget only ever samples up to
 * config('relationship_rules.today_actions.max_per_category') rows (default
 * 50) for exactly "yesterday". This page is the true backlog view: every
 * pending missed-call queue item (yesterday or older), paginated, filterable,
 * with row selection + bulk WhatsApp / bulk Dismiss, and a per-row Ignore.
 *
 * Reuses YesterdayReviewService::missedCallsQuery() — the same source of
 * truth the dashboard widget reads from — rather than duplicating the query.
 */
class MissedCallsController extends Controller
{
    public function __construct(
        private readonly YesterdayReviewService $yesterdayReview,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // GET /relationship/today/missed-calls
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $showIgnored = $request->boolean('show_ignored');

        $query = $this->yesterdayReview->missedCallsQuery($showIgnored);

        // Filters — mirrors the existing Communication List filter convention
        // ($request->only([...]) + if (!empty(...))).
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

        $items = $query->paginate(50)->withQueryString();

        // Purpose options for the filter dropdown — reuse the model's lookup
        // table rather than hardcoding a duplicate list in the view.
        $purposeOptions = CommunicationQueue::PURPOSES;

        return view('relationship.today.missed-calls', compact('items', 'filters', 'purposeOptions', 'showIgnored'));
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/missed-calls/bulk-whatsapp
    // ─────────────────────────────────────────────────────────────────────

    public function bulkWhatsapp(Request $request, OutboundMessageService $outbound): RedirectResponse
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

        return back()->with('success', $message);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/missed-calls/bulk-dismiss
    // ─────────────────────────────────────────────────────────────────────

    public function bulkDismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'comm_ids'   => ['required', 'array', 'min:1'],
            'comm_ids.*' => ['integer', 'exists:communication_queue,id'],
        ]);

        $items = CommunicationQueue::whereIn('id', $validated['comm_ids'])->get();

        foreach ($items as $item) {
            $item->dismiss(auth()->id());
        }

        return back()->with('success', count($items) . ' item(s) dismissed.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/missed-calls/{id}/ignore
    // POST /relationship/today/missed-calls/{id}/unignore
    // ─────────────────────────────────────────────────────────────────────

    public function ignore(CommunicationQueue $missedCall): RedirectResponse
    {
        $missedCall->ignore(auth()->id());

        return back()->with('success', 'Removed from the missed-calls queue. You can restore it from "Show ignored".');
    }

    public function unignore(CommunicationQueue $missedCall): RedirectResponse
    {
        $missedCall->unignore();

        return back()->with('success', 'Restored to the missed-calls queue.');
    }
}
