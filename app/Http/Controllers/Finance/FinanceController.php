<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceTransaction;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\Finance\FinancePayroll;
use App\Models\Finance\FinanceBankAccount;
use App\Models\Finance\FinanceVoucher;
use App\Models\FinalBill;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceItem;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Assistant\ReceiptScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Finance Module Controller — F5: Finance Mirror + Accounts Module
 *
 * Income:    reads from invoice_payments (real billing receipts)
 * Expenses:  reads/writes from finance_expenses (full CRUD)
 * Vendors:   reads/writes from finance_vendors (full CRUD)
 * Payroll:   reads/writes from finance_payroll (CRUD)
 * Cashbook:  aggregate daily cash in/out
 * Banking:   finance_bank_accounts list
 * GST:       invoice_items with gst_pct > 0
 * CA Export: downloadable CSV
 */
class FinanceController extends Controller
{
    // ── Finance Dashboard ──────────────────────────────────────────────────

    public function dashboard()
    {
        $now   = now();
        $today = today();

        // Today KPIs — from real billing payments and expenses
        $todayIncome   = InvoicePayment::whereDate('payment_date', $today)->sum('amount');
        $todayExpenses = FinanceExpense::whereDate('expense_date', $today)->sum('total_amount');

        // This Month
        $monthlyRevenue = InvoicePayment::whereMonth('payment_date', $now->month)
                            ->whereYear('payment_date', $now->year)
                            ->sum('amount');
        $monthlyExpense = FinanceExpense::thisMonth()->sum('total_amount');
        $monthlyProfit  = $monthlyRevenue - $monthlyExpense;
        $profitPct      = $monthlyRevenue > 0
                            ? round(($monthlyProfit / $monthlyRevenue) * 100, 1)
                            : 0;

        // Avg daily & month-end projection
        $dayOfMonth        = (int) $today->format('j');
        $daysInMonth       = (int) $today->daysInMonth;
        $avgDaily          = $dayOfMonth > 0 ? round($monthlyRevenue / $dayOfMonth) : 0;
        $projectedMonthEnd = $avgDaily * $daysInMonth;

        // Outstanding (unpaid invoices from real billing)
        $outstandingAmount = Invoice::whereIn('status', ['draft', 'partial'])->sum('balance_due');
        $outstandingCount  = Invoice::whereIn('status', ['draft', 'partial'])->count();

        // Cash in hand estimate (cash received minus cash expenses)
        $cashReceived = InvoicePayment::where('payment_mode', 'cash')->sum('amount');
        $cashSpent    = FinanceExpense::where('payment_mode', 'cash')->sum('total_amount');

        $kpis = [
            'today_collection'     => $todayIncome,
            'today_expenses'       => $todayExpenses,
            'cash_in_hand'         => max(0, $cashReceived - $cashSpent),
            'bank_balance'         => 0, // wire to FinanceBankAccount when balances tracked
            'net_collection'       => $todayIncome - $todayExpenses,
            'pending_payments'     => $outstandingAmount,
            'outstanding_amount'   => $outstandingAmount,
            'outstanding_count'    => $outstandingCount,
            'monthly_revenue'      => $monthlyRevenue,
            'monthly_expense'      => $monthlyExpense,
            'monthly_profit'       => $monthlyProfit,
            'profit_percentage'    => $profitPct,
            'avg_daily_collection' => $avgDaily,
            'projected_month_end'  => $projectedMonthEnd,
        ];

        // Recent Payments (last 10)
        $recentTransactions = InvoicePayment::with(['invoice' => fn($q) => $q->with('patient')])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'patient'  => $p->invoice?->patient?->name ?? 'N/A',
                'type'     => 'income',
                'category' => 'Invoice Payment',
                'amount'   => $p->amount,
                'mode'     => $p->payment_mode,
                'date'     => $p->payment_date?->isToday()
                                ? 'Today'
                                : ($p->payment_date?->isYesterday() ? 'Yesterday' : $p->payment_date?->format('d M')),
                'status'   => 'active',
            ]);

        // Top expense categories this month
        $topExpensesRaw = FinanceExpense::thisMonth()
            ->join('finance_expense_categories as ec', 'finance_expenses.category_id', '=', 'ec.id')
            ->select('ec.name as category', DB::raw('SUM(finance_expenses.total_amount) as amount'))
            ->groupBy('ec.name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        $totalExpAmt = $topExpensesRaw->sum('amount') ?: 1;
        $topExpenses = $topExpensesRaw->map(fn($r) => [
            'category' => $r->category,
            'amount'   => $r->amount,
            'percent'  => round(($r->amount / $totalExpAmt) * 100),
        ])->toArray();

        return view('finance.dashboard', compact('kpis', 'recentTransactions', 'topExpenses'));
    }

    // ── Income ─────────────────────────────────────────────────────────────
    // Real data from invoice_payments — mirrored from billing on every payment recorded.

    public function income(Request $request)
    {
        $today  = today();
        $activeTab = $request->input('tab', 'invoices'); // invoices | receipts | bills | trash

        // ── Date preset resolution ─────────────────────────────────────────
        $preset = $request->input('preset', '');
        [$defaultFrom, $defaultTo] = $this->resolveDatePreset($preset, $today);
        $from   = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : $defaultFrom;
        $to     = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : $defaultTo;

        $mode         = $request->input('mode', '');
        $search       = $request->input('search', '');
        $statusFilter = $request->input('status', '');
        $sortBy       = $request->input('sort', 'newest');
        $modes        = ['cash', 'card', 'upi', 'cheque', 'netbanking', 'emi', 'other', 'bank_transfer', 'debit_card'];
        $presets      = ['today', 'yesterday', 'week', 'month', 'quarter', 'fy'];

        // ── KPI strip (always shown) ───────────────────────────────────────
        $kpis = [
            'today'       => InvoicePayment::whereDate('payment_date', $today)->sum('amount'),
            'this_week'   => InvoicePayment::whereBetween('payment_date', [
                                $today->copy()->startOfWeek(), $today->copy()->endOfWeek(),
                             ])->sum('amount'),
            'this_month'  => InvoicePayment::whereMonth('payment_date', now()->month)
                                ->whereYear('payment_date', now()->year)->sum('amount'),
            'outstanding' => Invoice::whereIn('status', ['draft', 'partial'])->sum('balance_due'),
        ];

        // ── Tab data ──────────────────────────────────────────────────────
        $invoices = $receipts = $bills = $trashInvoices = $trashReceipts = $trashBills = collect();
        $byMode   = collect();

        // ── INVOICES tab ─────────────────────────────────────────────────
        if ($activeTab === 'invoices') {
            $q = Invoice::with('patient')
                ->join('patients', 'invoices.patient_id', '=', 'patients.id')
                ->select('invoices.*')
                ->whereBetween('invoices.invoice_date', [$from, $to]);

            if ($statusFilter === 'paid')    $q->where('invoices.status', 'paid');
            elseif ($statusFilter === 'partial') $q->where('invoices.status', 'partial');
            elseif ($statusFilter === 'unpaid')  $q->where('invoices.status', 'draft');

            if ($search) $q->where(fn($s) => $s
                ->where('patients.name', 'like', "%$search%")
                ->orWhere('patients.phone', 'like', "%$search%")
                ->orWhere('invoices.invoice_number', 'like', "%$search%"));

            match ($sortBy) {
                'oldest'       => $q->orderBy('invoices.invoice_date')->orderBy('invoices.id'),
                'amount_asc'   => $q->orderBy('invoices.total_amount'),
                'amount_desc'  => $q->orderByDesc('invoices.total_amount'),
                'patient_asc'  => $q->orderBy('patients.name'),
                'patient_desc' => $q->orderByDesc('patients.name'),
                default        => $q->orderByDesc('invoices.invoice_date')->orderByDesc('invoices.id'),
            };
            $invoices = $q->paginate(30)->withQueryString();
        }

        // ── RECEIPTS tab ─────────────────────────────────────────────────
        if ($activeTab === 'receipts') {
            $q = InvoicePayment::with(['invoice' => fn($q) => $q->with('patient')])
                ->join('invoices', function ($join) {
                    $join->on('invoice_payments.invoice_id', '=', 'invoices.id')
                         ->whereNull('invoices.deleted_at'); // exclude cancelled/soft-deleted invoices
                })
                ->join('patients', 'invoices.patient_id', '=', 'patients.id')
                // Left-join receipts so we can surface receipt_id + receipt_number for the void modal
                ->leftJoin('receipts', function ($join) {
                    $join->on('receipts.invoice_payment_id', '=', 'invoice_payments.id')
                         ->whereNull('receipts.deleted_at');
                })
                ->select('invoice_payments.*', 'receipts.id as receipt_id', 'receipts.receipt_number as receipt_number')
                ->whereBetween('invoice_payments.payment_date', [$from, $to]);

            if ($mode) $q->where('invoice_payments.payment_mode', $mode);
            if ($statusFilter === 'paid')    $q->where('invoices.status', 'paid');
            elseif ($statusFilter === 'partial') $q->where('invoices.status', 'partial');
            elseif ($statusFilter === 'unpaid')  $q->where('invoices.status', 'draft');

            if ($search) $q->where(fn($s) => $s
                ->where('patients.name', 'like', "%$search%")
                ->orWhere('patients.phone', 'like', "%$search%"));

            match ($sortBy) {
                'oldest'       => $q->orderBy('invoice_payments.payment_date')->orderBy('invoice_payments.id'),
                'amount_asc'   => $q->orderBy('invoice_payments.amount'),
                'amount_desc'  => $q->orderByDesc('invoice_payments.amount'),
                'patient_asc'  => $q->orderBy('patients.name'),
                'patient_desc' => $q->orderByDesc('patients.name'),
                default        => $q->orderByDesc('invoice_payments.payment_date')->orderByDesc('invoice_payments.id'),
            };
            $receipts = $q->paginate(30)->withQueryString();

            $byMode = InvoicePayment::whereBetween('payment_date', [$from, $to])
                ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
                ->groupBy('payment_mode')->get()->keyBy('payment_mode');
        }

        // ── BILLS tab ────────────────────────────────────────────────────
        if ($activeTab === 'bills') {
            $q = FinalBill::with('patient')
                ->join('patients', 'final_bills.patient_id', '=', 'patients.id')
                ->select('final_bills.*')
                ->whereBetween('final_bills.generated_date', [$from, $to]);

            if ($search) $q->where(fn($s) => $s
                ->where('patients.name', 'like', "%$search%")
                ->orWhere('patients.phone', 'like', "%$search%")
                ->orWhere('final_bills.bill_number', 'like', "%$search%"));

            match ($sortBy) {
                'oldest'       => $q->orderBy('final_bills.generated_date')->orderBy('final_bills.id'),
                'amount_asc'   => $q->orderBy('final_bills.total_amount'),
                'amount_desc'  => $q->orderByDesc('final_bills.total_amount'),
                'patient_asc'  => $q->orderBy('patients.name'),
                'patient_desc' => $q->orderByDesc('patients.name'),
                default        => $q->orderByDesc('final_bills.generated_date')->orderByDesc('final_bills.id'),
            };
            $bills = $q->paginate(30)->withQueryString();
        }

        // ── TRASH tab ────────────────────────────────────────────────────
        if ($activeTab === 'trash') {
            $trashInvoices = Invoice::onlyTrashed()->with('patient')
                ->when($search, fn($q) => $q->whereHas('patient', fn($p) => $p
                    ->where('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                ))
                ->orderByDesc('deleted_at')->paginate(15, ['*'], 'inv_page')->withQueryString();

            $trashReceipts = Receipt::onlyTrashed()->with(['invoice.patient'])
                ->when($search, fn($q) => $q->whereHas('invoice.patient', fn($p) => $p
                    ->where('name', 'like', "%$search%")
                ))
                ->orderByDesc('deleted_at')->paginate(15, ['*'], 'rcp_page')->withQueryString();

            $trashBills = FinalBill::onlyTrashed()->with('patient')
                ->when($search, fn($q) => $q->whereHas('patient', fn($p) => $p
                    ->where('name', 'like', "%$search%")
                ))
                ->orderByDesc('deleted_at')->paginate(15, ['*'], 'bill_page')->withQueryString();
        }

        return view('finance.income', compact(
            'activeTab', 'kpis', 'byMode', 'modes', 'presets',
            'from', 'to', 'mode', 'search', 'preset', 'statusFilter', 'sortBy',
            'invoices', 'receipts', 'bills',
            'trashInvoices', 'trashReceipts', 'trashBills'
        ));
    }

    // ── Trash: restore soft-deleted records ───────────────────────────────

    public function restoreInvoice(int $id)
    {
        Invoice::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Invoice restored.');
    }

    public function restoreReceipt(int $id)
    {
        Receipt::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Receipt restored.');
    }

    public function restoreBill(int $id)
    {
        FinalBill::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Final bill restored.');
    }

    /**
     * Resolve a named date preset into [from, to] Carbon instances.
     */
    private function resolveDatePreset(string $preset, \Carbon\Carbon $today): array
    {
        return match ($preset) {
            'today'     => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'week'      => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'month'     => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'quarter'   => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'fy'        => $this->financialYearRange($today),
            default     => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
        };
    }

    /**
     * Indian financial year: April 1 → March 31.
     */
    private function financialYearRange(\Carbon\Carbon $today): array
    {
        $year = $today->month >= 4 ? $today->year : $today->year - 1;
        return [
            Carbon::create($year, 4, 1)->startOfDay(),
            Carbon::create($year + 1, 3, 31)->endOfDay(),
        ];
    }

    /**
     * Export income to PDF or Excel.
     * GET /finance/income/export?format=pdf|excel&from=&to=&mode=&status=&sort=
     */
    public function incomeExport(Request $request)
    {
        $today  = today();
        $preset = $request->input('preset', '');
        [$defaultFrom, $defaultTo] = $this->resolveDatePreset($preset, $today);

        $from        = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : $defaultFrom;
        $to          = $request->filled('to')   ? Carbon::parse($request->to)->endOfDay()     : $defaultTo;
        $mode        = $request->input('mode');
        $statusFilter = $request->input('status', '');
        $sortBy      = $request->input('sort', 'newest');
        $format      = $request->input('format', 'excel');

        $query = InvoicePayment::with(['invoice' => fn($q) => $q->with(['patient', 'items'])])
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->join('patients', 'invoices.patient_id', '=', 'patients.id')
            ->select('invoice_payments.*')
            ->whereBetween('invoice_payments.payment_date', [$from, $to]);

        if ($mode) { $query->where('invoice_payments.payment_mode', $mode); }
        if ($statusFilter === 'paid')    { $query->where('invoices.status', 'paid'); }
        elseif ($statusFilter === 'unpaid')  { $query->where('invoices.status', 'draft'); }
        elseif ($statusFilter === 'partial') { $query->where('invoices.status', 'partial'); }

        match ($sortBy) {
            'oldest'       => $query->orderBy('invoice_payments.payment_date'),
            'amount_asc'   => $query->orderBy('invoice_payments.amount'),
            'amount_desc'  => $query->orderByDesc('invoice_payments.amount'),
            'patient_asc'  => $query->orderBy('patients.name'),
            'patient_desc' => $query->orderByDesc('patients.name'),
            default        => $query->orderByDesc('invoice_payments.payment_date'),
        };

        $payments = $query->get();

        if ($format === 'pdf') {
            $totals = [
                'amount'  => $payments->sum('amount'),
                'balance' => $payments->sum(fn($p) => $p->invoice?->balance_due ?? 0),
                'count'   => $payments->count(),
            ];
            return view('finance.income-export-pdf', compact('payments', 'totals', 'from', 'to'));
        }

        // ── Excel ─────────────────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Income');

        $headers = [
            'A' => 'Date', 'B' => 'Patient', 'C' => 'Phone', 'D' => 'Invoice No',
            'E' => 'Treatment', 'F' => 'Invoice Total (₹)', 'G' => 'Amount Paid (₹)',
            'H' => 'Balance (₹)', 'I' => 'Mode', 'J' => 'Reference', 'K' => 'Invoice Status',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($payments as $p) {
            $treatments = $p->invoice?->items?->pluck('treatment_name')->filter()->implode(', ') ?? '';
            $sheet->setCellValue("A{$row}", $p->payment_date?->format('d-m-Y'));
            $sheet->setCellValue("B{$row}", $p->invoice?->patient?->name ?? '');
            $sheet->setCellValue("C{$row}", $p->invoice?->patient?->phone ?? '');
            $sheet->setCellValue("D{$row}", $p->invoice?->invoice_number ?? '');
            $sheet->setCellValue("E{$row}", $treatments);
            $sheet->setCellValue("F{$row}", $p->invoice?->total_amount ?? 0);
            $sheet->setCellValue("G{$row}", $p->amount);
            $sheet->setCellValue("H{$row}", $p->invoice?->balance_due ?? 0);
            $sheet->setCellValue("I{$row}", ucfirst($p->payment_mode ?? ''));
            $sheet->setCellValue("J{$row}", $p->reference_no ?? '');
            $sheet->setCellValue("K{$row}", ucfirst($p->invoice?->status ?? ''));
            $row++;
        }

        // Totals row
        $sheet->setCellValue("E{$row}", 'TOTAL');
        $sheet->setCellValue("F{$row}", $payments->sum(fn($p) => $p->invoice?->total_amount ?? 0));
        $sheet->setCellValue("G{$row}", $payments->sum('amount'));
        $sheet->setCellValue("H{$row}", $payments->sum(fn($p) => $p->invoice?->balance_due ?? 0));
        $sheet->getStyle("E{$row}:H{$row}")->getFont()->setBold(true);

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'income_' . $from->toDateString() . '_to_' . $to->toDateString() . '.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'inc_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Expenses ───────────────────────────────────────────────────────────

    public function expenses(Request $request)
    {
        $from   = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to     = $request->filled('to')   ? $request->to   : today()->toDateString();
        $cat    = $request->input('category_id');
        $search = $request->input('search');
        $tab    = $request->input('tab', 'all'); // all | unpaid | recurring | vouchers

        // ── Voucher Register tab ──────────────────────────────────────────────
        $vouchers     = null;
        $voucherTotal = 0;
        if ($tab === 'vouchers') {
            $vq = FinanceVoucher::with('vendor')
                ->whereBetween('voucher_date', [$from, $to]);

            $vendorFilter = $request->input('vendor_id');
            $modeFilter   = $request->input('vmode');

            if ($search) {
                $vq->where(fn($q) => $q
                    ->where('voucher_number', 'like', "%$search%")
                    ->orWhere('purpose',       'like', "%$search%")
                    ->orWhere('vendor_name',   'like', "%$search%"));
            }
            if ($vendorFilter) { $vq->where('vendor_id', $vendorFilter); }
            if ($modeFilter)   { $vq->where('payment_mode', $modeFilter); }

            $vouchers     = $vq->orderByDesc('voucher_date')->orderByDesc('id')
                               ->paginate(30)->withQueryString();
            $voucherTotal = FinanceVoucher::whereBetween('voucher_date', [$from, $to])->sum('amount');
        }

        $base = FinanceExpense::with(['category', 'vendor', 'voucher']); // Phase 2: voucher eager-loaded

        // Tab filtering
        if ($tab === 'unpaid') {
            $base->where('payment_status', 'unpaid');
            // Unpaid tab: don't restrict by date — show all outstanding bills
        } elseif ($tab === 'recurring') {
            $base->where('is_recurring', true)
                 ->whereBetween('expense_date', [$from, $to]);
        } elseif ($tab === 'vouchers') {
            // Voucher tab handled above — expense query is irrelevant, return empty
            $base->whereRaw('1=0');
        } else {
            $base->whereBetween('expense_date', [$from, $to]);
        }

        if ($cat)    { $base->where('category_id', $cat); }
        if ($search && $tab !== 'vouchers') { $base->where('title', 'like', "%$search%"); }

        $expenses = $base->orderByDesc('expense_date')->orderByDesc('id')
                         ->paginate(30)->withQueryString();

        $categories = FinanceExpenseCategory::orderBy('name')->get();
        $vendors    = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name', 'company_name']);

        // Summary strip (all-time for unpaid tab, date-range for others)
        $sumQ = FinanceExpense::query();
        if ($tab !== 'unpaid') { $sumQ->whereBetween('expense_date', [$from, $to]); }
        if ($tab === 'unpaid')  { $sumQ->where('payment_status', 'unpaid'); }
        $summary = $sumQ->selectRaw('SUM(amount) as subtotal, SUM(gst_amount) as gst, SUM(total_amount) as total, COUNT(*) as cnt')->first();

        // Unpaid counters for tab badges
        $unpaidCount   = FinanceExpense::where('payment_status', 'unpaid')->count();
        $unpaidAmount  = FinanceExpense::where('payment_status', 'unpaid')->sum('total_amount');
        $overdueCount  = FinanceExpense::overdue()->count();
        $recurringCount = FinanceExpense::where('is_recurring', true)->count();

        // Clinic bank accounts for the Mark as Paid modal (Phase 3)
        $bankAccounts = \App\Models\Finance\FinanceBankAccount::where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'account_type']);

        return view('finance.expenses', compact(
            'expenses', 'categories', 'vendors', 'summary',
            'from', 'to', 'cat', 'search', 'tab',
            'unpaidCount', 'unpaidAmount', 'overdueCount', 'recurringCount',
            'bankAccounts',
            'vouchers', 'voucherTotal'
        ));
    }

    /**
     * Scan Bill — read a photographed receipt with the local vision model and
     * return pre-fill values for the expense form. EXTRACTION ONLY: this never
     * writes to the database. The owner reviews the filled form and taps Save.
     *
     * POST /finance/expenses/scan  (expects an "image" file upload)
     * Responds JSON: { ok: true, data: {...} } or { ok: false, message: "..." }
     */
    public function expenseScan(Request $request, ReceiptScanService $scanner)
    {
        // Vision can be switched off entirely from config (kill-switch).
        if (!config('assistant.vision.enabled', true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Bill scanning is turned off. You can enter the expense manually.',
            ], 422);
        }

        $request->validate([
            // Accept common phone-camera formats, cap at ~12MB.
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,heic|max:12288',
        ]);

        try {
            // Hand the model the clinic's real category names so it picks one of OURS.
            $categoryNames = FinanceExpenseCategory::orderBy('name')->pluck('name')->all();

            $result = $scanner->scan(
                $request->file('image')->getRealPath(),
                $categoryNames
            );

            // Map the model's category guess back to a real category_id (if any).
            $categoryId = null;
            if (!empty($result['category_guess'])) {
                $match = FinanceExpenseCategory::whereRaw('LOWER(name) = ?', [
                    mb_strtolower($result['category_guess']),
                ])->first();
                $categoryId = $match?->id;
            }

            // Match vendor by name too, so the dropdown can preselect it.
            $vendorId = null;
            if (!empty($result['vendor_name'])) {
                $vendor = FinanceVendor::where('is_active', true)
                    ->where(function ($q) use ($result) {
                        $q->whereRaw('LOWER(vendor_name) = ?', [mb_strtolower($result['vendor_name'])])
                          ->orWhereRaw('LOWER(company_name) = ?', [mb_strtolower($result['vendor_name'])]);
                    })->first();
                $vendorId = $vendor?->id;
            }

            return response()->json([
                'ok'   => true,
                'data' => array_merge($result, [
                    'category_id' => $categoryId,
                    'vendor_id'   => $vendorId,
                ]),
            ]);
        } catch (\Throwable $e) {
            // Service throws friendly, human-readable messages — pass them through.
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function expenseCreate()
    {
        $categories = FinanceExpenseCategory::orderBy('name')->get();
        $vendors    = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name', 'company_name']);
        $expense    = null;
        return view('finance.expense-form', compact('categories', 'vendors', 'expense'));
    }

    public function expenseStore(Request $request)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:200',
            'category_id'       => 'nullable|exists:finance_expense_categories,id',
            'vendor_id'         => 'nullable|exists:finance_vendors,id',
            'expense_date'      => 'required|date',
            'amount'            => 'required|numeric|min:0',
            'gst_applicable'    => 'nullable|boolean',
            'gst_rate'          => 'nullable|numeric|min:0|max:28',
            'payment_mode'      => 'nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'payment_reference' => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:1000',
            'notes'             => 'nullable|string|max:500',
            'is_recurring'      => 'nullable|boolean',
            'recurring_period'  => 'nullable|in:daily,weekly,monthly,quarterly,yearly',
            'payment_status'    => 'nullable|in:paid,unpaid',
            'due_date'          => 'nullable|date',
        ]);

        $paymentStatus = $data['payment_status'] ?? 'paid';
        $gstApplicable = (bool) ($data['gst_applicable'] ?? false);
        $gstRate       = $gstApplicable ? (float) ($data['gst_rate'] ?? 0) : 0;
        $gstAmount     = round((float) $data['amount'] * $gstRate / 100, 2);

        FinanceExpense::create(array_merge($data, [
            'gst_applicable' => $gstApplicable,
            'gst_rate'       => $gstRate,
            'gst_amount'     => $gstAmount,
            'total_amount'   => (float) $data['amount'] + $gstAmount,
            'payment_status' => $paymentStatus,
            // payment_mode is optional for unpaid bills
            'payment_mode'   => $paymentStatus === 'paid' ? ($data['payment_mode'] ?? 'cash') : null,
            'status'         => 'approved',
            'created_by'     => auth()->id(),
        ]));

        $msg = $paymentStatus === 'unpaid'
            ? 'Bill recorded as unpaid/pending.'
            : 'Expense recorded successfully.';

        return redirect()->route('finance.expenses')->with('success', $msg);
    }

    public function expenseEdit(FinanceExpense $expense)
    {
        $categories = FinanceExpenseCategory::orderBy('name')->get();
        $vendors    = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name', 'company_name']);
        return view('finance.expense-form', compact('expense', 'categories', 'vendors'));
    }

    public function expenseUpdate(Request $request, FinanceExpense $expense)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:200',
            'category_id'       => 'nullable|exists:finance_expense_categories,id',
            'vendor_id'         => 'nullable|exists:finance_vendors,id',
            'expense_date'      => 'required|date',
            'amount'            => 'required|numeric|min:0',
            'gst_applicable'    => 'nullable|boolean',
            'gst_rate'          => 'nullable|numeric|min:0|max:28',
            'payment_mode'      => 'nullable|in:cash,upi,card,bank_transfer,cheque,other',
            'payment_reference' => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:1000',
            'notes'             => 'nullable|string|max:500',
            'is_recurring'      => 'nullable|boolean',
            'recurring_period'  => 'nullable|in:daily,weekly,monthly,quarterly,yearly',
            'payment_status'    => 'nullable|in:paid,unpaid',
            'due_date'          => 'nullable|date',
        ]);

        $gstApplicable = (bool) ($data['gst_applicable'] ?? false);
        $gstRate       = $gstApplicable ? (float) ($data['gst_rate'] ?? 0) : 0;
        $gstAmount     = round((float) $data['amount'] * $gstRate / 100, 2);

        $expense->update(array_merge($data, [
            'gst_applicable' => $gstApplicable,
            'gst_rate'       => $gstRate,
            'gst_amount'     => $gstAmount,
            'total_amount'   => (float) $data['amount'] + $gstAmount,
            'updated_by'     => auth()->id(),
        ]));

        return redirect()->route('finance.expenses')->with('success', 'Expense updated.');
    }

    /**
     * Mark an unpaid expense as paid — AJAX/form POST.
     * Accepts: paid_at, paid_amount, paid_mode, paid_reference
     */
    public function expenseMarkPaid(Request $request, FinanceExpense $expense)
    {
        $mode = $request->input('paid_mode');

        // ── Validation — Phase 3 mandatory fields ─────────────────────────────
        $rules = [
            'paid_at'             => 'required|date',
            'paid_amount'         => 'required|numeric|min:0.01',
            'paid_mode'           => 'required|in:cash,upi,card,bank_transfer,cheque,other',
            'paid_clinic_account' => 'required|exists:finance_bank_accounts,id',
            'notes'               => 'nullable|string|max:500',
        ];

        // UTR/reference required for all non-cash modes
        if ($mode !== 'cash') {
            $rules['paid_reference'] = 'required|string|max:100';
        } else {
            $rules['paid_reference'] = 'nullable|string|max:100';
        }

        // Cheque number required when mode is cheque
        if ($mode === 'cheque') {
            $rules['paid_cheque_number'] = 'required|string|max:50';
        }

        $data = $request->validate($rules);

        // Resolve clinic account name for caching on the record
        $clinicAccount     = \App\Models\Finance\FinanceBankAccount::findOrFail($data['paid_clinic_account']);
        $clinicAccountName = $clinicAccount->account_name;

        DB::transaction(function () use ($expense, $data, $mode, $clinicAccountName) {
            // ── 1. Mark expense as paid ───────────────────────────────────
            $expense->update([
                'payment_status'          => 'paid',
                'paid_at'                 => $data['paid_at'],
                'paid_amount'             => $data['paid_amount'],
                'paid_mode'               => $data['paid_mode'],
                'paid_reference'          => $data['paid_reference'] ?? null,
                'paid_clinic_account_id'  => $data['paid_clinic_account'],
                'paid_clinic_account_name'=> $clinicAccountName,
                'paid_cheque_number'      => $data['paid_cheque_number'] ?? null,
                // Mirror to legacy fields for backward-compat
                'payment_mode'            => $data['paid_mode'],
                'payment_reference'       => $data['paid_reference'] ?? null,
                'updated_by'              => auth()->id(),
            ]);

            // ── 2. Auto-generate Payment Voucher ──────────────────────────
            // Only create if one doesn't already exist for this expense.
            if (! $expense->voucher()->exists()) {
                \App\Models\Finance\FinanceVoucher::create([
                    'voucher_number'     => \App\Models\Finance\FinanceVoucher::generateNumber(),
                    'expense_id'         => $expense->id,
                    'vendor_id'          => $expense->vendor_id,
                    'vendor_name'        => $expense->vendor?->vendor_name
                                             ?? $expense->vendor?->company_name,
                    'voucher_date'       => $data['paid_at'],
                    'amount'             => $data['paid_amount'],
                    'payment_mode'       => $data['paid_mode'],
                    'reference'          => $data['paid_reference'] ?? null,
                    'clinic_account_id'  => $data['paid_clinic_account'],
                    'clinic_account_name'=> $clinicAccountName,
                    'cheque_number'      => $data['paid_cheque_number'] ?? null,
                    'purpose'            => $expense->title,
                    'notes'              => $data['notes'] ?? $expense->notes,
                    'created_by'         => auth()->id(),
                    'source_type'        => $expense->source_type,
                    'source_id'          => $expense->source_id,
                ]);
            }

            // ── 3. Sync Lab Bill status if this expense came from Lab Reconciliation ──
            if ($expense->source_type === \App\Models\LabMonthlyReconciliation::class) {
                $reconciliation = \App\Models\LabMonthlyReconciliation::find($expense->source_id);
                if ($reconciliation && $reconciliation->status === 'approved') {
                    $oldStatus = $reconciliation->status;
                    $reconciliation->update(['status' => 'paid']);
                    $reconciliation->logEvent('paid', $oldStatus, 'paid',
                        'Payment recorded via Finance. Voucher auto-generated.');

                    $reconciliation->items()->with('labCase')->each(function ($item) {
                        $item->labCase?->update(['billing_status' => 'paid']);
                    });
                }
            }
        });

        return redirect()->back()->with('success', "'{$expense->title}' marked as paid. Voucher generated.");
    }

    // ── Expense Export ─────────────────────────────────────────────────────

    /**
     * Export expenses to Excel or print-friendly HTML (PDF via browser).
     * GET /finance/expenses/export?format=excel|pdf&from=&to=&category_id=&vendor_id=&payment_status=
     */
    public function expenseExport(Request $request)
    {
        $from     = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to       = $request->filled('to')   ? $request->to   : today()->toDateString();
        $catId    = $request->input('category_id');
        $vendorId = $request->input('vendor_id');
        $pStatus  = $request->input('payment_status');
        $format   = $request->input('format', 'excel');

        $query = FinanceExpense::with(['category', 'vendor'])
            ->whereBetween('expense_date', [$from, $to])
            ->orderByDesc('expense_date');

        if ($catId)    { $query->where('category_id', $catId); }
        if ($vendorId) { $query->where('vendor_id', $vendorId); }
        if ($pStatus && $pStatus !== 'all') { $query->where('payment_status', $pStatus); }

        $expenses = $query->get();

        if ($format === 'pdf') {
            $totals = [
                'subtotal' => $expenses->sum('amount'),
                'gst'      => $expenses->sum('gst_amount'),
                'total'    => $expenses->sum('total_amount'),
            ];
            return view('finance.expenses-export-pdf', compact('expenses', 'totals', 'from', 'to'));
        }

        // ── Excel ──────────────────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expenses');

        $headers = ['A' => 'Date', 'B' => 'Title', 'C' => 'Category', 'D' => 'Vendor',
                    'E' => 'Amount (Rs)', 'F' => 'GST (Rs)', 'G' => 'Total (Rs)',
                    'H' => 'Payment Mode', 'I' => 'Status'];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($expenses as $e) {
            $sheet->setCellValue("A{$row}", $e->expense_date->format('d-m-Y'));
            $sheet->setCellValue("B{$row}", $e->title);
            $sheet->setCellValue("C{$row}", $e->category?->name ?? '');
            $sheet->setCellValue("D{$row}", $e->vendor?->vendor_name ?? '');
            $sheet->setCellValue("E{$row}", $e->amount);
            $sheet->setCellValue("F{$row}", $e->gst_amount ?? 0);
            $sheet->setCellValue("G{$row}", $e->total_amount);
            $sheet->setCellValue("H{$row}", ucfirst(str_replace('_', ' ', $e->payment_mode ?? 'N/A')));
            $sheet->setCellValue("I{$row}", ucfirst($e->payment_status ?? 'N/A'));
            $row++;
        }

        $sheet->setCellValue("D{$row}", 'TOTAL');
        $sheet->setCellValue("E{$row}", $expenses->sum('amount'));
        $sheet->setCellValue("F{$row}", $expenses->sum('gst_amount'));
        $sheet->setCellValue("G{$row}", $expenses->sum('total_amount'));
        $sheet->getStyle("D{$row}:G{$row}")->getFont()->setBold(true);

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'expenses_' . $from . '_to_' . $to . '.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'exp_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function expenseDestroy(FinanceExpense $expense)
    {
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }

    // ── Vendors ────────────────────────────────────────────────────────────

    public function vendors(Request $request)
    {
        $search = $request->input('search');
        $type   = $request->input('type');

        $query = FinanceVendor::withCount('expenses')->orderBy('vendor_name');
        if ($search) {
            $query->where(fn($q) => $q
                ->where('vendor_name', 'like', "%$search%")
                ->orWhere('company_name', 'like', "%$search%"));
        }
        if ($type) { $query->where('vendor_type', $type); }

        $vendors = $query->paginate(25)->withQueryString();
        $types   = FinanceVendor::typeLabels();

        return view('finance.vendors', compact('vendors', 'types', 'search', 'type'));
    }

    public function vendorCreate()
    {
        $types  = array_keys(FinanceVendor::typeLabels());
        $vendor = null;
        return view('finance.vendor-form', compact('vendor', 'types'));
    }

    public function vendorStore(Request $request)
    {
        $typeKeys = implode(',', array_keys(FinanceVendor::typeLabels()));
        $data = $request->validate([
            'vendor_name'    => 'required|string|max:150',
            'company_name'   => 'nullable|string|max:150',
            'vendor_type'    => "required|in:{$typeKeys}",
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:150',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'gstin'          => 'nullable|string|max:20',
            'pan'            => 'nullable|string|max:15',
            'credit_days'    => 'nullable|integer|min:0',
            'bank_name'      => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code'      => 'nullable|string|max:15',
            'notes'          => 'nullable|string|max:500',
        ]);
        FinanceVendor::create(array_merge($data, ['created_by' => auth()->id()]));
        return redirect()->route('finance.vendors')->with('success', 'Vendor added.');
    }

    public function vendorEdit(FinanceVendor $vendor)
    {
        $types = array_keys(FinanceVendor::typeLabels());
        return view('finance.vendor-form', compact('vendor', 'types'));
    }

    public function vendorUpdate(Request $request, FinanceVendor $vendor)
    {
        $typeKeys = implode(',', array_keys(FinanceVendor::typeLabels()));
        $data = $request->validate([
            'vendor_name'    => 'required|string|max:150',
            'company_name'   => 'nullable|string|max:150',
            'vendor_type'    => "required|in:{$typeKeys}",
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:150',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'gstin'          => 'nullable|string|max:20',
            'pan'            => 'nullable|string|max:15',
            'credit_days'    => 'nullable|integer|min:0',
            'bank_name'      => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code'      => 'nullable|string|max:15',
            'notes'          => 'nullable|string|max:500',
            'is_active'      => 'nullable|boolean',
        ]);
        $vendor->update(array_merge($data, ['is_active' => $request->boolean('is_active', true)]));
        return redirect()->route('finance.vendors')->with('success', 'Vendor updated.');
    }

    public function vendorDestroy(FinanceVendor $vendor)
    {
        $vendor->delete();
        return back()->with('success', 'Vendor removed.');
    }

    // ── Payroll ────────────────────────────────────────────────────────────

    public function payroll(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$year, $mon] = array_pad(explode('-', $month), 2, '01');

        $records = FinancePayroll::with('staff')
            ->where('year', $year)->where('month', (int)$mon)
            ->orderBy('id')
            ->get();

        $staff = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);

        $summary = [
            'total_salary' => $records->sum('net_salary'),
            'total_count'  => $records->count(),
            'paid'         => $records->where('status', 'paid')->count(),
        ];

        return view('finance.payroll', compact('records', 'staff', 'summary', 'month', 'year', 'mon'));
    }

    public function payrollStore(Request $request)
    {
        $data = $request->validate([
            'user_id'          => 'required|exists:users,id',
            'month'            => 'required|integer|between:1,12',
            'year'             => 'required|integer|min:2020',
            'payment_date'     => 'required|date',
            'fixed_salary'     => 'required|numeric|min:0',
            'incentives'       => 'nullable|numeric|min:0',
            'bonus'            => 'nullable|numeric|min:0',
            'deductions'       => 'nullable|numeric|min:0',
            'advance_adjusted' => 'nullable|numeric|min:0',
            'payment_mode'     => 'required|in:cash,upi,card,bank_transfer,cheque,other',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        $net = (float)$data['fixed_salary']
             + (float)($data['incentives'] ?? 0)
             + (float)($data['bonus'] ?? 0)
             - (float)($data['deductions'] ?? 0)
             - (float)($data['advance_adjusted'] ?? 0);

        FinancePayroll::create(array_merge($data, [
            'net_salary' => max(0, $net),
            'status'     => 'paid',
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('finance.payroll', [
            'month' => str_pad($data['month'], 2, '0', STR_PAD_LEFT) . '-' . $data['year']
        ])->with('success', 'Payroll entry saved.');
    }

    public function payrollDestroy(FinancePayroll $payroll)
    {
        $payroll->delete();
        return back()->with('success', 'Payroll entry deleted.');
    }

    // ── Cashbook ───────────────────────────────────────────────────────────

    public function cashbook(Request $request)
    {
        $from = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to   = $request->filled('to')   ? $request->to   : today()->toDateString();

        $cashIn  = InvoicePayment::where('payment_mode', 'cash')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $cashOut = FinanceExpense::where('payment_mode', 'cash')
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('DATE(expense_date) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $period  = CarbonPeriod::create($from, $to);
        $rows    = collect();
        $balance = 0;

        foreach ($period as $date) {
            $d   = $date->toDateString();
            $in  = (float) ($cashIn[$d]  ?? 0);
            $out = (float) ($cashOut[$d] ?? 0);
            $balance += $in - $out;
            $rows->push(['date' => $d, 'cash_in' => $in, 'cash_out' => $out, 'net' => $in - $out, 'balance' => $balance]);
        }

        $totals = ['in' => $rows->sum('cash_in'), 'out' => $rows->sum('cash_out'), 'net' => $rows->sum('net')];

        return view('finance.cashbook', compact('rows', 'totals', 'from', 'to'));
    }

    // ── Banking ────────────────────────────────────────────────────────────

    public function banking(Request $request)
    {
        $accounts = FinanceBankAccount::orderBy('bank_name')->get();
        return view('finance.banking', compact('accounts'));
    }

    // ── CA Export ──────────────────────────────────────────────────────────

    /**
     * CA Export — structured multi-section Excel/CSV download for accountants.
     * Supports date range and Indian financial year (FY) presets.
     */
    public function caExport(Request $request)
    {
        $today  = today();
        $preset = $request->input('preset', 'fy');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to   = Carbon::parse($request->to)->endOfDay();
        } else {
            [$from, $to] = match ($preset) {
                'month'   => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
                'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfDay()],
                'fy'      => $this->financialYearRange($today),
                default   => $this->financialYearRange($today),
            };
        }

        $incomeTotal  = InvoicePayment::whereBetween('payment_date', [$from, $to])->sum('amount');
        $expenseTotal = FinanceExpense::whereBetween('expense_date', [$from, $to])->sum('total_amount');
        $gstCollected = InvoiceItem::whereHas('invoice', fn($q) =>
                            $q->whereBetween('invoice_date', [$from, $to])
                              ->whereNotIn('status', ['cancelled'])
                          )->where('gst_pct', '>', 0)->sum('gst_amount');

        if ($request->has('download')) {
            $format = $request->input('format', 'excel');
            return $this->downloadCaExport($from, $to, $format);
        }

        return view('finance.ca-export', compact('from', 'to', 'incomeTotal', 'expenseTotal', 'gstCollected', 'preset'));
    }

    private function downloadCaExport(Carbon $from, Carbon $to, string $format = 'excel')
    {
        $income   = InvoicePayment::with(['invoice' => fn($q) => $q->with(['patient', 'items'])])
            ->whereBetween('payment_date', [$from, $to])->orderBy('payment_date')->get();
        $expenses = FinanceExpense::with(['category', 'vendor'])
            ->whereBetween('expense_date', [$from, $to])->orderBy('expense_date')->get();
        $vouchers = FinanceVoucher::with('vendor')
            ->whereBetween('voucher_date', [$from, $to])->orderBy('voucher_date')->get();

        if ($format === 'csv') {
            return $this->caExportCsv($from, $to, $income, $expenses, $vouchers);
        }
        return $this->caExportExcel($from, $to, $income, $expenses, $vouchers);
    }

    private function caExportExcel(Carbon $from, Carbon $to, $income, $expenses, $vouchers)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()->setTitle(
            'CA Export ' . $from->format('d M Y') . ' to ' . $to->format('d M Y')
        );

        // Sheet 1: Income
        $sh = $spreadsheet->getActiveSheet()->setTitle('Income');
        foreach (['A' => 'Date', 'B' => 'Invoice No', 'C' => 'Patient', 'D' => 'Phone',
                  'E' => 'Treatments', 'F' => 'Invoice Total', 'G' => 'Amount Received',
                  'H' => 'Mode', 'I' => 'Reference'] as $c => $l) {
            $sh->setCellValue("{$c}1", $l);
            $sh->getColumnDimension($c)->setAutoSize(true);
            $sh->getStyle("{$c}1")->getFont()->setBold(true);
        }
        $row = 2;
        foreach ($income as $p) {
            $tx = $p->invoice?->items?->pluck('treatment_name')->filter()->implode(', ') ?? '';
            $sh->setCellValue("A{$row}", $p->payment_date?->format('d-m-Y'));
            $sh->setCellValue("B{$row}", $p->invoice?->invoice_number ?? '');
            $sh->setCellValue("C{$row}", $p->invoice?->patient?->name ?? '');
            $sh->setCellValue("D{$row}", $p->invoice?->patient?->phone ?? '');
            $sh->setCellValue("E{$row}", $tx);
            $sh->setCellValue("F{$row}", (float)($p->invoice?->total_amount ?? 0));
            $sh->setCellValue("G{$row}", (float)$p->amount);
            $sh->setCellValue("H{$row}", ucfirst($p->payment_mode ?? ''));
            $sh->setCellValue("I{$row}", $p->reference_no ?? '');
            $row++;
        }
        $sh->setCellValue("F{$row}", 'TOTAL'); $sh->setCellValue("G{$row}", $income->sum('amount'));
        $sh->getStyle("F{$row}:G{$row}")->getFont()->setBold(true);

        // Sheet 2: Expenses
        $spreadsheet->createSheet()->setTitle('Expenses');
        $sh2 = $spreadsheet->getSheetByName('Expenses');
        foreach (['A' => 'Date', 'B' => 'Title', 'C' => 'Category', 'D' => 'Vendor',
                  'E' => 'Amount', 'F' => 'GST', 'G' => 'Total', 'H' => 'Mode', 'I' => 'Status'] as $c => $l) {
            $sh2->setCellValue("{$c}1", $l); $sh2->getColumnDimension($c)->setAutoSize(true);
            $sh2->getStyle("{$c}1")->getFont()->setBold(true);
        }
        $row = 2;
        foreach ($expenses as $e) {
            $sh2->setCellValue("A{$row}", $e->expense_date?->format('d-m-Y'));
            $sh2->setCellValue("B{$row}", $e->title);
            $sh2->setCellValue("C{$row}", $e->category?->name ?? '');
            $sh2->setCellValue("D{$row}", $e->vendor?->vendor_name ?? '');
            $sh2->setCellValue("E{$row}", (float)$e->amount);
            $sh2->setCellValue("F{$row}", (float)$e->gst_amount);
            $sh2->setCellValue("G{$row}", (float)$e->total_amount);
            $sh2->setCellValue("H{$row}", ucfirst($e->payment_mode ?? ''));
            $sh2->setCellValue("I{$row}", ucfirst($e->payment_status ?? ''));
            $row++;
        }
        $sh2->setCellValue("D{$row}", 'TOTAL');
        $sh2->setCellValue("E{$row}", $expenses->sum('amount'));
        $sh2->setCellValue("F{$row}", $expenses->sum('gst_amount'));
        $sh2->setCellValue("G{$row}", $expenses->sum('total_amount'));
        $sh2->getStyle("D{$row}:G{$row}")->getFont()->setBold(true);

        // Sheet 3: Vouchers
        $spreadsheet->createSheet()->setTitle('Vouchers');
        $sh3 = $spreadsheet->getSheetByName('Vouchers');
        foreach (['A' => 'Date', 'B' => 'Voucher No', 'C' => 'Vendor', 'D' => 'Purpose',
                  'E' => 'Amount', 'F' => 'Mode', 'G' => 'Reference'] as $c => $l) {
            $sh3->setCellValue("{$c}1", $l); $sh3->getColumnDimension($c)->setAutoSize(true);
            $sh3->getStyle("{$c}1")->getFont()->setBold(true);
        }
        $row = 2;
        foreach ($vouchers as $v) {
            $sh3->setCellValue("A{$row}", $v->voucher_date?->format('d-m-Y'));
            $sh3->setCellValue("B{$row}", $v->voucher_number);
            $sh3->setCellValue("C{$row}", $v->vendor_name ?? $v->vendor?->vendor_name ?? '');
            $sh3->setCellValue("D{$row}", $v->purpose ?? '');
            $sh3->setCellValue("E{$row}", (float)$v->amount);
            $sh3->setCellValue("F{$row}", ucfirst($v->payment_mode ?? ''));
            $sh3->setCellValue("G{$row}", $v->reference ?? '');
            $row++;
        }
        $sh3->setCellValue("D{$row}", 'TOTAL'); $sh3->setCellValue("E{$row}", $vouchers->sum('amount'));
        $sh3->getStyle("D{$row}:E{$row}")->getFont()->setBold(true);

        $spreadsheet->setActiveSheetIndex(0);
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'ca_export_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'ca_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function caExportCsv(Carbon $from, Carbon $to, $income, $expenses, $vouchers)
    {
        $rows     = collect();
        $filename = 'ca_export_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv';
        $headers  = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($income, $expenses, $vouchers) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['=== INCOME (Receipts) ===']);
            fputcsv($out, ['Date', 'Invoice No', 'Patient', 'Mode', 'Amount']);
            foreach ($income as $p) {
                fputcsv($out, [
                    $p->payment_date?->format('d-m-Y'),
                    $p->invoice?->invoice_number ?? '',
                    $p->invoice?->patient?->name ?? '',
                    ucfirst($p->payment_mode ?? ''),
                    $p->amount,
                ]);
            }
            fputcsv($out, ['', '', '', 'TOTAL', $income->sum('amount')]);
            fputcsv($out, []);

            fputcsv($out, ['=== EXPENSES ===']);
            fputcsv($out, ['Date', 'Title', 'Category', 'Vendor', 'Amount', 'GST', 'Total', 'Mode', 'Status']);
            foreach ($expenses as $e) {
                fputcsv($out, [
                    $e->expense_date?->format('d-m-Y'), $e->title,
                    $e->category?->name ?? '', $e->vendor?->vendor_name ?? '',
                    $e->amount, $e->gst_amount, $e->total_amount,
                    ucfirst($e->payment_mode ?? ''), ucfirst($e->payment_status ?? ''),
                ]);
            }
            fputcsv($out, ['', '', '', 'TOTAL', $expenses->sum('amount'), $expenses->sum('gst_amount'), $expenses->sum('total_amount')]);
            fputcsv($out, []);

            fputcsv($out, ['=== PAYMENT VOUCHERS ===']);
            fputcsv($out, ['Date', 'Voucher No', 'Vendor', 'Purpose', 'Amount', 'Mode']);
            foreach ($vouchers as $v) {
                fputcsv($out, [
                    $v->voucher_date?->format('d-m-Y'), $v->voucher_number,
                    $v->vendor_name ?? $v->vendor?->vendor_name ?? '',
                    $v->purpose ?? '', $v->amount, ucfirst($v->payment_mode ?? ''),
                ]);
            }
            fputcsv($out, ['', '', '', 'TOTAL', $vouchers->sum('amount')]);
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── GST Summary ────────────────────────────────────────────────────────

    public function gst(Request $request)
    {
        $from = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to   = $request->filled('to')   ? $request->to   : today()->toDateString();

        $gstItems = InvoiceItem::with(['invoice.patient'])
            ->where('gst_pct', '>', 0)
            ->whereHas('invoice', fn($q) => $q
                ->whereBetween('invoice_date', [$from, $to])
                ->whereNotIn('status', ['cancelled']))
            ->orderByDesc('invoice_id')
            ->paginate(30)
            ->withQueryString();

        $byRate = InvoiceItem::where('gst_pct', '>', 0)
            ->whereHas('invoice', fn($q) => $q
                ->whereBetween('invoice_date', [$from, $to])
                ->whereNotIn('status', ['cancelled']))
            ->selectRaw('gst_pct, SUM(gst_amount) as total_gst, SUM(net_amount) as taxable_value, COUNT(*) as cnt')
            ->groupBy('gst_pct')
            ->orderBy('gst_pct')
            ->get();

        $totalGst = $byRate->sum('total_gst');

        return view('finance.gst', compact('gstItems', 'byRate', 'totalGst', 'from', 'to'));
    }
}
