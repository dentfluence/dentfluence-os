<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\RelationshipJourney;
use App\Support\Features\Feature;
use Illuminate\View\View;

/**
 * LeadPipelineController — PRE (Phase 1 · Workstream D, slice 2).
 *
 * A read-only, relationship-centric lead board for the receptionist. Leads are
 * grouped into columns by the RELIABLE legacy `leads.stage` column (the same
 * stage vocabulary as the legacy PRM board), so the grouping is authoritative
 * today. The shadow relationship-journey state is shown alongside each lead for
 * context ONLY, and only when the feature flag is on (journeys are not
 * authoritative until Blueprint Phase 4).
 *
 * Fully additive and non-disruptive:
 *   - NEW route (relationship.pipeline). The legacy PRM board is untouched.
 *   - No writes, no data migration — purely a read view.
 *   - The one optional behaviour (surfacing shadow journey state) sits behind
 *     the flag `relationship.pipeline_journey_column`, which defaults OFF.
 *
 * Route: GET /relationship/pipeline  [relationship.pipeline]
 */
class LeadPipelineController extends Controller
{
    /**
     * Board columns, keyed by the legacy `leads.stage` value.
     * Mirrors PrmController::getStages() plus a terminal "Lost" column so the
     * PRE board shows the full picture. Kept local (self-contained) so this
     * slice never reaches into the PRM controller.
     */
    private const STAGES = [
        'new_lead'     => ['label' => 'New Lead',     'color' => '#534AB7', 'bg' => '#EEEDFE'],
        'contacted'    => ['label' => 'Contacted',    'color' => '#0F6E56', 'bg' => '#E1F5EE'],
        'appointment'  => ['label' => 'Appointment',  'color' => '#854F0B', 'bg' => '#FAEEDA'],
        'consultation' => ['label' => 'Consultation', 'color' => '#185FA5', 'bg' => '#E6F1FB'],
        'plan_given'   => ['label' => 'Plan Given',   'color' => '#993556', 'bg' => '#FBEAF0'],
        'converted'    => ['label' => 'Converted',    'color' => '#3B6D11', 'bg' => '#EAF3DE'],
        'lost'         => ['label' => 'Lost',         'color' => '#8A1F1F', 'bg' => '#FDECEC'],
    ];

    /** Max cards rendered per column (rest summarised as "+N more"). */
    private const CARDS_PER_COLUMN = 40;

    public function index(): View
    {
        // Optional context surface — OFF by default (journeys are shadow).
        $showJourney = Feature::enabled('relationship.pipeline_journey_column');

        // Read side: pull leads with just the columns the board needs.
        // Grouping uses the reliable legacy `stage` column.
        $leads = Lead::query()
            ->select([
                'id', 'name', 'phone', 'stage', 'treatment', 'lead_value',
                'followup_date', 'assigned_to', 'urgency', 'relationship_id',
            ])
            ->orderByRaw('followup_date IS NULL, followup_date ASC')
            ->orderByDesc('id')
            ->get();

        $grouped = $leads->groupBy('stage');

        // Shadow journey state, keyed by relationship_id — fetched in ONE query
        // and only when the flag is on. Context only; never used for grouping.
        $journeyByRelationship = [];
        if ($showJourney) {
            $relationshipIds = $leads->pluck('relationship_id')->filter()->unique()->values();
            if ($relationshipIds->isNotEmpty()) {
                $journeyByRelationship = RelationshipJourney::query()
                    ->whereIn('relationship_id', $relationshipIds)
                    ->where('type', RelationshipJourney::TYPE_LEAD)
                    ->pluck('state', 'relationship_id')
                    ->toArray();
            }
        }

        // Build ordered columns with per-stage count + pipeline value.
        $columns = [];
        foreach (self::STAGES as $key => $meta) {
            $bucket = $grouped->get($key, collect());
            $columns[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'bg'     => $meta['bg'],
                'count'  => $bucket->count(),
                'value'  => (float) $bucket->sum('lead_value'),
                'leads'  => $bucket->take(self::CARDS_PER_COLUMN),
                'hidden' => max(0, $bucket->count() - self::CARDS_PER_COLUMN),
            ];
        }

        // Headline numbers (active = everything except the two terminal stages).
        $activeCount   = $leads->whereNotIn('stage', ['converted', 'lost'])->count();
        $pipelineValue = (float) $leads->whereNotIn('stage', ['converted', 'lost'])->sum('lead_value');

        return view('relationship.pipeline.index', [
            'columns'               => $columns,
            'totalLeads'            => $leads->count(),
            'activeCount'           => $activeCount,
            'pipelineValue'         => $pipelineValue,
            'showJourney'           => $showJourney,
            'journeyByRelationship' => $journeyByRelationship,
            // When PRM is secondary (slice F3), offer a link back to the legacy board.
            'prmSecondary'          => Feature::enabled('prm.secondary'),
        ]);
    }
}
