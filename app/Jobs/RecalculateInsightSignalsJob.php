<?php

namespace App\Jobs;

use App\Services\Insights\InsightsProjector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RecalculateInsightSignalsJob — Phase 6 · Slice 1 (Insights Engine).
 *
 * Dispatched (async, queued) by RecalculateInsightSignalsListener when an
 * insight-relevant ActivityRecorded event fires for a relationship. Mirrors
 * RecalculateRelationshipScoreJob's shape exactly — same queue discipline,
 * same "never break the caller" failure handling.
 */
class RecalculateInsightSignalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly int $relationshipId,
    ) {}

    public function handle(InsightsProjector $projector): void
    {
        $result = $projector->rebuildFor($this->relationshipId);

        if (! $result['found']) {
            Log::warning("RecalculateInsightSignalsJob: relationship [{$this->relationshipId}] not found — skipping.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RecalculateInsightSignalsJob failed for relationship [{$this->relationshipId}]", [
            'error' => $exception->getMessage(),
        ]);
    }
}
