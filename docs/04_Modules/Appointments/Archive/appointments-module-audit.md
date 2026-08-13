# Appointments Module — Forensic Engineering Audit

**CEO Directive #004 · 2026-07-22 · Audit only — no code changed**
**Method:** Five parallel codebase sweeps (core module, cross-module consumers, comms/automation, API/mobile parity, frontend + bug hunt), followed by direct source spot-verification of every Critical/High finding. All line numbers verified against the working tree on 2026-07-22. Findings marked ⚠ where evidence is partial.

---

## 1. Executive Summary

Appointments is functionally rich but architecturally fragmented. The single biggest structural problem is that **the shared brain (`AppointmentService`) is only half-adopted**: the API controller uses it for everything; the 961-line web controller re-implements almost every write operation by hand; and a third booking path (the AI Assistant's `BookAppointmentTool`) bypasses it entirely with a weaker conflict check and no permission check. Every duplication, drift, and parity bug in this report traces back to that split.

Headline numbers:

- **3 independent booking implementations** (web controller, AppointmentService, BookAppointmentTool) — plus 4 dead booking modals out of 5 that exist.
- **The 7-value status vocabulary is independently redefined in ~12 places** (PHP + JS), including one typo variant (`noshow`) and one user-facing label conflict ("Checked In" vs "Waiting").
- The "active appointment" rule (`whereNotIn status cancelled/no_show`) is **copy-pasted in 15+ locations** across Huddle, Relationship, Insights, Lab, Reports, Assistant.
- **5 confirmed live bugs** shipping today, including a silently dead "Hide from calendar" feature and a daily scheduled reminder job that throws an exception on every real run.
- **2 permission holes**: web write routes gated on *view* permission only; API write routes hard-coded to `admin,front_desk`, locking doctors out of their own schedule on mobile.
- **Zero events/observers** on the Appointment model — reschedule, delete, and revert leave no Timeline trace and can never trigger automation.
- **No transactions or locks** around any booking write — the double-booking guard is check-then-write and race-exposed on all 6+ call sites.

The module is a strong candidate for the CEO#003 "polish existing" mandate: nothing here needs a new module, but the core needs consolidation before Appointments can be trusted as the clinic's workflow engine.

---

## 2. Current Architecture

### Layer map

| Layer | File | State |
|---|---|---|
| Model | `app/Models/Appointment.php` (100 lines) | `SoftDeletes`, `Auditable`, `BelongsToBranch` (global branch scope). **No status constants, no `$hidden`, no observer.** Two unused scopes (`forBranch`, `forDoctor`), one unused method (`isActive()`). |
| Service | `app/Services/AppointmentService.php` (424 lines) | Real shared brain: `assertSlotIsBookable()`, `overlapConflict()`, `blockedSlotConflict()`, `create()`, `updateStatus()`, `reschedule()`, `cancel()`, `createWalkIn()`, `blockSlot()`, `todayCounts()`, `filteredQuery()`. Its own docblock scopes it to the **API side only**. |
| Web controller | `app/Http/Controllers/AppointmentController.php` (961 lines) | Fat controller. Injects the service but only uses it for conflict checks; re-implements create/updateStatus/cancel/reschedule/destroy inline. Three inline validation rule-sets inside one 200-line `store()`. Zero FormRequests on the web side. |
| API controller | `app/Http/Controllers/Api/V1/AppointmentController.php` | Thin wrapper over the service + FormRequests (`StoreAppointmentRequest`, `WalkInRequest`, `BlockSlotRequest`) + `AppointmentResource`. The architecturally correct pattern. |
| Views | `resources/views/appointments/*` | `index.blade.php` is a 2,387-line embedded SPA (FullCalendar, drag-drop reschedule, status maps, slot builder, wa.me wiring — all inline JS). `today.blade.php` has its own independent status maps. `edit.blade.php` is a bare form with no conflict UI and a full-patient `<select>`. |
| Live modal | `resources/views/partials/appointment-modal-global.blade.php` (1,273 lines) | The ONLY live booking modal, included on every authenticated page via `layouts/app.blade.php:1128`. Owns Appointment / Walk-In / Block-Slot tabs + edit mode. |
| Authorization | `module:appointments` route middleware only | No `AppointmentPolicy`, no controller-level `authorize()`, no `@can` in any appointment blade. Legacy `role === 'admin'` hard bypass in `User::canAccess()` (User.php:204-206). |
| Events | — | **None.** `AppointmentCompleted` exists only as a docblock example in `app/Domain/Events/AbstractDomainEvent.php:11-21`. Lifecycle side effects are manual calls to `AppointmentActivityLogger` sprinkled through controller + service. |
| Schema | 12 migrations | Final table reconstructed in §12. `status` and `type` are DB enums; no composite index on `(branch_id, doctor_id, appointment_date)`; `patient_id` FK is `cascadeOnDelete` (hard-deletes appointment history if a patient row is ever hard-deleted, bypassing the appointment's own soft delete). |

### Booking entry points (the real map)

1. **Web full/scheduled booking** — global modal → `AppointmentController::store()` (inline logic).
2. **Web walk-in (new patient)** — `store()` path at :122-196, `Patient::create()` at :160 (no `created_by`).
3. **Web walk-in (existing patient)** — `store()` path at :200-257.
4. **API booking** — `AppointmentService::create()` (guarded, correct).
5. **API walk-in** — `AppointmentService::createWalkIn()`, `Patient::create()` at :364 (sets `created_by`, different field set from #2).
6. **AI Assistant** — `BookAppointmentTool.php:101` `Appointment::create()` direct: prefix string-match conflict check only (`appointment_time LIKE $time%`, :91), no blocked-slot check, no interval math, no RBAC, no Timeline logging.

Six entry points, three rule implementations, one of them materially weaker.

---

## 3. Appointment Touchpoint Map

| Module | Touchpoint | Nature | Evidence |
|---|---|---|---|
| Consultations | `consultations.appointment_id` nullable FK; store() adopts linked appointment's date | Read-only link; **no status write-back ever** (starting a consultation never moves the appointment to `in_chair`/`done`) | `ConsultationController.php:62-67`; `StoreConsultationRequest.php:150-151` |
| Consultations (sub-flows) | Same-Issue / Minor-Visit / Emergency / COHA consultations accept **no** appointment_id at all | Orphan consultations by design | `ConsultationController.php:374-593`; API `ConsultationController.php:339-349` |
| Patients | Walk-in booking mints Patient + TDC before arrival (violates locked lifecycle Appointment→Arrived→Registration) | Known debt, whitelisted in `PatientInvariantCheck.php:36-58` | `AppointmentController.php:160`, `AppointmentService.php:364` |
| Patients (merge) | `appointments` in `PatientMergeManifest::CHILD_TABLES` — blind patient_id re-parent | Correctly generic | `PatientMergeManifest.php:31`, `PatientMergeService.php:132-142` |
| Patients (journey) | `PatientJourneyService` → `UnifiedTimelineService::forPatient()` reads appointments as one timeline source | Correct delegation | `UnifiedTimelineService.php:118` |
| Treatment | `treatment_visits.appointment_id` nullable link; follow-ups create Tasks/queue rows, never Appointments | Boundary respected | `TreatmentVisitService.php:59,494` |
| Billing | `invoices.appointment_id` nullable, **no FK constraint**; null → "Unassigned" doctor bucket in provider reports | Honest handling; unguarded column | `Invoice.php:49`; `FinanceReportsController.php:372-403`; `2026_06_05_000001_create_billing_tables.php:39` |
| Dashboard (web) | Direct `Appointment::` queries for today's KPIs | Duplicates API dashboard | `DashboardController.php:20-24,63,83-86` |
| Dashboard (API) | Same KPIs via `AppointmentService::todayCounts()/filteredQuery()` — kept in sync **by comment** | `Api/V1/DashboardController.php:37-47` |
| Huddle | **Four** parallel read models of today's appointments: `HuddleService`, `HuddleBoardApiService` (comment: "keep both in sync"), `HuddleAggregationService` + `AppointmentToCardTransformer` (huddle_cards table), 3 separate HuddleControllers | Heaviest duplication cluster | §4 |
| Reports | Web + API report controllers independently aggregate appointment KPIs; identical clarifying comment duplicated verbatim in both | `ReportsController.php:35-38` vs `Api/V1/ReportController.php:54-55` |
| Insights | `EloquentAppointmentReadContract` (clean extraction) — but `RiskSignalCalculator::noShowRate()` re-derives "missed" interpretation beside it | `RiskSignalCalculator.php:93-107` |
| Relationship/PRE | `AppointmentActivityLogger` (booked/checked_in/completed/cancelled/missed → ActivityEngine), `AppointmentReminderEngine` (broken — §8), `TodayActionsEngine`, `YesterdayReviewService` | §5, §8 |
| WhatsApp | `whatsapp:send-reminders` (10:00, template, consent-enforced, deduped by `appt:{id}:{date}`); wa.me click-to-chat on calendar (`index.blade.php:2235-2276`) with consent guard in **shadow mode** | §10 |
| Recall | `RecallEngineService` — 6 triggers write to `communication_queue`, never auto-send, never create Appointments | Boundary respected |
| Lab | `LabCaseObserver:87-91` checks for upcoming appointment on `final_received` | Legitimate cross-module read |
| Marketing | `StandaloneAppointmentProvider` — manual-entry counts, never touches the table | Cleanly isolated |
| Tasks | Reminder Tasks reference appointments by **free-text** "Appointment ID: #{id}" in description — no FK | `AppointmentReminderEngine.php:82` |
| AI Assistant | `BookAppointmentTool` — parallel weak booking path | §2, §10 |
| Search | Global search indexes Relationships only; appointments not searchable | `SearchIndexProjector.php:19-21` |
| Inventory / Membership / Clinical Media | No appointment coupling found | — |

---

## 4. Duplicate Logic Report

### D1 — Booking/write logic: service vs web controller (root duplication)

Every write operation exists twice; web copies drift from service copies:

| Operation | Web copy | Service copy (API) | Drift observed |
|---|---|---|---|
| Create | `AppointmentController.php:260-315` | `AppointmentService.php:205-240` | Web has `autoDuration()` fallback; service falls back to flat 30 min |
| Update status | :318-359 (duplicate `match()` blocks) | :246-276 | Was drifted on cancel-logging until 2026-07-14 fix |
| Cancel | :362-385 | :324-336 | Independently written, same fields |
| Reschedule | :604-636 | :284-311 | Different 422 response shapes |
| Destroy | :639-648 | :318-321 | Two copies of one line |
| Walk-in | :117-257 (two branches) | :343-398 | Different Patient field sets (§6 of Bugs) |
| Conflict error message | :792-802 | :84-93 | Wording already differs ("resend with allow_overlap" vs "confirm to double-book") |
| Serialization | `formatAppointment()` :884-926 | `AppointmentResource.php` | Different field lists; doctor color computed two different ways |
| Today counts | `getTodayStatusCounts()` :828-843 | `todayCounts()` :184-199 | Web adds chair-utilization metrics the API never gets |

### D2 — Status vocabulary: ~12 independent definitions

Canonical set: `scheduled, checkin, in_chair, checkout, done, cancelled, no_show`. Redefined at: DB enum (create migration), `Appointment::isActive()` (3-value subset, unused), `AppointmentService::filteredQuery():161` (same subset, separate code), `AppointmentService::updateStatus()` (2 match blocks), `AppointmentService::todayCounts()`, web `updateStatus():321` + match copies, web `getTodayStatusCounts()`, API `updateStatus():111`, JS `STATUS_META` (`index.blade.php:1387-1395`), JS `statusLabel()/statusClass()` (`today.blade.php:268-289`), Blade `@switch`+`match()` (`show.blade.php:25-46`), JS sort order (`index.blade.php:~2125`). No single source of truth; labels and palettes already conflict.

### D3 — "Active appointment" rule: 15+ copy-paste sites

`whereNotIn('status', ['cancelled','no_show'])` independently re-implemented in: `LabCaseObserver:90`, `ReminderAutomationRunner:46,106`, `HuddleService:81,266`, `HuddleBoardApiService:323,337,631`, `AppointmentReminderEngine:43` (**with typo variant `noshow`** — dead branch, no such DB value), `TodayActionsEngine:466,670,991`, `YesterdayReviewService:116`, `RiskSignalCalculator:107`, `BookAppointmentTool:90`, `Api/V1/ReportController:50,53,78`, `Modules/Huddle HuddleController:175,205,510`.

### D4 — Working hours 08:00–22:00: 5 hardcoded copies

`AppointmentController.php:79-83` (index), :507-512 (create), :533-537 (edit) — identical PHP loops; `index.blade.php:1305-1325` (JS `buildTimeSlots()`); `index.blade.php:1500-1501` (FullCalendar `slotMinTime/slotMaxTime`). Only `appointments.daily_capacity_hours` AppSetting exists, and it feeds a percentage, not the grid. **No holiday handling exists anywhere in the module** (zero grep hits).

### D5 — Auto-duration map: 2 copies, drifted

PHP `autoDuration()` (`AppointmentController.php:928-961`) vs JS `AUTO_DURATION` (`index.blade.php:1414-1419`) — PHP has `x-ray => 15`, `veneer => 90`; JS has neither. A third source (`treatments.default_duration_minutes` via API `formOptions`) can disagree with both. The API/service has **no** fallback beyond flat 30.

### D6 — Dashboards & Huddle: 4+ copies of "today's appointments"

Web dashboard (direct Eloquent), API dashboard (via service), Huddle Blade path (`Modules/Huddle HuddleController:59-92` raw `DB::table`), `HuddleAggregationService` (raw SQL ×5 sites), `HuddleBoardApiService:214-266` (comment admits mirroring), plus `yesterdayFlow`/`comms` blocks with literal "keep both in sync" comments (`HuddleBoardApiService:692`).

### D7 — Reminder plumbing

"Tomorrow" computed as `now()->addDay()->toDateString()` in 3 places (`AppointmentReminderEngine:37`, `ReminderAutomationRunner:39,101`) + a 4th configurable variant (`WhatsAppSendReminders:39`); config knob `relationship_rules.today_actions.appointment_reminder_hours_ahead` exists but is **read nowhere**. Dedupe query copy-pasted verbatim between legacy engine (:57-61) and runner (:127-134). 6-month recall cutoff duplicated between `RecallEngineService` and `RecallAutomationRunner` (self-flagged in comments, guarded only by manual `automation:parity`).

### D8 — Message templates

`appointment_reminder`/`appointment_confirmation` copy maintained independently in `config/whatsapp.php:60-121` (Cloud API/Meta) and `config/communication.php:228-254` (wa.me click-to-chat). No shared source or parity test (recall/birthday templates DO have a DB-driven single source).

### D9 — Doctor colors

`app/Support/DoctorColors.php` (PHP "source of truth") vs `const DOC_COLORS` (`index.blade.php:1366`) — currently matching, kept in sync by comment only.

### D10 — Modals

Live `appointment-modal-global.blade.php` is a near-verbatim fork of dead `_modal.blade.php` (same function names, same double-booking confirm wording — compare `_modal:645-647` with global `:1038-1040`).

---

## 5. Loose Ends Report

1. **Broken-by-default reminder pipeline.** Legacy `AppointmentReminderEngine::generateReminders()` passes `created_by => null` (:79) and never sets `branch_id`, but `tasks.created_by`/`branch_id` are NOT NULL (`2025_01_01_000001_create_tasks_table.php:22-26`). The fixed replacement (`ReminderAutomationRunner`) only runs when `automation.engine` is ON — and it defaults to `false` (`config/features.php:60`) with no `.env` override found. A characterization test literally pins the exception (`AppointmentReminderCharacterizationTest.php:64-71`). **The 08:00 daily job throws and creates zero reminder-call tasks**, with no `onFailure()` alert on its schedule entry.
2. **Registered-but-never-fired notification.** `appointment_confirmed` notification type (→ `role:doctor`) is registered in `NotificationEngine.php:55-62` but has zero call sites. Doctors get no in-app alert on booking.
3. **Missing lifecycle events.** Reschedule, delete, and revert-status fire nothing — no `AppointmentActivityLogger` call, no Timeline entry, no rule can ever react (`AppointmentController.php:604-648, 388-412`; `AppointmentService.php:284-321`).
4. **No blocked-slot delete route.** Staff can block a doctor's slot (`appointments.block.slot`) but no web route exists to remove one.
5. **No conflict UI on edit.** `edit.blade.php` (65 lines) has zero client-side conflict checking and a full-branch patient `<select>` (no search).
6. **Stale lock registry.** `LOCKED_FEATURES.md:20-35` locks all five modal files as "feature complete and stable" — four are dead, one is structurally broken. The pre-commit hook now protects ~1,500 lines of unreachable code from cleanup.
7. **Unused model members.** `scopeForBranch`, `scopeForDoctor`, `isActive()` — no callers found.
8. **`cron/` directory is empty** — vestigial; all scheduling is `routes/console.php` (confirmed no competing schedule).
9. **Empty module folders.** `app/Modules/Appointment` exists and is empty — the module system was scaffolded and abandoned for this domain.
10. ⚠ **`allow_overlap` mobile contract is string-matched.** The mobile "Book anyway?" flow depends on the literal substring `allow_overlap` appearing in the 422 error message (comment at `AppointmentService.php:88-90`); rewording the message silently breaks mobile double-booking confirmation.

---

## 6. Dead Code Report

| Item | Evidence | Verdict |
|---|---|---|
| `resources/views/appointments/_modal.blade.php` (843 lines) | Only reference is its own header comment; no `@include` anywhere | **Dead** — the live global modal is its fork |
| `resources/views/components/appointment-modal.blade.php` (69 lines) | No `<x-appointment-modal>` usage; raw HTML nested inside an unclosed `<script>` (:34-44) | **Dead AND broken** |
| `resources/views/partials/add-appointment-modal.blade.php` (388 lines) | Never `@include`d; its `open-add-appointment` event listeners route to the global modal instead | **Dead** |
| `resources/views/partials/appointment-booking-modal.blade.php` (205 lines) | Never `@include`d; huddle's `open-booking-modal` listener calls the global modal | **Dead** |
| `queue_position`, `estimated_wait_minutes` columns | Added `2026_05_19_000001`, in `$fillable`, zero reads/writes/renders anywhere | **Dead schema** |
| Migration `2026_05_18_201650_add_staff_instruction...` | Empty `Schema::table` body in up() and down(); superseded by `2026_05_28_120000` which says so in its docblock | **Dead migration stub** (verified) |
| `show.blade.php:25-46` `@switch` block | Near-empty cases; `scheduled` case emits a stray nested `class="..."` into the parent span (malformed HTML); superseded by the adjacent `match()` | **Dead + emits broken markup** |
| `hidden_from_calendar` feature | Not in `$fillable` → mass-assignment silently discarded (verified: zero grep hits in Appointment.php) | **Dead feature** (see Bugs B1) |
| `appointment_confirmed` notification type | Registered, zero invocations | Dead plumbing |
| `appointment_reminder_hours_ahead` config knob | Declared, read nowhere | Dead config |
| `noshow` status branch (`AppointmentReminderEngine:43`) | No such DB value exists | Dead branch / false confidence |
| `dataset.isoDate` (`index.blade.php:1349-1353`) | Written (with the UTC date bug), never read | Dead output / landmine |
| Misleading migration filenames | `2026_06_18_100001_add_followup_type...` actually widens the `type` enum (no `followup_type` column anywhere); `2026_07_06_000002_add_flow_details...` actually adds `amount_to_collect`/`prep_item`/`chairside_assistant_id` (no `flow_details` column) | Confusing history, not runtime bugs |

---

## 7. Architecture Violations

1. **Half-adopted service layer.** `AppointmentService` is documented and built as the shared brain but the web controller re-implements it (§4 D1). The service's own docblock scopes it to API only — the split is by (bad) design, not accident.
2. **Fat controller.** 961 lines, 20 actions, inline validation (3 rule-sets inside one `store()`), formatting, utilization math, duration heuristics, patient creation — all in the controller. Zero web FormRequests.
3. **Business logic in Blade.** `index.blade.php` (2,387 lines) contains slot generation, status meta, auto-duration, conflict-check orchestration, drag-drop reschedule with revert-on-422, wa.me wiring — an unversioned SPA in a template. `today.blade.php` and `show.blade.php` carry their own status logic.
4. **No events, no observer.** Unlike `LabCase` (which has `LabCaseObserver`), `Appointment` has no observer and no domain events. All side effects are manual calls; any future writer (command, job, data fix) silently skips Timeline/rules.
5. **No policy / per-action authorization.** No `AppointmentPolicy`; route middleware checks only `view` for all 20 routes including destructive writes (§10 S1). The middleware supports `module:appointments,edit` — the patients module uses it; appointments never does.
6. **No transactions.** Zero `DB::transaction`/`lockForUpdate` in the entire module (grep-verified). Walk-in paths create Patient then Appointment unwrapped — a failure between the two leaves an orphan patient (and a burned TDC).
7. **Wrong ownership (see §11).** Booking rules live in three places; "active appointment" semantics live in 15; today's-schedule read models live in 4+.
8. **Model minting violation.** Two whitelisted `Patient::create()` calls in booking paths violate the PERMANENT `PatientService::register()` invariant, enforced only by whitelist exceptions in `PatientInvariantCheck.php`.
9. **Contract-by-comment.** At least four places keep parity via "keep both in sync" comments instead of shared code (`Api/V1/DashboardController:45-47`, `HuddleBoardApiService:88,692`, `DoctorColors.php` docblock) — one of which has already demonstrably failed (`noShow: 0`, Bug B6).

---

## 8. Potential Bugs

**Confirmed (spot-verified against source):**

| # | Bug | Evidence | Impact |
|---|---|---|---|
| B1 | **"Hide from calendar" silently no-ops.** `hidden_from_calendar` absent from `$fillable`; `hideFromCalendar()` mass-updates it; Eloquent silently discards; route returns `{ok:true}` | `Appointment.php:17-49` (zero grep hits for the column), `AppointmentController.php:651-659` | Feature dead; `index()`'s `where('hidden_from_calendar', false)` filter never has anything to hide |
| B2 | **Daily reminder job throws every real run.** `created_by => null` + missing `branch_id` vs NOT NULL columns; fixed runner gated behind `automation.engine=false` default; no `onFailure()` on the schedule entry | `AppointmentReminderEngine.php:73-83`; `config/features.php:60`; pinned by `AppointmentReminderCharacterizationTest.php:64-71` | Zero staff reminder-call tasks generated daily, silently |
| B3 | **Wrong-date blocked-slot fetches (always, not edge-case).** `.toISOString().split('T')[0]` on local-midnight Dates in an Asia/Kolkata app → previous day, every time | `index.blade.php:1542-1543, 2184-2185` (correct pattern exists 20 lines away at :1521-1524) | Blocked-slot overlay fetched for wrong range after blocking or filtering |
| B4 | **Modal date defaults to yesterday between 00:00–05:29 IST.** Global modal `reset()` uses the same UTC bug the team already fixed elsewhere in the same file (with an explanatory comment at :1111) | `appointment-modal-global.blade.php:1240, 1253` | New Appointment / Block Slot default date wrong for early-morning use |
| B5 | **Uncaught 500 on malformed time.** Only `reschedule()` validates `date_format:H:i`; `store()`/walk-in use bare `required`; `Carbon::parse("$date $time")` in the service has no try/catch | `AppointmentController.php:203,264` vs :608; `AppointmentService.php:45,115` | Same class as the prior prod booking 500 |
| B6 | **Huddle no-show stat hard-coded to 0.** DTO construction wires confirmed/checkedIn/inChair/done but leaves `noShow: 0` | `HuddleAggregationService.php:48` (verified) | Huddle board under-reports no-shows always |
| B7 | **Double-booking race (TOCTOU).** Check-then-write with no transaction/lock/DB constraint on all 6+ write sites; overlap check loads the day into PHP | `AppointmentService.php:105-133, 205-240`; zero `unique()`/`index()` in any migration | Two concurrent requests can both book the same slot — the exact scenario the 2026-07-14 hardening was meant to stop |
| B8 | **API rejects `follow-up` type.** `in:consultation,treatment` vs web's `in:consultation,treatment,follow-up` and the widened DB enum | `StoreAppointmentRequest.php:23` (verified) vs `AppointmentController.php:266,553` | Mobile cannot book a follow-up appointment |
| B9 | **API duration fallback under-books chairs.** No `autoDuration()` on service; flat 30-min fallback → an implant booked via API without explicit duration gets 30 min where web gives 90, and overlap math then trusts the wrong duration | `AppointmentService.php:227,384` vs `AppointmentController.php:928-961` | Silent chair under-booking + missed conflicts |
| B10 | **Status label conflict.** `checkin` = "Checked In" on the calendar, "Waiting" on Today | `index.blade.php:1389` vs `today.blade.php:270` | Staff-facing inconsistency |

**High-confidence (agent-verified, not independently re-checked):**

- B11 — `cascadeOnDelete` on `appointments.patient_id`: a hard patient delete hard-deletes appointment history, bypassing the appointment's soft delete (create migration).
- B12 — Soft-deleting an appointment never triggers the `nullOnDelete` on `consultations.appointment_id`/`treatment_visits.appointment_id` (soft delete = UPDATE, not DELETE) → relations silently return `null` without `withTrashed()`. `invoices.appointment_id` and `reviews.appointment_id` have **no FK constraint at all**.
- B13 — Web `store()` validates `patient_id` with bare `exists:patients,id` (no branch qualifier); API explicitly branch-checks. ⚠ Exploitability not fully traced (global BranchScope may mitigate reads but not the FK write).
- B14 — No state machine on status: `done → scheduled`, `cancelled → in_chair` are all accepted by the flat `in:` rule.
- B15 — Legacy patients without `relationship_id`: `RulesEngine` silently skips rules for them (`RulesEngine.php:78-96`), so `missed_appointment_followup` never fires for unlinked patients.
- B16 — `duration_minutes` bounds diverge: API 5–480, web store/update 10–240, web reschedule 10–480 (web internally inconsistent).

---

## 9. Performance Risks

1. **Overlap check materializes the whole day in PHP** — `overlapConflict()` does `->get()->first(closure)` instead of SQL interval math (`AppointmentService.php:105-133`). Fine at one clinic; degrades with multi-doctor/multi-branch growth.
2. **No composite index** on `(branch_id, doctor_id, appointment_date)` — the filter triple used by every overlap check, calendar query, and today queue. FK auto-indexes only.
3. **In-memory chair-utilization sum** — `getChairUtilization()` pulls rows to PHP to sum `duration_minutes` (`AppointmentController.php:845-882`), and runs alongside 7 status COUNT queries on `index()`, `updateStatus()`, `cancelWithReason()`, `revertStatus()`, `statusCounts()`.
4. **2,387-line Blade with inline JS** — no asset caching/versioning benefits; every calendar load ships the full SPA.
5. **`edit.blade.php` renders every branch patient into a `<select>`** — unbounded with patient growth.
6. Reads are otherwise well eager-loaded (`->with([...])` used consistently) — no confirmed N+1 in the module's read paths.

---

## 10. Security Risks

| # | Risk | Evidence |
|---|---|---|
| S1 | **Web: view permission grants write.** Entire route group is bare `module:appointments` (verified :265); `CheckModulePermission` defaults action to `view`. The seeded matrix gives Assistant `[1,0,0]` (view-only) — yet Assistant can hit `store/update/destroy/cancel/reschedule`. The patients module shows the correct convention (`module:patients,edit` / `,delete`). No `@can` in any appointment blade either, so the UI renders every button for everyone. |
| S2 | **API: doctors locked out of writes.** All 7 write endpoints hard-code `api.role:admin,front_desk` (verified :513-532), even though `EnsureApiRole` supports `module:` mode and other route groups use it. A doctor with `edit=1` can manage their schedule on web but not on mobile. High-impact parity/authorization drift. |
| S3 | **AI Assistant booking bypasses everything.** `BookAppointmentTool`: no `canAccess()`, no blocked-slot check, prefix-only conflict match (`LIKE $time%`, verified :91), direct `Appointment::create()` (verified :101), no Timeline logging. Corroborated by `AI_ARCHITECTURE_ASSESSMENT_2026-07-19.md:152,194`. Currently gated only by `config('assistant.enabled')` + auth. |
| S4 | **Consent guard is shadow-only for wa.me.** `WhatsAppLinkService::guardDecision()` blocks only when `guard.consent_required`/`guard.full_8factor` are ON; both default OFF. The calendar's WhatsApp button logs a consent decision but never blocks. (Cloud-API template sends ARE unconditionally consent-gated — the gap is only the manual channel.) Deliberate rollout posture, but live behavior = unenforced. |
| S5 | **Legacy admin bypass.** `User::canAccess()` hard-passes `role === 'admin'` users with no `role_id` (User.php:204-206). Documented transitional backdoor with no expiry plan. |
| S6 | **No field-level PHI redaction.** `notes`/`chief_complaint`/`cancel_reason` serialized identically for all roles on both surfaces; model has no `$hidden` (mitigated: API always uses `AppointmentResource`; no raw dump found). |
| S7 | **`allow_overlap` unaudited.** Deliberate double-bookings are indistinguishable from normal bookings in the activity trail. |
| S8 | Access denial for `module:appointments` is redirect(302)-with-flash for non-JSON requests (as designed, same as patients) — note for testing, not a bug. |

---

## 11. Ownership Corrections

| Logic | Currently lives | Should live | Why |
|---|---|---|---|
| Status vocabulary + "active" semantics | ~12 + 15 scattered sites | `Appointment` model: enum/constants + `scopeActive()` | Core domain fact; typo variant + label drift prove the cost |
| All booking/status/cancel/reschedule writes | Web controller re-implementations | `AppointmentService` (single brain, both surfaces) | The service already exists and is API-proven |
| AI assistant booking | Parallel implementation in `BookAppointmentTool` | Thin wrapper over `AppointmentService::assertSlotIsBookable()` + `create()` | Currently the weakest guard wins |
| Walk-in patient minting | `Patient::create()` ×2 in booking paths | `PatientService::register()` — or better, deferred to Arrival per the locked lifecycle | PERMANENT invariant; TDC burned on no-shows today |
| Today's-schedule read model | 4+ Huddle/dashboard implementations | One Appointments-owned read service consumed by Huddle/Dashboard/API | `noShow: 0` bug is the proof of failure |
| Dashboard KPIs (appointments slice) | Web + API dashboard controllers | Shared metrics service | Currently synced by comment |
| Lifecycle side effects (Timeline, rules, notifications) | Manual calls in controller + service | `AppointmentObserver` (follow the `LabCaseObserver` precedent) + real domain events | Closes the reschedule/delete/revert blind spots permanently |
| Consultation → appointment status sync | Nowhere (gap) | Appointments-owned listener reacting to Consultation creation | Four consultation store methods should not each poke appointments |
| Working hours / slot grid | 5 hardcoded copies | Settings-backed single source (already hinted by `daily_capacity_hours`) | Prereq for holidays/doctor schedules ever existing |
| Auto-duration | Web controller keyword map + JS copy | `treatments.default_duration_minutes` (data), service-side fallback | Data beats keyword heuristics; kills 3-way disagreement |
| Reminder windows | 3 hardcoded copies + dead config knob | The already-declared config key, actually wired | Knob exists, nothing reads it |
| Consultations' optional `appointment_id` link | ConsultationController | Stays — legitimate consumer read | Correct boundary |
| Lab / Billing / Merge / Marketing touchpoints | In place | Stay — clean consumer reads | Correct boundaries |

---

## 12. Technical Debt (schema + registry)

Final `appointments` table (reconstructed from 12 migrations): core booking fields + `type` enum(consultation, treatment, follow-up), `status` enum(7), walk-in/queue fields (2 dead), operatory/chair, cancel fields (`cancel_reason`, `previous_status` as plain string(30) beside enum statuses, `cancelled_party` enum), huddle fields (`amount_to_collect`, `prep_item`, `chairside_assistant_id`), `hidden_from_calendar`, soft deletes.

Debt register:

1. `PatientInvariantCheck` whitelist carries both appointment booking files as "temporary" exceptions (the third path, `PatientController::quickStore()`, already migrated correctly — the pattern to copy).
2. `LOCKED_FEATURES.md` blocks cleanup of 4 dead modal files via pre-commit hook.
3. Dead migration stub + two misleadingly named migrations pollute schema history.
4. `previous_status` string vs `status` enum typing inconsistency.
5. Dead schema columns (`queue_position`, `estimated_wait_minutes`) imply a queue/ETA feature that never shipped.
6. Characterization test pins a broken behavior (reminder QueryException) instead of the fix being default-on.
7. Task→appointment traceability is free-text string parsing, not a FK.
8. `app/Modules/Appointment` empty scaffold.

---

## 13. Refactoring Opportunities (audit-level, no implementation proposed)

1. **Single write path**: web controller delegates every write to `AppointmentService`; delete the inline copies. This single move eliminates D1 and most future drift.
2. **`AppointmentStatus` (and `AppointmentType`) backed enum** + `scopeActive()` — retires D2/D3 (~27 sites).
3. **`AppointmentObserver` + domain events** — retires the manual-call fragility, closes reschedule/delete/revert blind spots, and gives Automation real triggers.
4. **Wrap booking writes in `DB::transaction` with a `lockForUpdate` on the doctor's day** (or a DB-level exclusion approach) — closes B7; also wraps Patient+Appointment creation.
5. **Route actions**: `module:appointments,edit`/`,delete` on writes (web) and `api.role:module:appointments,edit` (API) — closes S1/S2 with existing middleware.
6. **One today's-schedule read service** consumed by Huddle ×3, dashboards ×2, reports ×2.
7. **FormRequests for web** (the API trio already models this).
8. **Delete dead files/columns** (needs LOCKED_FEATURES.md amendment first).
9. **Settings-backed working hours** — the prerequisite for holidays, per-doctor schedules, and any future online-booking surface.
10. **`BookAppointmentTool` → service wrapper** with explicit `canAccess()` (aligns with the V3 Copilot gap list in AI_ARCHITECTURE_ASSESSMENT).

---

## 14. Risk Assessment

**Broken in production today (no redesign needed to justify fixing):** B1 (hide feature dead), B2 (daily reminder job dead), B3/B4 (wrong dates in IST), B5 (500-class validation gap), B6 (huddle stat), S1/S2 (permission holes). These are all small-surface fixes.

**Latent but real:** B7 race condition (probability scales with bookings/minute — low today at one clinic, guaranteed eventually), B11/B12 FK integrity (fires on patient hard-delete / trashed-appointment reads), S3 (fires the day the AI assistant is enabled for staff), S4 (fires under DPDP scrutiny — note the 2027-05-13 deadline).

**Redesign risk:** the module has no test coverage for its web write paths (only the API side and one characterization test surfaced); the 2,387-line calendar blade is the highest-regression-risk surface to touch; mobile's `allow_overlap` string-matching contract must be preserved verbatim through any error-message refactor.

**What is NOT broken:** API controller architecture, consent gating on Cloud-API sends, WhatsApp reminder dedupe (`appt:{id}:{date}` — genuinely idempotent), recall/treatment/billing module boundaries, branch isolation (double-enforced), eager loading on reads, scheduler overlap protection. The redesign should preserve these.

---

## 15. Priorities

### P0 — broken or unsafe now
1. `hidden_from_calendar` fillable fix (B1) — one line.
2. Reminder pipeline: either flip `automation.engine` on (after parity check) or fix the legacy engine's `created_by`/`branch_id`; add `onFailure()` alert (B2).
3. Permission gates: `module:appointments,edit`/`,delete` on web writes; `module:` mode on API writes so doctors regain mobile schedule control (S1, S2).
4. IST date bugs: 4 call sites to `toLocaleDateString('en-CA')` (B3, B4).
5. `date_format:H:i` validation on all time inputs (B5).

### P1 — correctness & drift-stoppers
6. Transactions + locking around booking writes; wrap Patient+Appointment creation (B7).
7. Web controller delegates writes to `AppointmentService` (D1 — the structural fix).
8. Status enum + `scopeActive()`; fix `noshow` typo, `noShow: 0`, checkin label (D2, D3, B6, B10).
9. API accepts `follow-up`; unify duration fallback via `treatments.default_duration_minutes` (B8, B9).
10. `BookAppointmentTool` → service wrapper + `canAccess()` (S3) — or keep the assistant flag off until done.
11. Walk-in paths through `PatientService::register()` (or defer minting to arrival) — clears the invariant whitelist.

### P2 — cleanup & consolidation
12. Delete 4 dead modals, stub migration, dead columns, `show.blade` @switch; amend LOCKED_FEATURES.md.
13. One today's-schedule read service for Huddle/dashboards/reports (D6).
14. `AppointmentObserver` + events for reschedule/delete/revert; wire or delete `appointment_confirmed` notification.
15. Composite index `(branch_id, doctor_id, appointment_date)`; SQL-side overlap math.
16. Unify serializers on `AppointmentResource`; align duration bounds.
17. Decide consent-guard flag posture for wa.me before launch (S4).

### P3 — deferred by design
18. Settings-backed working hours; holiday calendar (currently absent entirely).
19. Queue/ETA feature (or drop the dead columns permanently).
20. Status state machine (valid-transition map).
21. Field-level PHI redaction by role; `allow_overlap` audit trail.
22. Appointment search integration; blocked-slot delete UI; edit-form conflict UI.

---

*End of audit. This document is the architectural foundation for the Appointments Module redesign (CEO Directive #004). No code was modified during this audit.*

