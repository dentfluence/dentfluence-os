<?php

namespace App\Http\Controllers;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\Finance\FinanceVoucher;
use App\Models\LabCase;
use App\Models\LabMonthlyReconciliation;
use App\Models\LabReconciliationItem;
use App\Models\LabVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LabReconciliationController — Phase 2 Lab Monthly Reconciliation.
 *
 * Workflow:
 *   GET  /lab/reconciliation                  index()
 *   GET  /lab/reconciliation/create           create()
 *   POST /lab/reconciliation                  store()
 *   GET  /lab/reconciliation/{rec}            show()
 *   POST /lab/reconciliation/{rec}/submit     submit()     draft → pending_review
 *   POST /lab/reconciliation/{rec}/approve    approve()    pending_review → approved (creates Finance AP)
 *   POST /lab/reconciliation/{rec}/dispute    dispute()    → disputed
 *   POST /lab/reconciliation/{rec}/items/{item}/update  updateItem()  per-line amounts + match status
 *   DELETE /lab/reconciliation/{rec}          destroy()
 */
class LabReconciliationController extends Controller
{
    // ── INDEX ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $status   = $request->input('status');
        $vendorId = $request->input('vendor_id');
        $year     = $request->input('year', now()->year);

        $query = LabMonthlyReconciliation::with(['labVendor', 'financeExpense'])
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month');

        if ($status)   { $query->where('status', $status); }
        if ($vendorId) { $query->where('lab_vendor_id', $vendorId); }
        if ($year)     { $query->where('billing_year', $year); }

        $reconciliations = $query->paginate(20)->withQueryString();

        // KPI strip
        $kpis = [
            'draft'          => LabMonthlyReconciliation::where('status', 'draft')->count(),
            'pending_review' => LabMonthlyReconciliation::where('status', 'pending_review')->count(),
            'approved'       => LabMonthlyReconciliation::where('status', 'approved')->count(),
            'total_approved' => LabMonthlyReconciliation::where('status', 'approved')->sum('agreed_amount'),
            'disputed'       => LabMonthlyReconciliation::where('status', 'disputed')->count(),
        ];

        $vendors = LabVendor::active()->orderBy('name')->get(['id', 'name']);
        $statuses = LabMonthlyReconciliation::STATUSES;

