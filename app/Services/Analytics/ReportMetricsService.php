<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Finance\FinanceExpense;
use App\Models\InvoicePayment;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ReportMetricsService — ONE definition for the money/appointment numbers
 * every report surface shows. Created 2026-07-14 because three surfaces
 * computed "collections" from three different tables (web reports:
 * InvoicePayment; huddle report: FinanceTransaction; mobile API: Receipt)
 * and two different "outstanding" filters — so web and mobile could show a
 * dentist different totals for the same period.
 *
 * Canonical definitions (source of truth = web main Reports page):
 *   collected    = InvoicePayment.amount summed over payment_date
 *   outstanding  = Invoice(status in draft,partial).balance_due
 *   appointments done = status 'done' ('completed' does not exist on
 *                   appointments; treatment_visits DOES use 'completed')
 *
 * All methods take an optional $branchId — web (single-clinic pages) passes
 * null; the mobile API passes the caller's branch.
 */
class ReportMetricsService
{
    /**
     * Resolve ?period=7|30|90|365|custom(&from&to) into [from, to] —
     * exactly the web ReportsController::index() range logic.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(?string $period, ?string $from = null, ?string $to = null): array
    {
        $period = $period ?: '30';

        if ($period === 'custom') {
            return [
                Carbon::parse($from ?: now()->subDays(30)->toDateString())->startOfDay(),
                Carbon::parse($to ?: now()->toDateString())->endOfDay(),
            ];
        }

        return [
            now()->subDays((int) $period)->startOfDay(),
            now()->endOfDay(),
        ];
    }

    /** Money collected in the range — canonical table: invoice_payments. */
    public function collected(Carbon $from, Carbon $to, ?int $branchId = null): float
    {
        return (float) $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');
    }

    /**
     * Number of collection events (payment transactions) in the range.
     *
     * KPI 28 wants a transaction COUNT, not a second money figure. It is
     * deliberately the same query as collected() — same table, same filters,
     * counted instead of summed - so "collections" can never come to mean two
     * different sets of rows depending on which screen you are looking at.
     */
    public function collectionEvents(Carbon $from, Carbon $to, ?int $branchId = null): int
    {
        return $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->count();
    }

    /** Total receivables right now — canonical filter: draft + partial. */
    public function outstanding(?int $branchId = null): float
    {
        return (float) Invoice::whereIn('status', ['draft', 'partial'])
            ->when($branchId, fn ($q) => $q->whereHas(
                'patient', fn ($p) => $p->where('branch_id', $branchId)
            ))
            ->sum('balance_due');
    }

