<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrDepartment;
use App\Models\User;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    /**
     * HR Dashboard — today's attendance snapshot + key metrics.
     */
    public function index()
    {
        $today = now()->toDateString();

        // All active staff (exclude admin? No — include everyone with hr profile)
        $totalStaff = User::where('is_active', true)
                          ->whereHas('hrProfile')
                          ->count();

        // Today's attendance breakdown
        $todayAttendance = HrAttendance::with('user')
            ->whereDate('date', $today)
            ->get();

        $presentCount  = $todayAttendance->whereIn('status', ['present', 'late', 'half_day'])->count();
        $absentCount   = $todayAttendance->where('status', 'absent')->count();
        $onLeaveCount  = $todayAttendance->where('status', 'on_leave')->count();
        $notMarkedCount = $totalStaff - $todayAttendance->count();

        // Staff with license expiring in next 30 days
        $expiringLicenses = \App\Models\HrStaffProfile::with('user')
            ->whereNotNull('license_expiry')
            ->whereDate('license_expiry', '>=', now())
            ->whereDate('license_expiry', '<=', now()->addDays(30))
            ->get();

        // Departments for quick nav
        $departments = HrDepartment::active()->withCount([
            'staffProfiles as staff_count' => fn($q) => $q->whereHas('user', fn($u) => $u->where('is_active', true))
        ])->get();

        // Recent attendance (last 7 days) for the chart
        $weeklyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayAttendance = HrAttendance::whereDate('date', $date)->get();
            $weeklyStats[] = [
                'date'    => now()->subDays($i)->format('D'),
                'present' => $dayAttendance->whereIn('status', ['present', 'late', 'half_day'])->count(),
                'absent'  => $dayAttendance->where('status', 'absent')->count(),
            ];
        }

        return view('hr.dashboard', compact(
            'totalStaff',
            'presentCount',
            'absentCount',
            'onLeaveCount',
            'notMarkedCount',
            'expiringLicenses',
            'departments',
            'weeklyStats',
            'today',
            'todayAttendance',
        ));
    }
}
