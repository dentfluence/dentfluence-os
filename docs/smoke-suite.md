# Dentfluence Smoke Suite — `php artisan dentfluence:smoke`

**Scope: the three frozen modules only — Patients V1.0, Appointments V1.0, Inventory V1.0.**
Purpose: after every deployment, run ONE command and answer *"are the core clinic workflows still alive, and is the persisted data correct?"* A green HTTP response is never treated as sufficient: every action is verified as ACTION → response → database persistence → related records → activity/lifecycle events → no unexpected duplicates.

Built 2026-07-24. Test infrastructure only — no production code was changed, no business logic is duplicated in the suite. Every write goes through the frozen canonical paths (`PatientService::register`/`updateFromInput`, `FamilyLinkService`, `AppointmentService`, `StockMovement` + `InventoryService::receivePurchaseOrder`) and the suite only asserts the outcomes they produced.

## Files

| File | Role |
|---|---|
| `app/Console/Commands/DentfluenceSmoke.php` | Runner: modes, safety rails, orchestration, cleanup, classified report, exit code |
| `app/Services/Smoke/SmokeRun.php` | Run context: Run ID, check ledger, failure classes, created-record registry |
| `app/Services/Smoke/HttpProbe.php` | In-process GET through the real HTTP kernel (full middleware) — catches "DB right, screen 500s" |
| `app/Services/Smoke/Journeys/PatientsSmokeJourney.php` | 18-step patient journey (+ integrity checks) |
| `app/Services/Smoke/Journeys/AppointmentsSmokeJourney.php` | 22-step appointment journey (+ reminder idempotency, comms tripwire) |
| `app/Services/Smoke/Journeys/InventorySmokeJourney.php` | 11-step inventory journey (+ ledger math) |
| `tests/Browser/SmokeCoreJourneysTest.php` | Small Dusk layer (LOCAL ONLY): login, profile + lazy tab, day view, JS console capture |

## How to run

Local (Laragon), zero residue — everything rolls back:

    php artisan dentfluence:smoke

One module only:

    php artisan dentfluence:smoke --module=patients

True end-to-end persistence (records really commit, then are individually cleaned):

    php artisan dentfluence:smoke --commit          # asks for confirmation
    php artisan dentfluence:smoke --commit --force  # CI / scripted
    php artisan dentfluence:smoke --commit --keep   # keep records for inspection

Browser layer (LOCAL ONLY — needs ChromeDriver + `.env.dusk.local` with `CRAWL_EMAIL`/`CRAWL_PASSWORD`):

    php artisan dusk --filter=SmokeCore

### On the VPS (production smoke)

    docker compose exec app php artisan dentfluence:smoke

Default rollback mode is the recommended production mode: the entire run happens inside one DB transaction that is always rolled back (same convention as `patients:register-smoketest`), so nothing is ever committed. Use `--commit` on production only when you specifically want to prove commit durability; it never touches real patients/appointments/stock, never merges patients, never creates financial records (the PO→GRN chain is skipped in commit mode because a GRN auto-posts a real AP bill — canonical manual stock-in is used instead), and cleans up only the ids it created. Anything it cannot clean is reported as `retained → … [SMOKE_<runid>]` so it can be found by its Run ID prefix. Never run Dusk or the PHPUnit suites against the VPS.

Exit code: `0` = OVERALL PASS, `1` = OVERALL FAIL — wire it into `deploy.sh` / CI directly after a deploy.

## Safety rails (both modes, in-process only, for the run's lifetime)

