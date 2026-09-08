<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicHoliday;
use App\Models\ClinicHour;
use App\Models\DoctorBlockedSlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * ClinicHoursService — the ONE answer to "is the clinic open, and when is this
 * doctor actually free?".
 *
 * Everything that needs a free slot asks this: the calendar's render window,
 * the booking guard in AppointmentService, and the two-slot reschedule offer in
 * the WhatsApp Moments PRD. There must never be a second implementation — a
 * calendar that disagrees with the message a patient just received is worse
 * than having neither.
 *
 * SAFE WHEN UNCONFIGURED. With no clinic_hours rows isConfigured() is false and
 * every method degrades to today's behaviour: the day is open, nothing is
 * refused, and the calendar keeps its existing window. A clinic only starts
 * being constrained once someone fills the table in.
 */
class ClinicHoursService
{
    /** Used only for the calendar's visible window when hours are unset. */
    public const FALLBACK_WINDOW = ['start' => '08:00', 'end' => '21:00'];

    /** @var array<int, \Illuminate\Support\Collection<int, ClinicHour>> */
    private array $weekMemo = [];

    /**
     * Has anyone configured hours for this branch at all?
     *
     * Callers use this to decide whether to enforce anything. A branch with no
     * rows is not "closed all week" — it is unconfigured, and the difference
     * matters: one blocks every booking, the other blocks none.
     */
    public function isConfigured(?int $branchId): bool
    {
        return $this->week($branchId)->isNotEmpty();
    }

    /** @return \Illuminate\Support\Collection<int, ClinicHour> keyed by weekday */
    public function week(?int $branchId): \Illuminate\Support\Collection
    {
        $key = (int) $branchId;

        return $this->weekMemo[$key] ??= ClinicHour::where('branch_id', $branchId)
            ->get()
            ->keyBy('weekday');
    }

    public function forget(): void
    {
        $this->weekMemo = [];
    }

    // ── Holidays ─────────────────────────────────────────────────────────────

    public function holidayOn(CarbonInterface $date, ?int $branchId): ?ClinicHoliday
    {
        return ClinicHoliday::query()
            ->forBranch($branchId)
            ->onDate($date)
            ->first();
    }

    // ── The day ──────────────────────────────────────────────────────────────

    /**
     * Open sessions on a given date, after holidays.
     *
     * Returns [] when the clinic is shut that day, and null when hours are not
     * configured at all — an EMPTY ARRAY and NULL mean different things here
     * and callers must not conflate them: [] is "closed", null is "we do not
     * know, carry on as before".
     *
     * @return array<int, array{start:string, end:string}>|null
     */
    public function sessionsOn(CarbonInterface $date, ?int $branchId): ?array
    {
        if (! $this->isConfigured($branchId)) {
            return null;
        }

        if ($this->holidayOn($date, $branchId)) {
            return [];
        }

        $row = $this->week($branchId)->get($date->dayOfWeek);

        // A weekday with no row at all, on a branch that HAS configured other
        // days, is treated as closed: the clinic described its week and left
        // this day out.
        return $row ? $row->sessions() : [];
    }

    public function isOpenOn(CarbonInterface $date, ?int $branchId): bool
    {
        $sessions = $this->sessionsOn($date, $branchId);

        return $sessions === null || $sessions !== [];
    }

