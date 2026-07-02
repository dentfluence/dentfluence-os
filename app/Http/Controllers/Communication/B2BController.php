<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationQueue;
use App\Models\CommActivityLog;
use App\Models\LabVendor;
use App\Models\LabCase;
use App\Models\Finance\FinanceVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * B2BController — Phase 4 Communication OS
 *
 * Handles all non-patient communications:
 *   - Vendor follow-ups (supply, equipment, service)
 *   - Lab case status comms (auto-linked to lab_cases)
 *   - Consultant referral notes
 *   - Maintenance / service comms
 *
 * UI Rule: staff entry form is dead-simple (max 5 fields visible at once).
 * Admin list view shows full detail.
 */
class B2BController extends Controller
{
    // ── Index — B2B Inbox Tab ────────────────────────────────────────────

    public function index(Request $request)
    {
        $q = CommunicationQueue::query()
            ->where(function ($q) {
                $q->where('contact_type', '!=', 'patient')
                  ->orWhere('source_engine', 'b2b');
            })
            ->with(['labVendor', 'financeVendor', 'labCase'])
            ->orderByRaw("FIELD(status,'overdue','pending','waiting_for_patient','closed')")
            ->orderByDesc('created_at');

        // Filters
        if ($type = $request->get('contact_type')) {
            $q->where('contact_type', $type);
        }
        if ($subtype = $request->get('b2b_subtype')) {
            $q->where('b2b_subtype', $subtype);
        }
        if ($status = $request->get('status')) {
            $q->where('status', $status);
        }
        if ($request->get('sla_breached')) {
            $q->where('sla_breached', true);
        }

        $items = $q->paginate(25)->withQueryString();

        // Counts for tab badges
        $counts = [
            'open'     => CommunicationQueue::where('contact_type', '!=', 'patient')
                            ->whereIn('status', ['pending', 'waiting_for_patient', 'overdue'])->count(),
            'overdue'  => CommunicationQueue::where('contact_type', '!=', 'patient')
                            ->where('sla_breached', true)->where('status', '!=', 'closed')->count(),
            'lab'      => CommunicationQueue::where('contact_type', 'lab')
                            ->where('status', '!=', 'closed')->count(),
            'vendor'   => CommunicationQueue::where('contact_type', 'vendor')
                            ->where('status', '!=', 'closed')->count(),
        ];

        // Data for filter dropdowns
        $labVendors     = LabVendor::active()->orderBy('name')->get(['id', 'name']);
        $financeVendors = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name']);

        return view('communication.b2b.index', compact(
            'items', 'counts', 'labVendors', 'financeVendors'
        ));
    }

    // ── Create form ──────────────────────────────────────────────────────

    public function create(Request $request)
    {
        // Pre-fill if arriving from a lab case or vendor record
        $prefill = [
            'contact_type' => $request->get('contact_type', 'lab'),
            'contact_id'   => $request->get('contact_id'),
            'b2b_subtype'  => $request->get('b2b_subtype'),
            'lab_case_id'  => $request->get('lab_case_id'),
        ];

        $labVendors     = LabVendor::active()->orderBy('name')->get(['id', 'name', 'phone', 'whatsapp_number']);
        $financeVendors = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name', 'phone', 'email']);

        // If a lab_case_id is given, load that case for the form context
        $labCase = null;
        if ($prefill['lab_case_id']) {
            $labCase = LabCase::with('labVendor')->find($prefill['lab_case_id']);
        }

        return view('communication.b2b.create', compact(
            'prefill', 'labVendors', 'financeVendors', 'labCase'
        ));
    }

    // ── Store ────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_type'  => 'required|in:lab,vendor,consultant',
            'contact_id'    => 'nullable|integer',
            'b2b_subtype'   => 'required|string',
            'person_name'   => 'required|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'channel'       => 'required|in:call,whatsapp,email,walk_in,other',
            'note'          => 'nullable|string|max:2000',
            'priority'      => 'nullable|in:high,medium,low',
            'follow_up_date'=> 'nullable|date',
            'lab_case_id'   => 'nullable|exists:lab_cases,id',
            'opportunity_value' => 'nullable|numeric|min:0',
        ]);

        $comm = new CommunicationQueue($validated);
        $comm->source_engine  = 'b2b';
        $comm->status         = 'pending';
        $comm->priority       = $validated['priority'] ?? 'medium';
        $comm->created_by     = Auth::id();
        $comm->last_modified_by = Auth::id();

        // Set SLA (4 hours for B2B by default)
        $comm->sla_deadline = now()->addHours(4);

        $comm->save();

        CommActivityLog::log(
            $comm->id,
            'created',
            "B2B comm created: {$comm->b2b_subtype}",
            ['contact_type' => $comm->contact_type, 'by' => Auth::id()]
        );

        return redirect()
            ->route('communication.b2b.index')
            ->with('success', 'Communication logged successfully.');
    }

    // ── Show single record ───────────────────────────────────────────────

    public function show(int $id)
    {
        $comm = CommunicationQueue::with([
            'labVendor', 'financeVendor', 'labCase.labVendor', 'activityLogs'
        ])->findOrFail($id);

        return view('communication.b2b.show', compact('comm'));
    }

    // ── Log an attempt ───────────────────────────────────────────────────

    public function logAttempt(Request $request, int $id)
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        $comm = CommunicationQueue::findOrFail($id);
        $comm->logAttempt($request->input('notes', ''));

        if ($request->ajax()) {
            return response()->json(['success' => true, 'attempt_count' => $comm->attempt_count]);
        }

        return back()->with('success', 'Attempt logged.');
    }

    // ── Close with outcome ───────────────────────────────────────────────

    public function close(Request $request, int $id)
    {
        $request->validate([
            'outcome'        => 'required|string',
            'outcome_reason' => 'nullable|string|max:500',
        ]);

        $comm = CommunicationQueue::findOrFail($id);
        $comm->status         = 'closed';
        $comm->outcome        = $request->input('outcome');
        $comm->outcome_reason = $request->input('outcome_reason');
        $comm->last_modified_by = Auth::id();
        $comm->save();

        CommActivityLog::log(
            $comm->id,
            'closed',
            "Closed: {$comm->outcome}",
            ['outcome_reason' => $comm->outcome_reason, 'by' => Auth::id()]
        );

        return back()->with('success', 'Communication closed.');
    }

    // ── AJAX: get open lab cases for a given lab vendor ──────────────────

    public function labCasesForVendor(Request $request)
    {
        $vendorId = $request->get('lab_vendor_id');

        $cases = LabCase::where('lab_vendor_id', $vendorId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->orderByDesc('created_at')
            ->get(['id', 'case_number', 'status', 'work_category', 'expected_return_date'])
            ->map(fn ($c) => [
                'id'    => $c->id,
                'label' => "#{$c->case_number} — " . LabCase::STATUS_LABELS[$c->status] . " ({$c->work_category})",
            ]);

        return response()->json($cases);
    }
}
