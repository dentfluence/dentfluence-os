<?php

namespace App\Enums;

/**
 * AppointmentStatus — the single source of truth for appointment statuses.
 *
 * Every status value, label, calendar colour, the "active vs terminal"
 * distinction, and the validation rule live here. Nothing else in the app
 * should hard-code the status strings, the label/colour maps, or the
 * cancelled/no_show "not active" set.
 *
 * Canonical spelling: the no-show status is `no_show` (underscore). The stray
 * `noshow` spelling that appeared in a few reminder queries was a dead value
 * (no row ever stored it) and has been normalised away via terminalValues().
 *
 * The backing values mirror the appointments table enum exactly, so this enum
 * is a definition/helper layer only — the DB column stays a plain string and
 * is NOT cast to this enum (that would break the many string comparisons and
 * whereIn queries across the app). Slice 4 keeps behaviour identical.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case CheckIn   = 'checkin';
    case InChair   = 'in_chair';
    case CheckOut  = 'checkout';
    case Done      = 'done';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';

    /** All raw status values, in canonical order (matches the DB enum). */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /**
     * Terminal statuses that do NOT count as a live/active appointment.
     * This is the canonical "cancelled or no-show" set that ~14 queries used
     * to hard-code (some with a stray 'noshow'); they now call this instead.
     */
    public static function terminalValues(): array
    {
        return [self::Cancelled->value, self::NoShow->value];
    }

    /**
     * In-progress ("open") statuses: booked but not yet closed out
     * (i.e. not checked-out / done / cancelled / no-show).
     */
    public static function inProgressValues(): array
    {
        return [self::Scheduled->value, self::CheckIn->value, self::InChair->value];
    }

    /**
     * Closed-out / completed statuses (done or checked-out) — the canonical
     * set for "completed appointment" counts. Some legacy Huddle queries also
     * listed 'completed', which is treatment-visit vocabulary and never a valid
     * appointment status (it matched no rows); it is normalised away here, the
     * same as the stray 'noshow'.
     */
    public static function completedValues(): array
    {
        return [self::Done->value, self::CheckOut->value];
    }

    /** Laravel validation rule string for a status field (unchanged set/order). */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /** Human label as shown on the calendar. */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::CheckIn   => 'Checked In',
            self::InChair   => 'In Chair',
            self::CheckOut  => 'Checked Out',
            self::Done      => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow    => 'No Show',
        };
    }

    /** Foreground colour (hex) used by the calendar. */
    public function color(): string
    {
        return match ($this) {
            self::Scheduled => '#2563eb',
            self::CheckIn   => '#92400e',
            self::InChair   => '#5b21b6',
            self::CheckOut  => '#14532d',
            self::Done      => '#14532d',
            self::Cancelled => '#991b1b',
            self::NoShow    => '#374151',
        };
    }

    /** Background colour (hex) used by the calendar. */
    public function background(): string
    {
        return match ($this) {
            self::Scheduled => '#eff6ff',
            self::CheckIn   => '#fef3c7',
            self::InChair   => '#ede9fe',
            self::CheckOut  => '#dcfce7',
            self::Done      => '#dcfce7',
            self::Cancelled => '#fee2e2',
            self::NoShow    => '#f1f5f9',
        };
    }

    /**
     * The status → {label,color,bg} map the calendar renders (STATUS_META).
     * The Blade view now injects this instead of hard-coding its own copy.
     */
    public static function calendarMeta(): array
    {
        $meta = [];
        foreach (self::cases() as $case) {
            $meta[$case->value] = [
                'label' => $case->label(),
                'color' => $case->color(),
                'bg'    => $case->background(),
            ];
        }

        return $meta;
    }
}
