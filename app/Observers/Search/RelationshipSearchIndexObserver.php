<?php

namespace App\Observers\Search;

use App\Jobs\RebuildSearchIndexEntryJob;
use App\Models\Relationship;
use App\Support\Features\Feature;
use Illuminate\Support\Facades\Log;

/**
 * RelationshipSearchIndexObserver — Phase 6 · Slice 3 (Search Engine).
 *
 * Registered on the Relationship model (via SearchServiceProvider, NOT by
 * editing Relationship.php itself) so the index stays fresh whenever a
 * relationship's searchable fields (name/phone/email/score/status) change.
 *
 * Flag-gated by `search.index` (default OFF): while off, this observer still
 * fires on every save (registering is harmless) but does nothing — so this
 * slice ships with zero behaviour change until the flag is flipped. Mirrors
 * the same discipline used by RecalculateInsightSignalsListener (Slice 1).
 */
class RelationshipSearchIndexObserver
{
    public function saved(Relationship $relationship): void
    {
        if (! Feature::enabled('search.index')) {
            return;
        }

        try {
            RebuildSearchIndexEntryJob::dispatch($relationship->id);
        } catch (\Throwable $e) {
            Log::warning('RelationshipSearchIndexObserver: dispatch failed', [
                'relationship_id' => $relationship->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
