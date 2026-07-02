# Phase 1 · Sprint 2 — Identity Backfill (Workstream G)

**Status:** tooling implemented (code + tests). **Not executed** — the backfill is an operator-run data job; you dry-run it, review, then apply.
**Risk:** HIGH (touches real lead/patient records). Built dry-run-first, idempotent, and non-merging.

## What was built

| Piece | File |
|---|---|
| Backfill engine | `app/Services/Relationship/RelationshipBackfillService.php` |
| Operator command | `app/Console/Commands/RelationshipBackfill.php` (`relationship:backfill`) |
| Tests | `tests/Feature/Phase2/RelationshipBackfillTest.php` |

## What it does

- **Links** every unlinked `lead` and `patient` to a Master Relationship using the existing, idempotent `RelationshipEngine::linkLead` / `linkPatient` (matches by phone → email, else creates).
- **Queues** — never merges — relationships that share an exact phone/email into `dedup_candidates` for human review.
- Crosses **all branches** (bypasses `BranchScope`), processes in **batches**, isolates per-row failures, and is **restartable** (re-running links only what's still unlinked).

## Safety properties

- **Dry-run by default.** `relationship:backfill` with no flags writes nothing — it prints what *would* happen.
- **`--apply` asks for confirmation** before writing (skip with `--force`).
- **No auto-merge.** Duplicates are only queued; merging stays a deliberate, reversible `MergeService::merge` action (Sprint 1).
- **Idempotent.** Re-running creates no duplicates.
- **Independent of the `identity.link_patient` flag** — that flag gates *live* patient-creation linking (shadow); this is a one-off operator backfill.

## How to run (Laragon terminal — you run these)

```
# 1. See the plan (safe, read-only):
php artisan relationship:backfill

# 2. Review the numbers. Then apply:
php artisan relationship:backfill --apply

# 3. Review duplicates queued for merge:
#    SELECT * FROM dedup_candidates WHERE status = 'pending';
```

Then run the tests:

```
php artisan test --filter=Phase2
```

## Definition of Done — Sprint 2 (backfill)

- [ ] `php artisan test --filter=Phase2` green.
- [ ] Dry-run report looks sensible (unlinked counts match expectations).
- [ ] `--apply` links leads + patients; `relationship_id` populated.
- [ ] `dedup_candidates` holds only *pending review* rows — nothing auto-merged.
- [ ] Existing suite still green.

## Recommended sequence

1. Run the **dry-run** and paste me the report — we sanity-check the match/create/duplicate numbers together **before** applying.
2. Apply once the numbers look right.
3. Review the dedup queue; merge true duplicates manually (reversible).

---

## Workstream B — Unified Timeline (built)

| Piece | File |
|---|---|
| `ActivityRecorded` domain event | `app/Domain/Events/Relationship/ActivityRecorded.php` |
| ActivityEngine publishes it (after commit) | `app/Services/Relationship/ActivityEngine.php` (additive) |
| Timeline projection | `app/Services/Relationship/UnifiedTimelineService.php` |
| Tests | `tests/Feature/Phase2/UnifiedTimelineServiceTest.php`, `ActivityRecordedEventTest.php` |

**What it does:** `UnifiedTimelineService::for($relationship)` merges the `activities` ledger with the legacy activity/communication sources (lead activities, appointments, patient communications, tasks, notes) into one newest-first timeline. It's a faithful, reusable extraction of `ProfileController::buildTimeline`.

**Behaviour-neutral:** the profile page is **not** rewired yet — `ProfileController` still builds its own timeline. Pointing it at `UnifiedTimelineService` behind the `activity.single_ledger_reads` flag is the **Sprint 3 cutover**. `ActivityEngine` now also publishes `ActivityRecorded` after each log commits — no subscribers yet, so it's a harmless no-op that sets up the event backbone.

**No mass data writes** in this chunk — the timeline reads both old and new sources and merges them at read time. Making `activities` the single physical source (mirroring legacy logs in, then dropping the merge) stays in Sprint 3.

Run: `php artisan test --filter=Phase2`

## Sprint 2 — status

- ✅ Workstream G — identity backfill (applied & verified: 3,814 relationships, 0 unlinked).
- ✅ Workstream B — unified timeline (built; cutover deferred to Sprint 3).

## Next (Sprint 3)

Journeys authoritative-in-shadow (Workstream C), the Timeline/Today's-Actions **cutover** behind flags, and the first PRE UI (Workstream D).
