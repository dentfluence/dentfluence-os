<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\RelationshipJourney;
use App\Models\TreatmentOpportunity;
use App\Support\Features\Feature;
use Illuminate\View\View;

/**
 * OpportunityPipelineController — PRE (Phase 1 · Workstream D, slice 3).
 *
 * A read-only, relationship-centric board of treatment opportunities. Columns
 * come from the RELIABLE legacy `treatment_opportunities.status` (the vocabulary
 * already declared on TreatmentOpportunity::STAGES), so grouping is
 * authoritative today. The shadow opportunity-journey state is shown per card
 * for context ONLY, and only when `relationship.opportunity_journey_column` is
 * on (journeys are shadow until Blueprint Phase 4).
 *
 * Fully additive: NEW route (relationship.opportunities); the legacy
 * Communication / Opportunity surfaces are untouched. No writes, no migration.
 *
 * Person names are read from the plain Relationship spine (never the encrypted
 * Patient fields).
 *
 * Route: GET /relationship/opportunities  [relationship.opportunities]
 */
class OpportunityPipelineController extends Controller
{
    /** Max cards rendered per column (rest summarised as "+N more"). */
    private const CARDS_PER_COLUMN = 40;

    public function index(): View
    {
        $showJourney = Feature::enabled('relationship.opportunity_journey_column');

        // Read side — legacy status is the reliable grouping key.
        // Eager-load the relationship (plain name) for a safe display label.
        $opportunities = TreatmentOpportunity::query()
            ->with(['relationship:id,name,phone'])
            ->select([
                'id', 'relationship_id', 'patient_id', 'type', 'label', 'status',
                'priority', 'follow_up_date', 'estimated_value', 'assigned_to',
            ])
            ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $opportunities->groupBy('status');

        // Shadow journey state per opportunity (keyed by metadata.opportunity_id).
        // One query, flag-gated. Context only; never used for grouping.
        $journeyByOpportunity = [];
        if ($showJourney) {
            $relationshipIds = $opportunities->pluck('relationship_id')->filter()->unique()->values();
            if ($relationshipIds->isNotEmpty()) {
                RelationshipJourney::query()
                    ->where('type', RelationshipJourney::TYPE_OPPORTUNITY)
                    ->whereIn('relationship_id', $relationshipIds)
                    ->get(['metadata', 'state'])
                    ->each(function ($journey) use (&$journeyByOpportunity) {
                        $oppId = $journey->metadata['opportunity_id'] ?? null;
                        if ($oppId) {
                            $journeyByOpportunity[$oppId] = $journey->state;
                        }
                    });
            }
        }

        // Build ordered columns from the model's canonical STAGES map.
        $columns = [];
        foreach (TreatmentOpportunity::STAGES as $key => $meta) {
            $bucket = $grouped->get($key, collect());
            $columns[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'count'  => $bucket->count(),
                'value'  => (float) $bucket->sum('estimated_value'),
                'items'  => $bucket->take(self::CARDS_PER_COLUMN),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_COLUMN),
            ];
        }

        $openStatuses  = array_diff(array_keys(TreatmentOpportunity::STAGES), ['completed', 'declined']);
        $openCount     = $opportunities->whereIn('status', $openStatuses)->count();
        $pipelineValue = (float) $opportunities->whereIn('status', $openStatuses)->sum('estimated_value');

        return view('relationship.opportunities.index', [
            'columns'              => $columns,
            'total'                => $opportunities->count(),
            'openCount'            => $openCount,
            'pipelineValue'        => $pipelineValue,
            'showJourney'          => $showJourney,
            'journeyByOpportunity' => $journeyByOpportunity,
        ]);
    }
}
