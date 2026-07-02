<?php

namespace App\Jobs;

use App\Models\Relationship;
use App\Services\Relationship\RelationshipScoreEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * RecalculateRelationshipScoreJob — Phase 6, Relationship Engine
 *
 * Dispatched (async, queued) by ActivityEngine when a score-relevant event
 * fires. Calculates the relationship health score and saves it to
 * relationships.score.
 *
 * Dispatched from ActivityEngine::log() inside DB::afterCommit() to ensure
 * the triggering data is fully committed before the score recalculates.
 */
class RecalculateRelationshipScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly int $relationshipId,
    ) {}

    public function handle(RelationshipScoreEngine $scoreEngine): void
    {
        $relationship = Relationship::find($this->relationshipId);

        if (! $relationship) {
            Log::warning("RecalculateRelationshipScoreJob: relationship [{$this->relationshipId}] not found — skipping.");
            return;
        }

        $scoreEngine->recalculate($this->relationshipId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RecalculateRelationshipScoreJob failed for relationship [{$this->relationshipId}]", [
            'error' => $exception->getMessage(),
        ]);
    }
}
