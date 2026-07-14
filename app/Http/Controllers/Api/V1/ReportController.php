<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\Receipt;
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
    public function overview(Request $request): JsonResponse
    {
        $bid        = $request->user()->branch_id;
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $weekStart  = now()->startOfWeek()->toDateString();
        $weekEnd    = now()->endOfWeek()->toDateString();

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

        // ── Finance ───────────────────────────────────────────────────────────
        $receipts = fn () => Receipt::whereHas(
            'patient', fn ($q) => $q->where('branch_id', $bid)
        );
        $collectedToday = (float) $receipts()
            ->whereDate('receipt_date', $today)->sum('amount');
        $collectedMonth = (float) $receipts()
            ->whereDate('receipt_date', '>=', $monthStart)->sum('amount');
        $outstanding = (float) Invoice::whereHas(
            'patient', fn ($q) => $q->where('branch_id', $bid)
        )->where('status', '!=', 'cancelled')->sum('balance_due');

        // ── Lab ───────────────────────────────────────────────────────────────
        $labOpen = LabCase::where('branch_id', $bid)
            ->whereIn('status', LabCase::OPEN_STATUSES)->count();

        // ── 7-day collections series (oldest → today) ─────────────────────────
        $rows = Receipt::whereHas('patient', fn ($q) => $q->where('branch_id', $bid))
            ->whereDate('receipt_date', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(receipt_date) as d, SUM(amount) as total')
            ->groupBy('d')->pluck('total', 'd');

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->toDateString();
            $series[] = [
                'date'   => $key,
                'label'  => $day->format('D'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
        }

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
