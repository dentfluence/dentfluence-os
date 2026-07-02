<?php

namespace Tests\Feature\Characterization;

use App\Services\Communication\FollowUpRulesService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * CHARACTERIZATION TEST — pins the CURRENT output of the legacy
 * FollowUpRulesService::resolve() so the Slice 6 consolidation (porting it into
 * the Rules Engine) can be proven behaviour-preserving. Describes today's
 * behaviour; a safety net, not a target spec. No DB — resolve() reads config only.
 */
class FollowUpRulesCharacterizationTest extends TestCase
{
    private const BASE = '2026-07-06';

    private function ctx(): array
    {
        return ['base_date' => self::BASE, 'patient_id' => 1, 'lead_id' => null, 'assigned_to' => null];
    }

    public function test_treatment_complete_resolves_six_month_recall(): void
    {
        $defs = (new FollowUpRulesService())
            ->resolve('treatment_status_changed', 'extraction', 'complete', $this->ctx());

        $this->assertCount(1, $defs);

        $recall = $defs[0];
        $this->assertSame('6-Month Recall', $recall['label']);
        $this->assertSame('call', $recall['channel']);
        $this->assertSame('low', $recall['priority']);
        $this->assertSame('treatment_status_changed', $recall['trigger_type']);
        $this->assertSame('extraction', $recall['trigger_value']);
        $this->assertTrue($recall['auto_created']);

        // day_offset 180 (> 30) is a straight calendar add from the base date.
        $this->assertSame(
            Carbon::parse(self::BASE)->addDays(180)->toDateString(),
            $recall['due_date']
        );
    }

    public function test_new_lead_stage_resolves_first_contact_same_day(): void
    {
        $defs = (new FollowUpRulesService())
            ->resolve('prm_stage_changed', 'new_lead', '', $this->ctx());

        $this->assertNotEmpty($defs);
        $this->assertSame('New Lead First Contact', $defs[0]['label']);
        // day_offset 0 → due today (the base date).
        $this->assertSame(self::BASE, $defs[0]['due_date']);
    }

    public function test_unknown_trigger_resolves_to_empty(): void
    {
        $defs = (new FollowUpRulesService())
            ->resolve('nonexistent_trigger', 'whatever', '', $this->ctx());

        $this->assertSame([], $defs);
    }
}
