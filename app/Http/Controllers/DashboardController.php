<?php

namespace App\Http\Controllers;

use App\Services\Analytics\ReportMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Dashboard is the PERIOD screen: Today (default), This Month, This
 * Quarter, This FY, or a custom range.
 *
 * CEO ruling 24 Sep: it opens on TODAY. That is safe only because every
 * figure comes from ReportMetricsService — the old dashboard and the huddle
 * printed different numbers for the same day because they each ran their own
 * queries (Appointment::visibleTo() on one, not the other). The Huddle still
 * owns today's WORK (who to call, what is blocked); this screen owns the
 * NUMBERS for whatever window is picked.
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

        // ?period=today|month|quarter|fy|custom&from=&to=. The old rolling
        // 7|30|90|365 values still resolve, so bookmarks do not break.
        $period = (string) $request->get('period', 'today');
        [$from, $to] = $this->metrics->resolveRange(
            $period,
            $request->get('from'),
            $request->get('to')
        );

        // Like-for-like comparison: yesterday, last month to date, etc.
        [$prevFrom, $prevTo] = $this->metrics->previousRange($period, $from, $to);
        $days = max(1, (int) $from->diffInDays($to) + 1);

        $compareLabel = match ($period) {
            'today'   => 'vs yesterday',
            'month'   => 'vs last month, same days',
            'quarter' => 'vs last quarter, same days',
            'fy'      => 'vs last FY, same date',
            default   => 'vs previous ' . $days . ' ' . ($days === 1 ? 'day' : 'days'),
        };

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
            'compareLabel',
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
