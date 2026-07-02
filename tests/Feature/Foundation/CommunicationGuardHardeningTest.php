<?php

namespace Tests\Feature\Foundation;

use App\Services\Relationship\CommunicationGuard;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0 — Communication Guard hardening (foundation).
 *
 * Verifies the invariants without touching real DB history by overriding the
 * check seams: default behaviour unchanged, fail-closed only when flagged, and
 * — critically — CONSENT IS NEVER OVERRIDDEN BY URGENCY.
 */
class CommunicationGuardHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_default_path_allows_when_no_history(): void
    {
        $guard = new HardeningTestGuard();

        // All flags default off, not urgent, no blocking conditions → allowed.
        $this->assertTrue($guard->canContact(1, 'whatsapp', 'general'));
    }

    public function test_fail_closed_only_blocks_on_error_when_flag_is_on(): void
    {
        $guard = new HardeningTestGuard();
        $guard->throwInCheck = true;

        // Flag OFF → fails open (current behaviour).
        $this->assertTrue($guard->decide(1, 'whatsapp')->allowed());

        // Flag ON → fails closed. (Use the override API — flag keys contain dots
        // so config()->set() cannot target them.)
        Feature::set('guard.fail_closed', true);

        $decision = $guard->decide(1, 'whatsapp');
        $this->assertFalse($decision->allowed());
        $this->assertSame('guard_error_fail_closed', $decision->reason());
    }

    public function test_urgency_relaxes_frequency(): void
    {
        $guard = new HardeningTestGuard();
        $guard->totalExceeded = true; // frequency cap hit

        // Non-urgent → blocked by frequency.
        $this->assertFalse($guard->canContact(1, 'whatsapp', 'appointment_reminder', isUrgent: false));

        // Urgent → frequency relaxed → allowed.
        $this->assertTrue($guard->canContact(1, 'whatsapp', 'appointment_reminder', isUrgent: true));
    }

    public function test_consent_is_never_overridden_by_urgency(): void
    {
        Feature::set('guard.consent_required', true); // override API (dotted key)

        $guard = new HardeningTestGuard();
        $guard->consent = false; // patient has NOT consented

        // Even urgent, even with frequency ok — consent blocks. This is the invariant.
        $decision = $guard->decide(1, 'whatsapp', 'appointment_reminder', isUrgent: true);

        $this->assertFalse($decision->allowed());
        $this->assertSame('consent', $decision->reason());
    }

    public function test_consent_gate_is_dormant_by_default(): void
    {
        $guard = new HardeningTestGuard();
        $guard->consent = false; // no consent...

        // ...but the flag is OFF by default, so consent is not enforced → allowed.
        $this->assertTrue($guard->canContact(1, 'whatsapp', 'general'));
    }
}

/**
 * Test double: overrides the Guard's check seams so we exercise decide()'s
 * orchestration (ordering + urgency + consent invariant) deterministically,
 * without seeding relationship_contact_log rows.
 */
class HardeningTestGuard extends CommunicationGuard
{
    public bool $consent = true;
    public bool $sameChannel = false;
    public bool $totalExceeded = false;
    public bool $quiet = false;
    public bool $birthday = false;
    public bool $throwInCheck = false;

    protected function patientHasConsent(int $relationshipId, string $channel, string $type): bool
    {
        return $this->consent;
    }

    protected function isSameChannelBlocked(int $relationshipId, string $channel, array $config): bool
    {
        if ($this->throwInCheck) {
            throw new \RuntimeException('boom');
        }
        return $this->sameChannel;
    }

    protected function isTotalContactsExceeded(int $relationshipId, array $config): bool
    {
        return $this->totalExceeded;
    }

    protected function isQuietHoursBlocked(array $config): bool
    {
        return $this->quiet;
    }

    protected function isBirthdayBlockActive(int $relationshipId, string $type, array $config): bool
    {
        return $this->birthday;
    }
}
