<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $todayAppointments = Appointment::with(['patient', 'treatment', 'treatmentCategory'])
            ->where('branch_id', Auth::user()->branch_id)
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time')
            ->get();

        $stats = [
            'today_total'    => $todayAppointments->count(),
            'today_checkin'  => $todayAppointments->where('status', 'checkin')->count(),
            'today_in_chair' => $todayAppointments->where('status', 'in_chair')->count(),
            'today_done'     => $todayAppointments->where('status', 'done')->count(),
        ];

        return view('dashboard.index', compact('todayAppointments', 'stats'));
    }
}