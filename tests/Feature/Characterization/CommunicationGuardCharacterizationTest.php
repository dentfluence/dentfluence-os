<?php

namespace Tests\Feature\Characterization;

use App\Services\Relationship\CommunicationGuard;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERIZATION TEST — pins the CURRENT behaviour of CommunicationGuard so
 * later phases cannot change it accidentally. These assertions describe how the
 * guard behaves TODAY (Phase 0 defaults: all flags off, fail-open). They are a
 * safety net, not a specification of desired future behaviour.
 *
 * Regression framework note: characterization tests live under
 * tests/Feature/Characterization and are picked up by the existing "Feature"
 * suite. Add one here before refactoring any legacy engagement behaviour.
 */
class CommunicationGuardCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_current_default_allows_contact_with_no_history(): void
    {
        $guard = new CommunicationGuard();

        // With an empty contact log and default flags, contact is allowed.
        $this->assertTrue($guard->canContact(999001, 'whatsapp', 'general'));
    }

    public function test_current_default_fails_open_and_never_throws(): void
    {
        $guard = new CommunicationGuard();

        // The guard must never throw to its caller; worst case it returns a bool.
        $result = $guard->canContact(999002, 'sms', 'appointment_reminder');
        $this->assertIsBool($result);

        // Phase 0 default is fail-open: the structured decision is 'allowed'.
        $decision = $guard->decide(999002, 'sms', 'appointment_reminder');
        $this->assertTrue($decision->allowed());
        $this->assertNull($decision->reason());
    }

    public function test_legacy_signature_still_accepts_three_arguments(): void
    {
        // Backward compatibility: existing callers pass (id, channel, type).
        $guard = new CommunicationGuard();
        $this->assertTrue($guard->canContact(999003, 'email', 'general'));
    }
}
