<?php

namespace Tests\Feature\Relationship;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Relationship;
use App\Services\Relationship\CommunicationEngine;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 — CommunicationEngine: the single documented send() gateway.
 *
 * Built and tested here in isolation. NOT wired into any existing call site
 * yet (see docs/phase-4/README.md, Piece 4) — these tests prove the engine
 * itself is correct and safe to adopt later, not that anything currently
 * routes through it.
 *
 * Uses WhatsApp dry-run (enabled=true, dry_run=true) so sends never leave
 * the box but still exercise the real OutboundMessageService pipeline
 * (thread resolution, consent gate, audit log).
 */
class CommunicationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
        config(['whatsapp.enabled' => true, 'whatsapp.dry_run' => true]);
    }

    private function relationship(array $overrides = []): Relationship
    {
        return Relationship::create(array_merge([
            'name'  => 'Engine Test Person',
            'phone' => '900' . random_int(1000, 9999),
        ], $overrides));
    }

    public function test_blocked_by_guard_never_reaches_outbound_service(): void
    {
        Feature::set('guard.full_8factor', true);
        $relationship = $this->relationship(['do_not_contact' => true]);

        $result = app(CommunicationEngine::class)->send($relationship, 'appointment_reminder', 'Your appointment is tomorrow.');

        $this->assertFalse($result['ok']);
        $this->assertSame('guard', $result['via']);
        $this->assertSame('do_not_contact', $result['reason']);
    }

    public function test_send_with_no_linked_patient_and_closed_window_is_blocked_by_the_real_consent_gate(): void
    {
        // No Guard flags on — but OutboundMessageService's OWN live consent
        // gate still applies regardless, proving the layering never weakens
        // the existing real protection.
        $relationship = $this->relationship();

        $result = app(CommunicationEngine::class)->send($relationship, 'general', 'Hello there.');

        $this->assertFalse($result['ok']);
        $this->assertSame('whatsapp', $result['via']);
    }

    public function test_send_succeeds_for_a_consented_linked_patient(): void
    {
        $relationship = $this->relationship();

        $patient = Patient::create([
            'name'      => 'Engine Test Patient',
            'phone'     => $relationship->phone,
            'branch_id' => 1,
        ]);
        $patient->relationship_id = $relationship->id;
        $patient->save();

        $purpose = ConsentPurpose::create([
            'key' => 'whatsapp_comms', 'name' => 'WhatsApp messages', 'category' => 'communication',
            'is_mandatory' => false, 'requires_explicit' => true, 'version' => 1, 'active' => true,
        ]);
        PatientConsent::create([
            'patient_id'         => $patient->id,
            'consent_purpose_id' => $purpose->id,
            'status'             => PatientConsent::GRANTED,
            'granted_at'         => now(),
        ]);

        $result = app(CommunicationEngine::class)->send($relationship, 'general', 'Hello there.', [
            'patient_id' => $patient->id,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('whatsapp', $result['via']);
    }

    public function test_unsupported_channel_is_rejected_cleanly(): void
    {
        $relationship = $this->relationship();

        $result = app(CommunicationEngine::class)->send($relationship, 'general', 'Hi', ['channel' => 'sms']);

        $this->assertFalse($result['ok']);
        $this->assertSame('unsupported', $result['via']);
    }
}
