<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The weekly accountability read over the outcome trail.
 *
 * READ ONLY. This class never writes a row and never touches task state. It
 * exists because task_outcomes has been collecting evidence since Slice 1b and
 * nothing has ever read it back — an audit trail nobody reads is just storage.
 *
 * ── WHAT THIS DELIBERATELY DOES NOT DO ──────────────────────────────────────
 * It does not rank staff and it does not compute a completion percentage.
 *
 * A receptionist who closes 60 recall calls and a hygienist who closes 4 lab
 * chases are not comparable, and a percentage would invite exactly that
 * comparison by putting them on one scale. Worse, the denominator is genuinely
 * ambiguous — tasks assigned in the window, tasks due in it, or tasks touched
 * in it are three different numbers — so any percentage printed here would be
 * an opinion wearing a decimal point.
 *
 * What it prints instead is counts, each with a stated definition. A clinic
 * owner reading "12 done, 31 attempted, 9 rescheduled" learns something real:
 * that this person is working and not finishing, which is a conversation about
 * the work, not about the person.
 *
 * ── TWO CLOCKS, AND WHY THEY ARE SEPARATED ──────────────────────────────────
 * Activity counts (done/attempted/rescheduled/cancelled) come from the outcome
 * trail and are bounded by the date range — they answer "what happened that
 * week". Open and overdue counts are CURRENT STATE and ignore the range
 * entirely — an overdue task is overdue today, not "overdue last Tuesday".
 * Mixing them into one row without saying so is how a report starts lying, so
 * the view labels the second pair "right now".
 */
class TaskAccountabilityReport
{
    /** How many distinct blocking reasons are worth showing before it is noise. */
    public const TOP_REASONS = 8;

    /**
     * @return array{
     *   staff: array<int, array<string, mixed>>,
     *   reasons: array<int, array<string, mixed>>,
     *   totals: array<string, int>
     * }
     */
    public function build(int $branchId, CarbonInterface $from, CarbonInterface $to): array
    {
        // Inclusive of the whole end day. Passing a bare date to a datetime
        // column would silently exclude everything logged after midnight on
        // the last day — a full day of work missing from every week's report.
        $start = $from->copy()->startOfDay();
        $end   = $to->copy()->endOfDay();

        $activity = $this->activityByUser($branchId, $start, $end);
        $state    = $this->currentStateByUser($branchId);
        $staff    = $this->merge($branchId, $activity, $state);

        return [
            'staff'   => $staff,
            'reasons' => $this->blockingReasons($branchId, $start, $end),
            'totals'  => $this->totals($staff),
        ];
    }

    /**
     * Everything that HAPPENED in the window, attributed to whoever logged it.
     *
     * Attribution is by task_outcomes.user_id, not tasks.assigned_to, and the
     * difference matters: when a receptionist closes a task assigned to the
     * dentist, the work was hers. Assignment says who owes it; the trail says
     * who did it.
     *
     * "Slipped" = closed after the date it was originally due. original_due_date
     * is used in preference to due_date because rescheduling overwrites due_date
     * — without the original, every task could be made punctual by moving it.
     */
    private function activityByUser(int $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = DB::table('task_outcomes as o')
            ->join('tasks as t', 't.id', '=', 'o.task_id')
            ->where('o.branch_id', $branchId)
            ->whereBetween('o.created_at', [$start, $end])
            ->groupBy('o.user_id')
            ->select([
                'o.user_id',
                DB::raw("SUM(CASE WHEN o.action = 'done'        THEN 1 ELSE 0 END) as done_count"),
                DB::raw("SUM(CASE WHEN o.action = 'attempted'   THEN 1 ELSE 0 END) as attempted_count"),
                DB::raw("SUM(CASE WHEN o.action = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_count"),
                DB::raw("SUM(CASE WHEN o.action = 'cancelled'   THEN 1 ELSE 0 END) as cancelled_count"),
                DB::raw("SUM(CASE WHEN o.action = 'reopened'    THEN 1 ELSE 0 END) as reopened_count"),
                DB::raw("SUM(CASE WHEN o.action = 'done'
                                   AND DATE(o.created_at) > COALESCE(t.original_due_date, t.due_date)
                              THEN 1 ELSE 0 END) as slipped_count"),
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->user_id] = [
                'done'        => (int) $r->done_count,
                'attempted'   => (int) $r->attempted_count,
                'rescheduled' => (int) $r->rescheduled_count,
                'cancelled'   => (int) $r->cancelled_count,
                'reopened'    => (int) $r->reopened_count,
                'slipped'     => (int) $r->slipped_count,
            ];
        }

        return $out;
    }

    /**
     * What is on each person's plate RIGHT NOW. Not range-bound — see the
     * class docblock. Keyed by assigned_to, because this is about who owes
     * the work, not who last touched it.
     */
    private function currentStateByUser(int $branchId): array
    {
        $rows = DB::table('tasks')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->select([
                'assigned_to',
                DB::raw('COUNT(*) as open_count'),
                DB::raw('SUM(CASE WHEN COALESCE(original_due_date, due_date) < CURDATE() THEN 1 ELSE 0 END) as overdue_count'),
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->assigned_to] = [
                'open'    => (int) $r->open_count,
                'overdue' => (int) $r->overdue_count,
            ];
        }

        return $out;
    }

    /**
     * One row per person who appears in either half, names resolved in a single
     * query rather than a lookup per row.
     */
    private function merge(int $branchId, array $activity, array $state): array
    {
        $ids = array_values(array_unique(array_merge(array_keys($activity), array_keys($state))));

        if (empty($ids)) {
            return [];
        }

        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        $staff = [];
        foreach ($ids as $id) {
            $a = $activity[$id] ?? ['done' => 0, 'attempted' => 0, 'rescheduled' => 0, 'cancelled' => 0, 'reopened' => 0, 'slipped' => 0];
            $s = $state[$id]    ?? ['open' => 0, 'overdue' => 0];

            $staff[] = array_merge($a, $s, [
                'user_id' => $id,
                // A deleted user still owns their history; the trail must not
                // collapse into a blank row because someone left the clinic.
                'name'    => $names[$id] ?? 'Removed user #' . $id,
            ]);
        }

        // Busiest first. This is an ordering, not a ranking — there is no score.
        usort($staff, fn ($x, $y) => ($y['done'] + $y['attempted']) <=> ($x['done'] + $x['attempted']));

        return $staff;
    }

    /**
     * Why work did not finish, most common first.
     *
     * Read from 'attempted' rows only. A 'done' outcome is a result, not an
     * obstacle, and mixing the two would put "Booked appointment" at the top
     * of a list headed "what is blocking us".
     *
     * Grouped on outcome_label, not outcome_key, because the label is the
     * snapshot staff actually saw — a clinic that renames an outcome in
     * Settings should see both spellings, not have its history silently
     * rewritten under one key.
     */
    private function blockingReasons(int $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        return DB::table('task_outcomes')
            ->where('branch_id', $branchId)
            ->where('action', 'attempted')
            ->whereNotNull('outcome_label')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('outcome_label')
            ->select(['outcome_label as label', DB::raw('COUNT(*) as count')])
            ->orderByDesc('count')
            ->limit(self::TOP_REASONS)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
            ->all();
    }

    private function totals(array $staff): array
    {
        $keys = ['done', 'attempted', 'rescheduled', 'cancelled', 'reopened', 'slipped', 'open', 'overdue'];
        $out  = array_fill_keys($keys, 0);

        foreach ($staff as $row) {
            foreach ($keys as $k) {
                $out[$k] += $row[$k];
            }
        }

        return $out;
    }
}
