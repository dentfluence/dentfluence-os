<?php

namespace Tests\Feature\Foundation;

use App\Support\Features\Feature;
use App\Support\Features\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0 — Feature-flag framework.
 *
 * Verifies: defaults are OFF, unknown flags are safe, global + per-clinic
 * overrides work, per-clinic wins over global, and removal restores default.
 */
class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_declared_flag_defaults_to_off(): void
    {
        $this->assertFalse(Feature::enabled('guard.fail_closed'));
    }

    public function test_unknown_flag_is_false_and_never_throws(): void
    {
        $this->assertFalse(Feature::enabled('this.flag.does.not.exist'));
    }

    public function test_config_default_is_honoured(): void
    {
        // Flag keys contain dots, so replace the whole flags map to set the
        // flat key's default (config()->set() would treat the dots as nesting).
        config()->set('features.flags', array_merge((array) config('features.flags'), [
            'guard.consent_required' => ['default' => true, 'description' => 'test'],
        ]));
        Feature::flushCache();

        $this->assertTrue(Feature::enabled('guard.consent_required'));
    }

    public function test_global_override_wins_over_config_default(): void
    {
        Feature::set('guard.fail_closed', true); // global override
        $this->assertTrue(Feature::enabled('guard.fail_closed'));

        Feature::set('guard.fail_closed', null); // remove → back to default
        $this->assertFalse(Feature::enabled('guard.fail_closed'));
    }

    public function test_per_clinic_override_wins_over_global(): void
    {
        Feature::set('today.projection', false);            // global off
        Feature::set('today.projection', true, branchId: 7); // clinic 7 on

        $this->assertFalse(Feature::enabled('today.projection'));          // global
        $this->assertTrue(Feature::for(7)->enabled('today.projection'));   // clinic 7
        $this->assertFalse(Feature::for(9)->enabled('today.projection'));  // other clinic falls back to global
    }

    public function test_all_returns_declared_flags_with_resolved_state(): void
    {
        $all = app(FeatureFlagService::class)->all();

        $this->assertArrayHasKey('guard.fail_closed', $all);
        $this->assertArrayHasKey('resolved', $all['guard.fail_closed']);
        $this->assertFalse($all['guard.fail_closed']['resolved']);
    }
}
