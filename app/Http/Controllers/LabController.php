<?php

namespace App\Http\Controllers;

use App\Models\LabCase;
use App\Models\LabCaseAttachment;
use App\Models\LabVendor;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use App\Services\LabAlertService;
use App\Models\LabCasePrescription;
use App\Models\LabCaseRating;
use App\Models\LabPrescriptionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * LabController — Lab Module v2 backend engine.
 *
 * Phase 2 additions:
 *   - store() / update() now handle estimated_cost + billing_status
 *   - index() exposes billing totals for the running-totals strip
 *   - transition() updates billing_status when case is received
 */
class LabController extends Controller
{
    /** Legacy work_type values → new work categories (old drawer form compatibility) */
    private const LEGACY_CATEGORY_MAP = [
        'crown_bridge' => 'Crown & Bridge',
        'denture'      => 'Removable Prosthesis',
        'implant'      => 'Implant Prosthesis',
        'ortho'        => 'Orthodontics',
    ];

    private const SORTS = [
        'newest'        => ['created_at', 'desc'],
        'oldest'        => ['created_at', 'asc'],
        'expected_asc'  => ['expected_return_date', 'asc'],
        'expected_desc' => ['expected_return_date', 'desc'],
        'sent_asc'      => ['sent_date', 'asc'],
        'sent_desc'     => ['sent_date', 'desc'],
        'cost_asc'      => ['lab_cost', 'asc'],
        'cost_desc'     => ['lab_cost', 'desc'],
    ];

    // ── DASHBOARD ────────────────────────────────────────────────────────

    public function dashboard(LabAlertService $alerts)
    {
        // ── KPI Cards ────────────────────────────────────────────────────
        $kpis = [
            'waiting_to_send'   => \App\Models\LabCase::status('order_placed')->count(),
            'at_lab'            => \App\Models\LabCase::whereIn('status', ['impression_sent','scan_sent','trial_returned'])->count(),
            'trial_pending'     => \App\Models\LabCase::whereIn('status', ['trial_received'])->count(),
            'ready_delivery'    => \App\Models\LabCase::status('final_received')->count(),
            'delayed'           => \App\Models\LabCase::overdue()->count(),
            'today_dispatch'    => \App\Models\LabCase::whereDate('impression_sent_date', today())->count()
                                 + \App\Models\LabCase::whereDate('order_placed_date', today())->whereIn('status',['order_placed'])->count(),
            'today_receive'     => \App\Models\LabCase::whereDate('final_received_date', today())->count()
                                 + \App\Models\LabCase::whereDate('received_date', today())->count(),
            'outstanding_bills' => (float) \App\Models\LabCase::where('billing_status','unbilled')
                                     ->whereNotNull('lab_cost')->sum('lab_cost'),
        ];

        // ── Alert collections ─────────────────────────────────────────────
        $overdueCases    = $alerts->overdue()->take(8);
        $awaitingCases   = $alerts->awaitingDelivery()->take(8);
        $trialCases      = \App\Models\LabCase::with(['patient:id,name','vendor:id,name'])
                            ->whereIn('status',['trial_received'])->orderBy('expected_return_date')->take(6)->get();
        $dueToday        = $alerts->dueToday()->take(6);

        // ── Recent events (last 12 across all cases) ──────────────────────
        $recentEvents    = \App\Models\LabCaseEvent::with(['labCase.patient:id,name','createdBy:id,name'])
                            ->orderByDesc('created_at')->take(12)->get();

        // ── Upcoming expected returns (next 7 days) ───────────────────────
        $upcomingReturns = \App\Models\LabCase::with(['patient:id,name','vendor:id,name'])
                            ->whereIn('status', \App\Models\LabCase::OPEN_STATUSES)
                            ->whereDate('expected_return_date', '>=', today())
                            ->whereDate('expected_return_date', '<=', today()->addDays(7))
                            ->orderBy('expected_return_date')
                            ->take(8)->get();

        $vendors = LabVendor::active()->orderBy('name')->get(['id','name']);

        return view('lab.dashboard', compact(
            'kpis', 'overdueCases', 'awaitingCases', 'trialCases',
            'dueToday', 'recentEvents', 'upcomingReturns', 'vendors'
        ));
    }

    // ── INDEX / DASHBOARD ────────────────────────────────────────────────

