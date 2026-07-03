# Phase 6 · Workstream C — Search Engine (Slice 3: index projection)

Materialises `ProfileController::search()`'s live LIKE-query (name/phone/
email, ordered by score) into a derived index, behind the already-declared
`search.index` flag (default off). Same "build → rebuild command → parity"
discipline as every other Phase 6 slice.

## Slice 3 — index projection (built, shadow)

| Piece | File |
|---|---|
| Migration | `database/migrations/2026_07_05_800001_create_search_index_table.php` |
| Model | `app/Models/SearchIndexEntry.php` |
| Projector | `app/Services/Search/SearchIndexProjector.php` — `rebuildAll()`, `rebuildFor($id)`, `query($term,$limit)`, `parity()` |
| Command | `app/Console/Commands/RebuildSearchIndex.php` → `search:rebuild-index [--relationship=] [--check]` |
| Incremental refresh | `app/Observers/Search/RelationshipSearchIndexObserver.php` + `app/Jobs/RebuildSearchIndexEntryJob.php` + `app/Providers/SearchServiceProvider.php` (registered in `bootstrap/providers.php`) |
| Tests | `tests/Feature/Search/SearchIndexProjectorTest.php`, `SearchIndexObserverTest.php` |

**How it works:** `search_index` is a denormalised, one-row-per-relationship
copy of exactly the fields the live search already matches on
(name/phone/email/score/status/source), plus a pre-built profile link. The
projector's `query()` method mirrors `ProfileController::search()`'s output
shape exactly (same keys, same initials/meta formatting) so a future cutover
is a drop-in read swap — nothing calls `query()` yet, `ProfileController`
is completely untouched. `parity()` compares stored rows against a fresh
read of `relationships` to catch staleness, the same self-check style as
Insights/Analytics.

**Incremental refresh — model observer, not a domain event:** relationship
name/phone/email/score changes aren't (yet) published as a formal domain
event, so `RelationshipSearchIndexObserver` hooks Eloquent's native `saved`
event instead — registered from `SearchServiceProvider`, **not** by editing
`app/Models/Relationship.php`. While `search.index` is off (default), the
observer fires on every save but does nothing, so this ships with zero
behaviour change.

**Safety:** one additive table, one line in `bootstrap/providers.php`. No
existing controller, route, or model file touched.

### Run (you)

```
php artisan migrate                            # creates search_index
php artisan search:rebuild-index               # build the index (shadow)
php artisan search:rebuild-index --check       # parity vs a fresh read
php artisan test --filter=SearchIndex
```

Nothing user-visible changes — the universal search box still queries
`relationships` live via `ProfileController::search()`.