        return view('lab.reconciliation.index',
            compact('reconciliations', 'kpis', 'vendors', 'statuses', 'status', 'vendorId', 'year'));
    }

    // ── CREATE ────────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $vendors = LabVendor::active()->orderBy('name')->get();

        // Pre-select vendor if passed
        $selectedVendor = $request->filled('vendor_id')
            ? LabVendor::with('financeVendor')->find($request->vendor_id)
            : null;

        // Default period: last full month
        $defaultYear  = now()->subMonth()->year;
        $defaultMonth = now()->subMonth()->month;

        // If vendor selected, auto-load eligible cases for the period
        $eligibleCases = collect();
        if ($selectedVendor && $request->filled('billing_month') && $request->filled('billing_year')) {
            $eligibleCases = $this->getEligibleCases(
                $selectedVendor->id,
                (int) $request->billing_year,
                (int) $request->billing_month
            );
        }

        return view('lab.reconciliation.create',
            compact('vendors', 'selectedVendor', 'defaultYear', 'defaultMonth', 'eligibleCases'));
    }

    // ── STORE ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'lab_vendor_id'      => 'required|exists:lab_vendors,id',
            'billing_month'      => 'required|integer|between:1,12',
            'billing_year'       => 'required|integer|min:2020|max:2100',
            'vendor_bill_number' => 'nullable|string|max:100',
            'vendor_bill_date'   => 'nullable|date',
            'vendor_total'       => 'required|numeric|min:0',
            'notes'              => 'nullable|string|max:1000',
            // Line items: case_ids[] with vendor_amounts[]
            'case_ids'           => 'required|array|min:1',
            'case_ids.*'         => 'exists:lab_cases,id',
            'vendor_amounts'     => 'required|array',
            'vendor_amounts.*'   => 'nullable|numeric|min:0',
        ]);

        // Check for duplicate reconciliation for same vendor + period
        $existing = LabMonthlyReconciliation::where('lab_vendor_id', $data['lab_vendor_id'])
            ->where('billing_month', $data['billing_month'])
            ->where('billing_year',  $data['billing_year'])
            ->whereNotIn('status', ['disputed'])
            ->first();

        if ($existing) {
            return back()
                ->withInput()
                ->with('error', "A reconciliation for this vendor and period already exists ({$existing->reconciliation_ref}).");
        }

        $labVendor       = LabVendor::findOrFail($data['lab_vendor_id']);
        $financeVendorId = $labVendor->finance_vendor_id;

        $reconciliation = DB::transaction(function () use ($data, $financeVendorId) {

            // ── 1. Calculate our total from lab_cost on the selected cases ──
            $caseIds     = $data['case_ids'];
            $vendorAmts  = $data['vendor_amounts'];
            $cases       = LabCase::whereIn('id', $caseIds)->get()->keyBy('id');

            $ourTotal     = 0;
            $vendorTotal  = (float) $data['vendor_total'];
            $items        = [];

            foreach ($caseIds as $idx => $caseId) {
                $case      = $cases[$caseId] ?? null;
                if (! $case) { continue; }

                $ourAmt    = (float) ($case->lab_cost ?? 0);
                $vendorAmt = (float) ($vendorAmts[$idx] ?? $ourAmt);
                $diff      = $vendorAmt - $ourAmt;
                $ourTotal += $ourAmt;

                $items[] = [
                    'lab_case_id'    => $caseId,
                    'our_amount'     => $ourAmt,
                    'vendor_amount'  => $vendorAmt,
                    'difference'     => $diff,
                    'match_status'   => abs($diff) <= 1.0 ? 'matched' : 'conflict',
                    'auto_selected'  => true,
                ];
            }

            // ── 2. Create reconciliation header ───────────────────────────
            $rec = LabMonthlyReconciliation::create([
                'reconciliation_ref'  => LabMonthlyReconciliation::generateRef(),
                'lab_vendor_id'       => $data['lab_vendor_id'],
                'finance_vendor_id'   => $financeVendorId,
                'billing_month'       => $data['billing_month'],
                'billing_year'        => $data['billing_year'],
                'our_total'           => $ourTotal,
                'vendor_total'        => $vendorTotal,
                'difference'          => $vendorTotal - $ourTotal,
                'agreed_amount'       => $ourTotal,  // default: our amount; user can adjust
                'vendor_bill_number'  => $data['vendor_bill_number'] ?? null,
                'vendor_bill_date'    => $data['vendor_bill_date'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'status'              => 'draft',
                'created_by'          => auth()->id(),
            ]);

            // ── 3. Create line items ──────────────────────────────────────
            foreach ($items as $item) {
                $rec->items()->create($item);
            }

            // ── 4. Mark included lab cases as in_reconciliation ──────────
            LabCase::whereIn('id', $caseIds)->update(['billing_status' => 'in_reconciliation',
                                                      'reconciliation_id' => $rec->id]);

            // ── 5. Log audit event ────────────────────────────────────────
            $rec->logEvent('created', null, 'draft',
                count($items) . ' cases included. Our total: ₹' . number_format($ourTotal, 2) .
                ' | Vendor total: ₹' . number_format($vendorTotal, 2));

            return $rec;
        });

        return redirect()
            ->route('lab.reconciliation.show', $reconciliation)
            ->with('success', "Reconciliation {$reconciliation->reconciliation_ref} created.");
    }

    // ── SHOW ──────────────────────────────────────────────────────────────

    public function show(LabMonthlyReconciliation $reconciliation)
    {
        $reconciliation->load([
            'labVendor',
            'financeVendor',
            'financeExpense.voucher',
            'voucher',
            'items.labCase.patient',
            'events.createdBy',
            'createdBy',
            'approvedBy',
        ]);

        return view('lab.reconciliation.show', compact('reconciliation'));
    }

    // ── SUBMIT (draft → pending_review) ──────────────────────────────────

    public function submit(Request $request, LabMonthlyReconciliation $reconciliation)
    {
        if ($reconciliation->status !== 'draft') {
            return back()->with('error', 'Only draft reconciliations can be submitted.');
        }

        // Allow updating agreed_amount before submitting
        $agreed = $request->input('agreed_amount');
        $updates = ['status' => 'pending_review'];
        if ($agreed !== null) {
            $updates['agreed_amount'] = (float) $agreed;
        }

        $old = $reconciliation->status;
        $reconciliation->update($updates);
        $reconciliation->logEvent('submitted', $old, 'pending_review',
            $request->input('notes') ?? 'Submitted for review.');

        return back()->with('success', 'Reconciliation submitted for review.');
    }

    // ── UPDATE ITEM (per-case vendor amount + match_status) ───────────────

    public function updateItem(Request $request, LabMonthlyReconciliation $reconciliation, LabReconciliationItem $item)
    {
        if (! in_array($reconciliation->status, ['draft', 'pending_review'])) {
            return back()->with('error', 'Cannot edit items on an approved reconciliation.');
        }

        $data = $request->validate([
            'vendor_amount' => 'required|numeric|min:0',
            'match_status'  => 'required|in:matched,conflict,disputed,accepted',
            'remarks'       => 'nullable|string|max:500',
        ]);

        $diff = (float) $data['vendor_amount'] - (float) $item->our_amount;

        $item->update([
            'vendor_amount' => $data['vendor_amount'],
            'difference'    => $diff,
            'match_status'  => $data['match_status'],
            'remarks'       => $data['remarks'] ?? null,
        ]);

        // Recalculate reconciliation totals
        $this->recalcTotals($reconciliation);

        return back()->with('success', 'Line item updated.');
    }

    // ── APPROVE (pending_review → approved + Finance AP entry) ────────────

    public function approve(Request $request, LabMonthlyReconciliation $reconciliation)
    {
        if ($reconciliation->status !== 'pending_review') {
            return back()->with('error', 'Only pending-review reconciliations can be approved.');
        }

        $data = $request->validate([
            'agreed_amount' => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($reconciliation, $data) {

            $old = $reconciliation->status;

            // ── 1. Update reconciliation ──────────────────────────────────
            $reconciliation->update([
                'status'        => 'approved',
                'agreed_amount' => $data['agreed_amount'],
                'approved_by'   => auth()->id(),
                'approved_at'   => now(),
            ]);

            // ── 2. Auto-create Finance Expense (Accounts Payable) ─────────
            // This is the Finance Synchronization step (Phase 2 §6).
            $labCategory = FinanceExpenseCategory::where('name', 'like', '%Lab%')
                ->orWhere('name', 'like', '%lab%')
                ->first();

            $expense = FinanceExpense::create([
                'title'          => 'Lab Bill — ' . $reconciliation->labVendor->name
                                    . ' (' . $reconciliation->getBillingPeriodLabel() . ')'
                                    . ' · ' . $reconciliation->reconciliation_ref,
                'description'    => 'Monthly lab bill. Auto-created from reconciliation approval. '
                                    . $reconciliation->items()->count() . ' cases.',
                'expense_date'   => $reconciliation->vendor_bill_date ?? today(),
                'due_date'       => $reconciliation->vendor_bill_date
                                        ? \Carbon\Carbon::parse($reconciliation->vendor_bill_date)->addDays(30)
                                        : today()->addDays(30),
                'amount'         => $data['agreed_amount'],
                'gst_applicable' => false,
                'gst_amount'     => 0,
                'total_amount'   => $data['agreed_amount'],
                'category_id'    => $labCategory?->id,
                'vendor_id'      => $reconciliation->finance_vendor_id,
                'payment_status' => 'unpaid',
                'payment_mode'   => null,
                'status'         => 'approved',
                'source_type'    => LabMonthlyReconciliation::class,
                'source_id'      => $reconciliation->id,
                'notes'          => $data['notes'] ?? null,
                'created_by'     => auth()->id(),
            ]);

            // ── 3. Link expense back ──────────────────────────────────────
            $reconciliation->update(['finance_expense_id' => $expense->id]);

            // ── 4. Mark all included cases as billed ──────────────────────
            $caseIds = $reconciliation->items()->pluck('lab_case_id');
            LabCase::whereIn('id', $caseIds)->update(['billing_status' => 'billed']);

            // ── 5. Update Finance vendor outstanding ──────────────────────
            if ($reconciliation->finance_vendor_id) {
                FinanceVendor::where('id', $reconciliation->finance_vendor_id)
                    ->increment('outstanding_amount', $data['agreed_amount']);
            }

            // ── 6. Log event ──────────────────────────────────────────────
            $reconciliation->logEvent('approved', $old, 'approved',
                'Agreed amount: ₹' . number_format($data['agreed_amount'], 2)
                . '. Finance AP entry created: #' . $expense->id . '.');
        });

        return redirect()
            ->route('lab.reconciliation.show', $reconciliation)
            ->with('success', 'Reconciliation approved. Unpaid expense created in Finance → Expenses.');
    }

    // ── DISPUTE ───────────────────────────────────────────────────────────

    public function dispute(Request $request, LabMonthlyReconciliation $reconciliation)
    {
        if (! in_array($reconciliation->status, ['draft', 'pending_review'])) {
            return back()->with('error', 'Cannot dispute an approved or paid reconciliation.');
        }

        $data = $request->validate(['dispute_reason' => 'required|string|max:1000']);

        $old = $reconciliation->status;
        $reconciliation->update([
            'status'         => 'disputed',
            'dispute_reason' => $data['dispute_reason'],
        ]);
        $reconciliation->logEvent('disputed', $old, 'disputed', $data['dispute_reason']);

        // Revert case billing_status to unbilled so they can be re-reconciled
        $caseIds = $reconciliation->items()->pluck('lab_case_id');
        LabCase::whereIn('id', $caseIds)->update([
            'billing_status'    => 'unbilled',
            'reconciliation_id' => null,
        ]);

        return back()->with('success', 'Reconciliation marked as disputed. Cases returned to unbilled.');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────

    public function destroy(LabMonthlyReconciliation $reconciliation)
    {
        if (in_array($reconciliation->status, ['approved', 'paid'])) {
            return back()->with('error', 'Cannot delete an approved or paid reconciliation.');
        }

        DB::transaction(function () use ($reconciliation) {
            // Revert case billing_status
            $caseIds = $reconciliation->items()->pluck('lab_case_id');
            LabCase::whereIn('id', $caseIds)->update([
                'billing_status'    => 'unbilled',
                'reconciliation_id' => null,
            ]);

            $reconciliation->delete();
        });

        return redirect()
            ->route('lab.reconciliation.index')
            ->with('success', 'Reconciliation deleted. Cases returned to unbilled.');
    }

    // ── AJAX: get eligible cases for vendor + period ──────────────────────

    public function eligibleCases(Request $request)
    {
        $request->validate([
            'vendor_id'     => 'required|exists:lab_vendors,id',
            'billing_month' => 'required|integer|between:1,12',
            'billing_year'  => 'required|integer|min:2020',
        ]);

        $cases = $this->getEligibleCases(
            (int) $request->vendor_id,
            (int) $request->billing_year,
            (int) $request->billing_month
        );

        return response()->json($cases->map(fn($c) => [
            'id'             => $c->id,
            'case_number'    => $c->case_number,
            'patient'        => $c->patient?->name ?? 'N/A',
            'work_category'  => $c->work_category,
            'work_subtype'   => $c->work_subtype,
            'status'         => $c->status,
            'lab_cost'       => $c->lab_cost,
            'received_date'  => $c->received_date?->format('d M Y'),
        ]));
    }

    // ── PRIVATE ───────────────────────────────────────────────────────────

    /**
     * Get lab cases eligible for reconciliation:
     * - Belong to this vendor
     * - Not already in an active reconciliation
     * - billing_status = 'unbilled'
     * - received in the billing period
     */
    private function getEligibleCases(int $vendorId, int $year, int $month): \Illuminate\Support\Collection
    {
        return LabCase::with(['patient:id,name'])
            ->where('lab_vendor_id', $vendorId)
            ->where('billing_status', 'unbilled')
            ->whereNotNull('lab_cost')
            ->whereYear('received_date', $year)
            ->whereMonth('received_date', $month)
            ->orderBy('received_date')
            ->get();
    }

    private function recalcTotals(LabMonthlyReconciliation $reconciliation): void
    {
        $items        = $reconciliation->items;
        $ourTotal     = $items->sum('our_amount');
        $vendorTotal  = $items->sum('vendor_amount');

        $reconciliation->update([
            'our_total'    => $ourTotal,
            'vendor_total' => $vendorTotal,
            'difference'   => $vendorTotal - $ourTotal,
        ]);
    }
}
