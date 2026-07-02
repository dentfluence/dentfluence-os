# Phase 1 · Sprint 1 — Event Catalog + Workstream A (identity, shadow)

**Status:** implemented (code), pending your `migrate` + `test` run.
**Scope:** Sprint 1 of the Phase 1 execution plan — the shared event catalog and Workstream A's identity core. **Behaviour-neutral:** the `identity.link_patient` flag is OFF by default, so patient creation is unchanged.

## What was built

| Area | Files |
|---|---|
| **Phase 1 event catalog** (contracts over the Phase 0 bus) | `app/Domain/Events/Relationship/{PatientRegistered,LeadCaptured,RelationshipLinked,RelationshipMerged,JourneyTransitioned}.php` |
| **Identity resolution** (read-only matching + dedup detection) | `app/Services/Relationship/IdentityResolver.php` |
| **Merge** (reversible, with history) | `app/Services/Relationship/MergeService.php`, `app/Models/RelationshipMerge.php`, migration `…_create_relationship_merges_table` |
| **Dedup review queue** (schema) | `app/Models/DedupCandidate.php`, migration `…_create_dedup_candidates_table` |
| **`linkPatient` wiring (shadow)** | `app/Services/Relationship/PatientRelationshipLinker.php`; two one-line calls in `app/Services/PatientService.php` (`createFromInput`, `quickCreate`) |
| **Tests** | `tests/Feature/Phase1/{IdentityResolverTest,MergeServiceTest,PatientRelationshipLinkerTest,Phase1EventCatalogTest}.php` |
| **Hardening** | `FeatureFlagService` now fails safe to config defaults if `feature_flags` isn't migrated; linker's flag check is inside its try/catch. |

## Behaviour contract (why this is safe)

- **`identity.link_patient` defaults OFF** → `PatientRelationshipLinker::link()` is a no-op → patient creation behaves exactly as before.
- **When ON (later, in shadow):** a new patient is linked to a Master Relationship (via the existing, idempotent `RelationshipEngine::linkPatient`), and `RelationshipLinked` + `PatientRegistered` events are published. Linking **never breaks** patient creation (fully wrapped).
- **No auto-merge.** `MergeService` and `DedupCandidate` are built and tested but **not invoked from any live path**. Merges are manual/reviewed and fully reversible (`undo()`).
- **Live paths untouched:** `linkLead`, PRM, journeys, automation — all unchanged this sprint.

## What you run (Laragon terminal)

```
php artisan migrate     # adds: relationship_merges, dedup_candidates
php artisan test --filter=Phase1
```

Both migrations are additive; `down()` drops only the new tables.

## Definition of Done — Sprint 1

- [ ] `php artisan migrate` succeeds (2 additive tables).
- [ ] `php artisan test --filter=Phase1` green.
- [ ] Existing tests still green (patient creation unchanged with flag off).
- [ ] No behaviour change for the receptionist (flag off).

## Not in this sprint (next up)

- **Sprint 2 (high-risk, needs its own approval):** run the identity **backfill** across existing leads + patients (dry-run → dedup review queue → validate → apply), and begin the Activity ledger dual-write (Workstream B).
- Additional patient-create entry points (if any bypass `PatientService`) get wired as identified; the Sprint 2 backfill covers already-existing/unlinked patients regardless.
