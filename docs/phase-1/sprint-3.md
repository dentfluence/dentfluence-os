# Phase 1 · Sprint 3 — Journeys in Shadow (Workstream C)

**Status:** built (code + tests). Shadow sync is an operator command — dry-run, review, apply.
**Behaviour:** neutral. Nothing reads journeys as authoritative yet; reads still use legacy `leads.stage` / `treatment_opportunities.status`.

## What was built

| Piece | File |
|---|---|
| Journey sync engine | `app/Services/Relationship/JourneyService.php` |
| Operator command | `app/Console/Commands/SyncRelationshipJourneys.php` (`relationship:sync-journeys`) |
| Tests | `tests/Feature/Phase3/JourneyServiceTest.php` |

## What it does

- Maps the legacy pipeline state onto the journey state machines:
  - **Lead:** `new_lead→new_enquiry`, `contacted→contacted`, `appointment→appointment_booked`, `consultation→consultation`, `plan_given→treatment_planned`, `converted→closed`, `lost→lost`.
  - **Opportunity:** `prospect→identified`, `discussed→presented`, `quoted→quoted`, `accepted→accepted`, `declined→declined`, `completed→completed`.
- **Shadow-reconciles** each lead's and opportunity's journey to match its current legacy state (creates the journey if missing, updates it if it diverged). One opportunity journey per opportunity (keyed by `metadata.opportunity_id`).
- Reconcile uses a **direct state set**, not the strict `transition()` graph — a shadow sync legitimately jumps states to match reality.
- **Idempotent**: re-running reports everything `in_sync`.

## Why it's safe

- **Shadow only.** Journeys are dual-written to mirror the legacy columns. Reads (PRM board, opportunity board) are untouched. The authoritative cutover (reads use journeys) is Blueprint **Phase 4**, behind the `journey.authoritative` flag.
- **Additive + idempotent + reversible.** Journeys are derived shadow data; re-running is safe, and they can be recomputed from the legacy columns at any time.
- **No merges, no lead/patient writes** — only `relationship_journeys` rows.

## Note — opportunity linking fix (Workstream G completion)

Applying the journey sync revealed that the Sprint-2 identity backfill linked leads + patients but **not** `treatment_opportunities` (their `relationship_id` stayed null), so opportunity journeys were skipped. The backfill now **links opportunities via their patient** (`opp.relationship_id = patient.relationship_id`). Re-run `relationship:backfill --apply` once to populate them, then re-run the journey sync.

## How to run

```
# 0. Re-run the identity backfill so opportunities get linked (idempotent):
php artisan relationship:backfill --apply

# 1. See divergence (safe, read-only) — how many journeys would be created / reconciled:
php artisan relationship:sync-journeys

# 2. Apply the shadow sync (idempotent):
php artisan relationship:sync-journeys --apply

# 3. Re-run the dry-run — everything should now be "in sync":
php artisan relationship:sync-journeys
```

Then the tests:

```
php artisan test --filter=Phase3
```

## Definition of Done — Sprint 3 · C

- [ ] `php artisan test --filter=Phase3` green.
- [ ] Dry-run divergence report looks sensible (recall: the Sprint-2 backfill created every lead journey at `new_enquiry`, so leads whose real stage is further along will show as "would reconcile").
- [ ] `--apply` then dry-run shows everything `in_sync`.
- [ ] PRM board / opportunity board behave exactly as before (reads unchanged).

## Deferred (with reason)

- **Recall journeys.** Unlike leads/opportunities, "recall" has no single legacy status column to derive from — recall lives as `communication_queue` items with a purpose. Recall journeys will be driven by recall *events* during the automation phase, not back-derived here. Documented so it isn't mistaken for an omission.

## Next in Sprint 3

- **The cutovers** — point the profile timeline at `UnifiedTimelineService`, and Today's Actions at a projection, behind their flags, after parity checks.
- **Workstream D** — the first PRE screens (relationship dashboard / pipeline / profile), including the 18 multi-patient "household" relationships.
