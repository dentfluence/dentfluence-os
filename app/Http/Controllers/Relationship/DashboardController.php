<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\Scopes\BranchScope;
use App\Models\TreatmentOpportunity;
use App\Services\Relationship\TodayActionsProjector;
use Illuminate\View\View;

/**
 * DashboardController — PRE (Phase 1 · Workstream D, slice 1).
 *
 * A read-only relationship-first landing page for the receptionist: headline
 * counts, recent relationships, and entry points into Today's Actions, search,
 * profiles and analytics.
 *
 * Additive and non-disruptive — a NEW route (relationship.dashboard). The
 * legacy PRM board is untouched. Counts use reliable legacy columns; shadow
 * journeys are shown for context only.
 *
 * Route: GET /relationship/dashboard  [relationship.dashboard]
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly TodayActionsProjector $projector,
    ) {}

    public function index(): View
    {
        $stats = [
            'relationships'      => Relationship::count(),
            'patients'           => Patient::withoutGlobalScope(BranchScope::class)->count(),
            'active_leads'       => Lead::whereNotIn('stage', ['converted', 'lost'])->count(),
            'open_opportunities' => TreatmentOpportunity::whereNotIn('status', ['completed', 'declined'])->count(),
        ];

        // Journey snapshot (shadow) — informational context only.
        $journeys = [
            'lead'        => RelationshipJourney::where('type', RelationshipJourney::TYPE_LEAD)
                                ->whereNull('closed_at')->count(),
            'opportunity' => RelationshipJourney::where('type', RelationshipJourney::TYPE_OPPORTUNITY)
                                ->whereNull('closed_at')->count(),
        ];

        // Second-row right side: one glance metric (high-priority items waiting
        // today, read from the shared Today's Actions projection — same source
        // the Daily Huddle uses, no new query) + one quick action (Add Recall)
        // that is genuinely different from the "add a lead" buttons on the left,
        // rather than a second way to do the same thing.
        $highPriorityToday = $this->projector->summary()['by_priority']['high'] ?? 0;
        $openRecalls = CommunicationQueue::where(function ($q) {
                $q->where('purpose', 'like', '%recall%')->orWhere('source_engine', 'recall');
            })
            ->where('status', '!=', 'closed')
            ->count();

        $recent = Relationship::latest()
            ->limit(12)
            ->get(['id', 'name', 'phone', 'status', 'score', 'relationship_since']);

        return view('relationship.dashboard.index', compact(
            'stats', 'journeys', 'recent', 'highPriorityToday', 'openRecalls'
        ));
    }
}
