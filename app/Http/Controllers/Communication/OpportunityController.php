<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OpportunityController extends Controller
{
    // ── Index — board + list view ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = TreatmentOpportunity::with(['patient', 'assignedStaff'])
            ->orderBy('follow_up_date');

        // Optional filters
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $opportunities = $query->get();

        // Group by status for the kanban board
        $grouped = $opportunities->groupBy('status');

        // Summary stats
        $openOpps       = $opportunities->whereNotIn('status', ['completed', 'declined']);
        $pipelineValue  = $openOpps->sum('estimated_value');
        $followUpToday  = $opportunities->filter->due_today->count();
        $convertedMTD   = TreatmentOpportunity::where('status', 'completed')
                            ->whereMonth('updated_at', now()->month)
                            ->count();

        $stats = [
            'total_open'     => $openOpps->count(),
            'followup_today' => $followUpToday,
            'converted_mtd'  => $convertedMTD,
            'pipeline_value' => $pipelineValue,
        ];

        // Staff list for assign dropdown
        $staff = User::orderBy('name')->get(['id', 'name']);

        return view('communication.opportunities.index', compact(
            'opportunities', 'grouped', 'stats', 'staff'
        ));
    }

    // ── Board — alias of index (AJAX view switch kept simple) ─────────────────

    public function board()
    {
        return $this->index(request());
    }

    // ── Store — save new opportunity ───────────────────────────────────────────

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

        // Phase 4 — auto-link to the patient's Relationship record if one exists.
        $patient        = Patient::find($validated['patient_id']);
        $relationshipId = $patient?->relationship_id;

        TreatmentOpportunity::create([
            ...$validated,
            'relationship_id' => $relationshipId,
            'status'          => 'prospect',   // always starts at Identified
            'created_by'      => Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Opportunity created.']);
        }

        return redirect()->route('communication.opportunities.index')
                         ->with('success', 'Opportunity added successfully.');
    }

    // ── Show / Detail ──────────────────────────────────────────────────────────

    public function show(int $id)
    {
        $opportunity = TreatmentOpportunity::with(['patient', 'assignedStaff', 'author', 'treatmentPlan'])
            ->findOrFail($id);

        return view('communication.opportunities.detail', compact('opportunity'));
    }

    public function detail(int $id)
    {
        return $this->show($id);
    }

    // ── Update Stage — AJAX drag-drop / quick action ───────────────────────────

    public function updateStage(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:prospect,discussed,quoted,accepted,completed,declined',
        ]);

        $opp = TreatmentOpportunity::findOrFail($id);
        $opp->update(['status' => $request->status]);

        $stageInfo = TreatmentOpportunity::STAGES[$request->status] ?? [];

        return response()->json([
            'success'     => true,
            'status'      => $request->status,
            'stage_label' => $stageInfo['label'] ?? $request->status,
        ]);
    }

    // ── Convert to PRM Lead ────────────────────────────────────────────────────

    public function convertToLead(Request $request, int $id)
    {
        $request->validate([
            'stage'       => 'nullable|string|max:50',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $opp = TreatmentOpportunity::with('patient')->findOrFail($id);

        DB::transaction(function () use ($opp, $request) {
            // Mark opportunity as completed
            $opp->update(['status' => 'completed']);

            // Create PRM Lead from this opportunity
            Lead::create([
                'name'        => $opp->patient->name ?? 'Unknown',
                'phone'       => $opp->patient->phone ?? '',
                'stage'       => $request->stage ?? 'new',
                'lead_source' => 'referral',    // originated from internal clinical tagging
                'source'      => 'opportunity', // internal tag
                'treatment'   => $opp->display_label,
                'lead_value'  => $opp->estimated_value,
                'assigned_to' => $request->assigned_to ?? $opp->assigned_to,
                'notes'       => "Converted from Treatment Opportunity #{$opp->id}.\n\n" . ($opp->notes ?? ''),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Opportunity converted to PRM lead.',
        ]);
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
}
