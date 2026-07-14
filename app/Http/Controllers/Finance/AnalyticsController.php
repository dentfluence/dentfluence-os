<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\Finance\FinanceVoucher;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\GoodsReceiptNote;
use App\Models\LabCase;
use App\Models\LabMonthlyReconciliation;
use App\Models\LabVendor;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Phase 3 — Analytics & Reporting Controller
 *
 * All analytics read from existing Phase 1 & 2 tables.
 * No new tables are created. No existing data is duplicated.
 */
class AnalyticsController extends Controller
{
    // ── Hub ───────────────────────────────────────────────────────────────

    public function index()
    {
        return view('finance.analytics.index');
    }

    // ── 1. Vendor Analytics ───────────────────────────────────────────────

    public function vendorAnalytics(Request $request)
    {
        $months = (int) $request->input('months', 6);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // Vendors by outstanding amount (from finance_vendors table)
        $outstanding = FinanceVendor::where('is_active', true)
            ->where('outstanding_amount', '>', 0)
            ->orderByDesc('outstanding_amount')
            ->limit(10)
            ->get(['id', 'vendor_name', 'company_name', 'vendor_type', 'outstanding_amount', 'total_purchases', 'credit_days']);

        // Monthly expense by vendor (top 5 vendors, last N months)
        $monthlyByVendor = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->whereNotNull('vendor_id')
            ->join('finance_vendors as fv', 'finance_expenses.vendor_id', '=', 'fv.id')
            ->selectRaw("fv.vendor_name, DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total")
            ->groupByRaw("fv.vendor_name, DATE_FORMAT(expense_date, '%Y-%m')")
            ->orderBy('month')
            ->get();

        // Due payments (unpaid expenses with due_date set)
        $duePayments = FinanceExpense::where('payment_status', 'unpaid')
            ->whereNotNull('due_date')
            ->with(['vendor', 'category'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        // Monthly purchases total (line chart)
        $monthlyTotal = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total, COUNT(*) as cnt")
            ->groupByRaw("DATE_FORMAT(expense_date, '%Y-%m')")
            ->orderBy('month')
            ->get();

        // PO count & value by month (from Phase 1 purchase_orders)
        $poByMonth = PurchaseOrder::whereBetween('order_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as cnt, SUM(total_amount) as total")
            ->groupByRaw("DATE_FORMAT(order_date, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Overdue vendor invoices (Phase 1 vendor_invoices)
        // 2026-07-14: filtered on status 'unpaid', which is NOT in the enum
        // (draft|pending|approved|paid|cancelled) — so this always returned
        // zero and every overdue vendor bill was invisible. The unpaid states
        // are the non-terminal ones.
        $overdueInvoices = VendorInvoice::whereIn('status', VendorInvoice::UNPAID_STATUSES)
            ->where('due_date', '<', today())
            ->with(['financeVendor', 'purchaseOrder'])
            ->orderBy('due_date')
            ->limit(15)
            ->get();

        // Vendor type breakdown
        $byType = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->whereNotNull('vendor_id')
            ->join('finance_vendors as fv', 'finance_expenses.vendor_id', '=', 'fv.id')
            ->selectRaw('fv.vendor_type, SUM(finance_expenses.total_amount) as total, COUNT(*) as cnt')
            ->groupBy('fv.vendor_type')
            ->orderByDesc('total')
            ->get();

        return view('finance.analytics.vendor', compact(
            'outstanding', 'monthlyByVendor', 'duePayments',
            'monthlyTotal', 'poByMonth', 'overdueInvoices', 'byType',
            'months', 'from', 'to'
        ));
    }

    // ── 2. Expense Analytics ──────────────────────────────────────────────

    public function expenseAnalytics(Request $request)
    {
        $months = (int) $request->input('months', 6);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // Category-wise spend
        $byCategory = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->join('finance_expense_categories as ec', 'finance_expenses.category_id', '=', 'ec.id')
            ->selectRaw('ec.name as category, SUM(total_amount) as total, COUNT(*) as cnt')
            ->groupBy('ec.name')
            ->orderByDesc('total')
            ->get();

        $totalSpend = $byCategory->sum('total') ?: 1;
        $byCategory = $byCategory->map(fn($r) => array_merge($r->toArray(), [
            'pct' => round(($r->total / $totalSpend) * 100, 1),
        ]));

        // Vendor-wise spend (top 10)
        $byVendor = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->whereNotNull('vendor_id')
            ->join('finance_vendors as fv', 'finance_expenses.vendor_id', '=', 'fv.id')
            ->selectRaw('fv.vendor_name, fv.vendor_type, SUM(finance_expenses.total_amount) as total, COUNT(*) as cnt')
            ->groupBy('fv.vendor_name', 'fv.vendor_type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Monthly trend (last N months)
        $monthlyTrend = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total, COUNT(*) as cnt, SUM(IF(payment_status='unpaid',total_amount,0)) as unpaid_total")
            ->groupByRaw("DATE_FORMAT(expense_date, '%Y-%m')")
            ->orderBy('month')
            ->get();

        // Paid vs Unpaid summary
        $paidUnpaid = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->selectRaw("payment_status, SUM(total_amount) as total, COUNT(*) as cnt")
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        // Payment mode breakdown (for paid expenses)
        $byMode = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->where('payment_status', 'paid')
            ->whereNotNull('payment_mode')
            ->selectRaw('payment_mode, SUM(total_amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_mode')
            ->orderByDesc('total')
            ->get();

        // Recurring expenses count
        $recurringTotal = FinanceExpense::where('is_recurring', true)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('total_amount');

        // GST paid
        $gstPaid = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->sum('gst_amount');

        $summary = [
            'total'     => $byCategory->sum('total'),
            'gst'       => $gstPaid,
            'recurring' => $recurringTotal,
            'unpaid'    => $paidUnpaid['unpaid']?->total ?? 0,
            'count'     => FinanceExpense::whereBetween('expense_date', [$from, $to])->count(),
        ];

        return view('finance.analytics.expense', compact(
            'byCategory', 'byVendor', 'monthlyTrend', 'paidUnpaid',
            'byMode', 'summary', 'months', 'from', 'to'
        ));
    }

    // ── 3. Lab Analytics ─────────────────────────────────────────────────

    public function labAnalytics(Request $request)
    {
        $months = (int) $request->input('months', 6);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // Cases summary
        $totalCases    = LabCase::whereBetween('created_at', [$from, $to])->count();
        $openCases     = LabCase::whereIn('status', LabCase::OPEN_STATUSES)->count();
        $closedCases   = LabCase::whereIn('status', ['final_received', 'complete'])->whereBetween('created_at', [$from, $to])->count();
        $overdueCount  = LabCase::whereIn('status', LabCase::OPEN_STATUSES)
                            ->where('expected_return_date', '<', today())->count();

        // Cases by lab vendor (top vendors)
        // Qualify created_at — both lab_cases and lab_vendors have a created_at
        // column, so after the join the unqualified name is ambiguous.
        $casesByVendor = LabCase::whereBetween('lab_cases.created_at', [$from, $to])
            ->join('lab_vendors as lv', 'lab_cases.lab_vendor_id', '=', 'lv.id')
            ->selectRaw('lv.name as vendor, COUNT(*) as cnt, SUM(lab_cost) as total_charge, AVG(lab_cost) as avg_charge')
            ->groupBy('lv.name')
            ->orderByDesc('cnt')
            ->get();

        // Monthly case volume
        $monthlyCases = LabCase::whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt, SUM(lab_cost) as total_charge")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();

        // Billing status breakdown
        $billingStatus = LabCase::whereBetween('created_at', [$from, $to])
            ->selectRaw('billing_status, COUNT(*) as cnt, SUM(lab_cost) as total')
            ->groupBy('billing_status')
            ->get()
            ->keyBy('billing_status');

        // Reconciliation summary
        $reconcSummary = LabMonthlyReconciliation::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as cnt, SUM(agreed_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Outstanding lab bills (approved but unpaid reconciliations)
        $outstandingBills = LabMonthlyReconciliation::whereIn('status', ['approved'])
            ->with('labVendor')
            ->orderByDesc('agreed_amount')
            ->limit(10)
            ->get();

        // Cost per case by work category
        $costByCategory = LabCase::whereBetween('created_at', [$from, $to])
            ->whereNotNull('work_category')
            ->whereNotNull('lab_cost')
            ->where('lab_cost', '>', 0)
            ->selectRaw('work_category, COUNT(*) as cnt, SUM(lab_cost) as total, AVG(lab_cost) as avg_cost')
            ->groupBy('work_category')
            ->orderByDesc('total')
            ->get();

        // Avg turnaround time (days between created and received)
        $avgTat = LabCase::whereNotNull('received_date')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('AVG(DATEDIFF(received_date, created_at)) as avg_days')
            ->value('avg_days');

        $kpis = [
            'total_cases'    => $totalCases,
            'open_cases'     => $openCases,
            'closed_cases'   => $closedCases,
            'overdue_cases'  => $overdueCount,
            'total_spend'    => LabCase::whereBetween('created_at', [$from, $to])->sum('lab_cost'),
            'outstanding'    => LabMonthlyReconciliation::where('status', 'approved')->sum('agreed_amount'),
            'avg_tat'        => round($avgTat ?? 0, 1),
            'avg_cost'       => $totalCases > 0 ? round(LabCase::whereBetween('created_at', [$from, $to])->sum('lab_cost') / $totalCases, 0) : 0,
        ];

        return view('finance.analytics.lab', compact(
            'kpis', 'casesByVendor', 'monthlyCases', 'billingStatus',
            'reconcSummary', 'outstandingBills', 'costByCategory',
            'months', 'from', 'to'
        ));
    }

    // ── 4. Procurement Analytics ──────────────────────────────────────────

    public function procurementAnalytics(Request $request)
    {
        $months = (int) $request->input('months', 6);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // PO summary
        $poSummary = PurchaseOrder::whereBetween('order_date', [$from, $to])
            ->selectRaw('status, COUNT(*) as cnt, SUM(total_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Monthly PO trend
        $monthlyPo = PurchaseOrder::whereBetween('order_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as cnt, SUM(total_amount) as total")
            ->groupByRaw("DATE_FORMAT(order_date, '%Y-%m')")
            ->orderBy('month')
            ->get();

        // Top vendors by PO value
        $topVendors = PurchaseOrder::whereBetween('order_date', [$from, $to])
            ->join('finance_vendors as fv', 'purchase_orders.finance_vendor_id', '=', 'fv.id')
            ->selectRaw('fv.vendor_name, COUNT(*) as cnt, SUM(purchase_orders.total_amount) as total')
            ->groupBy('fv.vendor_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // GRN summary
        // GRN totals: the per-receipt value lives in grn_items.total_price,
        // so we join the line items and sum them up per receipt.
        $grnSummary = GoodsReceiptNote::whereBetween('received_date', [$from, $to])
            ->leftJoin('grn_items as gi', 'gi.grn_id', '=', 'goods_receipt_notes.id')
            ->selectRaw('COUNT(DISTINCT goods_receipt_notes.id) as cnt, COALESCE(SUM(gi.total_price), 0) as total')
            ->first();

        // Pending GRNs (POs with open/partial status)
        $pendingPos = PurchaseOrder::whereIn('status', ['pending', 'partial'])
            ->with(['financeVendor'])
            ->orderByDesc('order_date')
            ->limit(10)
            ->get();

        // Vendor invoice status
        $invoiceStatus = VendorInvoice::whereBetween('invoice_date', [$from, $to])
            ->selectRaw('status, COUNT(*) as cnt, SUM(total_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $kpis = [
            'total_pos'        => ($poSummary->sum('cnt')),
            'total_po_value'   => ($poSummary->sum('total')),
            'pending_pos'      => $pendingPos->count(),
            'total_invoiced'   => VendorInvoice::whereBetween('invoice_date', [$from, $to])->sum('total_amount'),
            'unpaid_invoices'  => VendorInvoice::whereIn('status', VendorInvoice::UNPAID_STATUSES)->sum('total_amount'),
            'grn_count'        => $grnSummary->cnt ?? 0,
            'grn_value'        => $grnSummary->total ?? 0,
        ];

        return view('finance.analytics.procurement', compact(
            'kpis', 'poSummary', 'monthlyPo', 'topVendors',
            'pendingPos', 'invoiceStatus', 'grnSummary',
            'months', 'from', 'to'
        ));
    }

    // ── 5. Cash Flow Dashboard ────────────────────────────────────────────

    public function cashflow(Request $request)
    {
        $months = (int) $request->input('months', 6);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // Monthly cash IN (invoice payments)
        $cashIn = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupByRaw("DATE_FORMAT(payment_date, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Monthly cash OUT (paid expenses)
        $cashOut = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->where('payment_status', 'paid')
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total")
            ->groupByRaw("DATE_FORMAT(expense_date, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Build combined months array
        $allMonths = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m');
            $in  = $cashIn[$key]->total ?? 0;
            $out = $cashOut[$key]->total ?? 0;
            $allMonths->push([
                'month'  => $key,
                'label'  => $cursor->format('M Y'),
                'in'     => $in,
                'out'    => $out,
                'net'    => $in - $out,
            ]);
            $cursor->addMonth();
        }

        // Forecast: unpaid expenses by due date (next 90 days)
        $forecast = FinanceExpense::where('payment_status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', today())
            ->where('due_date', '<=', today()->addDays(90))
            ->with(['vendor', 'category'])
            ->orderBy('due_date')
            ->get();

        // Overdue bills
        $overdue = FinanceExpense::where('payment_status', 'unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->with(['vendor', 'category'])
            ->orderBy('due_date')
            ->get();

        // Current month KPIs
        $thisMonth = $allMonths->last();

        $kpis = [
            'total_in'       => $allMonths->sum('in'),
            'total_out'      => $allMonths->sum('out'),
            'net_cashflow'   => $allMonths->sum('net'),
            'forecast_due'   => $forecast->sum('total_amount'),
            'overdue_amount' => $overdue->sum('total_amount'),
            'this_month_in'  => $thisMonth['in'] ?? 0,
            'this_month_out' => $thisMonth['out'] ?? 0,
            'this_month_net' => $thisMonth['net'] ?? 0,
        ];

        return view('finance.analytics.cashflow', compact(
            'allMonths', 'forecast', 'overdue', 'kpis',
            'months', 'from', 'to'
        ));
    }

    // ── 6. Outstanding Dashboard ──────────────────────────────────────────

    public function outstanding(Request $request)
    {
        // Patient outstanding (unpaid invoices)
        $patientOutstanding = Invoice::whereIn('status', ['draft', 'partial'])
            ->with('patient')
            ->orderByDesc('balance_due')
            ->paginate(20, ['*'], 'invoice_page')
            ->withQueryString();

        // Vendor outstanding (unpaid finance expenses)
        $vendorOutstanding = FinanceExpense::where('payment_status', 'unpaid')
            ->with(['vendor', 'category'])
            ->orderBy('due_date')
            ->paginate(20, ['*'], 'expense_page')
            ->withQueryString();

        // Vendor invoice outstanding (from procurement)
        $procurementOutstanding = VendorInvoice::whereIn('status', VendorInvoice::UNPAID_STATUSES)
            ->with(['financeVendor', 'purchaseOrder'])
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        // Lab reconciliation outstanding
        $labOutstanding = LabMonthlyReconciliation::whereIn('status', ['approved'])
            ->with('labVendor')
            ->orderByDesc('agreed_amount')
            ->get();

        $kpis = [
            'patient_outstanding'  => Invoice::whereIn('status', ['draft', 'partial'])->sum('balance_due'),
            'vendor_outstanding'   => FinanceExpense::where('payment_status', 'unpaid')->sum('total_amount'),
            'procurement_due'      => VendorInvoice::whereIn('status', VendorInvoice::UNPAID_STATUSES)->sum('total_amount'),
            'lab_outstanding'      => LabMonthlyReconciliation::where('status', 'approved')->sum('agreed_amount'),
            'overdue_patient_cnt'  => Invoice::where('status', '!=', 'paid')
                                        ->whereNotNull('due_date')->where('due_date', '<', today())->count(),
            'overdue_vendor_cnt'   => FinanceExpense::where('payment_status', 'unpaid')
                                        ->whereNotNull('due_date')->where('due_date', '<', today())->count(),
        ];

        return view('finance.analytics.outstanding', compact(
            'kpis', 'patientOutstanding', 'vendorOutstanding',
            'procurementOutstanding', 'labOutstanding'
        ));
    }

    // ── 7. Business Intelligence ──────────────────────────────────────────

    public function businessIntelligence(Request $request)
    {
        $months = (int) $request->input('months', 12);
        $from   = now()->subMonths($months - 1)->startOfMonth();
        $to     = now()->endOfMonth();

        // Monthly revenue vs expense vs profit
        $revenue = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupByRaw("DATE_FORMAT(payment_date, '%Y-%m')")
            ->orderBy('month')
            ->get()->keyBy('month');

        $expense = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->where('payment_status', 'paid')
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total")
            ->groupByRaw("DATE_FORMAT(expense_date, '%Y-%m')")
            ->orderBy('month')
            ->get()->keyBy('month');

        $profitability = collect();
        $cursor = $from->copy();
        while ($cursor <= $to) {
            $key     = $cursor->format('Y-m');
            $rev     = $revenue[$key]->total ?? 0;
            $exp     = $expense[$key]->total ?? 0;
            $profitability->push([
                'month'   => $key,
                'label'   => $cursor->format('M Y'),
                'revenue' => $rev,
                'expense' => $exp,
                'profit'  => $rev - $exp,
                'margin'  => $rev > 0 ? round((($rev - $exp) / $rev) * 100, 1) : 0,
            ]);
            $cursor->addMonth();
        }

        // Quarter-over-quarter roll-up of the same monthly figures — Indian FY
        // convention (Q1=Apr-Jun ... Q4=Jan-Mar), matching fyRange() in
        // Finance\FinanceReportsController. Reuses $profitability, no new query.
        // Built as a plain array, not a Collection, while accumulating — writing
        // to a nested array through Collection's ArrayAccess (`$c[$k]['x'] += 1`)
        // silently no-ops in PHP ("indirect modification of overloaded element"),
        // it does not throw, so it's an easy way to ship rows that never total.
        $quarterlyMap = [];
        foreach ($profitability as $row) {
            [$yearNum, $monthNum] = array_map('intval', explode('-', $row['month']));
            $fyStart = $monthNum >= 4 ? $yearNum : $yearNum - 1;
            $fyEnd   = $fyStart + 1;
            $qNum    = intdiv((($monthNum - 4 + 12) % 12), 3) + 1;
            $qKey    = "FY{$fyStart}-{$fyEnd} Q{$qNum}";

            if (! isset($quarterlyMap[$qKey])) {
                $quarterlyMap[$qKey] = ['quarter' => $qKey, 'revenue' => 0, 'expense' => 0, 'profit' => 0];
            }
            $quarterlyMap[$qKey]['revenue'] += $row['revenue'];
            $quarterlyMap[$qKey]['expense'] += $row['expense'];
            $quarterlyMap[$qKey]['profit']  += $row['profit'];
        }
        $quarterly = collect($quarterlyMap)->map(function ($q) {
            $q['margin'] = $q['revenue'] > 0 ? round(($q['profit'] / $q['revenue']) * 100, 1) : 0;
            return $q;
        })->values();

        // Revenue breakdown by payment mode
        $revenueByMode = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_mode')
            ->orderByDesc('total')
            ->get();

        // Expense breakdown by category
        $expenseByCategory = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->join('finance_expense_categories as ec', 'finance_expenses.category_id', '=', 'ec.id')
            ->selectRaw('ec.name as category, SUM(total_amount) as total')
            ->groupBy('ec.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // Procurement trend
        $procurementTrend = PurchaseOrder::whereBetween('order_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as cnt, SUM(total_amount) as total")
            ->groupByRaw("DATE_FORMAT(order_date, '%Y-%m')")
            ->orderBy('month')
            ->get()->keyBy('month');

        // KPI summary
        $totalRevenue = $profitability->sum('revenue');
        $totalExpense = $profitability->sum('expense');
        $totalProfit  = $profitability->sum('profit');

        $kpis = [
            'total_revenue'    => $totalRevenue,
            'total_expense'    => $totalExpense,
            'total_profit'     => $totalProfit,
            'profit_margin'    => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0,
            'avg_monthly_rev'  => $months > 0 ? round($totalRevenue / $months, 0) : 0,
            'avg_monthly_exp'  => $months > 0 ? round($totalExpense / $months, 0) : 0,
            'best_month_rev'   => $profitability->max('revenue'),
            'patient_outstanding' => Invoice::whereIn('status', ['draft', 'partial'])->sum('balance_due'),
        ];

        return view('finance.analytics.business', compact(
            'kpis', 'profitability', 'quarterly', 'revenueByMode', 'expenseByCategory',
            'procurementTrend', 'months', 'from', 'to'
        ));
    }

    // ── 8. Financial Audit Log ────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $from   = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to     = $request->filled('to')   ? $request->to   : today()->toDateString();
        $type   = $request->input('type', ''); // payments|vouchers|expenses|lab|procurement

        // Payments audit (InvoicePayments)
        $recentPayments = InvoicePayment::with(['invoice' => fn($q) => $q->with('patient')])
            ->whereBetween('payment_date', [$from, $to])
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->limit(50)->get();

        // Voucher history
        $recentVouchers = FinanceVoucher::with(['expense' => fn($q) => $q->with('vendor')])
            ->whereBetween('voucher_date', [$from, $to])
            ->orderByDesc('voucher_date')->orderByDesc('id')
            ->limit(50)->get();

        // Expense changes (recently updated)
        $recentExpenses = FinanceExpense::with(['category', 'vendor'])
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('updated_at')
            ->limit(50)->get();

        // Lab reconciliation history
        $recentReconciliations = LabMonthlyReconciliation::with('labVendor')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(20)->get();

        // Vendor invoice audit
        $recentVendorInvoices = VendorInvoice::with(['financeVendor', 'purchaseOrder'])
            ->whereBetween('invoice_date', [$from, $to])
            ->orderByDesc('invoice_date')
            ->limit(20)->get();

        $summary = [
            'payment_total'     => $recentPayments->sum('amount'),
            'voucher_total'     => $recentVouchers->sum('amount'),
            'expense_total'     => $recentExpenses->sum('total_amount'),
            'reconcil_total'    => $recentReconciliations->sum('agreed_amount'),
            'vendor_inv_total'  => $recentVendorInvoices->sum('total_amount'),
        ];

        return view('finance.analytics.audit', compact(
            'recentPayments', 'recentVouchers', 'recentExpenses',
            'recentReconciliations', 'recentVendorInvoices',
            'summary', 'from', 'to', 'type'
        ));
    }
}
