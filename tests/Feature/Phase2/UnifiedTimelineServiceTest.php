<?php

namespace Tests\Feature\Phase2;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Relationship;
use App\Services\Relationship\UnifiedTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Sprint 2 (Workstream B) — UnifiedTimelineService.
 *
 * Verifies the ledger + legacy activity logs merge into one stream, newest
 * first, scoped to the relationship, and that an empty relationship is safe.
 */
class UnifiedTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(): Relationship
    {
        return Relationship::create([
            'name'               => 'Timeline Person',
            'phone'              => '9990001111',
            'status'             => 'active',
            'score'              => 0,
            'relationship_since' => now()->toDateString(),
        ]);
    }

    private function leadFor(Relationship $rel): Lead
    {
        return Lead::withoutEvents(function () use ($rel) {
            $lead = new Lead(['name' => 'Timeline Person', 'phone' => '9990001111']);
            $lead->relationship_id = $rel->id;
            $lead->save();
            return $lead;
        });
    }

    public function test_merges_ledger_and_legacy_logs_newest_first(): void
    {
        $rel  = $this->relationship();
        $lead = $this->leadFor($rel);

        // Legacy lead activity — older.
        LeadActivity::create([
            'lead_id'       => $lead->id,
            'type'          => 'call',
            'label'         => 'Call Done',
            'note'          => 'Spoke to patient',
            'activity_date' => today()->subDays(2),
            'by'            => 'Reception',
        ]);

        // New ledger activity — newer.
        Activity::create([
            'relationship_id' => $rel->id,
            'subject_type'    => Lead::class,
            'subject_id'      => $lead->id,
            'event'           => 'lead.created',
            'description'     => 'Lead created',
            'occurred_at'     => now(),
        ]);

        $timeline = app(UnifiedTimelineService::class)->for($rel);

        $this->assertCount(2, $timeline);
        $this->assertSame('activity', $timeline[0]['type']);      // newest = ledger
        $this->assertSame('Lead created', $timeline[0]['title']);
        $this->assertSame('communication', $timeline[1]['type']); // older = legacy lead activity
        $this->assertSame('Call Done', $timeline[1]['title']);
    }

    public function test_empty_relationship_returns_empty_timeline(): void
    {
        $rel = $this->relationship();
        $this->assertTrue(app(UnifiedTimelineService::class)->for($rel)->isEmpty());
    }

    public function test_respects_limit(): void
    {
        $rel = $this->relationship();
        for ($i = 0; $i < 5; $i++) {
            Activity::create([
                'relationship_id' => $rel->id,
                'subject_type'    => Relationship::class,
                'subject_id'      => $rel->id,
                'event'           => 'note.added',
                'description'     => "Entry {$i}",
                'occurred_at'     => now()->subMinutes($i),
            ]);
        }

        $this->assertCount(3, app(UnifiedTimelineService::class)->for($rel, 3));
    }
}
