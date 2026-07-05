<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Models\CommActivityLog;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * CommunicationController — Communication List
 * PRM Update 2026-06-13
 * Phase 1 2026-06-13: attempt tracking, mandatory outcome, SLA on create
 */
class CommunicationController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $tab     = $request->get('tab', 'pending');
        $search  = $request->get('search');
        $filters = $request->only([
            'filter_date','filter_owner','filter_channel',
            'filter_type','filter_status','filter_priority',
        ]);

        $query = CommunicationQueue::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('person_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if (!empty($filters['filter_date']))     $query->whereDate('created_at', $filters['filter_date']);
        if (!empty($filters['filter_owner']))    $query->where('assigned_to', $filters['filter_owner']);
        if (!empty($filters['filter_channel']))  $query->where('channel', $filters['filter_channel']);
        if (!empty($filters['filter_type']))     $query->where('comm_type', $filters['filter_type']);
        if (!empty($filters['filter_status']))   $query->where('status', $filters['filter_status']);
        if (!empty($filters['filter_priority'])) $query->where('priority', $filters['filter_priority']);

        $baseQuery = clone $query;

        switch ($tab) {
            case 'today':
                $query->where(function ($q) {
                    $q->whereDate('created_at', today())
                      ->orWhereDate('follow_up_date', today())
                      ->orWhereDate('due_at', today());
                });
                break;
            case 'overdue':
                $query->where(function ($q) {
                    $q->where('is_overdue', true)->orWhere('status', 'overdue');
                });
                break;
            case 'completed':
                $query->where('status', 'closed');
                break;
            case 'my_queue':
                $query->where(function ($q) {
                    $q->where('created_by', auth()->id())
                      ->orWhere('assigned_to', auth()->user()->name ?? '');
                });
                break;
            case 'all': break;
            default:
                $query->where('status', 'pending');
        }

        $items = $query->orderByRaw("FIELD(priority,'high','medium','low')")
                       ->orderBy('created_at','desc')
                       ->get();

        $counts = [
            'pending'   => (clone $baseQuery)->where('status','pending')->count(),
            'today'     => (clone $baseQuery)->where(function ($q) {
                $q->whereDate('created_at', today())->orWhereDate('follow_up_date', today());
            })->count(),
            'overdue'   => (clone $baseQuery)->where(function ($q) {
                $q->where('is_overdue', true)->orWhere('status', 'overdue');
            })->count(),
            'completed' => (clone $baseQuery)->where('status','closed')->whereDate('updated_at',today())->count(),
            'my_queue'  => (clone $baseQuery)->where(function ($q) {
                $q->where('created_by', auth()->id())
                  ->orWhere('assigned_to', auth()->user()->name ?? '');
            })->count(),
            'all'       => (clone $baseQuery)->count(),
        ];

        $headerCounts = [
            'pending'      => CommunicationQueue::where('status','pending')->count(),
            'overdue'      => CommunicationQueue::where(function ($q) {
                $q->where('is_overdue', true)->orWhere('status', 'overdue');
            })->count(),
            'closed_today' => CommunicationQueue::where('status','closed')
                                ->whereDate('updated_at', today())->count(),
            'my_queue'     => CommunicationQueue::where(function ($q) {
                $q->where('created_by', auth()->id())
                  ->orWhere('assigned_to', auth()->user()->name ?? '');
            })->count(),
        ];

        return view('communication.manager.index', [
            'items'        => $items,
            'tab'          => $tab,
            'counts'       => $counts,
            'headerCounts' => $headerCounts,
            'search'       => $search,
            'filters'      => $filters,
            'users'        => User::orderBy('name')->get(['id','name']),
            'channels'     => CommunicationQueue::CHANNELS,
            'commTypes'    => CommunicationQueue::COMM_TYPES,
            'statuses'     => CommunicationQueue::STATUSES,
            'pageTitle'    => 'Communication List',
            'activeNav'    => 'manager',
        ]);
    }

    // ── Add Communication form ─────────────────────────────────────────────

    public function logForm(): View
    {
        return view('communication.manager.log-form', [
            'channels'    => CommunicationQueue::CHANNELS,
            'commTypes'   => CommunicationQueue::COMM_TYPES,
            'purposes'    => CommunicationQueue::PURPOSES,
            'nextActions' => CommunicationQueue::NEXT_ACTIONS,
            'users'       => User::orderBy('name')->get(['id','name']),
            'pageTitle'   => 'Add Communication',
            'activeNav'   => 'manager',
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────

    public function logStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone'          => 'required|string|max:20',
            'person_name'    => 'required|string|max:255',
            'comm_type'      => 'required|string',
            'patient_id'     => 'nullable|integer|exists:patients,id',
            'channel'        => 'required|string',
            'direction'      => 'required|in:incoming,outgoing',
            'purpose'        => 'nullable|string',
            'note'           => 'nullable|string|max:3000',
            'next_action'    => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'follow_up_time' => 'nullable|string|max:10',
            'priority'       => 'required|in:high,medium,low',
            'assigned_to'    => 'nullable|string|max:255',
            'move_to'        => 'required|string',
        ]);

        $status = 'pending';
        $isOverdue = false;
        $overdueSince = null;

        if ($validated['move_to'] === 'archive' || $validated['comm_type'] === 'spam') {
            $status = 'closed';
        } elseif (!empty($validated['follow_up_date'])) {
            $fud = Carbon::parse($validated['follow_up_date']);
            if ($fud->isPast()) {
                $isOverdue    = true;
                $overdueSince = $fud->diffForHumans(now(), true);
                $status       = 'overdue';
            }
        }

        $assignedTo = !empty($validated['assigned_to'])
            ? $validated['assigned_to']
            : auth()->user()->name;

        // Determine source_engine from channel
        $sourceEngine = match($validated['channel'] ?? '') {
            'instagram', 'facebook', 'website' => 'inbound',
            default                            => 'manual',
        };
        // Caller can override via hidden field (for future engines)
        if (!empty($validated['source_engine'])) {
            $sourceEngine = $validated['source_engine'];
        }

        $comm = CommunicationQueue::create([
            'phone'            => $validated['phone'],
            'person_name'      => $validated['person_name'],
            'comm_type'        => $validated['comm_type'],
            'patient_id'       => $validated['patient_id'] ?? null,
            'channel'          => $validated['channel'],
            'direction'        => $validated['direction'],
            'purpose'          => $validated['purpose'] ?? null,
            'note'             => $validated['note'] ?? null,
            'next_action'      => $validated['next_action'] ?? null,
            'follow_up_date'   => $validated['follow_up_date'] ?? null,
            'follow_up_time'   => $validated['follow_up_time'] ?? null,
            'priority'         => $validated['priority'],
            'assigned_to'      => $assignedTo,
            'assigned_avatar'  => strtoupper(substr($assignedTo, 0, 1)),
            'move_to'          => $validated['move_to'],
            'status'           => $status,
            'is_overdue'       => $isOverdue,
            'overdue_since'    => $overdueSince,
            'source_engine'    => $sourceEngine,
            'attempt_count'    => 0,
            'sla_breached'     => false,
            'created_by'       => auth()->id(),
            'last_modified_by' => auth()->id(),
        ]);

        // Set SLA deadline based on source_engine + priority (Phase 1)
        $comm->setSlaDeadline();
        $comm->save();

        CommActivityLog::log($comm->id, 'created', 'Communication created', [
            'comm_type' => $comm->comm_type,
            'channel'   => $comm->channel,
            'move_to'   => $comm->move_to,
        ]);

        $moveTo = $validated['move_to'];

        if ($moveTo === 'prm_pipeline' ||
            in_array($validated['comm_type'], ['new_lead', 'existing_patient'])) {
            $this->createLeadFromComm($comm);
            CommActivityLog::log($comm->id, 'moved', 'Lead created in Pipeline', ['destination' => 'prm_pipeline']);
            // Phase 8 PRM Retirement (Slice 5) — PRE's lead pipeline replaces the retired PRM board.
            return redirect()->route('relationship.pipeline')
                ->with('success', 'Communication logged — lead added to the pipeline.');
        }

        if ($moveTo === 'follow_ups') {
            CommActivityLog::log($comm->id, 'moved', 'Moved to Follow-ups', ['destination' => 'follow_ups']);
            return redirect()->route('communication.followup.index')
                ->with('success', 'Communication logged — added to Follow-ups.');
        }

        if ($moveTo === 'archive' || $validated['comm_type'] === 'spam') {
            return redirect()->route('communication.manager.index')
                ->with('success', 'Communication archived.');
        }

        return redirect()->route('communication.manager.index')
            ->with('success', 'Communication logged.');
    }

    // ── Show ───────────────────────────────────────────────────────────────

    public function show(int $id): View
    {
        $comm = CommunicationQueue::with(['patient','activityLogs','createdByUser'])->findOrFail($id);

        return view('communication.manager.show', [
            'comm'        => $comm,
            'users'       => User::orderBy('name')->get(['id','name']),
            'channels'    => CommunicationQueue::CHANNELS,
            'commTypes'   => CommunicationQueue::COMM_TYPES,
            'purposes'    => CommunicationQueue::PURPOSES,
            'nextActions' => CommunicationQueue::NEXT_ACTIONS,
            'statuses'    => CommunicationQueue::STATUSES,
            'pageTitle'   => 'Communication Detail',
            'activeNav'   => 'manager',
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): RedirectResponse
    {
        $comm = CommunicationQueue::findOrFail($id);

        $validated = $request->validate([
            'phone'          => 'required|string|max:20',
            'person_name'    => 'required|string|max:255',
            'comm_type'      => 'required|string',
            'channel'        => 'required|string',
            'direction'      => 'required|in:incoming,outgoing',
            'purpose'        => 'nullable|string',
            'note'           => 'nullable|string|max:3000',
            'next_action'    => 'nullable|string',
            'follow_up_date' => 'nullable|date',
            'follow_up_time' => 'nullable|string|max:10',
            'priority'       => 'required|in:high,medium,low',
            'assigned_to'    => 'nullable|string|max:255',
        ]);

        $old = $comm->only(['status','assigned_to','next_action','priority']);
        $comm->fill($validated);
        $comm->last_modified_by = auth()->id();
        $comm->recalculateOverdue();
        $comm->save();

        CommActivityLog::log($comm->id, 'edited', 'Communication updated', [
            'old' => $old,
            'new' => $comm->only(['status','assigned_to','next_action','priority']),
        ]);

        return redirect()->route('communication.manager.show', $comm->id)
            ->with('success', 'Communication updated.');
    }

    // ── Assign ─────────────────────────────────────────────────────────────

    public function assign(Request $request, int $id): RedirectResponse
    {
        $comm = CommunicationQueue::findOrFail($id);
        $validated = $request->validate(['assigned_to' => 'required|string|max:255']);

        $old = $comm->assigned_to;
        $comm->assigned_to      = $validated['assigned_to'];
        $comm->assigned_avatar  = strtoupper(substr($validated['assigned_to'], 0, 1));
        $comm->last_modified_by = auth()->id();
        $comm->save();

        CommActivityLog::log($comm->id, 'assigned', "Assigned from {$old} to {$comm->assigned_to}", [
            'old_owner' => $old, 'new_owner' => $comm->assigned_to,
        ]);

        return back()->with('success', "Assigned to {$comm->assigned_to}.");
    }

    // ── Move ───────────────────────────────────────────────────────────────

    public function move(Request $request, int $id): RedirectResponse
    {
        $comm = CommunicationQueue::findOrFail($id);
        $validated = $request->validate(['move_to' => 'required|string']);

        $dest = $validated['move_to'];
        $comm->move_to          = $dest;
        $comm->last_modified_by = auth()->id();
        $comm->save();

        CommActivityLog::log($comm->id, 'moved', "Moved to {$dest}", ['destination' => $dest]);

        if ($dest === 'prm_pipeline') {
            $this->createLeadFromComm($comm);
            // Phase 8 PRM Retirement (Slice 5) — PRE's lead pipeline replaces the retired PRM board.
            return redirect()->route('relationship.pipeline')
                ->with('success', 'Moved to the pipeline.');
        }
        if ($dest === 'follow_ups') {
            return redirect()->route('communication.followup.index')
                ->with('success', 'Moved to Follow-ups.');
        }
        if ($dest === 'archive') {
            $comm->status = 'closed';
            $comm->save();
        }

        return back()->with('success', 'Communication moved.');
    }

    // ── Log Attempt (Phase 1) ──────────────────────────────────────────────

    /**
     * POST /communication/manager/{id}/attempt
     * Records one contact attempt. Increments attempt_count, stamps
     * last_attempt_at, updates response_notes, checks SLA breach.
     * Outcome is optional here — staff can just log the try and move on.
     */
    public function logAttempt(Request $request, int $id): RedirectResponse
    {
        $comm = CommunicationQueue::findOrFail($id);

        $validated = $request->validate([
            'attempt_notes' => 'nullable|string|max:500',
        ]);

        $comm->logAttempt($validated['attempt_notes'] ?? '');

        return back()->with('success', "Attempt #{$comm->attempt_count} logged.");
    }

    // ── Close With Outcome (Phase 1 — replaces simple markClosed) ──────────

    /**
     * POST /communication/manager/{id}/close
     * Outcome is mandatory. Staff must record what happened before
     * a communication can be marked closed. This is the zero-leakage rule.
     */
    public function closeWithOutcome(Request $request, int $id): RedirectResponse
    {
        $comm = CommunicationQueue::findOrFail($id);

        $validated = $request->validate([
            'outcome'        => 'required|string|in:' . implode(',', array_keys(CommunicationQueue::OUTCOMES)),
            'outcome_reason' => 'nullable|string|max:1000',
        ]);

        $comm->status           = 'closed';
        $comm->is_overdue       = false;
        $comm->sla_breached     = $comm->sla_breached; // preserve existing breach flag
        $comm->outcome          = $validated['outcome'];
        $comm->outcome_reason   = $validated['outcome_reason'] ?? null;
        $comm->last_modified_by = auth()->id();
        $comm->save();

        CommActivityLog::log(
            $comm->id,
            'closed',
            'Closed — ' . (CommunicationQueue::OUTCOMES[$validated['outcome']] ?? $validated['outcome']),
            [
                'outcome'        => $validated['outcome'],
                'outcome_reason' => $validated['outcome_reason'],
            ]
        );

        return redirect()->route('communication.manager.index')
            ->with('success', 'Communication closed. Outcome recorded.');
    }

    // ── Bulk Action ────────────────────────────────────────────────────────

    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action'     => 'required|string',
            'comm_ids'   => 'required|array',
            'comm_ids.*' => 'integer|exists:communication_queue,id',
            'assign_to'  => 'nullable|string|max:255',
        ]);

        $comms  = CommunicationQueue::whereIn('id', $validated['comm_ids'])->get();
        $action = $validated['action'];

        foreach ($comms as $comm) {
            $comm->last_modified_by = auth()->id();
            switch ($action) {
                case 'mark_closed':
                    $comm->status = 'closed';
                    $comm->is_overdue = false;
                    $comm->save();
                    CommActivityLog::log($comm->id, 'closed', 'Bulk: marked closed');
                    break;
                case 'archive':
                    $comm->status  = 'closed';
                    $comm->move_to = 'archive';
                    $comm->save();
                    CommActivityLog::log($comm->id, 'closed', 'Bulk: archived');
                    break;
                case 'move_followups':
                    $comm->move_to = 'follow_ups';
                    $comm->save();
                    CommActivityLog::log($comm->id, 'moved', 'Bulk: moved to Follow-ups');
                    break;
                case 'assign':
                    $to = $validated['assign_to'] ?? null;
                    if ($to) {
                        $comm->assigned_to     = $to;
                        $comm->assigned_avatar = strtoupper(substr($to, 0, 1));
                        $comm->save();
                        CommActivityLog::log($comm->id, 'assigned', "Bulk: assigned to {$to}");
                    }
                    break;
            }
        }

        $count = count($validated['comm_ids']);
        if ($action === 'move_followups') return redirect()->route('communication.followup.index')->with('success', "{$count} moved to Follow-ups.");
        return redirect()->route('communication.manager.index')->with('success', "{$count} communications updated.");
    }

    // ── AJAX Patient Search ────────────────────────────────────────────────

    public function patientSearch(Request $request): JsonResponse
    {
        $term = trim($request->get('q', ''));
        if (strlen($term) < 2) return response()->json([]);

        $patients = Patient::where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name',  'like', "%{$term}%")
              ->orWhere('phone',      'like', "%{$term}%")
              ->orWhere('id', is_numeric($term) ? (int)$term : 0);
        })->limit(10)->get(['id','first_name','last_name','phone']);

        return response()->json($patients->map(fn($p) => [
            'id'    => $p->id,
            'name'  => trim("{$p->first_name} {$p->last_name}"),
            'phone' => $p->phone,
            'label' => trim("{$p->first_name} {$p->last_name}") . " | {$p->phone} | #{$p->id}",
        ]));
    }

    // ── Private: Create Lead ────────────────────────────────────────────────

    private function createLeadFromComm(CommunicationQueue $comm): Lead
    {
        return Lead::firstOrCreate(
            ['phone' => $comm->phone, 'stage' => 'new_lead'],
            [
                'name'        => $comm->person_name,
                'source'      => ucfirst(str_replace('_', ' ', $comm->channel ?? 'other')),
                'notes'       => $comm->note,
                'assigned_to' => $comm->assigned_to,
                'urgency'     => $comm->priority ?? 'low',
                'stage'       => 'new_lead',
            ]
        );
    }
}
