<?php

namespace Tests\Feature\Relationship;

use App\Models\Relationship;
use App\Services\Relationship\CommunicationGuard;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — the 3 previously missing/partial Guard factors: Preference,
 * Context, Channel eligibility. All gated behind guard.full_8factor
 * (default off) — with the flag off, decide() must behave exactly as before
 * (proven by the existing Phase 0 characterization tests, untouched).
 */
class GuardEightFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    private function relationship(array $overrides = []): Relationship
    {
        return Relationship::create(array_merge([
            'name'  => 'Guard Factor Test',
            'phone' => '900' . random_int(1000, 9999),
        ], $overrides));
    }

    public function test_flag_off_ignores_do_not_contact(): void
    {
        $relationship = $this->relationship(['do_not_contact' => true]);
        $guard        = new CommunicationGuard();

        // Flag is off by default — do_not_contact must not be consulted at all.
        $this->assertTrue($guard->canContact($relationship->id, 'whatsapp', 'general'));
    }

    public function test_flag_on_blocks_do_not_contact_even_when_urgent(): void
    {
        Feature::set('guard.full_8factor', true);
        $relationship = $this->relationship(['do_not_contact' => true]);
        $guard        = new CommunicationGuard();

        $decision = $guard->decide($relationship->id, 'whatsapp', 'appointment_reminder', isUrgent: true);

        $this->assertFalse($decision->allowed());
        $this->assertSame('do_not_contact', $decision->reason());
    }

    public function test_flag_on_blocks_channel_with_no_contact_detail(): void
    {
        Feature::set('guard.full_8factor', true);
        // A relationship created with only a phone — no email on file.
        $relationship = $this->relationship();
        $guard        = new CommunicationGuard();

        $emailDecision = $guard->decide($relationship->id, 'email', 'general');
        $this->assertFalse($emailDecision->allowed());
        $this->assertSame('channel_ineligible', $emailDecision->reason());

        $whatsappDecision = $guard->decide($relationship->id, 'whatsapp', 'general');
        $this->assertTrue($whatsappDecision->allowed()); // has a phone
    }

    public function test_flag_on_logs_preference_but_never_blocks_on_it(): void
    {
        Feature::set('guard.full_8factor', true);
        $relationship = $this->relationship(['preferred_channel' => 'email']);
        $guard        = new CommunicationGuard();

        // Sending on whatsapp (NOT the preferred channel) must still be allowed —
        // Preference is informational only.
        $decision = $guard->decide($relationship->id, 'whatsapp', 'general');

        $this->assertTrue($decision->allowed());
        $this->assertSame('email', $decision->factors['preferred_channel'] ?? null);
    }

    public function test_flag_on_context_is_a_pass_through_seam(): void
    {
        Feature::set('guard.full_8factor', true);
        $relationship = $this->relationship();
        $guard        = new CommunicationGuard();

        $decision = $guard->decide($relationship->id, 'whatsapp', 'general');

        $this->assertTrue($decision->allowed());
        $this->assertSame('not_evaluated', $decision->factors['context'] ?? null);
    }
}
