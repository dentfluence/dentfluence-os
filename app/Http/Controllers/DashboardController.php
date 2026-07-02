<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today    = Carbon::today();
        $branchId = Auth::user()->branch_id;

        // ── Today's appointments ─────────────────────────────────────────────
        $todayAppointments = Appointment::with(['patient', 'treatment', 'treatmentCategory', 'operatory'])
            ->where('branch_id', $branchId)
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time')
            ->get();

        // ── Today's revenue (invoices created today via patients in branch) ──
        $todayRevenue = Invoice::whereDate('invoice_date', $today)
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', ['cancelled'])
            ->sum('paid_amount');

        // ── Outstanding balance (unpaid / partial invoices) ──────────────────
        $outstandingBalance = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->sum('balance_due');

        $outstandingCount = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        // ── Pending lab cases (sent to lab, in progress, or ready) ───────────
        $pendingLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->count();

        $overdueLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', $today)
            ->count();

        // ── Alerts for the alert strip ───────────────────────────────────────
        $alerts = [];

        if ($overdueLabCount > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "{$overdueLabCount} lab " . str('case')->plural($overdueLabCount) . " overdue — follow up with lab.",
                'link'    => route('lab.index'),
            ];
        }

        $missedToday = $todayAppointments->where('status', 'no_show')->count();
        if ($missedToday > 0) {
            $alerts[] = [
                'type'    => 'info',
                'message' => "{$missedToday} no-show " . str('appointment')->plural($missedToday) . " today. Consider a recall message.",
                'link'    => route('appointments.index'),
            ];
        }

        if ($outstandingCount > 5) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => "₹" . number_format($outstandingBalance, 0) . " outstanding across {$outstandingCount} invoices.",
                'link'    => route('finance.income'),
            ];
        }

        // ── Appointment status breakdown ──────────────────────────────────────
        $stats = [
            'today_total'    => $todayAppointments->count(),
            'today_checkin'  => $todayAppointments->where('status', 'checkin')->count(),
            'today_in_chair' => $todayAppointments->where('status', 'in_chair')->count(),
            'today_done'     => $todayAppointments->where('status', 'done')->count(),
        ];

        return view('dashboard.index', compact(
            'todayAppointments',
            'stats',
            'todayRevenue',
            'outstandingBalance',
            'outstandingCount',
            'pendingLabCount',
            'overdueLabCount',
            'alerts',
        ));
    }
}