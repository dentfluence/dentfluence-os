# Repository Cleanup Audit — 2026-08-03 (Slice A: Report Only)

**Status: AUDIT ONLY. Zero files were moved, renamed, or deleted.**
Quarantine convention on approval: reuse existing `under_review/` (gitignored) — do **not** invent a second `_archive/` root.

Scope: full repo excl. `vendor/`, `node_modules/`. Method: 5 parallel static-analysis sweeps (routes/controllers, views reachability closure, app/ class reference index, assets/config/root, tests/DB/dead-code) + independent spot-verification of "never referenced" claims.

**Hard constraints applied:**
- **Migrations are never moved.** The `migrations` table stores filenames; moving run migrations breaks `migrate:status`, rollback, and fresh VPS installs. Marked only.
- **`resources/views/consultations/**` is an ACTIVE WORK ZONE** (uncommitted slice in flight: partials being re-extracted, `create.blade.php` modified, new `prescription`/`follow-up` partials untracked). Findings reported, but this directory is excluded from every move list until that work lands.
- Working tree has ~65 uncommitted changes (Consultations/Patients slice). **No cleanup move may execute before that is committed.**

---

## 1. Repository Cleanup Summary

| Area | Total | Live | Dead (verified) | Needs manual verification |
|---|---|---|---|---|
| Controllers | 162 files / 158 classes | 151 | 7 | 5 |
| Other app/ classes | 652 | 632 | 7 | 13 commands + 3 classes |
| Blade views | 468 | 390 | 63 zero-ref + 15 dead-by-chain | 2 (UI-reachability, not view-ref) |
| Routes | 952 named | 952 resolve — **0 broken targets** | 12 duplicate-name collisions | — |
| Config files | 33 | 31 | 2 (both 0 bytes) | — |
| Frontend assets | ~110 | most | ~20 files (~5 MB incl. 4.9 MB logos) | CMS JS cluster (loader bug) |
| Migrations | 418 | all (never move) | 0 | 2 filename pairs to read |
| Seeders | 48 | 26 | ~7 high-confidence | 17 ad-hoc-runnable |
| Tests | 137 | 133 | 4 support/scaffold files | 1 duplicate-coverage pair |
| Filesystem junk | — | — | 17 `.fuse_hidden*` orphans, 1 `.bak`, `.rename_test_b.tmp` | — |

Positive findings: **zero broken route targets**, **zero namespace/path mismatches** across 814 app/ files, `.gitignore` healthy, no tracked junk in storage/, build output current with vite entries.

---

## 2. Files Moved

**None.** Slice A is report-only by CEO decision. Proposed move lists are in §10.

---

## 3. Files Needing Manual Verification

### 3a. Webhook controllers — imported, never routed (KNOWN P0, not a cleanup item)
`app/Http/Controllers/Webhooks/{WebsiteLeadController, MetaLeadController, WhatsAppLeadController, ChatbotController}` are imported at `routes/api.php:29–32` but **no route uses them**. This matches the Legacy PRM Audit finding "webhooks unrouted = P0 outage". These are live integration code with a missing wiring step — **do not quarantine; decide fix-or-retire as a product call.** Note `public/js/prm-chatbot.js` (third-party embed) depends on `ChatbotController` having an endpoint.

### 3b. Deliberately parked (keep in place, decision later)
- `app/Http/Controllers/Communication/TimelineController.php` (330 ln) — routes commented out in `routes/timeline.php:28-35`; file header documents "TO RESTORE" checklist. `routes/timeline.php` itself is a no-op require loaded by `bootstrap/app.php:22`.
- Parked feature clusters (all wired, flag-gated or manual-only — **keep**): Tulip AI copilot, Voice notes, Automation Engine (flag off), Workflow Engine (flag off), Receipt/Patient scan.

### 3c. One-shot commands that have likely already run (verify then quarantine)
`Phase8\MergeCmsMedia`, `Phase8\MigratePatientDocuments`, `BackfillAbdmIdentifiers`, `BackfillExcessWallet`, `BackfillMembershipReceipts`, `ClinicalLibrary\BackfillMarketingEligibility`, `RelationshipSplitSharedPatients`. Confirm each ran in prod (check its target data) before archiving.

