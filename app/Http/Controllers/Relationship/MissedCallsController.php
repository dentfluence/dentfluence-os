<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Services\Relationship\YesterdayReviewService;
use Illuminate\Database\Eloquent\Builder;
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
 * with row selection + bulk Dismiss (including a "select all matching filter"
 * mode that isn't capped at the page size), and a per-row Ignore.
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

        // Filters — mirrors the existing Communication List filter convention
        // ($request->only([...]) + if (!empty(...))).
        $filters = $request->only(['search', 'purpose', 'priority']);

        $query = $this->filteredQuery($showIgnored, $filters);

        $items = $query->paginate(50)->withQueryString();

        // Purpose options for the filter dropdown — reuse the model's lookup
        // table rather than hardcoding a duplicate list in the view.
        $purposeOptions = CommunicationQueue::PURPOSES;

        return view('relationship.today.missed-calls', compact('items', 'filters', 'purposeOptions', 'showIgnored'));
    }

    /**
     * Shared query builder — same filters applied for both the paginated
     * index view and the "select all matching filter" bulk action below, so
     * the two never drift apart.
     */
    private function filteredQuery(bool $showIgnored, array $filters): Builder
    {
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

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST /relationship/today/missed-calls/bulk-dismiss
    //
    // Two modes:
    //  - Normal: comm_ids[] for the rows checked on the current page (≤50).
    //  - select_all=1: re-applies the same filters server-side and dismisses
    //    every matching row, not just the loaded page — this is what lets a
    //    2,000+ item backlog get cleared in one pass instead of 50 at a time.
    // ─────────────────────────────────────────────────────────────────────

    public function bulkDismiss(Request $request): RedirectResponse
    {
        if ($request->boolean('select_all')) {
            $showIgnored = $request->boolean('show_ignored');
            $filters     = $request->only(['search', 'purpose', 'priority']);

            $count = 0;
            $this->filteredQuery($showIgnored, $filters)
                ->chunkById(200, function ($chunk) use (&$count) {
                    foreach ($chunk as $item) {
                        $item->dismiss(auth()->id());
                        $count++;
                    }
                });

            return back()->with('success', "{$count} item(s) dismissed.");
        }

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
