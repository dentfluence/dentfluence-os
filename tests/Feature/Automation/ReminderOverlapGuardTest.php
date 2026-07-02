<?php

namespace Tests\Feature\Automation;

use App\Services\Relationship\RulesEngine;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 wrap-up — reminder overlap guard.
 *
 * When the Automation Engine owns reminders (automation.engine ON), the RulesEngine
 * must DEFER its reminder-producing rules (e.g. appointment_reminder) so the same
 * patient never gets a reminder from both engines. When OFF (default), every rule
 * fires exactly as before.
 */
class ReminderOverlapGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_appointment_reminder_rule_fires_normally_when_flag_off(): void
    {
        // Default: automation.engine OFF → RulesEngine keeps ownership.
        $this->assertFalse(app(RulesEngine::class)->shouldDeferToAutomation('appointment_reminder'));
    }

    public function test_appointment_reminder_rule_is_deferred_when_automation_on(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        $this->assertTrue(app(RulesEngine::class)->shouldDeferToAutomation('appointment_reminder'));
    }

    public function test_non_reminder_rules_are_never_deferred(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        // A clinical follow-up rule is NOT a reminder — Automation must not swallow it.
        $this->assertFalse(app(RulesEngine::class)->shouldDeferToAutomation('implant_followup'));
        $this->assertFalse(app(RulesEngine::class)->shouldDeferToAutomation('post_treatment_followup'));
    }
}
