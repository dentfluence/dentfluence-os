<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    /**
     * Main reports page — Appointment analytics.
     * Supports ?period=7|30|90|365 and ?period=custom&from=&to= for custom range.
     */
    public function index(Request $request)
    {
        // ── Date range ────────────────────────────────────────────
        $period = $request->get('period', '30');
        if ($period === 'custom') {
            $from = Carbon::parse($request->get('from', now()->subDays(30)->toDateString()))->startOfDay();
            $to   = Carbon::parse($request->get('to',   now()->toDateString()))->endOfDay();
        } else {
            $from = now()->subDays((int) $period)->startOfDay();
            $to   = now()->endOfDay();
        }

        // ── KPI Summary Cards ─────────────────────────────────────
        $base = Appointment::whereBetween('appointment_date', [$from, $to]);

        $totalAppointments  = (clone $base)->count();
        $completed          = (clone $base)->where('status', 'completed')->count();
        $cancelled          = (clone $base)->where('status', 'cancelled')->count();
        $noShow             = (clone $base)->where('status', 'no_show')->count();
        $walkins            = (clone $base)->where('is_walkin', true)->count();
        $completionRate     = $totalAppointments > 0
                                ? round(($completed / $totalAppointments) * 100, 1)
                                : 0;
        $newPatients        = Patient::whereBetween('created_at', [$from, $to])->count();

        // ── Daily trend (for line chart) ──────────────────────────
        $dailyData = (clone $base)
            ->select(
                DB::raw('DATE(appointment_date) as day'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
                DB::raw('SUM(CASE WHEN status = "no_show"   THEN 1 ELSE 0 END) as no_show')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        // ── Status breakdown (for doughnut chart) ─────────────────
        $statusBreakdown = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // ── By treatment category (for bar chart) ─────────────────
        $byCategory = (clone $base)
            ->leftJoin('treatment_categories', 'appointments.treatment_category_id', '=', 'treatment_categories.id')
            ->select(
                DB::raw('COALESCE(treatment_categories.name, "Uncategorised") as name'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // ── By doctor (bar chart) ─────────────────────────────────
        $byDoctor = (clone $base)
            ->leftJoin('users', 'appointments.doctor_id', '=', 'users.id')
            ->select(
                DB::raw('COALESCE(users.name, "Unassigned") as name'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // ── Recent appointments table ─────────────────────────────
        $recentAppointments = (clone $base)
            ->with(['patient', 'doctor', 'treatmentCategory'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->limit(50)
            ->get();

        // ── Day of week heatmap ───────────────────────────────────
        $byDayOfWeek = (clone $base)
            ->select(
                DB::raw('DAYOFWEEK(appointment_date) as dow'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('dow')
            ->orderBy('dow')
            ->pluck('total', 'dow');

        // ── Revenue by treatment category (via income entries) ────────
        $revenueByCategory = DB::table('finance_income_entries')
            ->join('treatments', 'finance_income_entries.treatment_id', '=', 'treatments.id')
            ->join('treatment_categories', 'treatments.treatment_category_id', '=', 'treatment_categories.id')
            ->whereBetween('finance_income_entries.income_date', [$from, $to])
            ->where('finance_income_entries.status', 'active')
            ->select(
                'treatment_categories.name',
                DB::raw('COALESCE(SUM(finance_income_entries.net_amount), 0) as revenue'),
                DB::raw('COUNT(*) as txn_count')
            )
            ->groupBy('treatment_categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->keyBy('name');

        // Merge revenue into byCategory (appointments already grouped by category name)
        $categoryKpi = $byCategory->map(function ($cat) use ($revenueByCategory) {
            $rev = $revenueByCategory->get($cat->name);
            $cat->revenue   = $rev ? (float) $rev->revenue   : 0;
            $cat->txn_count = $rev ? (int)   $rev->txn_count : 0;
            return $cat;
        });

        // ── Revenue tab data ──────────────────────────────────────────────
        [
            'revKpis'        => $revKpis,
            'revDailyData'   => $revDailyData,
            'revByMode'      => $revByMode,
            'revTopPatients' => $revTopPatients,
            'revOutstanding' => $revOutstanding,
        ] = $this->buildRevenueData($from, $to);

        [
            'patNewByMonth'   => $patNewByMonth,
            'patGender'       => $patGender,
            'patSource'       => $patSource,
            'patCity'         => $patCity,
            'patTotal'        => $patTotal,
            'patReturnRate'   => $patReturnRate,
        ] = $this->buildPatientData($from, $to);

        [
            'txVisitsByStatus'  => $txVisitsByStatus,
            'txVisitsByDoctor'  => $txVisitsByDoctor,
            'txVisitsByProc'    => $txVisitsByProc,
            'txPlansByStatus'   => $txPlansByStatus,
            'txMonthlyVisits'   => $txMonthlyVisits,
            'txTotalVisits'     => $txTotalVisits,
            'txCompletionRate'  => $txCompletionRate,
        ] = $this->buildTreatmentData($from, $to);

        [
            'labByStatus'    => $labByStatus,
            'labByVendor'    => $labByVendor,
            'labByCategory'  => $labByCategory,
            'labTurnaround'  => $labTurnaround,
            'labTotal'       => $labTotal,
            'labOpenCount'   => $labOpenCount,
            'labOverdueCount'=> $labOverdueCount,
        ] = $this->buildLabData($from, $to);

        [
            'invLowStock'      => $invLowStock,
            'invMovements'     => $invMovements,
            'invByCategory'    => $invByCategory,
            'invExpiring'      => $invExpiring,
            'invTotalItems'    => $invTotalItems,
            'invLowStockCount' => $invLowStockCount,
        ] = $this->buildInventoryData($from, $to);

        return view('reports.index', compact(
            'period', 'from', 'to',
            'totalAppointments', 'completed', 'cancelled', 'noShow',
            'walkins', 'completionRate', 'newPatients',
            'dailyData', 'statusBreakdown', 'byCategory', 'byDoctor',
            'recentAppointments', 'byDayOfWeek', 'categoryKpi',
            // Revenue tab
            'revKpis', 'revDailyData', 'revByMode', 'revTopPatients', 'revOutstanding',
            // Patient tab
            'patNewByMonth', 'patGender', 'patSource', 'patCity', 'patTotal', 'patReturnRate',
            // Treatment tab
            'txVisitsByStatus', 'txVisitsByDoctor', 'txVisitsByProc',
            'txPlansByStatus', 'txMonthlyVisits', 'txTotalVisits', 'txCompletionRate',
            // Lab tab
            'labByStatus', 'labByVendor', 'labByCategory', 'labTurnaround',
            'labTotal', 'labOpenCount', 'labOverdueCount',
            // Inventory tab
            'invLowStock', 'invMovements', 'invByCategory', 'invExpiring',
            'invTotalItems', 'invLowStockCount'
        ));
    }

    // ── Patient data ──────────────────────────────────────────────────────

    private function buildPatientData(Carbon $from, Carbon $to): array
    {
        $patTotal = Patient::whereBetween('created_at', [$from, $to])->count();

        // New patients by month (last 12 months regardless of filter)
        $patNewByMonth = Patient::where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%b %Y') as month, DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('month', 'ym')
            ->orderBy('ym')
            ->get();

        // Gender breakdown
        $patGender = Patient::selectRaw("COALESCE(gender, 'unknown') as gender, COUNT(*) as total")
            ->groupBy('gender')
            ->orderByDesc('total')
            ->get();

        // Source breakdown
        $patSource = Patient::selectRaw("COALESCE(source, 'Unknown') as source, COUNT(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // City breakdown
        $patCity = Patient::selectRaw("COALESCE(NULLIF(city,''), 'Unknown') as city, COUNT(*) as total")
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // Return rate: patients who had >1 appointment
        $returning = DB::table('appointments')
            ->select('patient_id')
            ->groupBy('patient_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()->count();
        $everVisited = DB::table('appointments')->distinct('patient_id')->count('patient_id');
        $patReturnRate = $everVisited > 0 ? round($returning / $everVisited * 100, 1) : 0;

        return compact('patNewByMonth', 'patGender', 'patSource', 'patCity', 'patTotal', 'patReturnRate');
    }


    // ── Treatment data ────────────────────────────────────────────────────

    private function buildTreatmentData(Carbon $from, Carbon $to): array
    {
        $base = DB::table('treatment_visits')->whereBetween('visit_date', [$from, $to]);

        $txTotalVisits = (clone $base)->count();

        $txVisitsByStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $completed       = (clone $base)->where('status', 'completed')->count();
        $txCompletionRate = $txTotalVisits > 0 ? round($completed / $txTotalVisits * 100, 1) : 0;

        $txVisitsByDoctor = (clone $base)
            ->leftJoin('users', 'treatment_visits.doctor_id', '=', 'users.id')
            ->selectRaw('COALESCE(users.name, "Unassigned") as name, COUNT(*) as total')
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $txVisitsByProc = (clone $base)
            ->selectRaw("COALESCE(NULLIF(`procedure`,''), 'Not specified') as procedure_name, COUNT(*) as total")
            ->groupBy('procedure_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $txPlansByStatus = DB::table('treatment_plans')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $txMonthlyVisits = DB::table('treatment_visits')
            ->where('visit_date', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(visit_date, '%b %Y') as month, DATE_FORMAT(visit_date, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('month', 'ym')
            ->orderBy('ym')
            ->get();

        return compact(
            'txVisitsByStatus', 'txVisitsByDoctor', 'txVisitsByProc',
            'txPlansByStatus', 'txMonthlyVisits', 'txTotalVisits', 'txCompletionRate'
        );
    }

    // ── Lab data ──────────────────────────────────────────────────────────

    private function buildLabData(Carbon $from, Carbon $to): array
    {
        $base = DB::table('lab_cases')->whereBetween('lab_cases.created_at', [$from, $to]);

        $labTotal     = (clone $base)->count();
        $labOpenCount = (clone $base)->whereIn('status', ['sent','in_progress','ready'])->count();
        $labOverdueCount = DB::table('lab_cases')
            ->whereIn('status', ['sent','in_progress','ready'])
            ->whereNotNull('expected_return_date')
            ->where('expected_return_date', '<', now()->toDateString())
            ->count();

        // Status breakdown
        $labByStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        // By vendor
        $labByVendor = (clone $base)
            ->leftJoin('lab_vendors', 'lab_cases.lab_vendor_id', '=', 'lab_vendors.id')
            ->selectRaw('COALESCE(lab_vendors.name, "No Vendor") as name, COUNT(*) as total')
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // By work category
        $labByCategory = (clone $base)
            ->selectRaw('work_category, COUNT(*) as total')
            ->groupBy('work_category')
            ->orderByDesc('total')
            ->get();

        // Avg turnaround (sent_date → received_date) for completed cases
        $labTurnaround = DB::table('lab_cases')
            ->whereBetween('received_date', [$from, $to])
            ->whereNotNull('sent_date')
            ->whereNotNull('received_date')
            ->selectRaw('ROUND(AVG(DATEDIFF(received_date, sent_date)), 1) as avg_days')
            ->value('avg_days');

        return compact('labByStatus', 'labByVendor', 'labByCategory', 'labTurnaround',
                       'labTotal', 'labOpenCount', 'labOverdueCount');
    }

    // ── Inventory data ────────────────────────────────────────────────────

    private function buildInventoryData(Carbon $from, Carbon $to): array
    {
        // Low stock items (available_qty <= minimum_qty)
        $invLowStock = DB::table('inventory_stocks')
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoin('inventory_categories', 'inventory_items.category_id', '=', 'inventory_categories.id')
            ->whereRaw('inventory_stocks.available_qty <= inventory_items.minimum_qty')
            ->select(
                'inventory_items.product_name',
                'inventory_items.minimum_qty',
                'inventory_stocks.available_qty',
                'inventory_categories.name as category'
            )
            ->orderBy('inventory_stocks.available_qty')
            ->limit(15)
            ->get();

        $invLowStockCount = $invLowStock->count();

        // Total active items
        $invTotalItems = DB::table('inventory_items')->where('is_active', true)->count();

        // Stock movements in period (in vs out)
        $invMovements = DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("movement_type, COUNT(*) as cnt, SUM(qty) as qty")
            ->groupBy('movement_type')
            ->orderByDesc('qty')
            ->get();

        // By category (stock value)
        $invByCategory = DB::table('inventory_stocks')
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->leftJoin('inventory_categories', 'inventory_items.category_id', '=', 'inventory_categories.id')
            ->selectRaw(
                'COALESCE(inventory_categories.name, "Uncategorised") as category,
                 COUNT(DISTINCT inventory_items.id) as item_count,
                 SUM(inventory_stocks.available_qty) as total_qty'
            )
            ->groupBy('category')
            ->orderByDesc('item_count')
            ->limit(8)
            ->get();

        // Items expiring in next 60 days
        $invExpiring = DB::table('stock_movements')
            ->join('inventory_items', 'stock_movements.inventory_item_id', '=', 'inventory_items.id')
            ->whereNotNull('stock_movements.expiry_date')
            ->where('stock_movements.expiry_date', '>', now()->toDateString())
            ->where('stock_movements.expiry_date', '<=', now()->addDays(60)->toDateString())
            ->whereRaw('stock_movements.qty > 0')
            ->select(
                'inventory_items.product_name',
                'stock_movements.expiry_date',
                'stock_movements.qty',
                'stock_movements.batch_no'
            )
            ->orderBy('stock_movements.expiry_date')
            ->limit(15)
            ->get();

        return compact('invLowStock', 'invMovements', 'invByCategory', 'invExpiring',
                       'invTotalItems', 'invLowStockCount');
    }

    // ── Revenue data ──────────────────────────────────────────────────────

    private function buildRevenueData(Carbon $from, Carbon $to): array
    {
        // ── KPIs ─────────────────────────────────────────────────────────
        $collected    = InvoicePayment::whereBetween('payment_date', [$from, $to])->sum('amount');
        $outstanding  = Invoice::whereIn('status', ['draft', 'partial'])->sum('balance_due');
        $invoiceCount = Invoice::whereBetween('invoice_date', [$from, $to])
                            ->whereNotIn('status', ['cancelled'])->count();
        $avgInvoice   = $invoiceCount > 0
                            ? Invoice::whereBetween('invoice_date', [$from, $to])
                                  ->whereNotIn('status', ['cancelled'])->avg('total_amount')
                            : 0;
        $topDayRow = InvoicePayment::whereBetween('payment_date', [$from, $to])
                         ->selectRaw('DATE(payment_date) as day, SUM(amount) as total')
                         ->groupBy('day')->orderByDesc('total')->first();

        $revKpis = [
            'collected'       => $collected,
            'outstanding'     => $outstanding,
            'invoice_count'   => $invoiceCount,
            'avg_invoice'     => round($avgInvoice, 0),
            'top_day'         => $topDayRow?->day,
            'top_day_amt'     => $topDayRow?->total ?? 0,
            'collection_rate' => ($collected + $outstanding) > 0
                                   ? round($collected / ($collected + $outstanding) * 100, 1)
                                   : 0,
        ];

        // ── Daily collections ─────────────────────────────────────────────
        $revDailyData = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw('DATE(payment_date) as day, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('day')->orderBy('day')->get();

        // ── By payment mode ───────────────────────────────────────────────
        $revByMode = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_mode, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_mode')->orderByDesc('total')->get();

        // ── Top 10 patients by revenue ────────────────────────────────────
        $revTopPatients = InvoicePayment::whereBetween('payment_date', [$from, $to])
            ->join('patients', 'invoice_payments.patient_id', '=', 'patients.id')
            ->select(
                'patients.id',
                'patients.name',
                'patients.phone',
                DB::raw('SUM(invoice_payments.amount) as total_paid'),
                DB::raw('COUNT(DISTINCT invoice_payments.invoice_id) as invoice_count')
            )
            ->groupBy('patients.id', 'patients.name', 'patients.phone')
            ->orderByDesc('total_paid')
            ->limit(10)
            ->get();

        // ── Outstanding invoices ──────────────────────────────────────────
        $revOutstanding = Invoice::with('patient')
            ->whereIn('status', ['draft', 'partial'])
            ->orderByDesc('balance_due')
            ->limit(10)
            ->get();

        return compact('revKpis', 'revDailyData', 'revByMode', 'revTopPatients', 'revOutstanding');
    }
}
