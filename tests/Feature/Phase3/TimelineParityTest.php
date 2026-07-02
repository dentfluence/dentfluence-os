<?php

namespace Tests\Feature\Phase3;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Relationship;
use App\Services\Relationship\TimelineParityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Sprint 3 — timeline cutover parity.
 *
 * Proves the legacy ProfileController::buildTimeline and UnifiedTimelineService
 * produce the SAME events, so switching the profile's data source behind the
 * flag is invisible.
 */
class TimelineParityTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(): Relationship
    {
        return Relationship::create([
            'name' => 'P', 'phone' => '111', 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    public function test_legacy_and_unified_timelines_are_at_parity(): void
    {
        $rel = $this->relationship();

        $lead = Lead::withoutEvents(function () use ($rel) {
            $l = new Lead(['name' => 'P', 'phone' => '111']);
            $l->relationship_id = $rel->id;
            $l->stage = 'contacted';
            $l->save();
            return $l;
        });

        LeadActivity::create([
            'lead_id' => $lead->id, 'type' => 'call', 'label' => 'Call Done',
            'note' => 'Spoke', 'activity_date' => today()->subDay(), 'by' => 'Reception',
        ]);

        Activity::create([
            'relationship_id' => $rel->id, 'subject_type' => Lead::class, 'subject_id' => $lead->id,
            'event' => 'lead.created', 'description' => 'Lead created', 'occurred_at' => now(),
        ]);
        Activity::create([
            'relationship_id' => $rel->id, 'subject_type' => Lead::class, 'subject_id' => $lead->id,
            'event' => 'call.logged', 'description' => 'Called patient', 'occurred_at' => now()->subHours(2),
        ]);

        $result = app(TimelineParityService::class)->compare($rel->fresh());

        $this->assertTrue(
            $result['match'],
            'Timelines diverged. Missing in unified: ' . json_encode($result['missing_in_unified'])
                . ' | Missing in legacy: ' . json_encode($result['missing_in_legacy'])
        );
        $this->assertSame($result['legacy_count'], $result['unified_count']);
        $this->assertSame(3, $result['unified_count']);
    }

    public function test_parity_holds_for_empty_relationship(): void
    {
        $result = app(TimelineParityService::class)->compare($this->relationship());

        $this->assertTrue($result['match']);
        $this->assertSame(0, $result['legacy_count']);
        $this->assertSame(0, $result['unified_count']);
    }
}
