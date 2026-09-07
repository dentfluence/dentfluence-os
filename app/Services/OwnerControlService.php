<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * OwnerControlService — the owner's switches over what staff may change.
 *
 * ONE place answers "is this allowed?". Controllers and services ASK; they never
 * re-read the settings themselves and never re-implement the rule, because a
 * control that is enforced in four places is a control with four bugs.
 *
 * Three controls, all off by default so installing this changes nothing until
 * the owner turns one on:
 *
 *   1. BACK-DATING     how many days back staff may date an entry.
 *                      null = unlimited (today's behaviour), 0 = today only.
 *   2. AMOUNT LOCK     once a money row is saved, its amount cannot be edited —
 *                      it must be voided and re-entered, leaving a trail.
 *   3. VERIFIED LOCK   a verified visit cannot be edited or deleted.
 *
 * ADMINS BYPASS ALL THREE. These are controls over staff, not over the owner —
 * an owner locked out of correcting a genuine mistake will simply turn the whole
 * feature off, and then it protects nothing.
 *
 * Read once per request: the settings table is hit at most one time however many
 * rows a save touches.
 */
class OwnerControlService
{
    public const GROUP = 'controls';

    public const KEY_BACKDATE_DAYS   = 'control_backdate_days';
    public const KEY_LOCK_AMOUNT     = 'control_lock_amount_after_save';
    public const KEY_LOCK_VERIFIED   = 'control_lock_verified_visit';
    public const KEY_VERIFICATION_ON = 'control_visit_verification_on';

    /** @var array<string,mixed>|null */
    private ?array $memo = null;

    /** @return array<string,mixed> */
    private function all(): array
    {
        return $this->memo ??= AppSetting::group(self::GROUP);
    }

    /** Test seam + a way to pick up a save inside the same request. */
    public function forget(): void
    {
        $this->memo = null;
    }

    // ── Who is exempt ────────────────────────────────────────────────────────

    /**
     * Admins are never blocked. Resolved by ROLE, never by name — the owner at
     * Tulip is "Dr. Firke" and any name-based check would scope him wrongly
     * (the same trap User::resolveAppointmentScope() documents).
     */
    public function bypasses(?User $user = null): bool
    {
        $user ??= Auth::user();

        return (bool) $user?->isAdminRole();
    }

    // ── 1. Back-dating ───────────────────────────────────────────────────────

    /** Days back staff may date an entry. null = unlimited. */
    public function backdateLimitDays(): ?int
    {
        $raw = $this->all()[self::KEY_BACKDATE_DAYS] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return max(0, (int) $raw);
    }

    /** Earliest date staff may use, or null when unlimited. */
    public function earliestAllowedDate(): ?Carbon
    {
        $days = $this->backdateLimitDays();

        return $days === null ? null : Carbon::today()->subDays($days);
    }

    /**
     * Refuse a date that is further back than the owner allows, or in the
     * future. Throws a normal ValidationException so the message lands on the
     * field the user is looking at rather than as a 500 or a silent no-op.
     *
     * Future dates are refused for every role INCLUDING admins: a visit dated
     * tomorrow is not a correction, it is a record of work that has not
     * happened. Scheduling lives on the appointment, not the visit.
     */
    public function assertDateAllowed(mixed $date, string $field = 'visit_date', ?User $user = null): void
    {
        if (empty($date)) {
            return;
        }

        $given = Carbon::parse($date)->startOfDay();

        if ($given->isFuture()) {
            throw ValidationException::withMessages([
                $field => 'This date is in the future. Record the visit on the day it happened.',
            ]);
        }

        if ($this->bypasses($user)) {
            return;
        }

        $earliest = $this->earliestAllowedDate();

        if ($earliest !== null && $given->lt($earliest)) {
            $days = $this->backdateLimitDays();

            throw ValidationException::withMessages([
                $field => $days === 0
                    ? 'Entries must be dated today. Ask an admin to record an earlier date.'
                    : "Entries can be dated at most {$days} day(s) back. Ask an admin to record an earlier date.",
            ]);
        }
    }

    // ── 2. Amount lock ───────────────────────────────────────────────────────

    /**
     * True when a saved amount may no longer be edited in place.
     * Slice 2 wires this into the invoice / payment / expense write paths;
     * it is defined here now so there is only ever one definition of it.
     */
    public function amountLocked(?User $user = null): bool
    {
        return $this->flag(self::KEY_LOCK_AMOUNT) && ! $this->bypasses($user);
    }

    // ── 3. Verified-visit lock ───────────────────────────────────────────────

    /** Is the verify / unverify workflow switched on at all? */
    public function verificationEnabled(): bool
    {
        return $this->flag(self::KEY_VERIFICATION_ON);
    }

    /** True when a verified visit is closed to further editing. */
    public function verifiedVisitsLocked(?User $user = null): bool
    {
        return $this->flag(self::KEY_LOCK_VERIFIED) && ! $this->bypasses($user);
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function flag(string $key): bool
    {
        return (string) ($this->all()[$key] ?? '0') === '1';
    }
}
