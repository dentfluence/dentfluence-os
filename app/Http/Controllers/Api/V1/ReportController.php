<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\Analytics\ReportMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Practice overview for the mobile Reports module.
 *
 *   GET /api/v1/reports/overview
 *
 * Read-only aggregates across patients, appointments, finance and lab — all
 * scoped to the caller's branch. Patients / LabCase carry the BelongsToBranch
 * global scope; appointments + receipts/invoices are scoped explicitly here.
 */
class ReportController extends ApiController
{
    public function overview(Request $request, ReportMetricsService $metrics): JsonResponse
    {
        $bid        = $request->user()->branch_id;
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart  = now()->startOfWeek()->toDateString();
        $weekEnd    = now()->endOfWeek()->toDateString();

        // ── Selected range — ?period=7|30|90|365|custom(&from&to), same
        //    semantics as the web Reports page. Defaults to 30 days.
        [$from, $to] = $metrics->resolveRange(
            $request->query('period'),
            $request->query('from'),
            $request->query('to'),
        );

        // ── Patients ──────────────────────────────────────────────────────────
        $patientsActive = Patient::where('branch_id', $bid)
            ->where('is_active', true)->count();
        $patientsNew = Patient::where('branch_id', $bid)
            ->whereDate('created_at', '>=', $monthStart)->count();

        // ── Appointments ──────────────────────────────────────────────────────
        $appt = fn () => Appointment::where('branch_id', $bid);
        $apptToday = $appt()->whereDate('appointment_date', $today)
            ->whereNotIn('status', ['cancelled', 'no_show'])->count();
        $apptWeek = $appt()
            ->whereBetween('appointment_date', [$weekStart, $weekEnd])
            ->whereNotIn('status', ['cancelled', 'no_show'])->count();
        // Appointments' terminal status is 'done' — 'completed' does not exist
        // on the appointments enum and always counted 0 here.
        $apptCompletedMonth = $appt()->where('status', 'done')
            ->whereDate('appointment_date', '>=', $monthStart)->count();
        $apptCancelledMonth = $appt()->whereIn('status', ['cancelled', 'no_show'])
            ->whereDate('appointment_date', '>=', $monthStart)->count();

        // ── Finance — shared ReportMetricsService (2026-07-14): same tables
        //    and filters as the web Reports page, so the totals MATCH. This
        //    endpoint previously summed Receipts and counted all non-cancelled
        //    invoices as outstanding, so mobile and desk disagreed.
        $collectedToday = $metrics->collected(
            now()->startOfDay(), now()->endOfDay(), $bid);
        $collectedMonth = $metrics->collected(
            now()->startOfMonth(), now()->endOfDay(), $bid);
        $outstanding    = $metrics->outstanding($bid);

        // ── Lab ───────────────────────────────────────────────────────────────
        $labOpen = LabCase::where('branch_id', $bid)
            ->whereIn('status', LabCase::OPEN_STATUSES)->count();

        // ── Selected-range block + collections series ─────────────────────────
        $rangeAppointments = Appointment::where('branch_id', $bid)
            ->whereBetween('appointment_date', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'no_show'])->count();

        $range = [
            'from'               => $from->toDateString(),
            'to'                 => $to->toDateString(),
            'collected'          => $metrics->collected($from, $to, $bid),
            'appointments'       => $rangeAppointments,
            'appointments_done'  => $metrics->appointmentsDone($from, $to, $bid),
            'new_patients'       => Patient::where('branch_id', $bid)
                ->whereBetween('created_at', [$from, $to])->count(),
        ];

        // Series over the selected range, capped at 90 points to keep the
        // payload phone-friendly (longer ranges: chart the last 90 days).
        $seriesFrom = $from->copy();
        if ($seriesFrom->diffInDays($to) > 89) {
            $seriesFrom = $to->copy()->subDays(89);
        }
        $series = $metrics->collectionsSeries($seriesFrom, $to, $bid);

        return $this->success([
            'patients' => [
                'active'        => $patientsActive,
                'new_this_month' => $patientsNew,
            ],
            'appointments' => [
                'today'                => $apptToday,
                'this_week'            => $apptWeek,
                'completed_this_month' => $apptCompletedMonth,
                'cancelled_this_month' => $apptCancelledMonth,
            ],
            'finance' => [
                'collected_today'      => $collectedToday,
                'collected_this_month' => $collectedMonth,
                'outstanding'          => $outstanding,
            ],
            'lab' => [
                'open_cases' => $labOpen,
            ],
            'range'          => $range,
            'collections_7d' => $series,
            'generated_at'   => now()->toIso8601String(),
        ], '');
    }

    /**
     * Outstanding-balance follow-up list, sorted highest balance first — the
     * one "report" front-desk staff actually work from daily (who to call for
     * collections). Deliberately a single focused drill-down rather than a
     * 1:1 port of every web report tab (most of those are desk-bound
     * finance/audit views with low mobile-usage frequency).
     *
     *   GET /api/v1/reports/outstanding
     */
    public function outstandingByPatient(Request $request): JsonResponse
    {
        $bid = $request->user()->branch_id;

        $rows = Invoice::with('patient:id,branch_id,name,patient_id,phone')
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $bid))
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->limit(200)
            ->get()
            ->filter(fn ($inv) => $inv->patient !== null)
            ->map(fn ($inv) => [
                'invoice_id'   => $inv->id,
                'number'       => $inv->invoice_number,
                'date'         => $inv->invoice_date,
                'patient'      => [
                    'id'    => $inv->patient->id,
                    'name'  => $inv->patient->name,
                    'phone' => $inv->patient->phone,
                ],
                'total_amount' => (float) $inv->total_amount,
                'paid_amount'  => (float) $inv->paid_amount,
                'balance_due'  => (float) $inv->balance_due,
            ])
            ->values();

        return $this->success([
            'total_outstanding' => (float) $rows->sum('balance_due'),
            'invoice_count'     => $rows->count(),
            'invoices'          => $rows,
        ], '');
    }
}
