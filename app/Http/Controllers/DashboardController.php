<?php

namespace App\Http\Controllers;

use App\Services\Analytics\ReportMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Dashboard is the PERIOD screen. It answers "how did this month go?".
 *
 * It deliberately shows NOTHING about today — the Daily Huddle owns today, and
 * two screens answering the same question is how they came to print different
 * numbers for the same day (the old dashboard scoped appointments with
 * Appointment::visibleTo(), the huddle board did not, so a scoped doctor saw
 * two different counts).
 *
 * EVERY figure here comes from ReportMetricsService and NOTHING is queried in
 * this controller. That service is the one canonical definition of collected /
 * billed / outstanding / profit (W-4, W-5, W-7, W-8). A number this screen
 * needs and the service lacks gets a new METHOD ON THE SERVICE — never a query
 * here, or the app grows a fourth KPI implementation.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportMetricsService $metrics
    ) {}

    public function index(Request $request)
    {
        $user     = Auth::user();
        $branchId = $user->branch_id;

        // Money is admin-only. The old dashboard leaked today's collection and
        // the full receivable to every logged-in role; a period profit figure
        // is a bigger exposure than that, so it is gated. Widening this to
        // doctors (their own row only) is a CEO decision, not a default.
        $showMoney = $user->isAdminRole();

        // Same ?period=7|30|90|365|custom&from=&to= contract as /reports.
        $period = (string) $request->get('period', '30');
        [$from, $to] = $this->metrics->resolveRange(
            $period,
            $request->get('from'),
            $request->get('to')
        );

        // The comparison window: the SAME number of days, immediately before.
        $days     = max(1, $from->diffInDays($to) + 1);
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $from->copy()->subDays($days)->startOfDay();

        // ── FLOWS — these follow the date filter ─────────────────────────────
        $appointmentsDone = $this->metrics->appointmentsDone($from, $to, $branchId);
        $newPatients      = $this->metrics->newPatients($from, $to, $branchId);

        $numbers = [
            'appointments' => $this->delta(
                $appointmentsDone,
                $this->metrics->appointmentsDone($prevFrom, $prevTo, $branchId)
            ),
            'new_patients' => $this->delta(
                $newPatients,
                $this->metrics->newPatients($prevFrom, $prevTo, $branchId)
            ),
        ];

        $stocks   = [];
        $byDoctor = collect();
        $byCategory = collect();
        $byMode   = collect();
        $profit   = null;

        if ($showMoney) {
            $billed    = $this->metrics->billed($from, $to, $branchId);
            $collected = $this->metrics->collected($from, $to, $branchId);

            $prevBilled    = $this->metrics->billed($prevFrom, $prevTo, $branchId);
            $prevCollected = $this->metrics->collected($prevFrom, $prevTo, $branchId);

            $numbers['billed']    = $this->delta($billed, $prevBilled);
            $numbers['collected'] = $this->delta($collected, $prevCollected);

            // Collection ratio — of THIS window's billing, how much has come
            // in. Deliberately NOT collected()/billed(): those are different
            // populations (a payment now can settle a June invoice) and that
            // ratio goes over 100% routinely, which reads as a bug on screen.
            $numbers['collection_ratio'] = $this->delta(
                $this->ratio($this->metrics->collectedOnBilled($from, $to, $branchId), $billed),
                $this->ratio($this->metrics->collectedOnBilled($prevFrom, $prevTo, $branchId), $prevBilled)
            );

            // profit() is deliberately NOT branch-scoped in the service:
            // finance_expenses carries clinic_id, not branch_id, and a
            // parameter that silently lies is worse than none (W-4).
            $profit = $this->metrics->profit($from, $to);
            $numbers['profit'] = $this->delta(
                $profit['cash_profit'],
                $this->metrics->profit($prevFrom, $prevTo)['cash_profit']
            );

            // ── STOCKS — these do NOT follow the date filter ──────────────────
            // CEO ruling 6 Sep: a receivable does not shrink because someone
            // changed a date filter. Rendered in their own strip, labelled.
            $stocks = [
                'outstanding'    => $this->metrics->outstanding($branchId),
                'patient_credit' => $this->metrics->patientCreditHeld($branchId),
            ];

            $byDoctor   = $this->doctorTable($from, $to, $branchId);
            $byCategory = $this->metrics->billedByCategory($from, $to, $branchId);
            $byMode     = $this->metrics->collectionsByMode($from, $to, $branchId);
        }

        return view('dashboard.index', compact(
            'period',
            'from',
            'to',
            'prevFrom',
            'prevTo',
            'days',
            'showMoney',
            'numbers',
            'stocks',
            'profit',
            'byDoctor',
            'byCategory',
            'byMode',
        ));
    }

    /**
     * One doctor per row: work done, money billed, money collected.
     *
     * Three separate questions that must never be collapsed into one (W-8):
     * visits are the doctor's, billing is whoever set the price, collection is
     * reception's. Merged on the doctor name because all three service methods
     * key by it and each may carry a doctor the others do not.
     */
    private function doctorTable($from, $to, ?int $branchId)
    {
        $visits    = $this->metrics->visitsByDoctor($from, $to, $branchId);
        $billed    = $this->metrics->billedByDoctor($from, $to, $branchId);
        $collected = $this->metrics->collectedByDoctor($from, $to, $branchId);

        return $visits->keys()
            ->merge($billed->keys())
            ->merge($collected->keys())
            ->unique()
            ->map(function ($name) use ($visits, $billed, $collected) {
                $v = (int)   ($visits->get($name)->visits ?? 0);
                $b = (float) ($billed->get($name)->billed ?? 0);

                return [
                    'doctor'    => $name,
                    'visits'    => $v,
                    'billed'    => $b,
                    'collected' => (float) ($collected->get($name)->collected ?? 0),
                    'per_visit' => $v > 0 ? $b / $v : 0.0,
                ];
            })
            ->sortByDesc('collected')
            ->values();
    }

    /** A figure plus how it moved against the same-length previous window. */
    private function delta(float|int $now, float|int $prev): array
    {
        return [
            'value'  => $now,
            'prev'   => $prev,
            'change' => $prev > 0 ? round((($now - $prev) / $prev) * 100) : null,
        ];
    }

    private function ratio(float $part, float $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
