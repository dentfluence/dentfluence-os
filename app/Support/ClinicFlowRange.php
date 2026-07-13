<?php

namespace App\Support;

use App\Models\Appointment;
use Carbon\Carbon;

/**
 * ClinicFlowRange — resolves the date range any "yesterday" style huddle
 * section should report on.
 *
 * Normally that's just literally yesterday. But the clinic is closed on
 * Sundays by default, so a Monday query for "yesterday" lands on a dark
 * Sunday and shows nothing — not because it was a clean day, but because
 * the clinic wasn't open. On Monday this resolves to Saturday instead.
 *
 * There's no fixed weekly-off setting (hours vary week to week and the
 * clinic sometimes does open on a Sunday), so "was Sunday open" is
 * inferred from whether any appointment was scheduled that day, not a
 * config flag. If it was, Saturday + Sunday are combined into one range so
 * nothing gets silently dropped; if not, only Saturday is returned.
 * Tuesday–Sunday queries are unaffected — always a single day.
 *
 * Single source of truth — every "yesterday" section in Huddle
 * (app/Services/Huddle/HuddleService.php, YesterdayReviewService,
 * TodayActionsEngine::wellnessCheckYesterday) should resolve through this,
 * not recompute Carbon::yesterday() independently, so the rule can't drift
 * out of sync between surfaces again.
 */
class ClinicFlowRange
{
    /**
     * @return array{0: Carbon, 1: Carbon} [$start, $end] — both dates inclusive.
     */
    public static function resolve(?Carbon $day = null): array
    {
        $day = ($day ?? Carbon::today())->copy()->startOfDay();
        $yesterday = $day->copy()->subDay();

        if (! $day->isMonday()) {
            return [$yesterday, $yesterday];
        }

        $saturday = $day->copy()->subDays(2);
        $sunday   = $yesterday;

        $sundayWasOpen = Appointment::whereDate('appointment_date', $sunday)->exists();

        return $sundayWasOpen ? [$saturday, $sunday] : [$saturday, $saturday];
    }

    /** True when resolve() returned a combined Saturday+Sunday range. */
    public static function isCombined(array $range): bool
    {
        return ! $range[0]->isSameDay($range[1]);
    }
}
