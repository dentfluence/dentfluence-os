<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Modules\Huddle\Models\HuddleBoard;
use App\Services\ClinicHoursService;
use App\Services\Huddle\HuddleService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * HuddleCloseService — "was the huddle actually held today?"
 * ----------------------------------------------------------------------------
 * The Huddle page itself is a live board: every number on it is recomputed from
 * the current state of the database each time it loads. That is correct for
 * today and USELESS for history — reopening the 5th of September next month
 * would show today's stock, today's tasks and today's lab queue under a
 * September date. A past huddle is therefore never recomputed here. It is a
 * SNAPSHOT, frozen at the moment someone ticked "Huddle done", or it does not
 * exist at all.
 *
 * Storage reuses the huddle_boards table that has existed since May 2026 and
 * was never written to: one row per (branch, role, date), with is_locked /
 * locked_at / locked_by as the tick and meta as the frozen briefing.
 *
 * Only TODAY can be ticked. Back-dating a huddle would let a clinic manufacture
 * a clean record a week later, and the snapshot would be a lie anyway — the
 * live data it freezes is no longer that day's data. A missed day stays missed.
 */
class HuddleCloseService
{
    /** huddle_boards.role — the daily clinic-wide huddle, not a per-role board. */
    public const ROLE = 'clinic';

    /** Snapshot format version, so a future reader can tell old rows apart. */
    public const SNAPSHOT_VERSION = 1;

    public function __construct(
        private readonly HuddleService $huddle,
        private readonly ClinicHoursService $hours,
    ) {}

    // ── Reading ──────────────────────────────────────────────────────────────

    public function boardFor(?int $branchId, Carbon $date): ?HuddleBoard
    {
        return HuddleBoard::query()
            ->where('branch_id', $branchId)
            ->where('role', self::ROLE)
            ->whereDate('date', $date->toDateString())
            ->first();
    }

    public function isDone(?int $branchId, Carbon $date): bool
    {
        return (bool) ($this->boardFor($branchId, $date)?->is_locked);
    }

    // ── Writing ──────────────────────────────────────────────────────────────

    /**
     * Tick "Huddle done" for today and freeze the briefing into meta.
     *
     * @throws \DomainException when $date is not today.
     */
    public function close(?int $branchId, User $user, Carbon $date): HuddleBoard
    {
        $this->assertToday($date);

        $board = HuddleBoard::firstOrNew([
            'branch_id' => $branchId,
            'role'      => self::ROLE,
            'date'      => $date->toDateString(),
        ]);

        $board->is_locked = true;
        $board->locked_at = now();
        $board->locked_by = $user->id;
        $board->title     = 'Daily Huddle — ' . $date->format('d M Y');
        $board->meta      = $this->snapshot($branchId, $user, $date);
        $board->save();

        return $board;
    }

    /**
     * Untick — for the "wrong button" case only, and only on the same day.
     * The snapshot is deliberately left in place: it is evidence of what the
     * board said at the moment it was ticked, and a re-tick simply overwrites
     * it with a fresher one.
     *
     * @throws \DomainException when $date is not today.
     */
    public function reopen(?int $branchId, Carbon $date): ?HuddleBoard
    {
        $this->assertToday($date);

        $board = $this->boardFor($branchId, $date);
        if (! $board) {
            return null;
        }

        $board->is_locked = false;
        $board->locked_at = null;
        $board->locked_by = null;
        $board->save();

        return $board;
    }

    // ── History ──────────────────────────────────────────────────────────────

    /**
     * One row per calendar day, newest first:
     *   date, is_expected, is_done, closed_by_name, closed_at, has_snapshot
     *
     * "Expected" means the clinic was supposed to run that day. Where clinic
     * hours are configured, that is the configured week minus holidays. Where
     * they are not, the same inference ClinicFlowRange uses applies: a day with
     * at least one appointment was a working day. A closed day is not a pending
     * huddle and is never counted as one.
     *
     * @return Collection<int, object>
     */
    public function history(?int $branchId, Carbon $from, Carbon $to): Collection
    {
        $boards = HuddleBoard::query()
            ->where('branch_id', $branchId)
            ->where('role', self::ROLE)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with([])
            ->get()
            ->keyBy(fn ($b) => $b->date->toDateString());

        $closers = User::whereIn('id', $boards->pluck('locked_by')->filter()->unique())
            ->pluck('name', 'id');

        $busyDays = Appointment::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('appointment_date', [$from->toDateString(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(appointment_date) as d')
            ->distinct()
            ->pluck('d')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $configured = $this->hours->isConfigured($branchId);

        $rows = collect();
        for ($day = $to->copy()->startOfDay(); $day->greaterThanOrEqualTo($from); $day->subDay()) {
            $key   = $day->toDateString();
            $board = $boards->get($key);

            $expected = $configured
                ? $this->hours->isOpenOn($day, $branchId)
                : isset($busyDays[$key]);

            $rows->push((object) [
                'date'           => $day->copy(),
                'is_expected'    => $expected,
                'is_done'        => (bool) ($board?->is_locked),
                'closed_by_name' => $board?->locked_by ? ($closers[$board->locked_by] ?? '—') : null,
                'closed_at'      => $board?->locked_at,
                'has_snapshot'   => ! empty(data_get($board?->meta, 'briefing')),
            ]);
        }

        return $rows;
    }

    /** Days the clinic ran and nobody ticked. Today is excluded — it is not late yet. */
    public function pendingCount(Collection $history): int
    {
        return $history
            ->filter(fn ($r) => $r->is_expected && ! $r->is_done && ! $r->date->isToday())
            ->count();
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function snapshot(?int $branchId, User $user, Carbon $date): array
    {
        return [
            'version'      => self::SNAPSHOT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'closed_by'    => ['id' => $user->id, 'name' => $user->name],
            'briefing'     => $this->huddle->build($branchId, $date->toDateString()),
        ];
    }

    private function assertToday(Carbon $date): void
    {
        if (! $date->isSameDay(Carbon::today())) {
            throw new \DomainException('A huddle can only be marked done on the day it was held.');
        }
    }
}
