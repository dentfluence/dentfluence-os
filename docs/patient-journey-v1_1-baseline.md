# Patient Journey V1.1 — Slice 0.1 Baseline Record

**Date:** 2026-07-25
**Branch:** `main` · **Pre-baseline HEAD:** `db2d4d9` (== `origin/main`, deployed state)
**Tag:** `patient-journey-v1.1-baseline` (final SHA recorded in Slice 0.1 report)

This is the rollback and comparison reference for all Patient Journey V1.1 slices.
Later slices must NOT claim ownership of any defect listed here.

## Baseline verification results

- **Smoke suite** (`dentfluence:smoke`, rollback mode, run `SMOKE_20260725_125747`): **PASS 66/66** — Patients 29/29, Appointments 25/25, Inventory 12/12. Zero data-integrity failures, zero duplicates, zero residue (12 records created, all rolled back).
- **Route crawler** (`app:crawl-routes`): 302 pages — **0 broken**, 24 warnings, 278 healthy. Report: `storage/app/route-reports/report-20260725-130031.html`.
- **Migrations:** all `Ran` (through batch 169, latest `2026_07_24_130000_drop_dead_queue_columns_from_appointments`). No pending migrations. No migrations were run, no schema changed, no production or dev data mutated during baseline. Tests run against `dentfluence_testing` (phpunit.xml), isolated from dev DB.
- **Targeted regression (all pre-existing suites):**
  - `tests/Feature/Patients` — 53 passed (incl. ProfileRefactorTest after the one-line stale-assertion fix below)
  - `tests/Feature/Appointments` — 89 passed
  - `tests/Feature/AppointmentStatusFlowTest` — 1 passed
  - `tests/Feature/TreatmentCreateTest` — 1 passed
  - `tests/Feature/Automation` — 14 passed
  - `tests/Feature/Characterization` — 16 passed
  - `tests/Feature/Relationship` — 45 passed, 1 failed, 2 fatals (BD-1..BD-3 below); `TodayReadCutoverTest` fatals standalone (see BD-2 addendum)

## Fixed in Slice 0.1 (authorized)

- `tests/Feature/Patients/ProfileRefactorTest.php:51` — asserted entity-encoded `Patient Details &amp; Rapport`; the heading in `tab-profile.blade.php` is raw HTML (`&` unencoded), eagerly included by `show.blade.php`. Assertion changed to the raw form. Test-only change; production untouched.

## Known baseline defects (pre-existing — NOT fixed in this slice)

**BD-1 — Stale test double (fatal):** `tests/Feature/Relationship/ReceptionDashboardTest.php:30` — anonymous stub declares `generate(): array`, incompatible with `TodayActionsEngine::generate(bool $includeDone = false): array` (signature changed by Action Board Done-state work). PHP fatal aborts any directory-wide run of `tests/Feature/Relationship` at this class.

**BD-2 — Same stale stub (fatal):** `tests/Feature/Relationship/TodayActionsProjectorTest.php:28` **and** `tests/Feature/Relationship/TodayReadCutoverTest.php:32` — identical incompatible stubs. Addendum (2026-07-25, post-tag): TodayReadCutoverTest was confirmed to carry the stub itself and fatals even standalone — it is a third defective file, not merely blocked by BD-1/BD-2. Three files total carry the stale `generate(): array` double.

**BD-3 — Missing factory (test error):** `tests/Feature/Relationship/RecallPipelineTest.php` `test_convert_to_opportunity_creates_opportunity_and_closes_recall` — calls `Patient::factory()` but no `Database\Factories\PatientFactory` exists (patients are minted only via `PatientService::register()`; sibling tests use `Patient::create()`). Error: `Class "Database\Factories\PatientFactory" not found`.

**BD-4 — Route crawler warnings:** 24 warnings incl. the known Treatment Plans print timeout. 0 broken pages. Warning list not individually triaged in this slice.

**BD-5 — Coverage gap:** Consultation has no dedicated Feature suite; covered only via smoke journeys, route crawler and Browser tests.

**BD-6 — Product defects already scheduled for later Journey V1.1 slices** (see `docs/pre-relationship-engine-audit.md`, `docs/patient-journey-audit.md`, `docs/legacy-prm-audit.md`): `last_visit_date` has no writer (1,810-item recall backlog root cause), unrouted webhooks, dead Today's-Actions categories, `recallCalls` unbounded query, OutcomeAutomation mobile-only, engine≠runner parity, lead→appointment missing FK, accepted-plan→scheduling gap, relationship routes auth-only. Intentionally untouched in Slice 0.1.

## Intentionally left untouched

- All BD-1..BD-6 items (execution discipline: one slice at a time).
- No `.env`, config, schema or data changes. Only file changes: this doc, the 10 previously-untracked files (6 audit/roadmap docs + 4 smoke-suite files), and the ProfileRefactorTest one-liner.
