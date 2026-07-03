<?php

namespace App\Providers;

use App\Models\Relationship;
use App\Observers\Search\RelationshipSearchIndexObserver;
use Illuminate\Support\ServiceProvider;

/**
 * SearchServiceProvider — Phase 6 · Slice 3 (Search Engine).
 *
 * Registers the search-index observer on the Relationship model WITHOUT
 * editing Relationship.php itself. Additive and self-contained: removing
 * this provider from bootstrap/providers.php fully disables the observer
 * (the `search.index` flag already gates its behaviour too).
 */
class SearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relationship::observe(RelationshipSearchIndexObserver::class);
    }
}
