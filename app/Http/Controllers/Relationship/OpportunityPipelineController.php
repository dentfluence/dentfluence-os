<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\RelationshipJourney;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use App\Support\Features\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * OpportunityPipelineController — PRE (Phase 1 · Workstream D, slice 3;
 * writes added 2026-07-06 — full retirement of the legacy Communication
 * "Opportunity Engine" board).
 *
 * A relationship-centric board of treatment opportunities. Columns come from
 * the RELIABLE legacy `treatment_opportunities.status` (the vocabulary already
 * declared on TreatmentOpportunity::STAGES), so grouping is authoritative.
 * The shadow opportunity-journey state is shown per card for context ONLY,
 * and only when `relationship.opportunity_journey_column` is on (journeys are
 * shadow until Blueprint Phase 4).
 *
 * This is now the ONLY place opportunities are managed — the legacy
 * communication/opportunities screen redirects here (routes/communication.php).
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
        // Eager-load the relationship (plain name) + assigned staff for a safe display label.
        $opportunities = TreatmentOpportunity::query()
            ->with(['relationship:id,name,phone', 'assignedStaff:id,name'])
            ->select([
                'id', 'relationship_id', 'patient_id', 'type', 'label', 'status',
                'priority', 'follow_up_date', 'estimated_value', 'assigned_to', 'updated_at',
                'declined_reason',
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

        $openStatuses   = array_diff(array_keys(TreatmentOpportunity::STAGES), ['completed', 'declined']);
        $openCount      = $opportunities->whereIn('status', $openStatuses)->count();
        $pipelineValue  = (float) $opportunities->whereIn('status', $openStatuses)->sum('estimated_value');
        $followUpToday  = $opportunities->filter(fn ($o) => $o->due_today)->count();
        $convertedMTD   = TreatmentOpportunity::where('status', 'completed')
                            ->whereMonth('updated_at', now()->month)
                            ->count();

        $staff = User::orderBy('name')->get(['id', 'name']);

        return view('relationship.opportunities.index', [
            'columns'              => $columns,
            'total'                => $opportunities->count(),
            'openCount'            => $openCount,
            'pipelineValue'        => $pipelineValue,
            'followUpToday'        => $followUpToday,
            'convertedMTD'         => $convertedMTD,
            'staff'                => $staff,
            'showJourney'          => $showJourney,
            'journeyByOpportunity' => $journeyByOpportunity,
        ]);
    }

    // ── Store — save new opportunity (Add Opportunity modal) ──────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id'      => 'required|exists:patients,id',
            'type'            => 'required|string|max:100',
            'label'           => 'nullable|string|max:150',
            'priority'        => 'required|in:high,medium,low',
            'estimated_value' => 'nullable|numeric|min:0',
            'follow_up_date'  => 'required|date',
            'follow_up_time'  => 'nullable|date_format:H:i',
            'assigned_to'     => 'nullable|exists:users,id',
            'notes'           => 'nullable|string|max:1000',
        ]);

        // Auto-link to the patient's Relationship record if one exists.
        $patient        = Patient::find($validated['patient_id']);
        $relationshipId = $patient?->relationship_id;

        TreatmentOpportunity::create([
            ...$validated,
            'relationship_id' => $relationshipId,
            'status'          => 'prospect',   // always starts at Identified
            'created_by'      => Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Opportunity added.']);
        }

        return redirect()->route('relationship.opportunities')
                         ->with('success', 'Opportunity added successfully.');
    }

    // ── Patient Search — AJAX autocomplete for Add Opportunity modal ───────────

    public function patientSearch(Request $request)
    {
        $term = $request->get('q', '');

        $patients = Patient::where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($patients);
    }

    // ── Detail Modal — rendered as a partial for the board's popup card ───────

    public function detailModal(int $id)
    {
        $opportunity = TreatmentOpportunity::with(['patient', 'assignedStaff', 'author', 'treatmentPlan'])
            ->findOrFail($id);

        $notes = $this->notesFor($opportunity);

        return view('relationship.opportunities._detail-card', compact('opportunity', 'notes'));
    }

    /**
     * Timestamped notes for this opportunity — Suggestion (staff observation)
     * or Response (what the patient said), newest first. Reuses the existing
     * ActivityEngine/Activity ledger instead of a new table — see
     * docs/feature-specs/feature-spec-stage-notes.md.
     */
    private function notesFor(TreatmentOpportunity $opportunity)
    {
        return Activity::query()
            ->with('actor')
            ->where('subject_type', TreatmentOpportunity::class)
            ->where('subject_id', $opportunity->id)
            ->ofEvent('opportunity.note_added')
            ->recent()
            ->get();
    }

    // ── Add a timestamped note (Suggestion / Patient Response) ────────────────

    public function addNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note_type' => ['required', 'in:suggestion,response'],
            'text'      => ['required', 'string', 'max:1000'],
        ]);

        $opportunity = TreatmentOpportunity::with('patient')->findOrFail($id);

        $label = $data['note_type'] === 'suggestion' ? 'Suggestion' : 'Patient response';

        app(ActivityEngine::class)->log(
            subject       : $opportunity,
            event         : 'opportunity.note_added',
            actor         : Auth::user(),
            metadata      : [
                'note_type'     => $data['note_type'],
                'text'          => $data['text'],
                'stage_at_time' => $opportunity->status,
            ],
            relationshipId: $opportunity->relationship_id,
            description   : "{$label} added on Opportunity #{$opportunity->id}: " . Str::limit($data['text'], 80),
        );

        return response()->json(['success' => true]);
    }

    // ── Update Stage — drag-drop / Move-to dropdown ────────────────────────────

    public function updateStage(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:prospect,discussed,quoted,accepted,completed,declined',
            'reason' => 'nullable|string|max:1000',
        ]);

        $opp = TreatmentOpportunity::findOrFail($id);

        $update = ['status' => $request->status];
        // Optional reason, only meaningful (and only saved) when declining.
        if ($request->status === 'declined') {
            $update['declined_reason'] = $request->filled('reason') ? $request->reason : null;
        }
        $opp->update($update);

        $stageInfo = TreatmentOpportunity::STAGES[$request->status] ?? [];

        return response()->json([
            'success'     => true,
            'status'      => $request->status,
            'stage_label' => $stageInfo['label'] ?? $request->status,
        ]);
    }

    // ── Convert to PRE Lead ─────────────────────────────────────────────────────

    public function convertToLead(Request $request, int $id)
    {
        $request->validate([
            'stage'       => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $opp = TreatmentOpportunity::with(['patient', 'assignedStaff'])->findOrFail($id);

        DB::transaction(function () use ($opp, $request) {
            $opp->update(['status' => 'completed']);

            // Lead::assigned_to is a plain staff-name string (unlike TreatmentOpportunity's
            // FK column of the same name) — resolve to a name before saving.
            $assignedName = $request->filled('assigned_to')
                ? User::find($request->assigned_to)?->name
                : $opp->assignedStaff?->name;

            Lead::create([
                'name'        => $opp->patient->name ?? 'Unknown',
                'phone'       => $opp->patient->phone ?? '',
                'stage'       => $request->stage ?? 'new',
                'lead_source' => 'referral',    // originated from internal clinical tagging
                'source'      => 'opportunity', // internal tag
                'treatment'   => $opp->display_label,
                'lead_value'  => $opp->estimated_value,
                'assigned_to' => $assignedName,
                'notes'       => "Converted from Treatment Opportunity #{$opp->id}.\n\n" . ($opp->notes ?? ''),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Opportunity converted to a lead in the Lead Pipeline.',
        ]);
    }
}
