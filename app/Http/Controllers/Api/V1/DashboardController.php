<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AppointmentResource;
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

        return $this->success([
            'patients' => [
                'total'          => $patientsTotal,
                'new_this_month' => $newPatientsThisMonth,
            ],
            'appointments' => [
                'today'          => $this->appointments->todayCounts($branchId),
                'upcoming_count' => $upcomingCount,
            ],
            'today_appointments' => AppointmentResource::collection($todayList),
            'generated_at'       => now()->toIso8601String(),
        ], 'Dashboard');
    }
}