    /**
     * Does [start, start+duration) sit entirely inside one open session?
     *
     * Deliberately whole-appointment: a slot that starts five minutes before
     * closing is not bookable just because its first minute is inside hours.
     */
    public function isWithinHours(CarbonInterface $date, string $time, int $durationMinutes, ?int $branchId): bool
    {
        $sessions = $this->sessionsOn($date, $branchId);

        if ($sessions === null) {
            return true;   // unconfigured — do not constrain
        }

        if ($sessions === []) {
            return false;  // closed
        }

        $start = Carbon::parse($date->format('Y-m-d') . ' ' . substr($time, 0, 5));
        $end   = $start->copy()->addMinutes(max(1, $durationMinutes));

        foreach ($sessions as $s) {
            $open  = Carbon::parse($date->format('Y-m-d') . ' ' . $s['start']);
            $close = Carbon::parse($date->format('Y-m-d') . ' ' . $s['end']);

            if ($start->gte($open) && $end->lte($close)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Earliest open and latest close across the configured week — what the
     * calendar should actually render instead of midnight to midnight.
     *
     * @return array{start:string, end:string}
     */
    public function renderWindow(?int $branchId): array
    {
        $starts = [];
        $ends   = [];

        foreach ($this->week($branchId) as $row) {
            foreach ($row->sessions() as $s) {
                $starts[] = $s['start'];
                $ends[]   = $s['end'];
            }
        }

        if (empty($starts)) {
            return self::FALLBACK_WINDOW;
        }

        return ['start' => min($starts), 'end' => max($ends)];
    }

    // ── The one free-slot query ──────────────────────────────────────────────

    /**
     * Bookable start times for a doctor on a date.
     *
     * Open sessions, minus this doctor's blocked slots, minus appointments that
     * still hold their time. Cancelled and no-show appointments release their
     * slot — the same rule AppointmentService::overlapConflict() already uses,
     * and it must stay the same rule in both places.
     *
     * @param  int  $step  minutes between candidate starts (the slot grid)
     * @return array<int, string> 'H:i' start times, in order
     */
    public function freeSlots(
        CarbonInterface $date,
        ?int $doctorId,
        ?int $branchId,
        int $durationMinutes = 30,
        int $step = 15
    ): array {
        $sessions = $this->sessionsOn($date, $branchId);

        if ($sessions === null) {
            $sessions = [self::FALLBACK_WINDOW];
        }

        if ($sessions === []) {
            return [];
        }

        $ymd      = $date->format('Y-m-d');
        $duration = max(1, $durationMinutes);
        $step     = max(5, $step);

        $busy = $this->busyIntervals($ymd, $doctorId, $branchId);
        $out  = [];

        foreach ($sessions as $s) {
            $cursor = Carbon::parse("{$ymd} {$s['start']}");
            $close  = Carbon::parse("{$ymd} {$s['end']}");

            while ($cursor->copy()->addMinutes($duration)->lte($close)) {
                $slotEnd = $cursor->copy()->addMinutes($duration);

                $clashes = false;
                foreach ($busy as [$bStart, $bEnd]) {
                    if ($cursor->lt($bEnd) && $slotEnd->gt($bStart)) {
                        $clashes = true;
                        break;
                    }
                }

                if (! $clashes && $cursor->isFuture()) {
                    $out[] = $cursor->format('H:i');
                }

                $cursor->addMinutes($step);
            }
        }

        return $out;
    }

    /**
     * The next N free slots from a date forward — what the WhatsApp
     * "Reschedule" tap offers the patient. Walks day by day so a holiday or a
     * closed weekday is skipped rather than offered.
     *
     * @return array<int, array{date:string, time:string}>
     */
    public function nextFreeSlots(
        CarbonInterface $from,
        ?int $doctorId,
        ?int $branchId,
        int $count = 2,
        int $durationMinutes = 30,
        int $lookAheadDays = 14
    ): array {
        $found  = [];
        $cursor = Carbon::parse($from->format('Y-m-d'));

        for ($i = 0; $i < $lookAheadDays && count($found) < $count; $i++) {
            foreach ($this->freeSlots($cursor, $doctorId, $branchId, $durationMinutes) as $time) {
                $found[] = ['date' => $cursor->format('Y-m-d'), 'time' => $time];

                if (count($found) >= $count) {
                    break;
                }
            }

            $cursor->addDay();
        }

        return $found;
    }

    /**
     * Everything that already holds time on this date: the doctor's blocked
     * slots and their live appointments.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function busyIntervals(string $ymd, ?int $doctorId, ?int $branchId): array
    {
        $busy = [];

        if ($doctorId) {
            foreach (DoctorBlockedSlot::where('doctor_id', $doctorId)->where('block_date', $ymd)->get() as $b) {
                $busy[] = [
                    Carbon::parse("{$ymd} " . substr((string) $b->start_time, 0, 5)),
                    Carbon::parse("{$ymd} " . substr((string) $b->end_time, 0, 5)),
                ];
            }
        }

        $appts = Appointment::query()
            ->whereDate('appointment_date', $ymd)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['appointment_time', 'duration_minutes']);

        foreach ($appts as $a) {
            $start = Carbon::parse("{$ymd} " . substr((string) $a->appointment_time, 0, 5));
            $busy[] = [$start, $start->copy()->addMinutes(max(1, (int) $a->duration_minutes))];
        }

        return $busy;
    }
}
