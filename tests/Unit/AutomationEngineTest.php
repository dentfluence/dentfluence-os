<?php

namespace Tests\Unit;

use App\Services\Automation\AutomationEngine;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the Phase 2 Automation Engine skeleton (Slice 2).
 *
 * Covers the four temporal primitives (schedule / retry / cooldown / expire) as
 * pure functions, plus the feature-flag gate. RefreshDatabase is used only so
 * the flag resolves to its config default (OFF) with no stray flag rows.
 */
class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    private AutomationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
        $this->engine = new AutomationEngine();
    }

    // ── Flag gate ────────────────────────────────────────────────────────────

    public function test_engine_is_disabled_by_default(): void
    {
        $this->assertFalse(
            $this->engine->enabled(),
            'automation.engine must default OFF so the skeleton stays dormant.'
        );
    }

    public function test_engine_reports_enabled_once_flag_is_set(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        $this->assertTrue($this->engine->enabled());
    }

    // ── Primitive 1: schedule ────────────────────────────────────────────────

    public function test_due_now_is_true_when_scheduled_time_has_passed(): void
    {
        $now = Carbon::parse('2026-07-02 12:00:00');

        $this->assertTrue($this->engine->dueNow($now->copy()->subMinute(), $now));
        $this->assertTrue($this->engine->dueNow($now->copy(), $now), 'Exactly-now counts as due.');
        $this->assertFalse($this->engine->dueNow($now->copy()->addMinute(), $now));
    }

    // ── Primitive 2: retry ───────────────────────────────────────────────────

    public function test_should_retry_respects_max_attempts(): void
    {
        $this->assertTrue($this->engine->shouldRetry(0, 3));
        $this->assertTrue($this->engine->shouldRetry(2, 3));
        $this->assertFalse($this->engine->shouldRetry(3, 3), 'No retry once attempts reach the max.');
        $this->assertFalse($this->engine->shouldRetry(4, 3));
    }

    public function test_next_retry_uses_exponential_backoff_from_base(): void
    {
        $from = Carbon::parse('2026-07-02 12:00:00');

        // Base = 5 min. Attempt 1 → +5, attempt 2 → +10, attempt 3 → +20.
        $this->assertSame('2026-07-02 12:05:00', $this->engine->nextRetryAt(1, $from)->toDateTimeString());
        $this->assertSame('2026-07-02 12:10:00', $this->engine->nextRetryAt(2, $from)->toDateTimeString());
        $this->assertSame('2026-07-02 12:20:00', $this->engine->nextRetryAt(3, $from)->toDateTimeString());
    }

    public function test_next_retry_is_capped_at_24_hours(): void
    {
        $from = Carbon::parse('2026-07-02 12:00:00');

        // A very high attempt count must clamp to the 24h ceiling, not overflow.
        $this->assertSame('2026-07-03 12:00:00', $this->engine->nextRetryAt(99, $from)->toDateTimeString());
    }

    // ── Primitive 3: cooldown ────────────────────────────────────────────────

    public function test_cooldown_is_false_when_never_fired_or_zero_window(): void
    {
        $now = Carbon::parse('2026-07-02 12:00:00');

        $this->assertFalse($this->engine->inCooldown(null, 30, $now), 'Never fired = not in cooldown.');
        $this->assertFalse(
            $this->engine->inCooldown($now->copy()->subDay(), 0, $now),
            'A zero-day window means no cooldown.'
        );
    }

    public function test_cooldown_true_inside_window_false_after(): void
    {
        $now = Carbon::parse('2026-07-02 12:00:00');

        // Fired 10 days ago, 30-day cooldown → still blocked.
        $this->assertTrue($this->engine->inCooldown($now->copy()->subDays(10), 30, $now));

        // Fired 31 days ago, 30-day cooldown → clear to fire again.
        $this->assertFalse($this->engine->inCooldown($now->copy()->subDays(31), 30, $now));
    }

    // ── Primitive 4: expire ──────────────────────────────────────────────────

    public function test_expiry_false_when_no_start_or_zero_window(): void
    {
        $now = Carbon::parse('2026-07-02 12:00:00');

        $this->assertFalse($this->engine->isExpired(null, 14, $now));
        $this->assertFalse($this->engine->isExpired($now->copy()->subDays(100), 0, $now));
    }

    public function test_expiry_true_once_window_has_passed(): void
    {
        $now = Carbon::parse('2026-07-02 12:00:00');

        // Offer opened 7 days ago with a 14-day window → still valid.
        $this->assertFalse($this->engine->isExpired($now->copy()->subDays(7), 14, $now));

        // Opened 14 days ago with a 14-day window → lapsed (boundary is expired).
        $this->assertTrue($this->engine->isExpired($now->copy()->subDays(14), 14, $now));
    }
}
