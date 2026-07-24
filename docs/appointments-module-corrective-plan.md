# Appointments Module — Corrective Execution Plan

**CEO Directive #005 · 2026-07-22 · PLANNING ONLY — no code changed**
**Baseline:** `docs/appointments-module-audit.md` (CEO#004, 2026-07-22)
**Mandate:** Make the *existing* module production-grade. No redesign, no new features, no scope creep. Preserve all current behaviour. Implement one reversible slice at a time, self-audit, wait for approval.

---

## 0. Validation Pass — findings re-checked against current code (2026-07-22)

Every finding in the audit was re-verified against the working tree today. Three items the audit already flagged as *previously-fixed* were re-confirmed fixed and are **excluded** from the corrective register so we don't re-open closed work:

| Audit item | Status now | Evidence |
|---|---|---|
| API forces `allow_overlap` / can't double-book | **Already fixed** — `allow_overlap` accepted + enforced on all 3 API write paths | `AppointmentService.php:217,296,358`; `StoreAppointmentRequest.php:31` |
| Mobile cancel-via-status leaves no trail | **Already fixed** — `cancel`/`missed` logging wired both surfaces | `AppointmentService.php:269,271` |
| No reschedule/delete API | **Already fixed** — routes live | `routes/api.php:528,531` |

Everything else in the audit is **confirmed still live**. Notably re-confirmed today: zero `DB::transaction`/`lockForUpdate` in the module; bare `module:appointments` on all web routes; `api.role:admin,front_desk` (not `module:` mode) on all API writes; `noshow` typo branch; `hidden_from_calendar` absent from `$fillable`; API `type` rule `in:consultation,treatment`.

---

## 1. Executive Summary

The audit produced ~45 distinct findings. They collapse into **one structural cause and a short tail of isolated defects.** The structural cause is a *half-adopted service layer*: `AppointmentService` is a working shared brain used correctly by the API, but the web controller and the AI assistant each re-implement booking independently. Fix that, and roughly half the duplication and drift findings disappear as a side effect.

This plan converts the audit into **12 independent, reversible slices**, deliberately ordered so that the cheapest, highest-safety, production-live bug fixes ship first (Slice 1), risk-bearing consolidation happens only after a test safety net exists, and cosmetic cleanup happens last when it can no longer hide a regression.

Guiding constraints for every slice:

- **Behaviour-preserving by default.** Where the audit found a *bug*, "preserve behaviour" means preserve the *intended* behaviour, and the slice ships a characterization test proving the corrected behaviour. Where the audit found *duplication*, the output must be byte-for-byte behaviourally identical.
- **Independently shippable.** Each slice compiles, passes tests, and can be deployed or reverted on its own. No slice is a prerequisite for the app to keep working.
- **No feature work.** Genuine *gaps* the audit noted (consultation→appointment status sync, holiday calendar, queue/ETA, appointment search, blocked-slot delete UI, field-level PHI redaction) are **explicitly out of scope** here and listed in §9 as "Deferred — not this directive."

Expected end state: same features, same screens, same URLs; but single-source status, single-path booking wrapped in transactions, correct permissions on both surfaces, a working reminder job, an event-driven audit trail, and ~1,700 lines of dead code removed.

---

## 2. Categorized Issue Register

Risk = production/data/security impact if left as-is. Complexity = S/M/L implementation effort. Regression = chance a fix breaks something else. "Indep?" = can be built without any other slice.

Recommended fixes and required test cases are listed beneath each category table, keyed by Issue ID.

### Category M — Production Bug Fixes (live defects)

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| M1 | "Hide from calendar" silently no-ops | `Appointment.php:17-49`; `AppointmentController.php:651-659` | Column missing from `$fillable`; mass-assignment discards it | High | — | S | Low | YES |
| M2 | Blocked-slot fetch + modal default use UTC `toISOString()` → wrong day in IST | `index.blade.php:1542-1543,2184-2185`; `appointment-modal-global.blade.php:1240,1253` | UTC conversion of local-midnight Date in Asia/Kolkata | High | — | S | Low | YES |
| M3 | Malformed `appointment_time` → uncaught 500 | `AppointmentController.php:203,264` (bare `required`); `AppointmentService.php:45,115` (no try/catch) | Only `reschedule()` validates `date_format:H:i` | High | — | S | Low | YES |
| M4 | Huddle no-show stat hard-coded `0` | `HuddleAggregationService.php:48` | Left unwired when siblings were fixed | Med | — | S | Low | YES |
| M5 | `checkin` label "Checked In" vs "Waiting" | `index.blade.php:1389` vs `today.blade.php:270` | Independent label maps | Low | E1 (ideal) | S | Low | YES |

*Recommended fixes:*
- **M1** — add `hidden_from_calendar` to `$fillable` (and cast `boolean`). *Tests:* feature test asserting `hideFromCalendar` route persists `true` and the appointment drops out of `index()`.
- **M2** — replace the 4 buggy `.toISOString().split('T')[0]` with `.toLocaleDateString('en-CA')` (the pattern already used correctly elsewhere in the same files). *Tests:* JS/manual: open modal before 05:30 IST simulated → date = today; block a slot on day view → overlay fetched for correct date. Add a Dusk/manual checklist item (no JS unit harness exists today).
- **M3** — add `date_format:H:i` to `appointment_time` on every write path; belt-and-braces `try/catch` around the two `Carbon::parse` sites returning a 422. *Tests:* post `"99:99"` and `"abc"` to store/walk-in → 422, not 500.
- **M4** — wire the real no-show count into `HuddleStatsDTO`. *Tests:* seed a `no_show` today → DTO `noShow == 1`.
- **M5** — align on one label. *Tests:* covered by E1's shared-map test once consolidated; interim assert both surfaces render the same label string.

### Category C — Permissions (authorization correctness)

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| C1 | Web: all writes gated on `view` permission only | `web.php:265`; `CheckModulePermission.php:22` | Route group uses bare `module:appointments`; default action = `view` | High | — | S | **Med** | YES |
| C2 | API: writes hard-coded `admin,front_desk` → doctors locked out on mobile | `api.php:513-532` | Old role list instead of `module:` mode | High | — | S | **Med** | YES |

*Recommended fixes:*
- **C1** — split the route group: reads keep `module:appointments`; writes get `module:appointments,edit`; `destroy` gets `module:appointments,delete`. Uses existing middleware capability (patients module already does this). *Tests:* Assistant (view-only) → 302/403 on store/update/destroy; Front-desk (edit) → allowed; delete permission distinct from edit.
- **C2** — change appointment write routes to `api.role:module:appointments,edit` / `,delete`. *Tests:* Doctor token can check-in/complete/reschedule via API; Assistant token blocked; matches web matrix.
- **Regression note:** both are Medium regression because they *tighten* access — must first enumerate every role currently relying on the loophole (seeded matrix in `RolePermissionSeeder.php:97-157`) and confirm intended writers all have `edit=1`.

### Category D — Booking Engine (the structural core)

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| D-A | Web controller re-implements every write instead of calling `AppointmentService` | `AppointmentController.php:117-359,604-648` | Service scoped "API only" | High | Slice-0 tests | L | **High** | NO (needs test net) |
| D-B | No transaction/lock around check-then-write → double-booking race (TOCTOU) | all 6+ write sites; no `unique` index | Guard added 07-14 without atomicity | High | D-A (ideal) | M | Med | YES (can wrap in place) |
| D-C | Walk-in mints Patient via `Patient::create()` (invariant violation, TDC burned) | `AppointmentController.php:160`; `AppointmentService.php:364` | Bypasses `PatientService::register()` | Med | Patient invariant | M | Med | YES |
| D-D | API duration fallback flat 30 min vs web `autoDuration()` → under-books chairs | `AppointmentService.php:227,384` vs `AppointmentController.php:928-961` | No shared duration source | Med | D-A (ideal) | M | Med | YES |
| D-E | API rejects `type=follow-up` (DB + web allow it) | `StoreAppointmentRequest.php:23` | Stale `in:` list | Med | — | S | Low | YES |
| D-F | `duration_minutes` bounds diverge (API 5-480; web 10-240/480) | `StoreAppointmentRequest.php:22` etc. | Independent rules | Low | — | S | Low | YES |

*Recommended fixes:*
- **D-A** — make the web controller delegate `store/updateStatus/cancel/reschedule/destroy` to the existing `AppointmentService` methods; keep the controller responsible only for HTTP shape (request → service call → response). Preserve exact JSON/redirect shapes and the literal `allow_overlap` token in the 422 message (mobile string-matches it). *Tests:* golden-master characterization tests captured in Slice 0 must pass unchanged.
- **D-B** — wrap each write in `DB::transaction()` with `lockForUpdate()` on the doctor's day-rows before the overlap re-check (or a DB-level guard). *Tests:* concurrent-booking test (two requests, same slot) → exactly one succeeds; existing single-booking tests unchanged.
- **D-C** — route walk-in patient creation through `PatientService::register()`; remove from `PatientInvariantCheck` whitelist once clean. *Tests:* walk-in new patient is relationship-linked; invariant command passes with appointment files removed from whitelist.
- **D-D** — expose one duration source (`treatments.default_duration_minutes`, already returned by API `formOptions`) and apply the same server-side fallback on both surfaces. *Tests:* API booking of a treatment with no explicit duration = same minutes as web.
- **D-E** — add `follow-up` to the API rule. *Tests:* API books `type=follow-up` → 201.
- **D-F** — pick one bound set (recommend web's stricter 10-min floor, 480 ceiling) applied both surfaces. *Tests:* boundary values accepted/rejected identically.

### Category E — Status Management

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| E1 | 7-value status set redefined ~12× (PHP+JS); labels/colours drift | model, service, both controllers, `index/today/show` blades | No enum/constant | Med | — | M | Med | YES |
| E2 | "Active" rule copy-pasted 15+× incl. dead `noshow` typo | `AppointmentReminderEngine:43` + 14 others | No shared scope | Med | E1 (ideal) | M | Med | YES |
| E3 | No state machine — any status→any status permitted | `updateStatus` inline `in:` rules | Flat validation | Low | E1 | M | Med | YES |

*Recommended fixes:*
- **E1** — introduce `AppointmentStatus` backed enum + label/colour map as the single PHP source; expose the same map to JS via one injected constant. Replace redefinitions incrementally; each replacement is behaviour-identical. *Tests:* one test asserting every surface resolves identical label+colour per status.
- **E2** — add `Appointment::scopeActive()` / `ACTIVE_STATUSES` const; replace the 15+ literals call-site by call-site (each independently). Kill the `noshow` dead branch. *Tests:* scope returns same rows the literal did on a fixture set.
- **E3** — (lower priority) add an allowed-transition map validated in the service. *Tests:* `done→scheduled` rejected; legal transitions unchanged. **Note:** E3 borders on behaviour *change* — must confirm no current workflow relies on arbitrary transitions before enabling.

### Category F — Events & Timeline

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| F1 | Reschedule/delete/revert fire no event → no Timeline trace, no rule | `AppointmentController.php:604-648,388-412`; `AppointmentService.php:284-321` | Manual logging, gaps | Med | D-A (ideal) | M | Med | YES |
| F2 | `appointment_confirmed` notification registered, never fired | `NotificationEngine.php:55-62` | Plumbing without producer | Low | — | S | Low | YES |
| F3 | No `AppointmentObserver` (unlike `LabCaseObserver`) → future writers skip side effects | model | Manual-call architecture | Med | D-A, E1 | M | Med | YES |

*Recommended fixes:*
- **F3/F1** — add an `AppointmentObserver` (follow `LabCaseObserver` precedent) that emits `AppointmentActivityLogger` calls on `created`/`updated(status dirty)`/`deleted`, covering the reschedule/delete/revert blind spots. Once the observer owns logging, remove the now-redundant manual calls carefully (behaviour-identical). *Tests:* reschedule/delete produce a Timeline entry; no double-logging vs pre-change counts.
- **F2** — decide: wire `appointment_confirmed` to fire on booking **or** delete the dead type. Recommend wiring (it's a registered, intended behaviour, not a new feature). *Tests:* booking creates a doctor notification of type `appointment`.
- **Caution:** F1/F3 must land *after or with* D-A so there is one write path to hook, else the observer fires inconsistently across the two implementations.

### Category G — Reminder Engine

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| G1 | Daily 08:00 reminder job throws every real run → 0 tasks | `AppointmentReminderEngine.php:73-83`; `config/features.php:60` | `created_by=null`/missing `branch_id` vs NOT NULL; fix gated behind `automation.engine=false` | High | — | M | Med | YES |
| G2 | No `onFailure()` on the reminder schedule entry → silent failure | `routes/console.php:269-273` | Missing handler | Med | — | S | Low | YES |
| G3 | "Tomorrow" date math duplicated 3× + dead config knob | `AppointmentReminderEngine:37`, `ReminderAutomationRunner:39,101`; `relationship_rules.php:37` | Copy-paste; knob never read | Low | G1 | S | Low | YES |

*Recommended fixes:*
- **G1** — two safe options: (a) fix the legacy engine to set `created_by` (system user id) + `branch_id`; or (b) flip `automation.engine` ON after running `automation:parity` and confirming the fixed runner matches intent. Recommend (a) as the smaller, more reversible change that doesn't alter unrelated recall/retry behaviour the flag also governs. *Tests:* run the command against a fixture with an appointment tomorrow → reminder Task created, no exception. Update the characterization test that currently pins the exception.
- **G2** — add `->onFailure(...)` alert to the schedule entry (mirror `audit:verify`). *Tests:* forced failure logs/alerts.
- **G3** — extract the "tomorrow" computation to one helper; wire or delete the `appointment_reminder_hours_ahead` knob. *Tests:* behaviour unchanged; single source returns same date.

### Category B — Duplicate Read Logic (non-booking)

| ID | Description | Location | Root Cause | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|---|
| B1d | "Today's schedule" read model duplicated 4× | Huddle ×3 + dashboards | Copy-paste, synced-by-comment | Med | — | L | Med | YES |
| B2d | Dashboard appointment KPIs duplicated web vs API | `DashboardController.php:20-24` vs `Api/V1/DashboardController.php:37-47` | No shared metrics service | Low | — | M | Med | YES |
| B3d | Two serializers (`formatAppointment` vs `AppointmentResource`) | `AppointmentController.php:884-926` vs `AppointmentResource.php` | Independent field lists | Low | D-A | M | Med | YES |
| B4d | Working hours 08-22 hardcoded 5× (+ no holiday support) | `AppointmentController.php:79-83,507-512,533-537`; `index.blade.php:1305-1325,1500-1501` | No settings source | Low | — | M | Med | YES |
| B5d | Auto-duration map duplicated 2× + drifted | `AppointmentController.php:928-961`; `index.blade.php:1414-1419` | Copy-paste | Low | D-D | S | Low | YES |
| B6d | Doctor colours PHP vs JS synced-by-comment | `DoctorColors.php` vs `index.blade.php:1366` | No shared injection | Low | — | S | Low | YES |
| B7d | WhatsApp templates duplicated (whatsapp.php vs communication.php) | config | Two send surfaces | Low | — | S | Low | YES |

*Recommended fixes (all behaviour-identical consolidations):*
- **B1d** — one Appointments-owned "today's schedule" read method consumed by all Huddle presenters + dashboards. *Tests:* each consumer returns the same rows/counts as before on a fixture.
- **B2d** — extract a shared appointment-KPI method; both dashboards call it. *Tests:* web and API dashboards return identical numbers.
- **B3d** — web `formatAppointment` delegates to `AppointmentResource` (or a shared presenter). *Tests:* payload keys unchanged per golden master.
- **B4d** — single working-hours source (settings-backed, defaulting to current 08-22) injected to PHP + JS. *Tests:* grid renders identical slots. (Holiday support stays out of scope — §9.)
- **B5d** — one duration source (ties to D-D); remove the JS/PHP copies. *Tests:* same defaults.
- **B6d** — inject `DoctorColors` palette to JS from the PHP source. *Tests:* colours identical.
- **B7d** — document the two surfaces or share copy where safe; lowest priority.

### Category H — Performance

| ID | Description | Location | Risk | Deps | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|---|
| H1 | Overlap check materializes whole day in PHP | `AppointmentService.php:105-133` | Low(now)/Med(scale) | D-A/D-B | M | Med | YES |
| H2 | No composite index `(branch_id,doctor_id,appointment_date)` | migrations | Low/Med | — | S | Low | YES |
| H3 | In-memory chair-utilization sum + 7 COUNTs per index load | `AppointmentController.php:845-882` | Low | — | S | Low | YES |
| H4 | `edit.blade` renders all branch patients in `<select>` | `edit.blade.php` | Low | J-cleanup | S | Low | YES |

*Recommended fixes:* H2 add index (new migration, additive, zero behaviour change — safest perf win). H1 move interval math to SQL once booking is consolidated. H3 use SQL `sum()`. H4 swap to the same debounced patient search the live modal uses. *Tests:* query result-set identical pre/post; index migration reversible.

### Category I — Dead Code

| ID | Description | Location | Risk | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|
| I1 | 4 dead booking modals (~1,500 lines) | `_modal`, `components/appointment-modal`, `partials/add-appointment-modal`, `partials/appointment-booking-modal` | Low | S | Low | YES (needs LOCKED_FEATURES amend) |
| I2 | Dead schema `queue_position`, `estimated_wait_minutes` | `2026_05_19` migration + `$fillable` | Low | S | Low | YES |
| I3 | Dead migration stub + 2 misnamed migrations | `2026_05_18_201650`, `..._followup_type`, `..._flow_details` | Low | S | Low | YES (leave applied; document) |
| I4 | `show.blade` `@switch` dead + emits malformed HTML | `show.blade.php:25-46` | Low | S | Low | YES |
| I5 | Dead config knob / dead notification / `noshow` branch | various | Low | S | Low | folded into G3/F2/E2 |
| I6 | `LOCKED_FEATURES.md` locks dead files | registry | Low | S | Low | YES (prereq for I1/I4) |
| I7 | Unused model members `scopeForBranch/forDoctor/isActive()` | `Appointment.php` | Low | S | Low | YES |
| I8 | Empty `app/Modules/Appointment` scaffold | dir | Low | S | Low | YES |

*Recommended fix:* delete after grep-proving zero references; **amend `LOCKED_FEATURES.md` first** (pre-commit hook blocks edits otherwise). Keep applied migrations in history (never delete applied migrations — project rule); just document I3 in the plan. *Tests:* full route crawl (`app:crawl-routes`) + test suite green after deletion.

### Category N — Technical Debt (data integrity / typing)

| ID | Description | Location | Risk | Cplx | Regr | Indep? |
|---|---|---|---|---|---|---|
| N1 | Soft-deleted appointment never nulls `consultations/treatment_visits.appointment_id`; `invoices/reviews.appointment_id` have no FK | migrations | Med | M | Med | YES |
| N2 | `patient_id` `cascadeOnDelete` hard-deletes appt history | create migration | Med | M | Med | YES |
| N3 | `previous_status` string vs enum typing | migration | Low | S | Low | YES |
| N4 | Task→appointment link is free-text, not FK | `AppointmentReminderEngine.php:82` | Low | M | Med | YES |
| N5 | Characterization test pins broken reminder behaviour | test | — | S | Low | folded into G1 |

*Recommended fixes:* N1/N2 require a **data-safety review before any schema change** (per project rule: no destructive migrations without approval); recommend read paths use `withTrashed()` on the appointment relation as the low-risk interim, deferring FK/onDelete changes. N3/N4 are cosmetic — defer to a debt slice or leave documented. *Tests:* relation-loads-after-soft-delete test for N1.

### Excluded (out of scope this directive)

Consultation→appointment status auto-sync, holiday calendar, queue/ETA feature, appointment search, blocked-slot delete UI, field-level PHI redaction, `allow_overlap` audit trail, wa.me consent-flag go-live decision (product/launch call, not corrective code). See §9.

---

## 3. Corrective Roadmap

Ordering logic: **live bugs first** (cheap, high value, low regression) → **safety net** → **permissions** (isolated, high value) → **structural consolidation** (highest regression, needs the net) → **derivative cleanups** (only safe once structure is unified) → **cosmetic/dead code last** (can't hide a regression there).

```
Slice 1  Production Bug Fixes (P0, M1-M5, G2)         ← ship first, tiny, live defects
Slice 2  Characterization Safety Net (tests only)     ← enables everything risky
Slice 3  Permissions Hardening (C1, C2)               ← isolated, high value
Slice 4  Status Single Source of Truth (E1, E2)       ← non-breaking, unblocks reads/events
Slice 5  Booking Service Consolidation (D-A, D-B)     ← the structural fix (needs Slice 2)
Slice 6  Booking Parity & Validation (D-C,D-D,D-E,D-F)← rides on unified path
Slice 7  Events & Observer (F1, F2, F3)               ← hooks the now-single write path
Slice 8  Reminder Engine Cleanup (G1, G3)             ← independent vertical
Slice 9  Read-Model Consolidation (B1d,B2d,B3d,B5d,B6d)← safe once status/booking unified
Slice 10 Performance (H1-H4)                          ← additive index + SQL sums
Slice 11 Dead Code & Frontend Cleanup (I1-I8, B4d)    ← last; LOCKED_FEATURES amend first
Slice 12 Documentation & Freeze (+ N-debt review)     ← master doc, invariant, sign-off
```

Slices 1, 3, 8 are fully independent and could ship in any order. Slice 5 is the only High-regression slice and is gated on Slice 2.

---

## 4. Slice-by-Slice Plan

For each slice: **what WILL change · what will NOT change · why this is the safest approach**, plus Objective, Files, Risk, Regression Tests, Rollback, Acceptance.

### Slice 1 — Production Bug Fixes (P0)
**Objective:** Kill the five live defects that affect users today, each a one-to-few-line change.
**Issues:** M1, M2, M3, M4, M5, G2.
**WILL change:** add `hidden_from_calendar` to `$fillable`; swap 4 JS date calls to `toLocaleDateString('en-CA')`; add `date_format:H:i` + parse guards; wire real no-show count; align `checkin` label; add reminder `onFailure()`.
**WILL NOT change:** any booking logic, any query shape, any permission, any UI layout. No status vocabulary refactor (M5 is a one-string alignment, not the E1 refactor).
**Why safest:** each change is local, additive, and independently revertible; none touches the write path or shared services.
**Files:** `Appointment.php`, `AppointmentController.php`, `AppointmentService.php` (guard only), `index.blade.php`, `appointment-modal-global.blade.php`, `HuddleAggregationService.php`, `today.blade.php`, `routes/console.php`.
**Risk:** Low.
**Regression tests:** hide-persists test; time-validation 422 tests; huddle no-show DTO test; manual IST date checklist; reminder failure-alert check.
**Rollback:** revert per-file; no data migration involved.
**Acceptance:** all five defects demonstrably fixed; full suite + route crawl green; no behaviour change elsewhere.

### Slice 2 — Characterization Safety Net (tests only)
**Objective:** Capture current booking/status/serialization behaviour as golden-master tests *before* touching structure. Directly answers the audit's "no web write-path test coverage" risk.
**WILL change:** add tests only. Zero production code.
**WILL NOT change:** any behaviour whatsoever.
**Why safest:** it is the insurance policy for Slices 5-9; a test-only slice cannot regress production.
**Files:** `tests/Feature/Appointments/*` (new).
**Risk:** None (test-only).
**Regression tests:** N/A — this *is* the regression harness (store/walk-in/update-status/cancel/reschedule/destroy request+response snapshots, both web and API; overlap 422 message incl. literal `allow_overlap`).
**Rollback:** delete tests.
**Acceptance:** every current write path has a passing golden-master test; suite documents the exact current JSON/redirect shapes.

### Slice 3 — Permissions Hardening
**Objective:** Enforce edit/delete distinctly from view on both surfaces.
**Issues:** C1, C2.
**WILL change:** web route group split into view/edit/delete middleware; API write routes to `module:` mode.
**WILL NOT change:** the permission matrix values, controller logic, or any behaviour for correctly-permissioned users.
**Why safest:** uses middleware capabilities already proven in the patients module; no business logic touched; fully reversible by restoring the single middleware line.
**Files:** `routes/web.php`, `routes/api.php`.
**Risk:** Med (tightening — must confirm intended writers hold `edit=1`).
**Regression tests:** role-matrix matrix test (each role × each action) web + API; doctor-can-write-on-mobile test; assistant-blocked test.
**Rollback:** restore prior middleware strings.
**Acceptance:** view-only roles cannot mutate; doctors regain API write; matrix matches `RolePermissionSeeder`.

### Slice 4 — Status Single Source of Truth
**Objective:** One canonical status definition (enum + label/colour) replacing ~12 redefinitions and the "active" rule's 15+ copies.
**Issues:** E1, E2 (E3 deferred to §9/optional).
**WILL change:** add `AppointmentStatus` enum + `Appointment::scopeActive()`/`ACTIVE_STATUSES`; inject one status-meta constant to JS; replace redefinitions call-site by call-site (each behaviour-identical); remove `noshow` dead branch.
**WILL NOT change:** the status values themselves, DB enum, or any user-visible label/colour (they're unified to the current correct values).
**Why safest:** additive first (introduce the source), then mechanical replacements each provable equal on fixtures; no write-path change.
**Files:** `Appointment.php` (+ new enum), both controllers, both services, `index/today/show` blades, the 15+ "active" call-sites (replaced incrementally).
**Risk:** Med (breadth), each edit Low.
**Regression tests:** per-status label+colour parity across surfaces; `scopeActive` returns same rows as the literals on a mixed fixture.
**Rollback:** revert per call-site; enum is additive.
**Acceptance:** grep shows one status source in PHP + one injected JS map; no drift; suite green.

### Slice 5 — Booking Service Consolidation (structural)
**Objective:** Web controller delegates all writes to `AppointmentService`; wrap writes in transactions + locks.
**Issues:** D-A, D-B.
**WILL change:** web `store/updateStatus/cancel/reschedule/destroy` call the existing service methods; each service write wrapped in `DB::transaction` + `lockForUpdate` on the doctor's day.
**WILL NOT change:** request validation surface, JSON/redirect shapes, the literal `allow_overlap` 422 token, or any user-facing behaviour — enforced by Slice 2's golden masters.
**Why safest:** the target methods already exist and are API-proven; Slice 2 pins exact current behaviour; transactions are wrapped around existing logic, not rewritten.
**Files:** `AppointmentController.php`, `AppointmentService.php`.
**Risk:** **High** (highest in the plan).
**Regression tests:** all Slice 2 golden masters unchanged; new concurrent-booking test (one winner); orphan-patient-on-failure test.
**Rollback:** revert controller to inline copies (kept in git); transaction wrapper is isolated.
**Acceptance:** one booking path; golden masters pass byte-identical; race test passes.

### Slice 6 — Booking Parity & Validation
**Objective:** Remove the API/web behavioural divergences on the now-single path.
**Issues:** D-C, D-D, D-E, D-F.
**WILL change:** walk-in patient via `PatientService::register()`; one duration source + shared fallback; API accepts `follow-up`; unified duration bounds.
**WILL NOT change:** existing successful bookings' outcomes for already-valid input.
**Why safest:** rides on Slice 5's unified path so each fix lands in one place; each is a small, independently testable rule/route change.
**Files:** `AppointmentService.php`, `AppointmentController.php`, `StoreAppointmentRequest.php`, `WalkInRequest.php`, `PatientInvariantCheck.php` (whitelist removal).
**Risk:** Med.
**Regression tests:** walk-in patient is relationship-linked; API/web same duration for same treatment; API `follow-up` 201; boundary tests; invariant command green.
**Rollback:** per-issue revert.
**Acceptance:** web/API booking parity for type, duration, patient creation; invariant whitelist clean.

### Slice 7 — Events & Observer
**Objective:** Single event/observer path so every status change (incl. reschedule/delete/revert) logs to Timeline and can trigger rules.
**Issues:** F1, F2, F3.
**WILL change:** add `AppointmentObserver`; move logging into it; wire `appointment_confirmed` notification (or delete if product declines); cover reschedule/delete/revert.
**WILL NOT change:** existing logged events' payloads or counts (no double-logging — manual calls removed as observer takes over).
**Why safest:** depends on Slice 5's single write path; follows the established `LabCaseObserver` pattern; verified by before/after log-count tests.
**Files:** new `AppointmentObserver.php`, provider registration, `AppointmentController.php`/`AppointmentService.php` (remove redundant manual calls), `NotificationEngine.php` producer.
**Risk:** Med.
**Regression tests:** reschedule/delete produce exactly one Timeline entry; existing booked/checkin/cancel/missed counts unchanged; doctor notified on booking.
**Rollback:** unregister observer, restore manual calls.
**Acceptance:** no lifecycle blind spots; no duplicate logging.

### Slice 8 — Reminder Engine Cleanup
**Objective:** Make the daily reminder job actually create tasks; de-duplicate its date math.
**Issues:** G1, G3.
**WILL change:** fix `created_by`/`branch_id` on the legacy engine (or flip `automation.engine` after parity — recommend the former); single "tomorrow" helper; wire/remove the dead knob; update the characterization test to assert success.
**WILL NOT change:** the WhatsApp send pipeline, recall engine, or any unrelated automation the feature flag governs.
**Why safest:** fully independent vertical; recommended fix avoids flipping a broad feature flag; the pinned-exception test is updated deliberately.
**Files:** `AppointmentReminderEngine.php`, `ReminderAutomationRunner.php` (shared helper), `AppointmentReminderCharacterizationTest.php`, `relationship_rules.php` (knob).
**Risk:** Med.
**Regression tests:** command creates a reminder Task, no exception; dedupe still prevents duplicates; no change to WhatsApp job.
**Rollback:** revert engine edits; restore test.
**Acceptance:** 08:00 job produces tasks daily; no silent throw.

### Slice 9 — Read-Model Consolidation
**Objective:** One source for "today's schedule", dashboard KPIs, serialization, duration/colour maps.
**Issues:** B1d, B2d, B3d, B5d, B6d.
**WILL change:** extract shared read/serialize/injection helpers; point all consumers at them.
**WILL NOT change:** any returned numbers, rows, payload keys, colours (byte-identical consolidation, proven on fixtures).
**Why safest:** only safe after status (Slice 4) and booking (Slice 5) are unified so the shared helper is well-defined; each consumer migrated independently with a same-output test.
**Files:** Huddle services/controllers ×3, both dashboards, `AppointmentResource`/`formatAppointment`, `DoctorColors` JS injection, `index.blade.php`.
**Risk:** Med.
**Regression tests:** each consumer returns identical output pre/post on fixtures.
**Rollback:** per-consumer revert.
**Acceptance:** single read model per concern; outputs unchanged.

### Slice 10 — Performance
**Objective:** Add the missing index, move sums/overlap to SQL, fix the edit `<select>`.
**Issues:** H1, H2, H3, H4.
**WILL change:** additive composite index migration; SQL `sum()` for utilization; SQL interval overlap; debounced patient search on edit.
**WILL NOT change:** any result set or computed number.
**Why safest:** index is additive/reversible; other changes verified to return identical data.
**Files:** new migration, `AppointmentService.php`, `AppointmentController.php`, `edit.blade.php`.
**Risk:** Low-Med.
**Regression tests:** query-equivalence tests; index migration up/down; overlap results identical to PHP version.
**Rollback:** drop index; revert query changes.
**Acceptance:** same data, fewer/cheaper queries; index present.

### Slice 11 — Dead Code & Frontend Cleanup
**Objective:** Remove proven-dead code; unify working-hours source.
**Issues:** I1-I8, B4d.
**WILL change:** amend `LOCKED_FEATURES.md`; delete 4 dead modals, dead `show.blade` `@switch`, dead columns (via additive-safe path/review), unused model members, empty module dir; single working-hours source (defaults to 08-22).
**WILL NOT change:** any live screen or route; the live global modal untouched.
**Why safest:** last, so a hidden dependency would already have surfaced; every deletion grep-proven + route-crawl verified; column drops go through the no-destructive-migration review.
**Files:** the dead files, `LOCKED_FEATURES.md`, `Appointment.php`, working-hours sources.
**Risk:** Low.
**Regression tests:** route crawl + full suite green post-deletion; calendar renders identical slots.
**Rollback:** git restore; column drops deferred if review flags risk.
**Acceptance:** dead code gone; registry accurate; grep clean.

### Slice 12 — Documentation & Freeze
**Objective:** Master reference + debt disposition + sign-off.
**Issues:** N1-N4 disposition, freeze doc.
**WILL change:** write `docs/appointments-module-master.md`; record N-debt decisions (defer vs fix); update `PatientInvariantCheck` note; mark module V1.0.
**WILL NOT change:** code (except any approved N-debt interim like `withTrashed()` reads).
**Why safest:** documentation and explicit debt disposition, mirroring the Patients-module freeze playbook.
**Files:** `docs/appointments-module-master.md`, memory.
**Risk:** None.
**Acceptance:** master doc exists; every audit finding is closed, deferred-with-reason, or excluded; module frozen.

---

## 5. Dependency Graph

```
Slice 1 (bugs) ─────────────── independent, ship first
Slice 3 (perms) ────────────── independent
Slice 8 (reminder) ─────────── independent

Slice 2 (test net) ──► Slice 5 (booking consolidation) ──► Slice 6 (parity)
                                     │
                                     ├─► Slice 7 (events/observer)   [also needs Slice 4]
                                     └─► Slice 9 (read-model)        [also needs Slice 4]

Slice 4 (status SSOT) ──► Slice 7, Slice 9   (soft dep: cleaner if status unified first)

Slice 9, Slice 5 ──► Slice 10 (perf: SQL overlap rides on unified booking)

everything ──► Slice 11 (dead code last) ──► Slice 12 (freeze)
```

Hard dependencies: **5 requires 2**; **7 requires 5**; **11 before 12**. Soft (recommended) dependencies: 4 before 7/9; 6 after 5; 10 after 5/9. Slices 1, 3, 8 have no dependencies and can run in parallel with the 2→5 spine.

---

## 6. Risk Matrix

| Slice | Prod Risk if skipped | Regression risk of doing it | Blast radius | Reversibility | Net priority |
|---|---|---|---|---|---|
| 1 Bug fixes | **High** (live defects) | Low | Local | Easy | **Do first** |
| 2 Test net | Med (no safety) | None | Tests only | Trivial | High |
| 3 Permissions | **High** (authz holes) | Med (tightening) | Routes | Easy | High |
| 4 Status SSOT | Med (drift) | Med (breadth) | Module-wide | Per-site | Med-High |
| 5 Booking consolidation | Med (dup/race) | **High** | Write path | Medium | High (gated on 2) |
| 6 Parity/validation | Med (divergence) | Med | Booking rules | Easy | Med |
| 7 Events/observer | Med (blind spots) | Med | Lifecycle | Medium | Med |
| 8 Reminder | **High** (job dead) | Med | Reminder vertical | Easy | High |
| 9 Read-model | Low (dup) | Med | Huddle/dash | Per-consumer | Med |
| 10 Performance | Low now | Low-Med | Queries | Easy | Med-Low |
| 11 Dead code | Low | Low | Deletions | Easy (git) | Low |
| 12 Freeze | — | None | Docs | Trivial | Final |

Highest-attention slice: **5** (only High regression) — mitigated entirely by shipping **2** first.

---

## 7. Testing Strategy

**Layered:**

1. **Golden-master (Slice 2)** — snapshot every current write request/response (web + API) *before* structural change. These are the regression firewall for Slices 5-9. Must include the literal `allow_overlap` 422 message (mobile contract).
2. **Unit** — enum/scope equivalence (Slice 4), duration fallback, validation rules, dedupe logic.
3. **Feature/integration** — per-issue behaviour tests listed in §2 (permissions matrix, transaction/concurrency, walk-in registration, observer log counts, reminder task creation).
4. **Concurrency** — explicit double-booking race test (two simultaneous requests, assert one winner) for Slice 5's lock.
5. **Data-integrity** — soft-delete-then-load-relation test (N1); invariant command (`patients:invariant-check`) after Slice 6.
6. **System** — `php artisan app:crawl-routes` (existing crawler) after every slice; full `phpunit` suite green before requesting approval.
7. **Manual/Dusk checklist** — IST date behaviour (M2), calendar drag/reschedule, since there is no JS unit harness today.

**Gate per slice:** suite green + route crawl clean + golden masters unchanged (for structural slices) + the slice's own acceptance tests passing. Per project rule, all Artisan/migration commands are listed for the user to run manually; no terminal execution here.

---

## 8. Acceptance Checklist

**Per-slice (repeat each time):**
- [ ] What-changes / what-doesn't / why-safest stated and approved before coding
- [ ] Only the slice's Issue IDs touched; no unrelated fixes bundled
- [ ] Compiles and passes full suite independently
- [ ] Golden-master tests unchanged (structural slices)
- [ ] Route crawler clean
- [ ] Rollback path confirmed (git revert / drop migration)
- [ ] Self-audit written; regressions checked; **approval obtained before next slice**

**Module-complete (Slice 12):**
- [ ] All Category M/C/D/G production + authz + booking issues resolved
- [ ] Status has one PHP source + one JS map (E1/E2)
- [ ] One booking write path, transaction-wrapped, race-tested (D-A/D-B)
- [ ] Web/API parity: type, duration, patient creation, permissions
- [ ] Reminder job creates tasks daily, alerts on failure
- [ ] Observer covers create/status/reschedule/delete; no double-logging
- [ ] Dead code removed; `LOCKED_FEATURES.md` accurate
- [ ] N-debt each closed / deferred-with-reason / excluded
- [ ] `docs/appointments-module-master.md` written; module frozen V1.0
- [ ] No feature added; no behaviour lost

---

## Implementation Mode (reaffirmed)

One slice at a time. After each: (1) self-audit, (2) verify no regressions, (3) **wait for approval**. Never continue automatically. Recommended first action after roadmap approval: **Slice 1 (Production Bug Fixes)** — highest user value, lowest risk — followed immediately by **Slice 2 (test net)** to unlock the structural work safely.

*Planning only. No code was modified in this step.*
