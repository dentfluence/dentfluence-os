<?php

namespace Tests\Feature\Search;

use App\Models\Relationship;
use App\Services\Search\SearchIndexProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 · Slice 3 — Search Engine index projection.
 *
 * Mirrors ProfileController::search()'s exact matching (name/phone/email,
 * ordered by score) — these tests prove the projector's own behaviour
 * (rebuild/idempotency/query shape/parity), not re-testing the match logic.
 */
class SearchIndexProjectorTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(array $overrides = []): Relationship
    {
        return Relationship::create(array_merge([
            'name' => 'Search Test Person', 'phone' => '9' . random_int(100000000, 999999999),
            'email' => 'search' . random_int(1000, 9999) . '@example.com',
            'status' => 'active', 'score' => 42, 'relationship_since' => now()->toDateString(),
        ], $overrides));
    }

    public function test_rebuild_all_indexes_every_relationship(): void
    {
        $a = $this->relationship(['name' => 'Alpha Patient']);
        $b = $this->relationship(['name' => 'Beta Patient']);

        $result = app(SearchIndexProjector::class)->rebuildAll();

        $this->assertGreaterThanOrEqual(2, $result['relationships']);
        $this->assertDatabaseHas('search_index', ['relationship_id' => $a->id, 'name' => 'Alpha Patient']);
        $this->assertDatabaseHas('search_index', ['relationship_id' => $b->id, 'name' => 'Beta Patient']);
    }

    public function test_rebuild_is_idempotent_no_duplicate_rows(): void
    {
        $rel = $this->relationship();

        $projector = app(SearchIndexProjector::class);
        $projector->rebuildFor($rel->id);
        $projector->rebuildFor($rel->id);

        $this->assertSame(1, DB::table('search_index')->where('relationship_id', $rel->id)->count());
    }

    public function test_query_matches_by_name_phone_or_email_ordered_by_score(): void
    {
        $low  = $this->relationship(['name' => 'Findable Low Score', 'score' => 10]);
        $high = $this->relationship(['name' => 'Findable High Score', 'score' => 90]);

        $projector = app(SearchIndexProjector::class);
        $projector->rebuildFor($low->id);
        $projector->rebuildFor($high->id);

        $results = $projector->query('Findable');

        $this->assertCount(2, $results);
        $this->assertSame($high->id, $results[0]['id']); // higher score first
        $this->assertSame($low->id, $results[1]['id']);
    }

    public function test_query_returns_nothing_for_short_terms(): void
    {
        $this->relationship(['name' => 'Anyone']);
        app(SearchIndexProjector::class)->rebuildAll();

        $this->assertSame([], app(SearchIndexProjector::class)->query('an'));
    }

    public function test_parity_matches_immediately_after_rebuild(): void
    {
        $rel = $this->relationship();

        $projector = app(SearchIndexProjector::class);
        $projector->rebuildFor($rel->id);

        $parity = $projector->parity(onlyRelationshipId: $rel->id);

        $this->assertTrue($parity['match'], json_encode($parity['diffs']));
    }

    public function test_parity_detects_drift_when_relationship_changes_without_rebuild(): void
    {
        $rel = $this->relationship(['name' => 'Original Name']);

        $projector = app(SearchIndexProjector::class);
        $projector->rebuildFor($rel->id);

        $rel->update(['name' => 'Changed Name']); // data changes; we do NOT rebuild the index

        $parity = $projector->parity(onlyRelationshipId: $rel->id);

        $this->assertFalse($parity['match']);
        $this->assertArrayHasKey($rel->id, $parity['diffs']);
    }
}
