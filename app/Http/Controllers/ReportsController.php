<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Patient;
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

        return view('reports.index', compact(
            'period', 'from', 'to',
            'totalAppointments', 'completed', 'cancelled', 'noShow',
            'walkins', 'completionRate', 'newPatients',
            'dailyData', 'statusBreakdown', 'byCategory', 'byDoctor',
            'recentAppointments', 'byDayOfWeek'
        ));
    }
}