### 3d. Manual-only ops tools (keep, but decide)
`whatsapp:test`, `whatsapp:template`, `patients:merge` (web wizard exists), `finance:sync-staff-vendors` (observer may make it redundant), `dpdp:retention-report` (should it be *scheduled* instead?), `lab:seed-dummy-prices` (dev seeder in prod tree), smoke-test commands (`patients:merge-smoketest`, `patients:register-smoketest`, `security:selftest`, `voicenote:test`).

### 3e. Suspicious-but-uncertain
- `app/Services/Relationship/CommunicationEngine.php` — the "Phase 4 single send() gateway" has **no production caller**, only its test. Either adopt it or retire it; today it's a false safety promise.
- `app/Domain/Events/Relationship/{JourneyTransitioned, LeadCaptured}` — constructed only in tests, never dispatched.
- `appointments/edit.blade.php`, `treatments/create.blade.php` — code-referenced (live) but the July UI audit says nothing links to them in the UI. Different question; needs a UI-reachability pass.
- `.env.dusk.local` is **tracked** — confirm it holds no real secrets.
- `.bat` files: `fix-hq-access.bat` and `setup-hq.bat` are tracked privilege-escalation/setup helpers (hardcoded superadmin grant). Local-only; must never reach the server. `start-tulip.bat` targets the dead `C:\laragon` tree (stale). `p24push.bat` is a spent one-off (untracked).
- CMS frontend cluster: `public/js/content-management/{cms-init,cms-search,cms-case-viewer,cms-timeline}.js` + `public/css/content-management/cms.css` are never loaded by any blade, but blades actively call `window.cmsSearch?.…` — **the CMS search UI silently no-ops today. This is a missing-loader bug, not dead code. Fix or consciously retire; do not just quarantine.**

---

## 4. Duplicate Files (byte-identical or near)

| Keep | Redundant copy |
|---|---|
| `resources/js/communication/timeline.js` | `resources/js/timeline.js` (byte-identical, neither is a vite entry — see §6) |
| `resources/css/communication/followup.css` (vite entry) | `resources/css/followup.css` (byte-identical) |
| — | `dentfluence.tokens.css` in BOTH `public/css/` and `resources/css/` — zero references to either |
| `public/css/communication/timeline.css` (loaded via asset()) | `resources/css/communication/timeline.css` (identical, not a vite entry) |
| `resources/guides/{inventory-module-guide, relationship-engine-demo}.html` (served by routes) | stale copies in `docs/` |
| `Documents/business-discussion/*` | byte-similar `docs/{architecture-board, architecture-map, pricing-packages}.html` |
| `Dentfluence_Experience_Center_V4.html`, `Master_Register_Dashboard_V2.html` | their V3/V1 predecessors at root |

Double-shipping bug: `resources/css/communication/manager.css` is a vite entry **and** the identical `public/css/communication/manager.css` is loaded via `asset()` in `layouts/app.blade.php:620` — module CSS ships twice on every page.

## 5. Duplicate Logic

1. **Follow-up rules ×2 (both live):** `Services/Communication/FollowUpRulesService` vs `Services/Relationship/FollowUpRuleEngine`. `config/features.php:61` claims the legacy one is retired, yet `FollowUpController` still instantiates it. Two config files (`followup_rules.php`, `followup_settings.php`) serve both.
2. **Recall logic ×3:** `RecallEngineService` (scheduled daily 07:00) + `Automation/RecallAutomationRunner` + `Automation/RecallShadowRunner` — the runners' docblocks admit they mirror the legacy SQL. Three maintenance surfaces for one business rule. Resolves itself when the automation cutover completes; until then, any recall change must touch all three.
3. **Huddle board assembly ×4:** `Services/Huddle/HuddleService` (Tulip), `HuddleBoardApiService` (mobile), `Modules/Huddle/Services/HuddleAggregationService` (web), `RoleBasedHuddleService`.
4. Blade forks (all live, drifting): `components/followup/` vs `components/timeline/` add-note + schedule-followup modals; `_detail-card.blade.php` ×3 (communication/opportunities, relationship/opportunities, relationship/pipeline); `empty-state` ×2; three module layout shells (`layouts/app`, `relationship/layouts/app`, `marketing/layouts/app`).
5. Same-name controller clusters worth review (not API-mirror pairs): `AnalyticsController` ×3, `DashboardController` ×5, `TemplateController` ×3, `HuddleController` ×3, `TreatmentVisitController` ×3, bare `SettingsController` vs `Settings\SettingsController` (bare one is a dead empty shell — §6).
6. `StorePatientRequest`/`UpdatePatientRequest` duplicated in `Api/V1/` vs `Patient/` — acceptable web/API split, but validation rules can drift; consider shared rule objects later.

