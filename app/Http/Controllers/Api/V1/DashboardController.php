<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AppointmentResource;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\Analytics\ReportMetricsService;
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
 *
 * MONEY (M-2, 8 Sep 2026): every rupee here comes from ReportMetricsService —
 * the one canonical definition the web dashboard, Reports and the Huddle
 * report already read (W-4, W-5, W-7, W-8). Before this, the phone computed
 * its own figures and they were wrong in two ways the web had already fixed:
 *
 *   - today_revenue summed invoices.paid_amount BY INVOICE DATE, so a payment
 *     taken today against last week's invoice was invisible, and a payment
 *     taken next week against today's invoice would count as today. The
 *     canonical figure is invoice_payments by payment_date — collected().
 *   - outstanding filtered status IN ('unpaid','partial'). There is no
 *     'unpaid' status on invoices — a fully unpaid invoice is 'draft' — so
 *     every invoice with nothing paid against it was missing from the phone's
 *     receivable. outstanding() reads draft + partial.
 *
 * Outstanding and patient credit are STOCKS (CEO ruling 6 Sep): they carry no
 * date and no trend. Money is admin-only, the same rule as the web dashboard;
 * other roles receive `finance` = null and `money_visible` = false so the
 * screen can hide the tiles instead of printing Rs 0.
 */
class DashboardController extends ApiController
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly ReportMetricsService $metrics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $branchId = $user->branch_id;

        $patientsTotal = Patient::where('branch_id', $branchId)->count();

        $newPatientsThisMonth = Patient::where('branch_id', $branchId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $todayList = $this->appointments
            ->filteredQuery($branchId, ['scope' => 'today'], $user)
            ->get();

        $upcomingCount = $this->appointments
            ->filteredQuery($branchId, ['scope' => 'upcoming'], $user)
            ->count();

        $today = now()->toDateString();

        // Money is admin-only — same gate as web DashboardController::index().
        $showMoney = $user->isAdminRole();
        $finance   = null;

        if ($showMoney) {
            $collectedToday = $this->metrics->collected(
                now()->startOfDay(),
                now()->endOfDay(),
                $branchId
            );

            // Stocks — no date range by design.
            $outstanding   = $this->metrics->outstanding($branchId);
            $patientCredit = $this->metrics->patientCreditHeld($branchId);

            // Count uses the SAME filter as outstanding() so the number of
            // invoices can never disagree with the rupees beside it.
            $outstandingCount = Invoice::whereIn('status', ['draft', 'partial'])
                ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId))
                ->count();

            $finance = [
                'today_revenue'       => $collectedToday,   // legacy key the app reads today
                'collected_today'     => $collectedToday,   // canonical name, same value
                'outstanding_balance' => $outstanding,
                'outstanding_count'   => $outstandingCount,
                'patient_credit'      => $patientCredit,
            ];
        }

        $pendingLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->count();

        $overdueLabCount = LabCase::where('branch_id', $branchId)
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', $today)
            ->count();

        // ── Alert strip — same rules as the web dashboard. `key` lets the
        //    client route to the right module (no web URLs on mobile).
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
        if ($finance && $finance['outstanding_count'] > 5) {
            $alerts[] = [
                'type'    => 'warning',
                'key'     => 'outstanding',
                'message' => '₹' . number_format($finance['outstanding_balance'], 0) . " outstanding across {$finance['outstanding_count']} invoices.",
            ];
        }

        return $this->success([
            'patients' => [
                'total'          => $patientsTotal,
                'new_this_month' => $newPatientsThisMonth,
            ],
            'appointments' => [
                'today'          => $this->appointments->todayCounts($branchId, $user),
                'upcoming_count' => $upcomingCount,
            ],
            'money_visible'      => $showMoney,
            'finance'            => $finance,
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
