<?php

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\RecallEngineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * RecallPipelineController — PRE Recalls (Phase 1 · Workstream D, slice 3;
 * rebuilt 2026-07-06 per Sumit's call that "Recall Pipeline" was the wrong
 * concept — recalls don't move through funnel stages, they're a work queue
 * you clear. Dropped the 4-column kanban in favour of a flat, filterable,
 * actionable list — same pattern as the proven Missed Calls screen
 * (App\Http\Controllers\Relationship\MissedCallsController): filters,
 * checkboxes, bulk dismiss/assign with a "select all matching filter" path
 * that doesn't choke on pagination.
 *
 * Recalls live in the legacy `communication_queue` table (purpose = 'recall'
 * or source_engine = 'recall'). Fully additive: this route
 * (relationship.recalls) doesn't touch the legacy Communication List.
 *
 * Route: GET /relationship/recalls  [relationship.recalls]
 */
class RecallPipelineController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $showIgnored = $request->boolean('show_ignored');
        $filters     = $request->only(['search', 'status', 'priority', 'assigned_to']);

        $recalls = $this->filteredQuery($showIgnored, $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $base         = $this->baseQuery();
        $total        = (clone $base)->count();
        $openCount    = (clone $base)->where('status', '!=', 'closed')->count();
        $overdueCount = (clone $base)
            ->where('status', '!=', 'closed')
            ->where(function ($q) {
                $q->where('is_overdue', true)->orWhere('status', 'overdue');
            })
            ->count();

        $staff = User::where('branch_id', auth()->user()->branch_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('relationship.recalls.index', [
            'recalls'      => $recalls,
            'total'        => $total,
            'openCount'    => $openCount,
            'overdueCount' => $overdueCount,
            'showIgnored'  => $showIgnored,
            'filters'      => $filters,
            'staff'        => $staff,
            'statuses'     => CommunicationQueue::STATUSES,
        ]);
    }

    /** Recall rows only — everything else filters on top of this. */
    private function baseQuery(): Builder
    {
        return CommunicationQueue::query()
            ->where(function ($q) {
                $q->where('purpose', 'recall')
                    ->orWhere('source_engine', 'recall')
                    ->orWhere('purpose', 'like', '%recall%');
            });
    }

    /**
     * Shared by index() and the bulk actions' "select all matching filter"
     * path, so what you see is exactly what gets acted on.
     */
    private function filteredQuery(bool $showIgnored, array $filters): Builder
    {
        $query = $this->baseQuery()->with('patient:id,name,phone,relationship_id');

        if (! $showIgnored) {
            $query->notIgnored();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('person_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query
            ->orderByRaw('follow_up_date IS NULL, follow_up_date ASC')
            ->orderByDesc('id');
    }

    /**
     * Manually add a recall for a patient — staff-initiated, distinct from the
     * 6 automated triggers in RecallEngineService (tagged 'manual' so it's
     * never mistaken for one of those in reporting). Writes to the same
     * communication_queue table those triggers use, so it shows up on this
     * list and in Today's Actions / recall analytics exactly like any other
     * recall — no separate storage, no new table.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id'     => ['required', 'integer', 'exists:patients,id'],
            'priority'       => ['required', 'in:high,medium,low'],
            'follow_up_date' => ['required', 'date'],
            'note'           => ['nullable', 'string', 'max:500'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        if (! $patient->phone) {
            return back()
                ->withErrors(['patient_id' => 'This patient has no phone number on file — add one before creating a recall.'])
                ->withInput();
        }

        app(RecallEngineService::class)->createManual($patient, $data);

        return redirect()->route('relationship.recalls')
            ->with('success', 'Recall added for ' . $patient->name . '.');
    }

    /** Soft, reversible exclude — same semantics as Missed Calls' ignore. */
    public function ignore(CommunicationQueue $recall): RedirectResponse
    {
        $recall->ignore(auth()->id());

        return back()->with('success', 'Recall ignored — hidden from this list. Restore it from "Show ignored".');
    }

    public function unignore(CommunicationQueue $recall): RedirectResponse
    {
        $recall->unignore();

        return back()->with('success', 'Recall restored.');
    }

    /**
     * Bulk Dismiss — either the checked rows, or every row matching the
     * current filter (select_all=1), chunked so it doesn't load thousands of
     * rows into memory at once. Mirrors MissedCallsController::bulkDismiss().
     */
    public function bulkDismiss(Request $request): RedirectResponse
    {
        if ($request->boolean('select_all')) {
            $showIgnored = $request->boolean('show_ignored');
            $filters     = $request->only(['search', 'status', 'priority', 'assigned_to']);

            $count = 0;
            $this->filteredQuery($showIgnored, $filters)
                ->chunkById(200, function ($chunk) use (&$count) {
                    foreach ($chunk as $item) {
                        $item->dismiss(auth()->id());
                        $count++;
                    }
                });

            return back()->with('success', "{$count} recall(s) dismissed.");
        }

        $validated = $request->validate([
            'recall_ids'   => ['required', 'array', 'min:1'],
            'recall_ids.*' => ['integer', 'exists:communication_queue,id'],
        ]);

        $items = CommunicationQueue::whereIn('id', $validated['recall_ids'])->get();

        foreach ($items as $item) {
            $item->dismiss(auth()->id());
        }

        return back()->with('success', count($items) . ' recall(s) dismissed.');
    }

    /**
     * Bulk Assign — delegate a batch of recall calls to one staff member.
     * `assigned_to` on communication_queue is a plain display string (not a
     * user_id FK), matching how it's already read/shown elsewhere on this
     * table — so we store the resolved user's name.
     */
    public function bulkAssign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($validated['assigned_to']);

        if ($request->boolean('select_all')) {
            $showIgnored = $request->boolean('show_ignored');
            $filters     = $request->only(['search', 'status', 'priority', 'assigned_to']);

            $count = 0;
            $this->filteredQuery($showIgnored, $filters)
                ->chunkById(200, function ($chunk) use (&$count, $assignee) {
                    foreach ($chunk as $item) {
                        $item->update([
                            'assigned_to'      => $assignee->name,
                            'last_modified_by' => auth()->id(),
                        ]);
                        $count++;
                    }
                });

            return back()->with('success', "{$count} recall(s) assigned to {$assignee->name}.");
        }

        $data = $request->validate([
            'recall_ids'   => ['required', 'array', 'min:1'],
            'recall_ids.*' => ['integer', 'exists:communication_queue,id'],
        ]);

        CommunicationQueue::whereIn('id', $data['recall_ids'])->update([
            'assigned_to'      => $assignee->name,
            'last_modified_by' => auth()->id(),
        ]);

        return back()->with('success', count($data['recall_ids']) . ' recall(s) assigned to ' . $assignee->name . '.');
    }

    /**
     * Convert a recall into a real Opportunity — for when the call-back
     * reveals an actual treatment need, not just "come back for a cleaning".
     * Mirrors OpportunityPipelineController::store()'s field set/defaults
     * (always starts at 'prospect'). The source recall is dismissed with a
     * distinct audit reason rather than left open, since the follow-up now
     * lives on the Opportunity Pipeline instead.
     */
    public function convertToOpportunity(Request $request, CommunicationQueue $recall): RedirectResponse
    {
        $validated = $request->validate([
            'type'            => ['required', 'string', 'max:100'],
            'priority'        => ['required', 'in:high,medium,low'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'follow_up_date'  => ['required', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $recall->patient_id) {
            return back()->withErrors([
                'convert' => 'This recall has no linked patient record — cannot convert to an opportunity.',
            ]);
        }

        $patient = Patient::findOrFail($recall->patient_id);

        DB::transaction(function () use ($recall, $patient, $validated) {
            TreatmentOpportunity::create([
                'patient_id'      => $patient->id,
                'relationship_id' => $patient->relationship_id,
                'type'            => $validated['type'],
                'priority'        => $validated['priority'],
                'estimated_value' => $validated['estimated_value'] ?? null,
                'follow_up_date'  => $validated['follow_up_date'],
                'notes'           => $validated['notes'] ?: ('Converted from recall: ' . ($recall->note ?? $recall->person_name)),
                'status'          => 'prospect',
                'created_by'      => auth()->id(),
            ]);

            $recall->dismiss(auth()->id(), 'Converted to an Opportunity');
        });

        return redirect()->route('relationship.recalls')
            ->with('success', 'Converted to an Opportunity for ' . $patient->name . '.');
    }
}