## 6. Dead Code Report (verified never-referenced)

### Controllers (7)
`CRMController` (empty shell), bare `SettingsController` (empty shell), `Marketing/MarketingController` (44 ln, unreachable), `Communication/ManagerController` (route now a redirect closure), `Communication/RecallSettingsController` (hollowed, 0 methods), `Communication/TemplateController` (hollowed, 0 methods), `ContentManagement/TreatmentVisitController` (comment-only stub). Plus **4 dead imports** in `routes/api.php:29–32` (webhooks — see §3a before touching).

### Other app/ classes (7)
`Models/CmsTreatmentCase`, `Models/Finance/FinanceCashbook`, `Models/HuddleNote` (superseded by `Modules/Huddle/Models/HuddleComment`), `Modules/Hq/Middleware/EnsureClinicHasPass`, `Services/ContentManagement/ClinicalMediaUploadService` (superseded by `ClinicalLibrary/ClinicalFileUploadService`), `Models/LabVendorPriceList` + `Services/Assistant/LabPriceListScanService` (abandoned lab-OCR pair — matches the reverted Lab Price OCR decision; retire as a unit).

### Blade views (63 zero-ref + 15 dead-by-chain; full lists from reachability closure)
- `communication/tasks/` (4 + partial task-card chain) — superseded by `tasks/` module
- `communication/{templates/index, templates/editor, recall-settings/index, opportunities/board, partials/coming-soon, partials/stats-row}` — routes are redirect closures; copies already in `under_review/`
- **`consultations/partials/` — 12 dead + 2 chained (`_tp-table`, `_tx-column`) — REPORT ONLY, active work zone, DO NOT MOVE**
- `marketing/overview/partials/` (8), `marketing/{blog/calendar, blog/_subtabs, brainstorm/partials/_tab-placeholder, campaigns/partials/_stub-tab}`
- `components/tasks/*` (4 + 2 chained), `components/marketing/*` (3 + 4 chained), `components/communication/{nav-item, empty-state, classification-picker, module-badge, filter-bar}`
- `content-management/partials/{clinical/tab, education/tab, marketing/tab, clinical/case-viewer, clinical/case-visit-history}` + 4 chained — **EXCEPT `clinical/results-table` which is LIVE** (`CmsController.php:173`)
- `partials/{sidebar, topbar, communication-sidebar, print-margin-vars}`, `layouts/partials/{communication, communication-topbar}`, `layouts/partials/communication-sidebar` (chained) — note `CommunicationServiceProvider.php:35` registers a View::composer on this dead view
- Single orphans: `crm/index`, `inventory/index`, `labs/index` (stray plural dir), `huddle/accountability` (controller returns JSON stub), `welcome`, `case-journeys/public/partials/block`, `relationship/_tabs`, `tasks/_row`
- `resources/views/layouts/app.blade.php.bak` (only .bak in tree)
- Dynamic-and-live (do not touch): `patients/tabs/*` — whitelisted via `PatientProfileService::LAZY_TABS`.

### Assets
Never referenced: 6 logo PNGs in `public/images/` (~4.9 MB), both `dentfluence.tokens.css`, `public/css/communication/prm.css` (retired per vite.config comment), `public/js/communication/lead-drawer.js` + resources twin, `resources/js/communication/tasks.js` + `tasks.css`, `resources/js/consultation.js`, `resources/js/timeline.js`, `resources/css/followup.css`, `resources/css/communication/timeline.css`, `public/guides/relationship-engine-demo.html` (redirect stub), `public/hq-check.txt`, `public/assets/` (empty dir). **`public/clear-opcache.php` — publicly reachable opcache-flush endpoint: security issue, remove from public/ regardless of cleanup.**

### Config
`config/constants.php`, `config/permissions.php` — both 0 bytes, zero references.

