<?php

namespace Tests\Feature\Phase1;

use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\RelationshipMerge;
use App\Services\Relationship\MergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream A — MergeService (reversible merge + history).
 */
class MergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRelationship(string $name, ?string $phone = null): Relationship
    {
        return Relationship::create([
            'name'               => $name,
            'phone'              => $phone,
            'status'             => 'active',
            'score'              => 0,
            'relationship_since' => now()->toDateString(),
        ]);
    }

    public function test_merge_reassigns_children_and_records_history(): void
    {
        $surviving  = $this->makeRelationship('Survivor', '111');
        $duplicate  = $this->makeRelationship('Duplicate', '222');

        $journey = RelationshipJourney::create([
            'relationship_id' => $duplicate->id,
            'type'            => RelationshipJourney::TYPE_LEAD,
            'state'           => RelationshipJourney::LEAD_NEW_ENQUIRY,
            'started_at'      => now(),
        ]);

        $merge = app(MergeService::class)->merge($surviving, $duplicate);

        // Child moved to survivor.
        $this->assertSame($surviving->id, $journey->fresh()->relationship_id);

        // Duplicate soft-deleted (hidden from default queries, present with trashed).
        $this->assertNull(Relationship::find($duplicate->id));
        $this->assertNotNull(Relationship::withTrashed()->find($duplicate->id));

        // History recorded and reversible.
        $this->assertInstanceOf(RelationshipMerge::class, $merge);
        $this->assertContains($journey->id, $merge->reassignments['relationship_journeys']);
        $this->assertSame($surviving->id, $merge->surviving_relationship_id);
    }

    public function test_merge_can_be_undone(): void
    {
        $surviving = $this->makeRelationship('Survivor', '111');
        $duplicate = $this->makeRelationship('Duplicate', '222');

        $journey = RelationshipJourney::create([
            'relationship_id' => $duplicate->id,
            'type'            => RelationshipJourney::TYPE_LEAD,
            'state'           => RelationshipJourney::LEAD_NEW_ENQUIRY,
            'started_at'      => now(),
        ]);

        $service = app(MergeService::class);
        $merge = $service->merge($surviving, $duplicate);
        $service->undo($merge);

        // Child restored to the (restored) duplicate.
        $this->assertSame($duplicate->id, $journey->fresh()->relationship_id);
        $this->assertNotNull(Relationship::find($duplicate->id)); // restored (not trashed)
        $this->assertNotNull($merge->fresh()->undone_at);
    }

    public function test_cannot_merge_into_self(): void
    {
        $r = $this->makeRelationship('Self', '111');
        $this->expectException(\InvalidArgumentException::class);
        app(MergeService::class)->merge($r, $r);
    }
}
