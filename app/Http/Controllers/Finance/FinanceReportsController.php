<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\CouponCode;
use App\Models\CouponUsage;
use App\Models\Finance\FinanceTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportsController extends Controller
{
    // ── Resolve date range ─────────────────────────────────────────────────

    private function dateRange(Request $request): array
    {
        $today  = today();
        $preset = $request->input('preset', 'fy');

        if ($request->filled('from') && $request->filled('to')) {
            return [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay(),
                $preset,
            ];
        }

        [$from, $to] = match ($preset) {
            'month'   => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            'week'    => [$today->copy()->startOfWeek(),  $today->copy()->endOfDay()],
            'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfDay()],
            'fy'      => $this->fyRange($today),
            default   => $this->fyRange($today),
        };
        return [$from, $to, $preset];
    }

    private function fyRange(Carbon $today): array
    {
        $year = $today->month >= 4 ? $today->year : $today->year - 1;
        return [
            Carbon::create($year, 4, 1)->startOfDay(),
            Carbon::create($year + 1, 3, 31)->endOfDay(),
        ];
    }

    // ── Main Reports Hub ───────────────────────────────────────────────────

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'income');
        [$from, $to, $preset] = $this->dateRange($request);

        $data = match ($tab) {
            'income'       => $this->incomeData($from, $to),
            'expense'      => $this->expenseData($from, $to),
            'receivables'  => $this->receivablesData($from, $to),
            'payables'     => $this->payablesData($from, $to),
            'membership'   => $this->membershipData($from, $to),
            'wallet'       => $this->walletData($from, $to),
            'coupon'       => $this->couponData($from, $to),
            'discount'     => $this->discountData($from, $to),
            'advance'      => $this->advanceData($from, $to),
            'liability'    => $this->liabilityData($from, $to),
            'collection'   => $this->collectionData($from, $to),
            'provider'     => $this->providerData($from, $to),
            default        => $this->incomeData($from, $to),
        };

        if ($request->has('download')) {
            return $this->export($tab, $data, $from, $to, $request->input('format', 'excel'));
        }

        return view('finance.reports', compact('tab', 'from', 'to', 'preset', 'data'));
    }

    // ── 1. Income Summary ──────────────────────────────────────────────────

    private function incomeData(Carbon $from, Carbon $to): array
    {
        $total = InvoicePayment::whereBetween('payment_date', [$from, $to])->sum('amount');

        $byMonth = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as cnt")
            ->groupBy('month')->orderBy('month')->get();

        $byMode = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_mode')->orderByDesc('total')->get();

        $topPatients = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->join('patients', 'invoice_payments.patient_id', '=', 'patients.id')
            ->selectRaw('patients.id, patients.name, patients.phone, SUM(invoice_payments.amount) as total, COUNT(DISTINCT invoice_payments.invoice_id) as invoices')
            ->groupBy('patients.id', 'patients.name', 'patients.phone')
            ->orderByDesc('total')->limit(10)->get();

        return compact('total', 'byMonth', 'byMode', 'topPatients');
    }

    // ── 2. Expense Summary ─────────────────────────────────────────────────

    private function expenseData(Carbon $from, Carbon $to): array
    {
        $total = FinanceExpense::whereBetween('expense_date', [$from, $to])->sum('total_amount');

        $byCategory = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->join('finance_expense_categories as c', 'finance_expenses.category_id', '=', 'c.id')
            ->selectRaw('c.name as category, SUM(finance_expenses.total_amount) as total, COUNT(*) as cnt')
            ->groupBy('c.name')->orderByDesc('total')->get();

        $byMonth = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(total_amount) as total, COUNT(*) as cnt")
            ->groupBy('month')->orderBy('month')->get();

        $topVendors = FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->whereNotNull('vendor_id')
            ->join('finance_vendors as v', 'finance_expenses.vendor_id', '=', 'v.id')
            ->selectRaw('v.vendor_name, SUM(finance_expenses.total_amount) as total, COUNT(*) as cnt')
            ->groupBy('v.vendor_name')->orderByDesc('total')->limit(10)->get();

        return compact('total', 'byCategory', 'byMonth', 'topVendors');
    }

    // ── 3. Outstanding Receivables ─────────────────────────────────────────

    private function receivablesData(Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::with('patient')
            ->whereIn('status', ['draft', 'partial'])
            ->orderByDesc('balance_due')
            ->get()
            ->map(function ($inv) {
                $inv->age_days = (int) $inv->invoice_date?->diffInDays(today());
                return $inv;
            });

        $total  = $invoices->sum('balance_due');
        $over30 = $invoices->filter(fn($i) => $i->age_days > 30)->sum('balance_due');
        $over90 = $invoices->filter(fn($i) => $i->age_days > 90)->sum('balance_due');

        return compact('invoices', 'total', 'over30', 'over90');
    }

    // ── 4. Outstanding Payables ────────────────────────────────────────────

    private function payablesData(Carbon $from, Carbon $to): array
    {
        $bills = FinanceExpense::with('vendor')
            ->where('payment_status', 'unpaid')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($e) {
                $e->age_days = (int) ($e->expense_date?->diffInDays(today()) ?? 0);
                $e->overdue  = $e->due_date && $e->due_date->isPast();
                return $e;
            });

        $total    = $bills->sum('total_amount');
        $overdue  = $bills->where('overdue', true)->sum('total_amount');
        $over30   = $bills->filter(fn($b) => $b->age_days > 30)->sum('total_amount');

        return compact('bills', 'total', 'overdue', 'over30');
    }

    // ── 5. Membership Revenue ──────────────────────────────────────────────

    private function membershipData(Carbon $from, Carbon $to): array
    {
        $subscriptions = FinancePatientMembership::with(['patient', 'plan'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();

        $total   = $subscriptions->sum('amount_paid');
        $active  = FinancePatientMembership::where('status', 'active')->count();
        $expired = FinancePatientMembership::where('status', 'expired')->count();

        $byPlan = $subscriptions->groupBy(fn($s) => $s->plan?->name ?? 'Unknown')
            ->map(fn($group) => [
                'count'   => $group->count(),
                'revenue' => $group->sum('amount_paid'),
            ]);

        return compact('subscriptions', 'total', 'active', 'expired', 'byPlan');
    }

    // ── 6. Wallet Summary ──────────────────────────────────────────────────

    private function walletData(Carbon $from, Carbon $to): array
    {
        $credits = WalletTransaction::where('direction', 'credit')
            ->whereBetween('created_at', [$from, $to])->sum('amount');
        $debits  = WalletTransaction::where('direction', 'debit')
            ->whereBetween('created_at', [$from, $to])->sum('amount');

        $byType = WalletTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw('direction, credit_type, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('direction', 'credit_type')->orderBy('direction')->get();

        $outstanding = DB::table('wallets')->sum('balance_total');
        $patients    = DB::table('wallets')->where('balance_total', '>', 0)->count();

        $monthly = WalletTransaction::whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, direction, SUM(amount) as total")
            ->groupBy('month', 'direction')->orderBy('month')->get();

        return compact('credits', 'debits', 'byType', 'outstanding', 'patients', 'monthly');
    }

    // ── 7. Coupon Summary ──────────────────────────────────────────────────

    private function couponData(Carbon $from, Carbon $to): array
    {
        $usage = CouponUsage::with('coupon')
            ->whereBetween('used_at', [$from, $to])
            ->get();

        $totalDiscount = $usage->sum('discount_amount');
        $totalUsed     = $usage->count();

        $byCoupon = CouponCode::withCount(['usages as used_count' => fn($q) =>
                        $q->whereBetween('used_at', [$from, $to])
                    ])
                    ->withSum(['usages as total_discount' => fn($q) =>
                        $q->whereBetween('used_at', [$from, $to])
                    ], 'discount_amount')
                    ->orderByDesc('used_count')
                    ->get();

        return compact('usage', 'totalDiscount', 'totalUsed', 'byCoupon');
    }

    // ── 8. Discount Report (Coupon vs Manual) ──────────────────────────────

    private function discountData(Carbon $from, Carbon $to): array
    {
        // Coupon discounts (from coupon usage ledger)
        $couponUsages = CouponUsage::with(['coupon', 'patient'])
            ->whereBetween('used_at', [$from, $to])
            ->orderByDesc('used_at')->get();
        $couponTotal = (float) $couponUsages->sum('discount_amount');

        // Manual discounts (recorded on the invoice header)
        $manualInvoices = Invoice::with(['patient', 'manualDiscountApplier'])
            ->whereNotNull('manual_discount_at')
            ->whereBetween('manual_discount_at', [$from, $to])
            ->where('manual_discount_amount', '>', 0)
            ->orderByDesc('manual_discount_at')->get();
        $manualTotal = (float) $manualInvoices->sum('manual_discount_amount');

        return compact('couponUsages', 'couponTotal', 'manualInvoices', 'manualTotal');
    }

    // ── 9. Advance Payments Report ─────────────────────────────────────────

    private function advanceData(Carbon $from, Carbon $to): array
    {
        $advances = WalletTransaction::with('patient')
            ->where('source', 'advance')
            ->where('direction', 'credit')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')->get();

        $total = (float) $advances->sum('amount');
        $count = $advances->count();

        return compact('advances', 'total', 'count');
    }

    // ── 10. Credit Liability + Outstanding After Wallet ────────────────────

    private function liabilityData(Carbon $from, Carbon $to): array
    {
        // Wallet balances (money the clinic owes patients) — snapshot, not ranged.
        $wallets = Wallet::with('patient')
            ->where('balance_total', '>', 0)
            ->orderByDesc('balance_total')->get();

        $totalLiability = (float) $wallets->sum('balance_total');
        $promoTotal     = (float) $wallets->sum('balance_promotional');
        $permTotal      = (float) $wallets->sum('balance_permanent');

        // Outstanding after wallet: each patient's open balance minus their wallet.
        $openByPatient = Invoice::whereIn('status', ['draft', 'partial'])
            ->selectRaw('patient_id, SUM(balance_due) as outstanding')
            ->groupBy('patient_id')
            ->having('outstanding', '>', 0)
            ->get();

        $walletMap = Wallet::pluck('balance_total', 'patient_id');
        $patientNames = \App\Models\Patient::whereIn('id', $openByPatient->pluck('patient_id'))
            ->pluck('name', 'id');

        $outstandingRows = $openByPatient->map(function ($row) use ($walletMap, $patientNames) {
            $wallet = (float) ($walletMap[$row->patient_id] ?? 0);
            $out    = (float) $row->outstanding;
            return (object) [
                'patient_id'  => $row->patient_id,
                'name'        => $patientNames[$row->patient_id] ?? ('#' . $row->patient_id),
                'outstanding' => $out,
                'wallet'      => $wallet,
                'net'         => max(0, round($out - $wallet, 2)),
            ];
        })->sortByDesc('net')->values();

        $totalOutstanding = (float) $outstandingRows->sum('outstanding');
        $totalNet         = (float) $outstandingRows->sum('net');

        return compact('wallets', 'totalLiability', 'promoTotal', 'permTotal',
            'outstandingRows', 'totalOutstanding', 'totalNet');
    }

    // ── 11. Daily Collection (Invoice / Advance / Wallet-use / Refunds) ────

    private function collectionData(Carbon $from, Carbon $to): array
    {
        // Invoice collections — cash/card/etc against invoices.
        $invoiceByDay = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as d, SUM(amount) as total')
            ->groupBy('d')->pluck('total', 'd');

        // Advance collections — money taken into wallet with no invoice.
        $advanceByDay = WalletTransaction::where('source', 'advance')->where('direction', 'credit')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, SUM(amount) as total')
            ->groupBy('d')->pluck('total', 'd');

        // Wallet utilisation — wallet credit consumed against invoices.
        $walletUseByDay = WalletTransaction::where('source', 'invoice_debit')->where('direction', 'debit')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, SUM(amount) as total')
            ->groupBy('d')->pluck('total', 'd');

        // Refunds — money returned (receipt voids + wallet refunds).
        $refundByDay = FinanceTransaction::where('type', 'refund')
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('DATE(transaction_date) as d, SUM(net_amount) as total')
            ->groupBy('d')->pluck('total', 'd');

        // Merge all dates into one sorted daily table.
        $dates = collect()
            ->merge($invoiceByDay->keys())->merge($advanceByDay->keys())
            ->merge($walletUseByDay->keys())->merge($refundByDay->keys())
            ->unique()->sort()->values();

        $daily = $dates->map(fn ($d) => (object) [
            'date'       => $d,
            'invoice'    => (float) ($invoiceByDay[$d] ?? 0),
            'advance'    => (float) ($advanceByDay[$d] ?? 0),
            'wallet_use' => (float) ($walletUseByDay[$d] ?? 0),
            'refund'     => (float) ($refundByDay[$d] ?? 0),
        ]);

        return [
            'daily'            => $daily,
            'invoiceTotal'     => (float) $invoiceByDay->sum(),
            'advanceTotal'     => (float) $advanceByDay->sum(),
            'walletUseTotal'   => (float) $walletUseByDay->sum(),
            'refundTotal'      => (float) $refundByDay->sum(),
        ];
    }

    // ── 12. Provider (Per-Dentist) Earnings ────────────────────────────────
    //
    // Attributes collected revenue to the treating doctor via
    // invoice_payments -> invoices.appointment_id -> appointments.doctor_id.
    // Invoices with no appointment_id (e.g. raised directly against a
    // treatment plan with no linked appointment) fall into an "Unassigned"
    // bucket rather than being silently dropped or guessed at — this is the
    // real attribution the data supports today, not a perfect one.

    private function providerData(Carbon $from, Carbon $to): array
    {
        $baseQuery = fn () => InvoicePayment::whereBetween('invoice_payments.payment_date', [$from, $to])
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->leftJoin('appointments', 'invoices.appointment_id', '=', 'appointments.id')
            ->leftJoin('users', 'appointments.doctor_id', '=', 'users.id');

        $byDoctor = $baseQuery()
            ->selectRaw("COALESCE(users.id, 0) as doctor_id, COALESCE(users.name, 'Unassigned') as doctor_name, SUM(invoice_payments.amount) as total, COUNT(DISTINCT invoice_payments.invoice_id) as invoice_count, COUNT(*) as payment_count")
            ->groupBy('doctor_id', 'doctor_name')
            ->orderByDesc('total')
            ->get();

        $byDoctorMonth = $baseQuery()
            ->selectRaw("COALESCE(users.id, 0) as doctor_id, COALESCE(users.name, 'Unassigned') as doctor_name, DATE_FORMAT(invoice_payments.payment_date, '%Y-%m') as month, SUM(invoice_payments.amount) as total")
            ->groupBy('doctor_id', 'doctor_name', 'month')
            ->orderBy('month')
            ->orderByDesc('total')
            ->get()
            ->groupBy('doctor_name');

        $total      = (float) $byDoctor->sum('total');
        $unassigned = (float) optional($byDoctor->first(fn ($r) => (int) $r->doctor_id === 0))->total;
        $doctorRows = $byDoctor->filter(fn ($r) => (int) $r->doctor_id !== 0)->values();

        return compact('byDoctor', 'byDoctorMonth', 'total', 'unassigned', 'doctorRows');
    }

    // ── Export ─────────────────────────────────────────────────────────────

    private function export(string $tab, array $data, Carbon $from, Carbon $to, string $format)
    {
        if ($format === 'pdf') {
            return view('finance.reports-pdf', compact('tab', 'data', 'from', 'to'));
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $title = match ($tab) {
            'income'      => 'Income Summary',
            'expense'     => 'Expense Summary',
            'receivables' => 'Outstanding Receivables',
            'payables'    => 'Outstanding Payables',
            'membership'  => 'Membership Revenue',
            'wallet'      => 'Wallet Summary',
            'coupon'      => 'Coupon Summary',
            'provider'    => 'Provider Earnings',
            default       => 'Finance Report',
        };
        $sheet->setTitle(substr($title, 0, 31));

        match ($tab) {
            'income'      => $this->exportIncome($sheet, $data),
            'expense'     => $this->exportExpense($sheet, $data),
            'receivables' => $this->exportReceivables($sheet, $data),
            'payables'    => $this->exportPayables($sheet, $data),
            'membership'  => $this->exportMembership($sheet, $data),
            'wallet'      => $this->exportWallet($sheet, $data),
            'coupon'      => $this->exportCoupon($sheet, $data),
            'provider'    => $this->exportProvider($sheet, $data),
            default       => $this->exportGeneric($sheet, $data),
        };

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = strtolower(str_replace(' ', '_', $title)) . '_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'rpt_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function head(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers): void
    {
        $cols = range('A', 'Z');
        foreach (array_values($headers) as $i => $label) {
            $sheet->setCellValue($cols[$i] . '1', $label);
            $sheet->getColumnDimension($cols[$i])->setAutoSize(true);
            $sheet->getStyle($cols[$i] . '1')->getFont()->setBold(true);
        }
    }

    private function exportIncome($sheet, array $d): void
    {
        $this->head($sheet, ['Month', 'Transactions', 'Total (Rs)']);
        $row = 2;
        foreach ($d['byMonth'] as $m) {
            $sheet->setCellValue("A{$row}", $m->month);
            $sheet->setCellValue("B{$row}", $m->cnt);
            $sheet->setCellValue("C{$row}", (float)$m->total);
            $row++;
        }
        $sheet->setCellValue("B{$row}", 'TOTAL'); $sheet->setCellValue("C{$row}", (float)$d['total']);
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
    }

    private function exportExpense($sheet, array $d): void
    {
        $this->head($sheet, ['Category', 'Transactions', 'Total (Rs)']);
        $row = 2;
        foreach ($d['byCategory'] as $c) {
            $sheet->setCellValue("A{$row}", $c->category);
            $sheet->setCellValue("B{$row}", $c->cnt);
            $sheet->setCellValue("C{$row}", (float)$c->total);
            $row++;
        }
        $sheet->setCellValue("B{$row}", 'TOTAL'); $sheet->setCellValue("C{$row}", (float)$d['total']);
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
    }

    private function exportReceivables($sheet, array $d): void
    {
        $this->head($sheet, ['Invoice No', 'Patient', 'Invoice Date', 'Total', 'Balance Due', 'Age (Days)']);
        $row = 2;
        foreach ($d['invoices'] as $inv) {
            $sheet->setCellValue("A{$row}", $inv->invoice_number);
            $sheet->setCellValue("B{$row}", $inv->patient?->name ?? '');
            $sheet->setCellValue("C{$row}", $inv->invoice_date?->format('d-m-Y'));
            $sheet->setCellValue("D{$row}", (float)$inv->total_amount);
            $sheet->setCellValue("E{$row}", (float)$inv->balance_due);
            $sheet->setCellValue("F{$row}", $inv->age_days);
            $row++;
        }
        $sheet->setCellValue("D{$row}", 'TOTAL'); $sheet->setCellValue("E{$row}", (float)$d['total']);
        $sheet->getStyle("D{$row}:E{$row}")->getFont()->setBold(true);
    }

    private function exportPayables($sheet, array $d): void
    {
        $this->head($sheet, ['Title', 'Vendor', 'Expense Date', 'Total (Rs)', 'Due Date', 'Age (Days)']);
        $row = 2;
        foreach ($d['bills'] as $b) {
            $sheet->setCellValue("A{$row}", $b->title);
            $sheet->setCellValue("B{$row}", $b->vendor?->vendor_name ?? '');
            $sheet->setCellValue("C{$row}", $b->expense_date?->format('d-m-Y'));
            $sheet->setCellValue("D{$row}", (float)$b->total_amount);
            $sheet->setCellValue("E{$row}", $b->due_date?->format('d-m-Y'));
            $sheet->setCellValue("F{$row}", $b->age_days);
            $row++;
        }
        $sheet->setCellValue("C{$row}", 'TOTAL'); $sheet->setCellValue("D{$row}", (float)$d['total']);
        $sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true);
    }

    private function exportMembership($sheet, array $d): void
    {
        $this->head($sheet, ['Date', 'Patient', 'Plan', 'Amount', 'Status']);
        $row = 2;
        foreach ($d['subscriptions'] as $s) {
            $sheet->setCellValue("A{$row}", $s->created_at?->format('d-m-Y'));
            $sheet->setCellValue("B{$row}", $s->patient?->name ?? '');
            $sheet->setCellValue("C{$row}", $s->plan?->name ?? '');
            $sheet->setCellValue("D{$row}", (float)$s->amount_paid);
            $sheet->setCellValue("E{$row}", ucfirst($s->status));
            $row++;
        }
        $sheet->setCellValue("C{$row}", 'TOTAL'); $sheet->setCellValue("D{$row}", (float)$d['total']);
        $sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true);
    }

    private function exportWallet($sheet, array $d): void
    {
        $this->head($sheet, ['Month', 'Direction', 'Amount (Rs)']);
        $row = 2;
        foreach ($d['monthly'] as $m) {
            $sheet->setCellValue("A{$row}", $m->month);
            $sheet->setCellValue("B{$row}", ucfirst($m->direction));
            $sheet->setCellValue("C{$row}", (float)$m->total);
            $row++;
        }
    }

    /** Generic totals export — used for the new billing report tabs. */
    private function exportGeneric($sheet, array $d): void
    {
        $this->head($sheet, ['Metric', 'Value (Rs)']);
        $row = 2;
        foreach ($d as $key => $val) {
            if (is_numeric($val)) {
                $sheet->setCellValue("A{$row}", ucwords(str_replace('_', ' ', $key)));
                $sheet->setCellValue("B{$row}", (float) $val);
                $row++;
            }
        }
        if ($row === 2) {
            $sheet->setCellValue('A2', 'See the on-screen report or PDF for full details.');
        }
    }

    private function exportCoupon($sheet, array $d): void
    {
        $this->head($sheet, ['Coupon Code', 'Usage Count', 'Total Discount (Rs)']);
        $row = 2;
        foreach ($d['byCoupon'] as $c) {
            $sheet->setCellValue("A{$row}", $c->code ?? '');
            $sheet->setCellValue("B{$row}", $c->used_count ?? 0);
            $sheet->setCellValue("C{$row}", (float)($c->total_discount ?? 0));
            $row++;
        }
        $sheet->setCellValue("B{$row}", 'TOTAL'); $sheet->setCellValue("C{$row}", (float)$d['totalDiscount']);
        $sheet->getStyle("B{$row}:C{$row}")->getFont()->setBold(true);
    }

    private function exportProvider($sheet, array $d): void
    {
        $this->head($sheet, ['Doctor', 'Invoices', 'Payments', 'Total Collected (Rs)']);
        $row = 2;
        foreach ($d['byDoctor'] as $r) {
            $sheet->setCellValue("A{$row}", $r->doctor_name);
            $sheet->setCellValue("B{$row}", $r->invoice_count);
            $sheet->setCellValue("C{$row}", $r->payment_count);
            $sheet->setCellValue("D{$row}", (float)$r->total);
            $row++;
        }
        $sheet->setCellValue("C{$row}", 'TOTAL'); $sheet->setCellValue("D{$row}", (float)$d['total']);
        $sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true);
    }
}
