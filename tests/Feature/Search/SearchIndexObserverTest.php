<?php

namespace Tests\Feature\Search;

use App\Jobs\RebuildSearchIndexEntryJob;
use App\Models\Relationship;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 6 · Slice 3 — Search Engine, incremental refresh path.
 *
 * RelationshipSearchIndexObserver is registered app-wide (via
 * SearchServiceProvider) on every Relationship save. These tests prove the
 * `search.index` flag genuinely gates behaviour: while off (the default),
 * saving a relationship dispatches nothing — zero behaviour change until the
 * flag is flipped.
 */
class SearchIndexObserverTest extends TestCase
{
    use RefreshDatabase;

    private function relationship(array $overrides = []): Relationship
    {
        return Relationship::create(array_merge([
            'name' => 'Observer Test Person', 'phone' => '9' . random_int(100000000, 999999999),
            'status' => 'active', 'score' => 0, 'relationship_since' => now()->toDateString(),
        ], $overrides));
    }

    public function test_flag_off_by_default_dispatches_nothing_on_save(): void
    {
        Queue::fake();

        $this->relationship();

        Queue::assertNotPushed(RebuildSearchIndexEntryJob::class);
    }

    public function test_flag_on_dispatches_a_reindex_job_for_the_saved_relationship(): void
    {
        Feature::set('search.index', true);
        Queue::fake();

        $rel = $this->relationship();

        Queue::assertPushed(RebuildSearchIndexEntryJob::class, function ($job) use ($rel) {
            return $job->relationshipId === $rel->id;
        });
    }

    public function test_flag_on_dispatches_again_on_update(): void
    {
        Feature::set('search.index', true);
        $rel = $this->relationship();

        Queue::fake();
        $rel->update(['name' => 'Updated Name']);

        Queue::assertPushed(RebuildSearchIndexEntryJob::class, function ($job) use ($rel) {
            return $job->relationshipId === $rel->id;
        });
    }
}