    public function index(Request $request, LabAlertService $alerts)
    {
        $filters = [
            'status'    => $request->query('status', 'all'),
            'doctor'    => $request->query('doctor'),
            'vendor'    => $request->query('vendor'),
            'category'  => $request->query('category'),
            'priority'  => $request->query('priority'),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
            'sort'      => $request->query('sort', 'newest'),
        ];
        $search = $request->query('q', '');

        $query = LabCase::with(['patient:id,name,phone', 'doctor:id,name', 'vendor:id,name,whatsapp_number,phone', 'items'])
            ->search($search);

        $query = match ($filters['status']) {
            'all'             => $query,
            'active'          => $query->active(),
            'overdue'         => $query->overdue(),
            'due_today'       => $query->dueToday(),
            // 'impression_sent' tab covers both impression_sent + scan_sent
            'impression_sent' => $query->whereIn('status', ['impression_sent', 'scan_sent']),
            // 'trial' tab covers both trial_received + trial_returned
            'trial'           => $query->whereIn('status', ['trial_received', 'trial_returned']),
            default           => in_array($filters['status'], LabCase::STATUSES, true)
                                     ? $query->status($filters['status'])
                                     : $query,
        };

        if ($filters['doctor'])    $query->where('doctor_id', $filters['doctor']);
        if ($filters['vendor'])    $query->where('lab_vendor_id', $filters['vendor']);
        if ($filters['category'])  $query->where('work_category', $filters['category']);
        if ($filters['priority'])  $query->where('priority', $filters['priority']);
        if ($filters['date_from']) $query->whereDate('sent_date', '>=', $filters['date_from']);
        if ($filters['date_to'])   $query->whereDate('sent_date', '<=', $filters['date_to']);

        $this->applySort($query, $filters['sort']);

        $cases = $query->paginate(25)->withQueryString();

        // Summary counts — keyed to match filter tabs in view
        $counts = [
            'all'             => LabCase::count(),
            'active'          => LabCase::active()->count(),
            'overdue'         => LabCase::overdue()->count(),
            'draft'           => LabCase::status('draft')->count(),
            'order_placed'    => LabCase::status('order_placed')->count(),
            'impression_sent' => LabCase::whereIn('status', ['impression_sent', 'scan_sent'])->count(),
            'trial'           => LabCase::whereIn('status', ['trial_received', 'trial_returned'])->count(),
            'final_received'  => LabCase::status('final_received')->count(),
            'complete'        => LabCase::status('complete')->count(),
            'rejected'        => LabCase::status('rejected')->count(),
        ];

        // Phase 2: billing totals for the running-totals strip
        $billingTotals = [
            'month_estimated' => (float) LabCase::thisMonth()->sum('estimated_cost'),
            'month_actual'    => (float) LabCase::thisMonth()->sum('lab_cost'),
            'unbilled'        => (float) LabCase::where('billing_status', 'unbilled')
                                    ->whereNotNull('lab_cost')
                                    ->sum('lab_cost'),
            'unbilled_count'  => LabCase::where('billing_status', 'unbilled')
                                    ->whereNotNull('lab_cost')
                                    ->count(),
            'billed'          => (float) LabCase::where('billing_status', 'billed')->sum('lab_cost'),
        ];

        $branchId   = auth()->user()?->branch_id;
        $doctors    = User::where('branch_id', $branchId)
                        ->where('is_active', true)
                        ->where(fn($q) => $q->whereIn('role', \App\Models\User::DOCTOR_ROLES)
                                           ->orWhere('name', 'like', 'Dr.%'))
                        ->orderBy('name')
                        ->get(['id', 'name', 'role']);
        $vendors    = LabVendor::active()->with(['services' => fn ($q) => $q->active()])->orderBy('name')->get();
        $patients   = Patient::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'phone']);
        $categories = array_keys(LabCase::WORK_CATEGORIES);
        $status     = $filters['status'];

        $isAdmin = auth()->user()?->isAdminRole();

        return view('lab.index', compact(
            'cases', 'counts', 'filters', 'search', 'status',
            'doctors', 'vendors', 'patients', 'categories',
            'billingTotals', 'isAdmin'
        ));
    }

    // ── CREATE FORM ──────────────────────────────────────────────────────

    public function create()
    {
        // The drawer on the index page handles creation.
        // This route exists for direct navigation / deep-link; redirect to index with drawer hint.
        return redirect()->route('lab.index');
    }

    // ── STORE ────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id'           => 'required|exists:patients,id',
            'doctor_id'            => 'nullable|exists:users,id',
            'lab_vendor_id'        => 'nullable|exists:lab_vendors,id',
            // Legacy field from the drawer: free-text vendor name (v1 compat)
            'lab_vendor'           => 'nullable|string|max:150',
            'work_category'        => 'nullable|string|max:100',
            'work_type'            => 'nullable|string|max:100',   // legacy alias
            'work_subtype'         => 'nullable|string|max:100',
            'priority'             => 'nullable|in:routine,urgent,express',
            'status'               => 'nullable|in:' . implode(',', LabCase::STATUSES),
            'sent_date'            => 'nullable|date',
            'expected_return_date' => 'nullable|date',
            'received_date'        => 'nullable|date',
            'delivered_date'       => 'nullable|date',
            'lab_cost'             => 'nullable|numeric|min:0',
            'estimated_cost'       => 'nullable|numeric|min:0',   // Phase 2
            'payment_status'       => 'nullable|in:pending,paid,monthly_account',
            'instructions'         => 'nullable|string|max:2000',
            'internal_notes'       => 'nullable|string|max:2000',
            'notes'                => 'nullable|string|max:2000',  // legacy alias
            // Per-tooth line items — tooth chart + shade guide (see partials.tooth-chart / partials.shade-select)
            'items'                    => 'nullable|array',
            'items.*.tooth_number'     => 'nullable|string|max:10',
            'items.*.work_type'        => 'nullable|string|max:100',
            'items.*.material'         => 'nullable|string|max:100',
            'items.*.shade'            => 'nullable|string|max:20',
            'items.*.shade_guide'      => 'nullable|string|max:20',
            // items_json — the /lab page's native-POST drawer sends items this way
            // (fed by the same shared tooth-chart/shade-select partials as items[]).
            'items_json'               => 'nullable|string',
        ]);

        // Resolve work_category: legacy work_type → new category
        $workCategory = $data['work_category']
            ?? (self::LEGACY_CATEGORY_MAP[$data['work_type'] ?? ''] ?? ($data['work_type'] ?? 'Other'));

        // Resolve lab_vendor_id from free-text if not set
        $vendorId = $data['lab_vendor_id'] ?? null;
        if (!$vendorId && !empty($data['lab_vendor'])) {
            $vendorId = LabVendor::where('name', $data['lab_vendor'])->value('id');
        }

        $items = $this->resolveItemsInput($data);

        // ── Repeat / remake detection ────────────────────────────────────
        // If same patient + any of the same teeth has a completed case, flag as remake
        $isRemake   = false;
        $remakeOfId = null;
        $teethInput = array_filter(array_column($items, 'tooth_number'));

        if ($teethInput && !empty($data['patient_id'])) {
            $priorCase = LabCase::where('patient_id', $data['patient_id'])
                ->whereHas('items', fn($q) => $q->whereIn('tooth_number', $teethInput))
                ->whereIn('status', ['complete', 'final_received'])
                ->latest()
                ->first();

            if ($priorCase) {
                $isRemake   = true;
                $remakeOfId = $priorCase->id;
            }
        }

        $labCase = LabCase::create([
            'patient_id'           => $data['patient_id'],
            'doctor_id'            => $data['doctor_id'] ?? null,
            'lab_vendor_id'        => $vendorId,
            'work_category'        => $workCategory,
            'work_subtype'         => $data['work_subtype'] ?? null,
            'priority'             => $data['priority'] ?? 'routine',
            'status'               => $data['status'] ?? 'draft',
            'sent_date'            => $data['sent_date'] ?? null,
            'expected_return_date' => $data['expected_return_date'] ?? null,
            'received_date'        => $data['received_date'] ?? null,
            'lab_cost'             => $data['lab_cost'] ?? null,
            'estimated_cost'       => $data['estimated_cost'] ?? null,
            'billing_status'       => 'unbilled',
            'payment_status'       => $data['payment_status'] ?? 'pending',
            'instructions'         => $data['instructions'] ?? null,
            'internal_notes'       => $data['internal_notes'] ?? ($data['notes'] ?? null),
            'is_remake'            => $isRemake,
            'remake_of_id'         => $remakeOfId,
            'created_by'           => auth()->id(),
        ]);

        $this->syncItems($labCase, $items, $workCategory, $data['work_subtype'] ?? null);

        $message = 'Lab case ' . $labCase->case_number . ' created.';
        if ($isRemake) {
            $message .= ' ⚠️ Flagged as repeat work for this tooth.';
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'lab_case' => $labCase->load('items')]);
        }

        return redirect()->route('lab.index')->with('success', $message);
    }

    /**
     * Normalize the two ways items can arrive: a real items[] array (fetch/JSON
     * callers — the patient-profile Lab tab) or an items_json string (the /lab
     * page's native-POST drawer, which can't array-encode a JS-built payload
     * into a plain form post). Both are produced by the same shared
     * partials.tooth-chart + partials.shade-select UI.
     */
    private function resolveItemsInput(array $data): array
    {
        if (!empty($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (!empty($data['items_json'])) {
            $decoded = json_decode($data['items_json'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Replace a case's LabCaseItem rows with the submitted tooth/shade lines.
     * work_type/material default from the case's own category/subtype since
     * neither form exposes a separate per-tooth work-type picker (MVP).
     */
    private function syncItems(LabCase $labCase, array $items, ?string $workCategory, ?string $workSubtype): void
    {
        $labCase->items()->delete();

        foreach (array_values($items) as $i => $row) {
            $toothNumber = trim((string) ($row['tooth_number'] ?? ''));
            $shade       = trim((string) ($row['shade'] ?? ''));
            $workType    = trim((string) ($row['work_type'] ?? ''));
            $material    = trim((string) ($row['material'] ?? ''));

            if ($toothNumber === '' && $shade === '' && $workType === '' && $material === '') {
                continue;
            }

            $labCase->items()->create([
                'tooth_number' => $toothNumber ?: null,
                'work_type'    => $workType ?: ($workSubtype ?: ($workCategory ?: 'Other')),
                'material'     => $material ?: ($workSubtype ?: null),
                'shade'        => $shade ?: null,
                'shade_guide'  => $shade !== '' ? ($row['shade_guide'] ?? 'vita_classical') : null,
                'sort_order'   => $i,
            ]);
        }
    }

    // ── SHOW ─────────────────────────────────────────────────────────────

    public function show(LabCase $labCase)
    {
        $labCase->load([
            'patient', 'doctor', 'vendor', 'items', 'attachments',
            'events.createdBy', 'expense', 'reconciliation',
            'prescription.createdBy',  // structured clinical prescription
            'remakeOf.patient',         // original case (if remake)
            'rating.ratedBy',           // doctor quality rating
        ]);

        $vendors   = \App\Models\LabVendor::active()->orderBy('name')->get(['id', 'name']);
        $nextSteps = \App\Models\LabCase::STATUS_FLOW[$labCase->status] ?? [];

        return view('lab.show', compact('labCase', 'vendors', 'nextSteps'));
    }

    // ── EDIT ─────────────────────────────────────────────────────────────

    public function edit(LabCase $labCase)
    {
        // The drawer handles editing on the index page.
        // For direct /lab/{id}/edit navigation, redirect to index.
        return redirect()->route('lab.index');
    }

    // ── UPDATE ───────────────────────────────────────────────────────────

    public function update(Request $request, LabCase $labCase)
    {
        $data = $request->validate([
            'patient_id'           => 'sometimes|exists:patients,id',
            'doctor_id'            => 'nullable|exists:users,id',
            'lab_vendor_id'        => 'nullable|exists:lab_vendors,id',
            'lab_vendor'           => 'nullable|string|max:150',
            'work_category'        => 'nullable|string|max:100',
            'work_type'            => 'nullable|string|max:100',
            'work_subtype'         => 'nullable|string|max:100',
            'priority'             => 'nullable|in:routine,urgent,express',
            'status'               => 'nullable|in:' . implode(',', LabCase::STATUSES),
            'sent_date'            => 'nullable|date',
            'expected_return_date' => 'nullable|date',
            'received_date'        => 'nullable|date',
            'delivered_date'       => 'nullable|date',
            'lab_cost'             => 'nullable|numeric|min:0',
            'estimated_cost'       => 'nullable|numeric|min:0',  // Phase 2
            'payment_status'       => 'nullable|in:pending,paid,monthly_account',
            'instructions'         => 'nullable|string|max:2000',
            'internal_notes'       => 'nullable|string|max:2000',
            'notes'                => 'nullable|string|max:2000',
            'items'                    => 'nullable|array',
            'items.*.tooth_number'     => 'nullable|string|max:10',
            'items.*.work_type'        => 'nullable|string|max:100',
            'items.*.material'         => 'nullable|string|max:100',
            'items.*.shade'            => 'nullable|string|max:20',
            'items.*.shade_guide'      => 'nullable|string|max:20',
            'items_json'               => 'nullable|string',
        ]);

        // Map legacy work_type → category
        if (isset($data['work_type']) && !isset($data['work_category'])) {
            $data['work_category'] = self::LEGACY_CATEGORY_MAP[$data['work_type']] ?? $data['work_type'];
        }

        // Resolve vendor from free-text
        if (empty($data['lab_vendor_id']) && !empty($data['lab_vendor'])) {
            $data['lab_vendor_id'] = LabVendor::where('name', $data['lab_vendor'])->value('id');
        }

        // Phase 2: map legacy internal notes alias
        if (isset($data['notes']) && !isset($data['internal_notes'])) {
            $data['internal_notes'] = $data['notes'];
        }

        $items = $this->resolveItemsInput($data);
        $itemsProvided = array_key_exists('items', $data) || array_key_exists('items_json', $data);

        unset($data['work_type'], $data['lab_vendor'], $data['notes'], $data['items'], $data['items_json']);

        $labCase->update(array_merge($data, ['updated_by' => auth()->id()]));

        if ($itemsProvided) {
            $this->syncItems(
                $labCase,
                $items,
                $data['work_category'] ?? $labCase->work_category,
                $data['work_subtype'] ?? $labCase->work_subtype
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Lab case updated.', 'lab_case' => $labCase->load('items')]);
        }

        return redirect()->route('lab.index')->with('success', 'Lab case updated.');
    }

    // ── STATUS TRANSITION ────────────────────────────────────────────────

    public function transition(Request $request, LabCase $labCase, string $to)
    {
        if (! $labCase->canTransitionTo($to)) {
            return back()->with('error', "Cannot move case from '{$labCase->status}' to '{$to}'.");
        }

        $from    = $labCase->status; // capture before update for notifications
        $updates = ['status' => $to];
        $today   = today()->toDateString();

        // ── Stamp the date for this step ────────────────────────────────
        match ($to) {
            'order_placed'    => $updates['order_placed_date']    = $today,
            'impression_sent' => $updates['impression_sent_date'] = $today,
            'scan_sent'       => $updates['impression_sent_date'] = $today,
            'final_received'  => $updates['final_received_date']  = $today,
            'complete'        => $updates['delivered_date']        = $today,
            default           => null,
        };

        // ── Trial round tracking ─────────────────────────────────────────
        if ($to === 'trial_received') {
            $updates['trial_round'] = ($labCase->trial_round ?? 0) + 1;
        }

        $labCase->update($updates);
        $labCase->refresh();

        $label    = LabCase::STATUS_LABELS[$to] ?? ucfirst(str_replace('_', ' ', $to));
        $patient  = $labCase->patient?->name  ?? 'Patient';
        $vendor   = $labCase->vendor?->name   ?? ($labCase->lab_vendor ?? 'Lab');
        $caseNo   = $labCase->case_number;
        $dueDate  = $labCase->expected_return_date?->format('Y-m-d') ?? $today;

        // ── Close / complete the previous active task for this case ─────
        if ($labCase->active_task_id) {
            Task::find($labCase->active_task_id)?->update([
                'status'  => 'done',
                'done_at' => now(),
            ]);
        }

        // ── Auto-create next task based on the new status ────────────────
        $task = null;

        // Find front desk and manager user IDs for assignment
        $frontDesk = \App\Models\User::where('role', 'receptionist')
            ->orWhere('role', 'front_desk')
            ->orderBy('id')->value('id') ?? auth()->id();

        $doctor    = $labCase->doctor_id ?? auth()->id();
        $manager   = \App\Models\User::where('role', 'admin')->orderBy('id')->value('id') ?? auth()->id();

        $taskBase = [
            'created_by'  => auth()->id(),
            'branch_id'   => $labCase->branch_id ?? 1,
            'patient_id'  => $labCase->patient_id,
            'lab_case_id' => $labCase->id,
            'category'    => 'lab',
            'status'      => 'pending',
            // tasks.priority enum = ['urgent','high','medium','low'] — no 'normal'
            'priority'    => $labCase->priority === 'express' ? 'high' : ($labCase->priority === 'urgent' ? 'high' : 'medium'),
        ];

        match ($to) {

            'order_placed' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Dispatch impression/scan to {$vendor} — {$patient}",
                'description' => "Lab case {$caseNo} has been ordered. Send physical impression or digital scan to lab. Expected return: {$dueDate}.",
                'assigned_to' => $frontDesk,
                'due_date'    => $today,
            ])),

            'impression_sent', 'scan_sent' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Confirm {$vendor} received impression — {$patient}",
                'description' => "Impression/scan dispatched for {$caseNo}. Call/WhatsApp lab to confirm receipt and expected return date.",
                'assigned_to' => $frontDesk,
                'due_date'    => now()->addDay()->toDateString(),
            ])),

            'trial_received' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Doctor to review Trial {$labCase->trial_round} — {$patient}",
                'description' => "Trial {$labCase->trial_round} received from {$vendor} for {$caseNo}. Doctor approval needed before next step.",
                'assigned_to' => $doctor,
                'due_date'    => $today,
                'priority'    => 'high',
            ])),

            'trial_returned' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Follow up with {$vendor} — Trial {$labCase->trial_round} correction — {$patient}",
                'description' => "Trial returned to lab for adjustment ({$caseNo}). Follow up in 2 days on correction progress.",
                'assigned_to' => $frontDesk,
                'due_date'    => now()->addDays(2)->toDateString(),
            ])),

            'final_received' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Schedule delivery appointment — {$patient}",
                'description' => "Final work received from {$vendor} for {$caseNo}. Book patient appointment for delivery/fit.",
                'assigned_to' => $frontDesk,
                'due_date'    => $today,
                'priority'    => 'high',
            ])),

            'complete' => null,   // case is done — no new task
            'rejected' => null,

            default => null,
        };

        // Store reference to the newly created task
        if ($task) {
            $labCase->update(['active_task_id' => $task->id]);
        }

        // ── In-app notifications + patient WhatsApp (all transitions, best-effort) ─
        try {
            app(\App\Services\LabNotificationService::class)
                ->onTransition($labCase, $from, $to, auth()->user());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('LabNotificationService::onTransition failed', [
                'case'  => $labCase->id,
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }

        // ── Auto-create Finance expense when final work received (best-effort) ──
        // Idempotent: LabExpenseService checks expense_id guard + skips if no cost.
        if ($to === 'final_received') {
            try {
                app(\App\Services\LabExpenseService::class)->createForCase($labCase);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('LabExpenseService::createForCase failed', [
                    'case'  => $labCase->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "{$caseNo} moved to {$label}." . ($task ? ' Task assigned.' : ''));
    }

    // ── DUPLICATE ────────────────────────────────────────────────────────

    public function duplicate(LabCase $labCase)
    {
        $new = $labCase->replicate(['case_number', 'status', 'sent_date',
            'received_date', 'delivered_date', 'expense_id',
            'reconciliation_id', 'billing_status']);

        $new->status          = 'draft';
        $new->billing_status  = 'unbilled';
        $new->is_remake       = false;
        $new->remake_of_id    = null;
        $new->created_by      = auth()->id();
        $new->save();

        foreach ($labCase->items as $item) {
            $new->items()->create([
                'tooth_number' => $item->tooth_number,
                'work_type'    => $item->work_type,
                'material'     => $item->material,
                'shade'        => $item->shade,
                'shade_guide'  => $item->shade_guide,
                'notes'        => $item->notes,
                'sort_order'   => $item->sort_order,
            ]);
        }

        return redirect()->route('lab.index')->with('success', "Duplicate case created: {$new->case_number}.");
    }

    // ── DESTROY (soft delete) ────────────────────────────────────────────

    public function destroy(Request $request, LabCase $labCase)
    {
        $isAdmin = auth()->user()?->isAdminRole();

        // Cases whose final work is received / completed cannot be deleted by anyone
        // (v2 statuses — old values received/delivered/closed no longer exist).
        if (in_array($labCase->status, ['final_received', 'complete'])) {
            return back()->with('error', 'Cannot delete a case that has already been received or completed.');
        }

        // Non-draft cases need admin permission
        if ($labCase->status !== 'draft' && ! $isAdmin) {
            return back()->with('error', 'Only an admin can delete a case that has already been sent to the lab.');
        }

        $request->validate(['delete_reason' => 'required|string|min:5|max:500']);

        // Log the reason before soft-deleting (event timeline preserved)
        $labCase->logEvent('archived', 'Deleted — ' . $request->delete_reason);

        $labCase->delete();
        return redirect()->route('lab.index')->with('success', "Case {$labCase->case_number} deleted.");
    }

    // ── PRINT ────────────────────────────────────────────────────────────

    public function print(LabCase $labCase)
    {
        $labCase->load(['patient', 'doctor', 'vendor', 'items', 'events' => fn($q) => $q->latest()]);
        $labCase->logEvent('printed', 'Case sheet printed by ' . (auth()->user()?->name ?? 'staff'));
        return view('lab.print', compact('labCase'));
    }

    // ── RESTORE ──────────────────────────────────────────────────────────

    public function restore(LabCase $labCase)
    {
        $labCase->restore();
        return back()->with('success', "Case #{$labCase->case_number} restored.");
    }

    // ── SUBTYPES (AJAX) ──────────────────────────────────────────────────

    public function subtypes(Request $request)
    {
        $category = $request->query('category', '');
        return response()->json(LabCase::subtypesFor($category));
    }

    // ── ATTACHMENTS ──────────────────────────────────────────────────────

    public function attachmentStore(Request $request, LabCase $labCase)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $path = $request->file('file')->store('lab-attachments', 'public');

        $labCase->attachments()->create([
            'file_path'   => $path,
            'file_name'   => $request->file('file')->getClientOriginalName(),
            'file_size'   => $request->file('file')->getSize(),
            'mime_type'   => $request->file('file')->getMimeType(),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function attachmentDestroy(LabCaseAttachment $attachment)
    {
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();
        return back()->with('success', 'Attachment removed.');
    }

    // ── PATIENT CASES (patient profile Lab tab) ──────────────────────────

    public function patientCases(Request $request, \App\Models\Patient $patient)
    {
        $cases = LabCase::with(['vendor', 'items'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->get();

        if ($request->expectsJson()) {
            return response()->json($cases);
        }

        // The Lab tab is rendered inline inside the patient profile
        // (patients.partials.lab-tab, included from patients/show.blade.php).
        // Direct/HTML navigation to this endpoint has no standalone view —
        // send the user to the profile instead, same convention as
        // create()/edit() above for direct navigation to drawer-only routes.
        return redirect()->route('patients.show', $patient);
    }

    // ── PRIVATE HELPERS ──────────────────────────────────────────────────

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'patient_az'    => $query->orderBy(
                                   Patient::select('name')->whereColumn('patients.id', 'lab_cases.patient_id')
                               ),
            'patient_za'    => $query->orderByDesc(
                                   Patient::select('name')->whereColumn('patients.id', 'lab_cases.patient_id')
                               ),
            'vendor_az'     => $query->orderBy(
                                   LabVendor::select('name')->whereColumn('lab_vendors.id', 'lab_cases.lab_vendor_id')
                               ),
            'vendor_za'     => $query->orderByDesc(
                                   LabVendor::select('name')->whereColumn('lab_vendors.id', 'lab_cases.lab_vendor_id')
                               ),
            'expected_asc'  => $query->orderBy('expected_return_date'),
            'expected_desc' => $query->orderByDesc('expected_return_date'),
            'sent_asc'      => $query->orderBy('sent_date'),
            'sent_desc'     => $query->orderByDesc('sent_date'),
            'cost_asc'      => $query->orderBy('lab_cost'),
            'cost_desc'     => $query->orderByDesc('lab_cost'),
            'oldest'        => $query->orderBy('created_at'),
            default         => $query->orderByDesc('created_at'),  // 'newest'
        };
    }

    // ── RATING ───────────────────────────────────────────────────────────────

    /**
     * Store or update the doctor's rating for a completed lab case.
     * POST /lab/{labCase}/rate
     */
    public function ratingStore(Request $request, LabCase $labCase)
    {
        $data = $request->validate([
            'fit'           => 'nullable|integer|min:1|max:5',
            'shade'         => 'nullable|integer|min:1|max:5',
            'margins'       => 'nullable|integer|min:1|max:5',
            'occlusion'     => 'nullable|integer|min:1|max:5',
            'quality'       => 'nullable|integer|min:1|max:5',
            'communication' => 'nullable|integer|min:1|max:5',
            'value'         => 'nullable|integer|min:1|max:5',
            'overall'       => 'required|integer|min:1|max:5',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $data['lab_vendor_id'] = $labCase->lab_vendor_id;
        $data['rated_by']      = auth()->id();

        $rating = LabCaseRating::updateOrCreate(
            ['lab_case_id' => $labCase->id],
            $data
        );

        $labCase->logEvent('rated', 'Case rated ' . $data['overall'] . '/5', [
            'meta' => ['overall' => $data['overall']],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'rating' => $rating]);
        }

        return back()->with('success', 'Rating saved. Thank you!');
    }

    // ── PRESCRIPTION ─────────────────────────────────────────────────────

    /**
     * Store (create) a prescription for a lab case.
     * Called via POST /lab/{labCase}/prescription
     */
    public function prescriptionStore(Request $request, LabCase $labCase)
    {
        // Prevent duplicate — redirect to update if already exists
        if ($labCase->prescription()->exists()) {
            return $this->prescriptionSave($request, $labCase, $labCase->prescription);
        }

        $data = $this->prescriptionData($request);
        $data['lab_case_id'] = $labCase->id;
        $data['created_by']  = auth()->id();

        // Auto-generate smart suggestions from category
        $category = $labCase->work_category ?? 'Other';
        $data['smart_suggestions'] = LabCasePrescription::SMART_SUGGESTIONS[$category] ?? [];

        $rx = LabCasePrescription::create($data);

        $labCase->logEvent('prescription_saved', 'Prescription created', [
            'meta' => ['material' => $rx->material, 'shade' => $rx->shade],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Prescription saved.']);
        }

        return back()->with('success', 'Prescription saved for ' . $labCase->case_number . '.');
    }

    /**
     * Update an existing prescription.
     * Called via PUT /lab/{labCase}/prescription
     */
    public function prescriptionUpdate(Request $request, LabCase $labCase)
    {
        $rx = $labCase->prescription;
        if (! $rx) {
            return $this->prescriptionStore($request, $labCase);
        }

        return $this->prescriptionSave($request, $labCase, $rx);
    }

    private function prescriptionSave(Request $request, LabCase $labCase, LabCasePrescription $rx)
    {
        $data = $this->prescriptionData($request);
        $data['updated_by'] = auth()->id();
        $rx->update($data);

        $labCase->logEvent('prescription_updated', 'Prescription updated', [
            'meta' => ['material' => $rx->material, 'shade' => $rx->shade],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Prescription updated.']);
        }

        return back()->with('success', 'Prescription updated.');
    }

    private function prescriptionData(Request $request): array
    {
        $validated = $request->validate([
            'template_id'              => 'nullable|exists:lab_prescription_templates,id',
            'material'                 => 'nullable|string|max:100',
            'shade'                    => 'nullable|string|max:30',
            'stump_shade'              => 'nullable|string|max:30',
            'clinical_fields'          => 'nullable|array',
            'special_instructions'     => 'nullable|string|max:3000',
            'suggestions_acknowledged' => 'nullable|boolean',
        ]);

        return $validated;
    }

    // ── PRESCRIPTION TEMPLATES ───────────────────────────────────────────

    /** GET /lab/templates — AJAX: list templates for current branch / category */
    public function templateIndex(Request $request)
    {
        $category  = $request->query('category');
        $branchId  = auth()->user()?->branch_id;

        $templates = LabPrescriptionTemplate::active()
            ->where(fn($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->when($category, fn($q) => $q->forCategory($category))
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'subtype', 'material', 'shade', 'clinical_fields']);

        return response()->json($templates);
    }

    /** POST /lab/templates — save a new prescription template */
    public function templateStore(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'category'         => 'nullable|string|max:100',
            'subtype'          => 'nullable|string|max:100',
            'material'         => 'nullable|string|max:100',
            'shade'            => 'nullable|string|max:30',
            'clinical_fields'  => 'nullable|array',
            'notes'            => 'nullable|string|max:500',
        ]);

        $template = LabPrescriptionTemplate::create(array_merge($data, [
            'branch_id'  => auth()->user()?->branch_id,
            'created_by' => auth()->id(),
            'is_active'  => true,
        ]));

        return response()->json(['success' => true, 'template' => $template]);
    }

    /** DELETE /lab/templates/{template} */
    public function templateDestroy(LabPrescriptionTemplate $template)
    {
        $template->delete();
        return response()->json(['success' => true]);
    }
}
