<?php

namespace App\Enums;

/**
 * CancellationReason — the fixed list of why an appointment died.
 *
 * Free text was the old design and it is why nobody can answer "are we losing
 * patients to price or to waiting time?" — 500 characters of prose cannot be
 * grouped. The dropdown is what gets counted; the free-text note beside it
 * survives for the cases where the number looks strange and someone has to read
 * what actually happened.
 *
 * Labels are English only. The screen's other labels are English, and half a
 * screen in each language reads worse than either one alone.
 *
 * The reason also carries the DEFAULT CALLBACK GAP, because the right moment to
 * ring back is a property of why they left, not a number reception should have
 * to invent under pressure:
 *   - money needs time to arrive     → two weeks
 *   - fear does not                  → two days, while the tooth still hurts
 *   - a clash is just a diary problem → three days
 *   - out of town                    → three weeks
 *   - our own fault                  → tomorrow; we owe them the call
 * These are defaults, not rules — reception can move the date in the modal.
 *
 * Adding or removing a reason is a change HERE and nowhere else: the column is a
 * string, the validation rule is generated from these cases, and the modal
 * renders whatever label() returns.
 */
enum CancellationReason: string
{
    case Cost           = 'cost';
    case FearOrPain     = 'fear_pain';
    case TimeClash      = 'time_clash';
    case OutOfTown      = 'out_of_town';
    case TreatedElsewhere = 'treated_elsewhere';
    case ClinicSide     = 'clinic_side';
    case Other          = 'other';

    /** All raw values, in the order the dropdown shows them. */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    /** Laravel validation rule for the reason_code field. */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Cost              => 'Cost / affordability',
            self::FearOrPain        => 'Fear or pain',
            self::TimeClash         => 'Time clash — could not make it',
            self::OutOfTown         => 'Out of town / travelling',
            self::TreatedElsewhere  => 'Treated elsewhere',
            self::ClinicSide        => 'Clinic side (doctor unavailable, rescheduled by us)',
            self::Other             => 'Other',
        };
    }

    /**
     * Days from today that the callback should default to.
     *
     * Clinic-side cancellations get 1: we broke the appointment, so the call
     * goes out tomorrow, not next week.
     */
    public function defaultCallbackDays(): int
    {
        return match ($this) {
            self::Cost              => 14,
            self::FearOrPain        => 2,
            self::TimeClash         => 3,
            self::OutOfTown         => 21,
            self::TreatedElsewhere  => 30,
            self::ClinicSide        => 1,
            self::Other             => 7,
        };
    }

    /**
     * The map the cancel modal renders: value → {label, days}.
     * The Blade view injects this instead of hard-coding its own copy — the
     * same pattern AppointmentStatus::calendarMeta() already established.
     */
    public static function modalMeta(): array
    {
        $meta = [];
        foreach (self::cases() as $case) {
            $meta[$case->value] = [
                'label' => $case->label(),
                'days'  => $case->defaultCallbackDays(),
            ];
        }

        return $meta;
    }
}