    /** Appointments completed in the range (status 'done'). */
    public function appointmentsDone(Carbon $from, Carbon $to, ?int $branchId = null): int
    {
        return Appointment::whereBetween('appointment_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'done')
            ->count();
    }

    /**
     * Daily collections series (oldest → newest), one row per day in the
     * range including zero days. Same source table as collected().
     *
     * @return array<int, array{date: string, label: string, amount: float}>
     */
    public function collectionsSeries(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $rows = $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as d, SUM(amount) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date'   => $key,
                'label'  => $cursor->format('D'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /* =====================================================================
       PROFIT — W-4 / G-06, added 2026-09-05.

       Before this, FOUR surfaces computed profit and three of them paired
       CASH revenue (money actually received) with ACCRUAL expenses (bills
       booked whether paid or not) — the most pessimistic pairing possible,
       and not an answer to any real question. Only Analytics filtered
       expenses to paid. Revenue was identical everywhere; the whole
       divergence was one WHERE clause.

       There are two legitimate questions and they need two numbers:
         cash   = collected - expensesPaid    'what stayed in hand'
         earned = billed    - expensesBooked  'what the practice earned'
       Both are defined HERE and nowhere else. A screen picks one and says
       on its face which it is showing.

       Deliberately NOT branch-scoped: finance_expenses carries clinic_id,
       not branch_id, so a $branchId argument could not be honoured. Rather
       than accept a parameter that silently lies, these are single-clinic.

       Known limit, left alone on purpose: 'paid' expenses are filtered by
       payment_status over expense_date, matching what Analytics already
       did. A strictly precise cash-out would use paid_at (the date money
       left). That is a separate change and needs a data check first.

       ALSO KNOWN, AND NOT FIXED HERE: payroll never posts to
       finance_expenses at all (KPI audit, 2026-09-04), so every figure
       below overstates profit by the salary bill on both bases. This
       method makes the number CONSISTENT, not CORRECT.
       ===================================================================== */

    /** Money BILLED in the range — invoices raised, cancelled excluded. */
    public function billed(Carbon $from, Carbon $to, ?int $branchId = null): float
    {
        return (float) Invoice::whereBetween('invoice_date', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->when($branchId, fn ($q) => $q->whereHas(
                'patient', fn ($p) => $p->where('branch_id', $branchId)
            ))
            ->sum('total_amount');
    }

    /** Expenses actually PAID — cash out. */
    public function expensesPaid(Carbon $from, Carbon $to): float
    {
        return (float) FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->paid()
            ->sum('total_amount');
    }

    /** Expenses BOOKED — every bill recorded in the range, paid or not. */
    public function expensesBooked(Carbon $from, Carbon $to): float
    {
        return (float) FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->sum('total_amount');
    }

    /** Bills recorded in the range that are still unpaid — money owed out. */
    public function expensesUnpaid(Carbon $from, Carbon $to): float
    {
        return (float) FinanceExpense::whereBetween('expense_date', [$from, $to])
            ->unpaid()
            ->sum('total_amount');
    }

    /**
     * The canonical profit block. Every profit surface reads this.
     *
     * @return array{collected:float, billed:float, expenses_paid:float,
     *               expenses_booked:float, expenses_unpaid:float,
     *               cash_profit:float, cash_margin:float,
     *               earned_profit:float, earned_margin:float}
     */
    public function profit(Carbon $from, Carbon $to): array
    {
        $collected = $this->collected($from, $to);
        $billed    = $this->billed($from, $to);
        $paid      = $this->expensesPaid($from, $to);
        $booked    = $this->expensesBooked($from, $to);

        $cash   = $collected - $paid;
        $earned = $billed - $booked;

        return [
            'collected'       => $collected,
            'billed'          => $billed,
            'expenses_paid'   => $paid,
            'expenses_booked' => $booked,
            'expenses_unpaid' => $booked - $paid,
            'cash_profit'     => $cash,
            'cash_margin'     => $collected > 0 ? round(($cash / $collected) * 100, 1) : 0.0,
            'earned_profit'   => $earned,
            'earned_margin'   => $billed > 0 ? round(($earned / $billed) * 100, 1) : 0.0,
        ];
    }

    /* =====================================================================
       W-5 / G-32, added 2026-09-06.

       FLOW vs STOCK. The CEO ruled on 6 Sep: "outstanding jopryant payment
       yet nahi topryant constant rahila pahije — date filter shi ghenadena
       nahi." A receivable does not shrink because someone changed a date
       filter. So billed() and collected() are FLOWS and take a range;
       outstanding() and patientCreditHeld() are STOCKS and take none.

       His model, in his words: invoice is raised when treatment starts; an
       advance goes into the WALLET against an advance receipt; the invoice
       debits the wallet; whatever is billed and neither paid nor covered by
       wallet is OUTSTANDING. Wallet must always be spent before taking new
       money, and both stocks should sit at zero.
       ===================================================================== */

    /**
     * Patient credit held in wallets right now — the clinic's cash-backed
     * liability to its patients. A STOCK: point-in-time, never range-scoped.
     *
     * PROMOTIONAL CREDIT IS EXCLUDED, by the CEO's ruling and by U8 rules 11
     * and 12: promotional credit was never money the clinic received, it may
     * expire, and it is not cash-refundable. Patient credit never expires and
     * is refundable — that is the money we actually owe back.
     *
     * Reads balance_patient_credit and nothing else. The two neighbouring
     * columns are TRAPS: balance_permanent is the pre-U8 name of this same
     * pot, kept live only so older readers would not break, and balance_total
     * is written as promotional + permanent — patient credit is NOT in it.
     */
    public function patientCreditHeld(?int $branchId = null): float
    {
        return (float) Wallet::query()
            ->when($branchId, fn ($q) => $q->whereHas(
                'patient', fn ($p) => $p->where('branch_id', $branchId)
            ))
            ->sum('balance_patient_credit');
    }

    /**
     * Collections split by payment mode — the SAME rows as collected(),
     * grouped instead of summed, for exactly the reason collectionEvents()
     * exists: a breakdown must never be able to disagree with the total
     * printed above it.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collectionsByMode(Carbon $from, Carbon $to, ?int $branchId = null)
    {
        return $this->paymentsQuery($branchId)
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_mode')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Billed, split by treatment category — W-7 / G-01, added 2026-09-06.
     *
     * This replaces a read of finance_income_entries, a table with EXACTLY
     * ZERO writers anywhere in the application. The report it fed had been
     * showing Rs 0 against every category since the day it shipped.
     *
     * WHY THE ARITHMETIC IS NOT JUST SUM(invoice_items.total):
     * five discount layers live on the INVOICE, not on the line —
     * Invoice::recalculate() computes
     *     total = (taxable + gst) - wallet - coupon - membership - manual
     * so a plain sum of line totals is always >= the invoice total, and this
     * table's Total would not agree with Billed anywhere else on the system.
     * That is the exact class of bug W-4 and W-5 spent the day closing.
     *
     * So each line is given its PRO-RATA share of the invoice's real total:
     *     share = line.total / sum(all line totals on that invoice)
     *     amount = share * invoice.total_amount
     * A 10% invoice discount lands as 10% off every treatment on it, and the
     * categories sum back to billed() to the rupee.
     *
     * Lines with no treatment_id fall into 'Uncategorised' rather than being
     * dropped — treatment_id is nullable by design (manual invoice lines pass
     * treatment_id ?? null), and silently omitting them would under-report the
     * total while looking perfectly healthy.
     *
     * @return \Illuminate\Support\Collection keyed by category name
     */
    public function billedByCategory(Carbon $from, Carbon $to, ?int $branchId = null)
    {
        $lineSums = DB::table('invoice_items')
            ->select('invoice_id', DB::raw('SUM(total) as line_sum'))
            ->groupBy('invoice_id');

        return DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->joinSub($lineSums, 'li', 'li.invoice_id', '=', 'ii.invoice_id')
            ->leftJoin('treatments as t', 't.id', '=', 'ii.treatment_id')
            ->leftJoin('treatment_categories as tc', 'tc.id', '=', 't.treatment_category_id')
            ->when($branchId, fn ($q) => $q
                ->join('patients as p', 'p.id', '=', 'inv.patient_id')
                ->where('p.branch_id', $branchId))
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->where('inv.status', '<>', 'cancelled')
            ->select(
                DB::raw("COALESCE(tc.name, 'Uncategorised') as name"),
                DB::raw('ROUND(SUM(ii.total * inv.total_amount / NULLIF(li.line_sum, 0)), 2) as revenue'),
                DB::raw('COUNT(*) as txn_count')
            )
            ->groupBy('name')
            ->orderByDesc('revenue')
            ->get()
            ->keyBy('name');
    }

    private function paymentsQuery(?int $branchId)
    {
        return InvoicePayment::query()
            ->when($branchId, fn ($q) => $q->whereHas(
                'invoice.patient', fn ($p) => $p->where('branch_id', $branchId)
            ));
    }
}
