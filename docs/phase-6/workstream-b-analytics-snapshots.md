# Phase 6 · Workstream B — Analytics Engine (Slice 2: dashboard snapshots)

Materialises the `/relationship/analytics` dashboard metrics into a derived
projection, without touching the dashboard's own query logic. Same
"build → rebuild command → parity" discipline as `TodayActionsProjector`
(Phase 1) and `InsightsProjector` (Phase 6 · Slice 1).

## Slice 2 — snapshot projection (built, shadow)

| Piece | File |
|---|---|
| Migration | `database/migrations/2026_07_04_800001_create_analytics_snapshots_table.php` |
| Model | `app/Models/AnalyticsSnapshot.php` |
| Visibility-only edit | `app/Http/Controllers/Relationship/AnalyticsController.php` — 7 metric methods `protected` → `public`, plus an optional `bool $fresh = false` param that bypasses `Cache::remember` (default unchanged; every existing call site behaves identically). |
| Projector | `app/Services/Analytics/AnalyticsProjector.php` — `rebuildAll()`, `rebuildFor($metric)`, `snapshotsFor()`, `parity($metric = null)` |
| Command | `app/Console/Commands/RebuildAnalyticsSnapshots.php` → `analytics:rebuild-snapshots [--metric=] [--check]` |
| Schedule | `routes/console.php` — new block, every 15 minutes (same cadence as `today:rebuild-projection`) |
| Tests | `tests/Feature/Analytics/AnalyticsProjectorTest.php` |

**Why the controller was touched at all:** unlike Insights (net-new signals),
Analytics already has a real, working metrics implementation. Duplicating
those 7 queries into a new engine would create a second place that could
silently drift from the live dashboard. Instead the projector calls
`AnalyticsController`'s own methods directly — there is exactly one place
that knows how to compute each metric. The only changes to the controller
are (a) method visibility, so the projector can call them, and (b) an
optional `$fresh` flag so the projector's rebuild/parity can force a true
recompute instead of getting back a same-hour cached value. Both are
additive with an unchanged default — `index()` and the live dashboard are
byte-for-byte unaffected.

**Parity, here, means something different than in Slice 1:** because the
projector calls the exact same code as the dashboard, a `--check` mismatch
means the *projection is stale* (new data arrived since the last rebuild),
never a logic divergence between two implementations.

### Run (you)

```
php artisan migrate                              # creates analytics_snapshots
php artisan analytics:rebuild-snapshots          # build the projection (shadow)
php artisan analytics:rebuild-snapshots --check  # parity vs a fresh recompute
php artisan test --filter=AnalyticsProjectorTest
```

Nothing user-visible changes — `/relationship/analytics` still renders
exactly as before. No new feature flag was introduced for this slice (the
blueprint's own Phase 6 wording for Analytics doesn't call for a read-cutover
flag yet); a future slice would add one when a dashboard read cutover is
actually proposed.

## Next (not started — hold for confirmation)

- Slice 3: Search Engine index projection (behind `search.index`).
- Slice 4: Read-contracts — thin read-model interface so Insights/Analytics/
  Today's Actions stop touching raw domain tables directly.
