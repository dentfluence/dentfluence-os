<?php

namespace App\Services\Automation;

use App\Support\Features\Feature;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * AutomationEngine — Phase 2, the execution layer for time-based & deferred work.
 * (Target architecture Rev 3, §B2 "Automation Engine".)
 *
 * WHAT IT OWNS (and nothing else): the *timing* mechanics behind automation —
 *   • schedule   → "is this timer due to run now?"
 *   • retry      → "should a failed action be re-attempted, and when?"
 *   • cooldown   → "did we already act on this too recently?"
 *   • expire     → "has this offer/window lapsed?"
 *
 * WHAT IT NEVER DOES: decide policy (that is the Rules Engine), send messages
 * (Communication), or create tasks directly (Task Engine). It answers timing
 * questions and, in later slices, drives deferred steps to completion by asking
 * those engines to act.
 *
 * SLICE 2 SCOPE (this file): the four primitives as PURE, side-effect-free
 * functions plus the feature-flag gate. No callers are switched to it yet and
 * the `automation.engine` flag stays OFF, so this class is dormant scaffolding —
 * it changes no production behaviour. Slices 3+ wire it to the recall shadow-run,
 * a schedule store, and the Decision Log.
 *
 * All primitives accept an explicit `$now` so tests are deterministic.
 */
class AutomationEngine
{
    /** Base wait (minutes) for the first retry. Doubles each attempt (exponential backoff). */
    private const RETRY_BASE_MINUTES = 5;

    /** Upper bound (minutes) on any single backoff wait, so it can't grow unbounded. */
    private const RETRY_MAX_MINUTES = 24 * 60; // 24 hours

    /** Default number of attempts before an action is given up on. */
    private const DEFAULT_MAX_ATTEMPTS = 5;

    // ─────────────────────────────────────────────────────────────────────────
    // Flag gate
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Is the Automation Engine active? Everything that would change production
     * behaviour must be guarded by this. Default OFF (see config/features.php).
     * Optionally scoped per branch so we can cut over one clinic at a time.
     */
    public function enabled(?int $branchId = null): bool
    {
        return Feature::enabled('automation.engine', $branchId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Primitive 1 — SCHEDULE ("is this timer due now?")
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * True once the scheduled moment has arrived (or passed).
     *
     * @param  CarbonInterface  $scheduledFor  When the timer should fire.
     * @param  Carbon|null      $now           Defaults to the current time.
     */
    public function dueNow(CarbonInterface $scheduledFor, ?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();

        return $scheduledFor->lessThanOrEqualTo($now);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Primitive 2 — RETRY ("should we re-attempt, and when?")
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Whether a failed action still has attempts left.
     *
     * @param  int  $attemptsMade  How many attempts have already happened.
     * @param  int  $maxAttempts   Ceiling (defaults to DEFAULT_MAX_ATTEMPTS).
     */
    public function shouldRetry(int $attemptsMade, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        return $attemptsMade < max(0, $maxAttempts);
    }

    /**
     * When the next retry should happen, using exponential backoff capped at
     * RETRY_MAX_MINUTES. Attempt 1 → base, attempt 2 → 2×base, attempt 3 → 4×base…
     *
     * @param  int          $attemptsMade  Attempts already made (>= 0). 0 is treated as 1.
     * @param  Carbon|null  $from          Base time to offset from (defaults to now).
     */
    public function nextRetryAt(int $attemptsMade, ?Carbon $from = null): Carbon
    {
        $from = $from ? $from->copy() : Carbon::now();

        // Guard against negatives; the first retry uses the base wait.
        $exponent = max(0, $attemptsMade - 1);

        // Cap the exponent so 2**exponent can't overflow before we clamp minutes.
        $exponent = min($exponent, 20);

        $waitMinutes = self::RETRY_BASE_MINUTES * (2 ** $exponent);
        $waitMinutes = (int) min($waitMinutes, self::RETRY_MAX_MINUTES);

        return $from->addMinutes($waitMinutes);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Primitive 3 — COOLDOWN ("did we already act too recently?")
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * True if an action fired within the cooldown window and must be suppressed.
     *
     * Mirrors the semantics already used by RulesEngine::checkCooldown() so the
     * shadow-run can prove parity: cooldown of 0 (or never fired) = not blocked.
     *
     * @param  CarbonInterface|null  $lastFiredAt   When the action last fired (null = never).
     * @param  int                   $cooldownDays  Window length in days (<= 0 = no cooldown).
     * @param  Carbon|null           $now           Defaults to the current time.
     */
    public function inCooldown(?CarbonInterface $lastFiredAt, int $cooldownDays, ?Carbon $now = null): bool
    {
        if ($lastFiredAt === null || $cooldownDays <= 0) {
            return false;
        }

        $now    = $now ?? Carbon::now();
        $expiry = $lastFiredAt->copy()->addDays($cooldownDays);

        // Still inside the window (boundary exactly at expiry counts as expired).
        return $now->lessThan($expiry);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Primitive 4 — EXPIRE ("has this offer/window lapsed?")
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * True once a time-limited offer/window has lapsed.
     *
     * @param  CarbonInterface|null  $startedAt   When the window opened (null = nothing to expire).
     * @param  int                   $windowDays  How many days the window stays open (<= 0 = never expires).
     * @param  Carbon|null           $now         Defaults to the current time.
     */
    public function isExpired(?CarbonInterface $startedAt, int $windowDays, ?Carbon $now = null): bool
    {
        if ($startedAt === null || $windowDays <= 0) {
            return false;
        }

        $now    = $now ?? Carbon::now();
        $expiry = $startedAt->copy()->addDays($windowDays);

        return $now->greaterThanOrEqualTo($expiry);
    }
}
