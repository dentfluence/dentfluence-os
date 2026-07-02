<?php

namespace Tests\Feature\Characterization;

use App\Models\Relationship;
use App\Services\Relationship\RulesEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CHARACTERIZATION TEST — pins the CURRENT behaviour of RulesEngine (the reactive
 * decision layer) so Phase 2 cannot change it by accident. Describes how rule
 * matching and cooldown behave TODAY, driven by config/relationship_rules.php.
 * Safety net, not a target spec.
 *
 * See docs/phase-2/automation-inventory.md §4.
 */
class RulesEngineCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): RulesEngine
    {
        return app(RulesEngine::class);
    }

    /** getRulesForEvent returns the enabled rules whose trigger matches the event. */
    public function test_get_rules_for_event_returns_enabled_matching_rules(): void
    {
        $rules = $this->engine()->getRulesForEvent('treatment.completed');

        // Both treatment.completed rules ship enabled in config/relationship_rules.php.
        $this->assertTrue($rules->has('implant_followup'));
        $this->assertTrue($rules->has('post_treatment_followup'));

        // Every returned rule actually matches the requested trigger.
        foreach ($rules as $rule) {
            $this->assertSame('treatment.completed', $rule['trigger']);
        }
    }

    /** An unknown event matches no rules. */
    public function test_get_rules_for_event_is_empty_for_unknown_event(): void
    {
        $this->assertTrue(
            $this->engine()->getRulesForEvent('nonexistent.event')->isEmpty()
        );
    }

    /** With no prior firing, cooldown allows the rule to fire. */
    public function test_checkcooldown_is_true_when_rule_never_fired(): void
    {
        $this->assertTrue(
            $this->engine()->checkCooldown('implant_followup', 987654),
            'A rule that never fired for this relationship should be clear to fire.'
        );
    }

    /** After a recent firing, cooldown blocks the rule (implant_followup = 90 days). */
    public function test_checkcooldown_is_false_after_a_recent_firing(): void
    {
        // relationship_rule_logs.relationship_id is FK-constrained to relationships,
        // so we need a real relationship row to log a firing against.
        $relationshipId = Relationship::create([
            'name'               => 'Cooldown Person',
            'status'             => 'active',
            'score'              => 0,
            'relationship_since' => now()->toDateString(),
        ])->id;

        // Mirror exactly what RulesEngine::logRuleFired() writes.
        DB::table('relationship_rule_logs')->insert([
            'rule_name'       => 'implant_followup',
            'relationship_id' => $relationshipId,
            'subject_type'    => 'App\\Models\\Patient',
            'subject_id'      => 1,
            'fired_at'        => now(),
            'metadata'        => json_encode(['test' => true]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->assertFalse(
            $this->engine()->checkCooldown('implant_followup', $relationshipId),
            'A rule fired just now should be blocked by its cooldown window.'
        );
    }
}
