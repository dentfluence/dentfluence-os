<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AppointmentResource;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController (API v1)
 * ----------------------------
 * One call that powers the mobile home screen: headline counts plus today's
 * schedule. Everything is branch-scoped to the logged-in user.
 *
 *   GET /api/v1/dashboard
 */
class DashboardController extends ApiController
{
    public function __construct(private AppointmentService $appointments) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $patientsTotal = Patient::where('branch_id', $branchId)->count();

        $newPatientsThisMonth = Patient::where('branch_id', $branchId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $todayList = $this->appointments
            ->filteredQuery($branchId, ['scope' => 'today'])
            ->get();

        $upcomingCount = $this->appointments
            ->filteredQuery($branchId, ['scope' => 'upcoming'])
            ->count();

        // ── KPIs the web dashboard shows — same queries as web
        //    DashboardController::index() so the two dashboards agree
        //    (2026-07-14 parity: these were missing from mobile entirely).
        $today = now()->toDateString();

        $todayRevenue = (float) Invoice::whereDate('invoice_date', $today)
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', ['cancelled'])
            ->sum('paid_amount');

        $outstandingBalance = (float) Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->sum('balance_due');

        $outstandingCount = Invoice::whereIn('status', ['unpaid', 'partial'])
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $pendingLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->count();

        $overdueLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', $today)
            ->count();

        // ── Alert strip — same three rules as the web dashboard. `key` lets
        //    the client route to the right module (no web URLs on mobile).
        $alerts = [];
        if ($overdueLabCount > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'key'     => 'lab_overdue',
                'message' => "{$overdueLabCount} lab " . str('case')->plural($overdueLabCount) . ' overdue — follow up with lab.',
            ];
        }
        $missedToday = $todayList->where('status', 'no_show')->count();
        if ($missedToday > 0) {
            $alerts[] = [
                'type'    => 'info',
                'key'     => 'no_show',
                'message' => "{$missedToday} no-show " . str('appointment')->plural($missedToday) . ' today. Consider a recall message.',
            ];
        }
        if ($outstandingCount > 5) {
            $alerts[] = [
                'type'    => 'warning',
                'key'     => 'outstanding',
                'message' => '₹' . number_format($outstandingBalance, 0) . " outstanding across {$outstandingCount} invoices.",
            ];
        }

        return $this->success([
            'patients' => [
                'total'          => $patientsTotal,
                'new_this_month' => $newPatientsThisMonth,
            ],
            'appointments' => [
                'today'          => $this->appointments->todayCounts($branchId),
                'upcoming_count' => $upcomingCount,
            ],
            'finance' => [
                'today_revenue'       => $todayRevenue,
                'outstanding_balance' => $outstandingBalance,
                'outstanding_count'   => $outstandingCount,
            ],
            'lab' => [
                'pending_count' => $pendingLabCount,
                'overdue_count' => $overdueLabCount,
            ],
            'alerts'             => $alerts,
            'today_appointments' => AppointmentResource::collection($todayList),
            'generated_at'       => now()->toIso8601String(),
        ], 'Dashboard');
    }
}
