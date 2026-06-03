<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceTransaction;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Finance Module — Skeleton Controller
 * All methods return views with mock data for visual proof.
 * Real data wiring will be done in Phase 2+.
 */
class FinanceController extends Controller
{
    // ── Finance Dashboard ──────────────────────────────────────────────────
    public function dashboard()
    {
        $now   = now();
        $today = today();

        // ── Today KPIs ──────────────────────────────────────────────────
        $todayIncome   = FinanceTransaction::income()->today()->sum('net_amount');
        $todayExpenses = FinanceTransaction::expense()->today()->sum('net_amount')
                       + FinanceExpense::whereDate('expense_date', $today)->sum('total_amount');

        // ── This Month ──────────────────────────────────────────────────
        $monthlyRevenue = FinanceTransaction::income()->thisMonth()->sum('net_amount');
        $monthlyExpense = FinanceTransaction::expense()->thisMonth()->sum('net_amount')
                        + FinanceExpense::thisMonth()->sum('total_amount');
        $monthlyProfit  = $monthlyRevenue - $monthlyExpense;
        $profitPct      = $monthlyRevenue > 0
                            ? round(($monthlyProfit / $monthlyRevenue) * 100, 1)
                            : 0;

        // ── Avg daily & projection ──────────────────────────────────────
        $dayOfMonth       = (int) $today->format('j');
        $daysInMonth      = (int) $today->daysInMonth;
        $avgDaily         = $dayOfMonth > 0 ? round($monthlyRevenue / $dayOfMonth) : 0;
        $projectedMonthEnd = $avgDaily * $daysInMonth;

        $kpis = [
            'today_collection'     => $todayIncome,
            'today_expenses'       => $todayExpenses,
            'cash_in_hand'         => FinanceTransaction::where('payment_mode', 'cash')->sum('net_amount'),
            'bank_balance'         => 0, // wire to FinanceBankAccount when balances are tracked
            'net_collection'       => $todayIncome - $todayExpenses,
            'pending_payments'     => FinanceTransaction::income()->where('status', 'pending')->sum('net_amount'),
            'outstanding_amount'   => FinanceTransaction::income()->where('status', 'pending')->sum('net_amount'),
            'monthly_revenue'      => $monthlyRevenue,
            'monthly_expense'      => $monthlyExpense,
            'monthly_profit'       => $monthlyProfit,
            'profit_percentage'    => $profitPct,
            'avg_daily_collection' => $avgDaily,
            'projected_month_end'  => $projectedMonthEnd,
        ];

        // ── Recent Transactions (last 10, income + expense) ─────────────
        $recentTransactions = FinanceTransaction::with(['patient', 'vendor'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn($t) => [
                'id'       => $t->id,
                'patient'  => $t->patient?->name ?? $t->vendor?->name ?? 'N/A',
                'type'     => $t->type,
                'category' => $t->source_type ? class_basename($t->source_type) : ucfirst($t->type),
                'amount'   => $t->net_amount,
                'mode'     => $t->payment_mode ?? '—',
                'date'     => $t->transaction_date?->isToday()
                                ? 'Today ' . $t->created_at->format('h:i A')
                                : ($t->transaction_date?->isYesterday() ? 'Yesterday' : $t->transaction_date?->format('d M')),
                'status'   => $t->status ?? 'active',
            ]);

        // ── Top Expense Categories this month ───────────────────────────
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

        // Fallback — if no expense categories, show from transactions
        if (empty($topExpenses)) {
            $topExpenses = FinanceTransaction::expense()->thisMonth()
                ->select('type as category', DB::raw('SUM(net_amount) as amount'))
                ->groupBy('type')
                ->orderByDesc('amount')
                ->limit(5)
                ->get()
                ->map(fn($r) => [
                    'category' => ucfirst($r->category),
                    'amount'   => $r->amount,
                    'percent'  => 100,
                ])->toArray();
        }

        return view('finance.dashboard', compact('kpis', 'recentTransactions', 'topExpenses'));
    }

    // ── Income ─────────────────────────────────────────────────────────────
    public function income(Request $request)
    {
        return view('finance.income');
    }

    // ── Expenses ───────────────────────────────────────────────────────────
    public function expenses(Request $request)
    {
        return view('finance.expenses');
    }

    public function expenseCreate()
    {
        return view('finance.expense-create');
    }

    // ── Vendors ────────────────────────────────────────────────────────────
    public function vendors(Request $request)
    {
        return view('finance.vendors');
    }

    // ── Payroll ────────────────────────────────────────────────────────────
    public function payroll(Request $request)
    {
        return view('finance.payroll');
    }

    // ── Cashbook ───────────────────────────────────────────────────────────
    public function cashbook(Request $request)
    {
        return view('finance.cashbook');
    }

    // ── Banking ────────────────────────────────────────────────────────────
    public function banking(Request $request)
    {
        return view('finance.banking');
    }

    // ── CA Export ──────────────────────────────────────────────────────────
    public function caExport(Request $request)
    {
        return view('finance.ca-export');
    }

    // ── GST ────────────────────────────────────────────────────────────────
    public function gst(Request $request)
    {
        return view('finance.gst');
    }
}