- `whatsapp.enabled=false`, `whatsapp.dry_run=true` — no outbound WhatsApp, plus a tripwire check that `wa_messages` / `communication_queue` row counts did not change during the run.
- `mail.default=array`, `queue.default=sync` (no jobs leak to real workers), `features.automation.engine=false` (no rule-driven comms from smoke events), `session.driver=array`.
- All created entities carry the Run ID (`SMOKE_YYYYMMDD_HHMMSS`) in names/notes/codes; phones are run-unique `99…` numbers.
- Reminder generation (targets tomorrow's real appointments) runs only in rollback mode; commit mode uses the read-only `previewCount()`.
- Smoke appointments are booked ~180 days out at 07:00–08:00 so they never collide with real bookings.

## Failure classification

`CRITICAL` (wrong/duplicated/leaked data, invariant violation) · `WORKFLOW` (action fails, wrong status, missing event, broken lazy tab) · `TECHNICAL` (500s, exceptions, probe failures) · `COSMETIC` (reported, never fails the run). Any non-cosmetic failure ⇒ `OVERALL: FAIL` + non-zero exit.

## Journey coverage

**Patients (directive steps 1–18):** canonical adult registration (TDC, branch/creator stamps, exactly-one row) → name + mobile search → profile page render + all 10 lazy tabs via HTTP probe → demographic update persistence → family member link (reciprocal, single row) → relationship change → removal (both directions) → minor registration → guardian-required state (service + consent screen) → attach guardian (graph both ways, single guardian row) → guardian in consent flow → demotion via `updateLink(as_guardian:false)` → removal → Journey Timeline read model. Plus: exactly 3 smoke patients (no dupes), `patients:invariant-check` still passes, no cross-patient bleed.

**Appointments (directive steps 1–22):** create via `AppointmentService` (exactly-one row, all fields, `appointment.booked` ×1) → conflicting booking rejected AND writes nothing → reschedule (same id, no dup, `rescheduled` ×1) → check-in (status + timestamp, canonical `AppointmentStatus`) → no-show on a second appointment (`missed` ×1) → cancel with reason on a third (`cancelled` ×1) → revert (previous status restored, events not double-logged) → hide (`hidden_from_calendar` persisted, `visibleOnCalendar` scope excludes) → soft delete (+`deleted` ×1). Plus reminder idempotency and the no-outbound-comms tripwire. `AppointmentService` *is* the suite's only write path, keeping the canonical-write-path invariant exercised on every run.

**Inventory (directive steps 1–11):** isolated TEST item + TEST location → opening stock 10 → receive 5 (rollback: full PO→GRN via `InventoryService::receivePurchaseOrder`, incl. PO status recalc + AP payable side-effect; commit: manual stock-in) → stock 10→15 exactly once → issue 3 → 15→12 exactly once → ledger math `FINAL = OPENING + RECEIVED − ISSUED ± adjustments` with exactly 3 movement rows → `StockStatusService` consistency → movements attribute the actor.

## Browser / E2E layer

Dusk was already installed (19 tests), so a small layer was added rather than a new framework: real login, patient profile + lazy-tab click, appointments day view, severe-JS-console capture. **Deliberately excluded:** appointment create/reschedule/check-in through the calendar modals — those flows have no `@dusk` selectors today, and blind CSS selectors would produce exactly the brittle UI tests the directive forbids. They are fully covered at service+HTTP+DB level by `dentfluence:smoke`. *Recommendation (needs CEO approval as it touches frozen views): add `dusk="…"` attributes to the appointment modal controls, then extend the Dusk layer with the booking journey.*

## Post-deployment checklist

    php artisan dentfluence:smoke        # this suite (exit code gates the deploy)
    php artisan test tests/Feature/Patients tests/Feature/Appointments   # frozen suites (dentfluence_testing DB — local/CI only)
    php artisan test --filter=Inventory  # inventory tests (local/CI only)
    php artisan app:crawl-routes         # authenticated page-health crawl (HTML report in storage/app/route-reports)

## Defects discovered (documented, NOT fixed — frozen module policy)

- **2026-07-25 · `ProfileRefactorTest::test_profile_page_renders_with_journey_timeline_and_tabs` fails on current main.** Latent defect in the frozen TEST, not a regression: `tab-profile.blade.php:19` emits a literal `Patient Details & Rapport` (hard-coded HTML, unescaped), while the test asserts the escaped `Patient Details &amp; Rapport` (raw mode). Both files are unchanged since the same commit (`ffbb91b`, 2026-07-24) — the assertion cannot pass against the committed view. Verified unrelated to the smoke suite (untracked files only; `git status` clean otherwise). **Proposed fix (needs CEO approval, one line in the test):** `$resp->assertSee('Patient Details & Rapport', false);`

## Known gaps

- Item/location creation uses direct model creates (the module has no item-creation service; this matches the existing test convention).
- Commit-mode cleanup can leave observer-created side rows (e.g. search-index entries) if FKs block a force delete; these are reported as retained with the Run ID rather than broad-deleted.
- The API (`/api/v1`) surfaces of the three modules are not probed here — they have their own contract tests (`ApiV1ContractTest`, characterization suites).
- Patient merge is intentionally untested against live data (directive).
