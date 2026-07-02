<?php

namespace Tests\Feature\Automation;

use App\Services\Communication\FollowUpRulesService;
use App\Services\Relationship\FollowUpRuleEngine;
use Tests\TestCase;

/**
 * Phase 2, Slice 6 — proves the Rules-Engine-owned FollowUpRuleEngine reproduces
 * the legacy FollowUpRulesService EXACTLY for every configured trigger. This
 * equivalence is what makes flipping rules.single_engine safe: both flag branches
 * resolve to byte-identical follow-up definitions. No DB — pure config resolution.
 */
class FollowUpRuleEnginePortTest extends TestCase
{
    private const CTX = ['base_date' => '2026-07-06', 'patient_id' => 1, 'lead_id' => null, 'assigned_to' => null];

    public function test_ported_engine_matches_legacy_for_every_trigger(): void
    {
        $legacy = new FollowUpRulesService();
        $ported = new FollowUpRuleEngine();

        $checked = 0;

        foreach (config('followup_rules', []) as $triggerType => $section) {
            foreach ($section as $value => $inner) {
                // treatment_status_changed nests one level deeper: [treatment][status].
                $subValues = ($triggerType === 'treatment_status_changed') ? array_keys($inner) : [''];

                foreach ($subValues as $sub) {
                    $a = $legacy->resolve($triggerType, (string) $value, (string) $sub, self::CTX);
                    $b = $ported->resolve($triggerType, (string) $value, (string) $sub, self::CTX);

                    $this->assertSame(
                        $a,
                        $b,
                        "Ported engine diverged from legacy for {$triggerType}/{$value}/{$sub}"
                    );
                    $checked++;
                }
            }
        }

        // Sanity: we actually exercised a meaningful number of trigger combinations.
        $this->assertGreaterThan(5, $checked, 'Expected several trigger combinations to be compared.');
    }

    public function test_ported_engine_returns_empty_for_unknown_trigger(): void
    {
        $this->assertSame(
            [],
            (new FollowUpRuleEngine())->resolve('nonexistent_trigger', 'whatever', '', self::CTX)
        );
    }
}
