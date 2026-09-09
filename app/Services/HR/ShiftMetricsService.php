<?php

namespace App\Services\HR;

use App\Models\HrAttendance;
use App\Models\HrShift;
use App\Models\HrStaffShift;
use App\Models\User;
use Carbon\Carbon;

/**
 * ShiftMetricsService — late arrival and overtime, from the shift a staffer
 * was actually assigned on that date (M-14, CEO request 9 Sep 2026).
 *
 * The tables have existed since 18 June (hr_shifts, hr_staff_shifts) but
 * NOTHING has ever read them for attendance: hr_attendance stores check_in
 * and check_out and that is all, so "did they come late" and "did they stay
 * back" were questions the system could not answer.
 *
 * RULES, deliberately narrow:
 *   - No shift assigned on that date  → no numbers at all (null), never a
 *     guessed 9-to-6. An unassigned staffer is a Settings gap, not overtime.
 *   - Overtime needs BOTH stamps. Someone still in the clinic is not
 *     "in overtime" — they simply have not checked out.
 *   - A shift ending before it starts is an overnight shift; the end rolls
 *     to the next day rather than producing a negative expected day.
 *   - Grace: LATE_GRACE_MINUTES. Arriving inside the grace is on time, and
 *     the minutes are still reported so a pattern is visible.
 *   - Nothing here writes. It reads the row the phone/QR already wrote.
 */
class ShiftMetricsService
{
    /** Minutes after shift start still counted as on time. */
    public const LATE_GRACE_MINUTES = 10;

    /** The shift assigned to this user on this date, if any. */
    public function shiftFor(User $user, Carbon $date): ?HrShift
    {
        $assignment = HrStaffShift::query()
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->with('shift')
            ->first();

        return $assignment?->shift;
    }

    /**
     * @return array{
     *   shift_name: ?string, shift_start: ?string, shift_end: ?string,
     *   expected_minutes: ?int, worked_minutes: ?int,
     *   late_minutes: ?int, is_late: ?bool,
     *   overtime_minutes: ?int, early_leave_minutes: ?int
     * }
     */
    public function metrics(User $user, HrAttendance $row): array
    {
        $date  = $row->date instanceof Carbon ? $row->date->copy() : Carbon::parse($row->date);
        $shift = $this->shiftFor($user, $date);

        $blank = [
            'shift_name'          => null,
            'shift_start'         => null,
            'shift_end'           => null,
            'expected_minutes'    => null,
            'worked_minutes'      => null,
            'late_minutes'        => null,
            'is_late'             => null,
            'overtime_minutes'    => null,
            'early_leave_minutes' => null,
        ];

        if (! $shift) {
            return $blank;
        }

        $start = $date->copy()->setTimeFromTimeString((string) $shift->start_time);
        $end   = $date->copy()->setTimeFromTimeString((string) $shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();   // overnight shift
        }

        $out = $blank;
        $out['shift_name']       = $shift->name;
        $out['shift_start']      = $start->format('H:i');
        $out['shift_end']        = $end->format('H:i');
        $out['expected_minutes'] = $start->diffInMinutes($end);

        if (! $row->check_in) {
            return $out;
        }

        $in = $date->copy()->setTimeFromTimeString((string) $row->check_in);
        $lateBy = $in->greaterThan($start) ? $start->diffInMinutes($in) : 0;
        $out['late_minutes'] = $lateBy;
        $out['is_late']      = $lateBy > self::LATE_GRACE_MINUTES;

        if (! $row->check_out) {
            return $out;   // still in the clinic — no worked/overtime yet
        }

        $outAt = $date->copy()->setTimeFromTimeString((string) $row->check_out);
        if ($outAt->lessThanOrEqualTo($in)) {
            $outAt->addDay();
        }

        $out['worked_minutes']      = $in->diffInMinutes($outAt);
        $out['overtime_minutes']    = max(0, $outAt->greaterThan($end) ? $end->diffInMinutes($outAt) : 0);
        $out['early_leave_minutes'] = max(0, $outAt->lessThan($end) ? $outAt->diffInMinutes($end) : 0);

        return $out;
    }
}
