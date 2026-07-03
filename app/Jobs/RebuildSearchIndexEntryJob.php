<?php

namespace App\Jobs;

use App\Services\Search\SearchIndexProjector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RebuildSearchIndexEntryJob — Phase 6 · Slice 3 (Search Engine).
 *
 * Dispatched (async, queued) by RelationshipSearchIndexObserver when a
 * relationship is saved and `search.index` is on. Mirrors the shape of
 * RecalculateInsightSignalsJob / RecalculateRelationshipScoreJob.
 */
class RebuildSearchIndexEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly int $relationshipId,
    ) {}

    public function handle(SearchIndexProjector $projector): void
    {
        $result = $projector->rebuildFor($this->relationshipId);

        if (! $result['found']) {
            Log::warning("RebuildSearchIndexEntryJob: relationship [{$this->relationshipId}] not found — skipping.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RebuildSearchIndexEntryJob failed for relationship [{$this->relationshipId}]", [
            'error' => $exception->getMessage(),
        ]);
    }
}