### Dead code in live files
- Large commented-out blocks (6): `MobileOtpController:129-147`, `Communication/TimelineController:79-95`, `ConsultationController:445-463` *(work zone)*, `Relationship/TodayController:510-527`, `UnifiedTimelineService:199-214`, `Traits/HashChained:67-84`.
- `config/communication.php:179` points to route `communication.tasks.index` which **does not exist** — will throw if rendered.
- Broken asset refs (fix, don't delete): `communication/tasks/index.blade.php:381` → missing `public/js/communication/tasks.js`; `followup/{queue:95, overdue:56}` → `asset('resources/js/…')` invalid path (vite entry exists, pages load nothing); `Models/{ClinicalMedia:82, ClinicalFile:184, EducationMedia:31, EducationTreatment:44}` → 5 placeholder images that don't exist in `public/images/`.

### Filesystem junk (delete-on-sight class, no quarantine needed — not code)
17 `.fuse_hidden*` orphans (8 in `database/migrations/`, 5 in `database/seeders/`, 8 in views/routes incl. `routes/.fuse_hidden000003e700000023` = old console.php copy), `.rename_test_b.tmp` (tracked, contents "hello"), `under_review/index.lock.stale`, `Documents/` shell-accident files (`canAccess('practice_protocols')]`, `toArray()`, `zioe2O0j`), stray Dusk log in `tests/Browser/console/`.

### Tests
Dead support files: `tests/Fixtures/ProgressGuard/FakeProgressReader.php`, `tests/Browser/Pages/{HomePage,Page}.php`, `tests/Unit/ExampleTest.php` (stock). Duplicate coverage: `AppointmentStatusFlowTest` fully subsumed by `AppointmentStatusCharacterizationTest`; `Characterization/AppointmentReminderCharacterizationTest` subsumed by the newer 7-case + cutover pair. Note: `tests/Browser/` (20 Dusk files) is in no phpunit suite — never runs in CI.

### Database
- Seeders, high-confidence dead: `SumitFirkePatientSeeder`, `DentalTreatmentsMasterSeeder2`, `HuddleTestUsersSeeder`, `ClearAllDummyDataSeeder`, `ClearDummyFinanceSeeder`, `ClearLeadsSeeder`, `RemoveDummyDataSeeder`. 17 more orphaned but ad-hoc-runnable — manual verification.
- Factories: only `UserFactory`, valid.
- Migrations: 0 duplicates by content (md5 over all 418). Two filename pairs to read by hand: `2026_05_26_100006_create_treatment_visits_table.php` (misnamed — it's an ALTER) and the two `add_staff_instruction_to_appointments_table` files (05_18 vs 05_28 — confirm the second isn't a redundant re-add).

## 7. Folder Structure Problems

- **33 empty scaffold directories:** `app/{Actions, DTOs, Helpers, Repositories, Workflows}` + all 28 subdirs of `app/Modules/{Appointment, Lab, Patient, Treatment}` — abandoned modularization; real code lives in Services/Models. Either commit to the module pattern or remove the skeletons (they mislead every "where does this go?" decision).
- `resources/views/labs/` stray plural dir (canonical is `lab/`); contains only the dead 9-line stub.
- `under_review/old_git_history_pre_v1/` is an entire **14 MB copied .git directory** inside the repo working tree.
- `Documents/` = 240 MB gitignored: 96 MB v1 zip, 52 MB deploy archives, and **`backup_before_backfill.sql` (5.9 MB) — production patient data unencrypted on disk.** Move out of the repo tree to encrypted storage; this is a DPDP exposure, not a tidiness item.
- `public/storage` is a real 43 MB directory, not a symlink (Windows/Laragon artifact) — local-only duplicate of `storage/app/public`; harmless but wasteful.
- Empty tracked-nothing dirs: `cron/`, `templates/`, `uploads/`, `.archive/backups/` (near-empty), `public/assets/`.
- ~30 loose deliverables at repo root (22 untracked docx/pdf/pptx/html + 8 tracked dated status .md) — belongs in `docs/` or archive.
- `docs/` (169 files): `docs/archive/` already exists; 9 patients phase docs superseded by `patients-module-master.md`, 2 appointments docs superseded by master, 6 dated one-offs, office binaries (pptx/pdf/xlsx).

## 8. Architecture Problems

1. **Route-name collisions with middleware divergence (security-adjacent):** `clinical-files.{index,show,store,update,destroy}` defined in both `web.php:218-222` (with `module:patients,edit|delete`) and `clinical-library.php` (own auth) — different URIs, different gates; later registration wins for `route()` resolution. Also `settings.tags.*` and `patients.tags.*` doubled (web.php vs tags-routes.php). 12 collisions total. **Resolve before any other route work.**
2. Unadopted gateway: `CommunicationEngine::send()` documented as the single send path; nothing uses it.
3. View composer registered on a dead view (`CommunicationServiceProvider.php:35`).
4. God classes: `Modules/Huddle/Controllers/HuddleController` (1320 ln), `Services/Relationship/TodayActionsEngine` (1277 ln), `Services/Inventory/InventoryService` (890 ln). Report-only; no refactor under Directive #004.
5. Half-completed retirements as a pattern: comm-manager, comm-templates, recall-settings, opportunities were all "retired" by copying to `under_review/` and swapping routes to redirects — but originals left live in the tree. Cleanup should finish these retirements first; they're pre-approved by their own history.
6. Silent no-op UI: CMS search (missing loader), followup queue/overdue modals (broken asset paths), `huddle/accountability` (JSON stub behind a real route).

## 9. Naming Problems

- `2026_05_26_100006_create_treatment_visits_table.php` — named `create`, actually an ALTER.
- `DentalTreatmentsMasterSeeder2` — numeric-suffix clone.
- Bare `App\Http\Controllers\SettingsController` shadows `Settings\SettingsController` (bare is dead — remove to end the ambiguity).
- `resources/views/labs/` vs `lab/`.
- `app/Services/Prm/` misnomer — known and accepted (LIVE PRE backend; do not rename during production push).
- `tasks/_row` vs live `tasks/_card`; `partials/topbar` (hardcoded "Dr. Sumit") vs `components/topbar`.

## 10. Recommendations (sequenced)

**Gate 0 — before any move:** land/commit the in-flight Consultations+Patients slice. No cleanup commits may interleave with feature work.

**Fix-first bugs surfaced by this audit (these move V1 to production; do them as their own micro-slices):**
1. Remove `public/clear-opcache.php` from public web root (security).
2. Resolve the `clinical-files.*` route-name/middleware collision (permission gate divergence).
3. Fix `config/communication.php:179` dead route reference.
4. Decide webhooks: wire the 4 controllers or formally retire them (known P0).
5. Fix or retire CMS search loader + followup modal asset paths (silent no-ops in live screens).
6. Move `Documents/backup_before_backfill.sql` (patient data) out of the tree.

**Slice B — zero-risk quarantine (proposed; needs approval). All untracked-or-junk, no git entanglement:**
- Delete outright (filesystem garbage, not code): 17 `.fuse_hidden*`, `index.lock.stale`, `Documents/` shell-accident files, stray Dusk log, `.rename_test_b.tmp` (git rm), `public/hq-check.txt`, empty dirs `public/assets/`, `templates/`, `uploads/` (confirm `cron/` unused on VPS first).
- Move 22 untracked root deliverables → `under_review/root_docs_2026-08-03/` (or `docs/archive/`): all `Dentfluence_*.{docx,pdf,pptx,html}`, `UI_CONSISTENCY_AUDIT_2026-07-31.md`, `p24push.bat`.
- `git mv` 8 tracked dated status docs at root → `docs/archive/`.
- Delete `start-tulip.bat` (stale C:\laragon path), 0-byte `config/{constants,permissions}.php`, `layouts/app.blade.php.bak`.

**Slice C — code quarantine, one category per commit, each prefixed by re-verification (needs approval per category):**
- C1: 7 dead controllers + 4 dead route imports + `routes/timeline.php` no-op require.
- C2: 7 dead app/ classes (retire LabVendorPriceList + LabPriceListScanService as a unit).
- C3: dead views EXCLUDING `consultations/` and `content-management/partials/clinical/results-table` — finish the half-done retirements first (comm-manager/templates/recall-settings/opportunities views whose copies already sit in `under_review/`).
- C4: dead assets (~5 MB), duplicate CSS/JS copies, deduplicate manager.css double-ship.
- C5: dead seeders (7 high-confidence), dead test-support files, subsumed duplicate tests.
- C6: docs/ archive consolidation (superseded phase docs → `docs/archive/`).
- C7 (verify-then-archive): spent one-shot commands from §3c.

**Never move:** migrations (418), `patients/tabs/*` (dynamic whitelist), parked feature clusters (Tulip/Voice/Automation/Workflow/Scan), `Prm/` namespace, anything in §3 until manually verified.

**Directive #004 note:** Slices B and C are hygiene, not production movement. Recommended posture: execute the six fix-first bugs and Slice B (30 min, zero risk), then park Slice C until after the Consultations production push — the dead files cost nothing while quarantine of live-adjacent code during an active slice costs risk.
