# Phase 1 · Workstream E — Today's Actions projection

Retire the "god reader": Today's Actions must read ONE pre-computed view, not query ~12 domains at request time. Built in small, verifiable slices, all behind the existing `today.projection` flag (default off).

## Slice E1 — Projection foundation (built, shadow)

Materialise the live `TodayActionsEngine` into a derived read model. Shadow only — the page still reads the live engine until E2.

| Piece | File |
|---|---|
| Migration | `database/migrations/2026_07_02_700001_create_today_actions_table.php` |
| Model | `app/Models/TodayAction.php` |
| Service | `app/Services/Relationship/TodayActionsProjector.php` — `rebuild()`, `grouped()`, `parity()` |
| Command | `app/Console/Commands/RebuildTodayActions.php` → `today:rebuild-projection [--check]` |
| Tests | `tests/Feature/Relationship/TodayActionsProjectorTest.php` |

**How it works:** `rebuild()` runs the engine's 12 categories and replaces the whole `today_actions` table inside a transaction (idempotent — it's a disposable view, never a source of truth). `grouped()` reads it back in the exact item shape the engine returns, so the E2 read cutover needs no view changes. `parity()` compares the stored projection to a fresh live read per category (mirrors the timeline-parity harness) to prove shadow correctness before cutover.

**Safety:** one additive table; no existing table touched; no read path changed; nothing scheduled yet (rebuild is run manually / by command for now).

### Run (you)

```
php artisan migrate                          # creates today_actions
php artisan today:rebuild-projection         # build the projection (shadow)
php artisan today:rebuild-projection --check # parity vs the live engine (expect OK right after a rebuild)
php artisan test --filter=TodayActionsProjectorTest
```

Nothing user-visible changes in E1 — `/relationship/today` still renders from the live engine. E2 flips the read to the projection behind `today.projection`.

## Slice E2 — Read cutover (built)

`TodayController@index` sources the 12 groups from the projection when `today.projection` is ON, and from the live engine when OFF (default). It projects onto the full known category set so empty groups still render identically. Instant rollback = flip the flag off.

- Controller: `app/Http/Controllers/Relationship/TodayController.php`
- Test: `tests/Feature/Relationship/TodayReadCutoverTest.php` (proves flag-on reads the projection and does NOT consult the engine).

## Slice E3 — Reception dashboard (built)

`GET /relationship/reception` (`relationship.reception`) — reads the projection and splits it into **Today's Calls** and **Today's Work**, priority-sorted, with summary tiles and a freshness stamp. Read-only, additive; reads the projection directly (independent of the read-cutover flag).

- Controller/View: `ReceptionController.php`, `resources/views/relationship/reception/index.blade.php`
- Test: `ReceptionDashboardTest.php`

## Slice E4 — Daily Huddle read wiring (built)

Shared read so the Huddle stops running its own queries for this: `GET /relationship/today/summary` (`relationship.today.summary`) returns `TodayActionsProjector::summary()`, and a reusable partial `resources/views/relationship/today/_snapshot.blade.php` is embedded into the **main Daily Huddle** page (`App\Modules\Huddle\Controllers\HuddleController@index` passes `$todaySnapshot`, rendered in `resources/views/huddle/index.blade.php` above the stats strip) — visually confirmed on `/huddle`. Also included on the Communication Huddle widgets fallback (`Communication\HuddleController@widgets`).

- Test: `HuddleSnapshotTest.php`

## Slice E5 — Scheduled rebuild (built)

`today:rebuild-projection` is scheduled every 15 minutes (`routes/console.php`) so the projection stays fresh for all readers. Idempotent; logs to `storage/logs/today-actions-projection.log`. The reception dashboard and Huddle snapshot show an "updated … ago" / "not built yet" freshness indicator.

## Verify (you)

```
php artisan today:rebuild-projection            # populate the view
php artisan test --filter="TodayActionsProjectorTest|TodayReadCutoverTest|ReceptionDashboardTest|HuddleSnapshotTest"
```

Then visit `/relationship/reception` (calls/work queues) and `/huddle` (Today's Actions snapshot at the top). To see the Today's Actions page served from the projection, flip the flag on:
`\App\Support\Features\Feature::set('today.projection', true)` and open `/relationship/today` (flip back to `false` to return to the live engine).
