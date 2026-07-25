# Patient Journey V1.1 — Phase & Slice Execution Roadmap

**Date:** 2026-07-25 · **Status:** ROADMAP FOR APPROVAL — nothing implemented.
**Basis:** `patient-journey-v1_1-architecture-proposal.md` (accepted working direction) + the four forensic audits.
**Operating rule once execution begins:** ONE SLICE → STOP → report (files, migrations, tests, PASS/FAIL, manual checks, risks) → CEO approval → next slice. Every slice ends with the smoke suite green.

---

## DEPENDENCY QUESTION — answered up front

**Should Clinical Truth freeze before the Relationship Spine?** Strictly: no hard technical dependency exists between them. The spine's mechanical slices (columns, RelationshipWorkService, outcome routing, history reader, claim) touch only `communication_queue`/`comm_activity_logs` and depend on nothing clinical. Clinical Truth (decisions, item progress) feeds only the *consumers* of the spine: the detectors (P5), recall exclusions (P6), and board context quality (P4).

**Recommendation nonetheless: Clinical Truth (P2) before Spine (P3), strict sequence.** Reasons ranked by the CEO's own criteria: *correctness* — freezing the facts before wiring their consumers prevents rework of detector/outcome semantics against moving clinical definitions; *testability* — P3's parity tests are cleaner when the outcome vocabulary can already reference real decision events; *reversibility* — both phases are independently reversible either way (tie); *staff continuity* — P2 is invisible to staff, P3 changes outcome behavior on screens staff use daily; doing the invisible phase first means staff-facing change arrives later, more polished, and once. The only parallelism I endorse: if a P2 slice stalls in CEO review, P3.1 (schema) and P3.2 (service skeleton) may be prepared in a branch — but nothing merges out of order. Independent-anytime slices are listed under CRITICAL PATH.

---

## MASTER PHASE MAP

| Phase | Name | Objective | Slices | Data Risk | Staff Impact | Freeze Output |
|---|---|---|---|---|---|---|
| P0 | Foundation & Safety | Committed baseline, production truth, broken instruments fixed | 3 | NONE | Near-zero (3 board cards start working) | Trusted baseline + census |
| P1 | Access Control | Close the audited permission holes | 3 | NONE | Low (some users lose write access they shouldn't have had) | Enforced permission matrix |
| P2 | Clinical Truth | Decisions & progress become stored facts | 3 (+Gate B) | ADDITIVE ONLY | None (backend truth) | Clinical facts API for all consumers |
| P3 | Relationship Spine | One work store, one write door, one outcome engine, one history | 6 | ADDITIVE ONLY | Medium (outcomes now always schedule-or-resolve; history visible) | The spine contract |
| P4 | Action Board V2 | One operational surface with why/history/owner/lanes | 3 (+Gate C) | NONE | Pilot users only | Board V2 validated by pilot |
| P5 | Journey Integration | Handoffs: appointments↔spine, doctor queue, detectors | 3 | ADDITIVE ONLY | Medium (doctor queue; new obligations appear) | Closed-loop journey wiring |
| P6 | Recall & Recovery | Recall on true attendance with real exclusions | 3 (+Gate A) | ADDITIVE ONLY (no backfill yet) | Low | Correct recall engine, awaiting data |
| P7 | Migration & Cutover | Controlled live-data conversion + staff cutover | 7 | BACKFILL + ROW STATE CHANGE + CUTOVER | High (board V2 becomes primary) | Live clinic on the new spine |
| P8 | Stabilisation & Retirement | Retire legacy, re-audit, freeze V1.1 | 3 | DELETIONS (approved only) | Low (old screens disappear) | 🔒 PATIENT JOURNEY V1.1 FROZEN |

**Staff cutover map (explicit):** current Action Board remains primary through P0–P6. Web/mobile shared outcome workflow activates in P3 (same screens, better behavior). Board V2 available to pilot users at P4.3. Old follow-up/communication screens remain fully usable through P6. At P7.6: Board V2 primary for all staff, old surfaces read-only. Retirement only in P8 after the observation window.

---

# PHASE 0 — FOUNDATION & SAFETY

**Objective:** make the ground trustworthy: everything committed, baselines green, production flags known, and the three silently-broken board categories fixed so later measurements are honest. **Staff-visible:** almost nothing; up to three board cards begin showing real items. **Data impact:** none. **Dependencies:** none. **Exit:** smoke green both modes, census documented, health signal live.

### Slice 0.1 — Repo hygiene & baseline
1. **Purpose:** start from a committed, reproducible, smoke-green state.
2. **Scope:** git only — commit/tag pending work (Patients Phase 4 files, WhatsApp wiring, smoke suite); no app code.
3. **Reuse:** `dentfluence:smoke`, existing test suites.
4. **New:** none (CI wiring if absent).
5. **Must not break:** everything — this slice proves the baseline.
6. **Data impact:** NO DATA CHANGE.
7. **Rollback:** git tag `pre-journey-v1.1`.
8. **Tests:** full smoke (rollback + `--commit` modes), full Feature suite, `app:crawl-routes`.
9. **Manual (CEO):** log in locally; open one patient (e.g., Sushil), an appointment, the Action Board, Huddle — confirm all behave exactly as yesterday.
10. **Freeze:** all suites green, tag pushed, deploy runbook confirmed. → 🔒
### Slice 0.2 — Production truth census
1. **Purpose:** know exactly what is live before changing anything.
2. **Scope:** read-only console command `journey:census` + a docs artifact; VPS `feature_flags` dump (`automation.engine`, `today.projection`, `tasks.human_system_split`, `case_acceptance.enabled`, `guard.consent_required`…); scheduler inventory; row counts + status breakdowns for leads, follow_ups, tasks, communication_queue (by purpose/status), treatment_opportunities, comm_activity_logs, plans, visits; snapshot + restore drill on local.
3. **Reuse:** census query patterns from the audits.
4. **New:** the census command (read-only).
5. **Must not break:** nothing writes.
6. **Data impact:** NO DATA CHANGE.
7. **Rollback:** n/a.
8. **Tests:** unit test census math on seeded data.
9. **Manual (CEO):** review the census document — especially which recall path is live and the true 1,810 breakdown.
10. **Freeze:** census doc approved; backup restore verified on local. → 🔒
### Slice 0.3 — Fix broken instruments
1. **Purpose:** repair verified bugs that falsify daily operations: 3 dead board categories, the Treatment Intelligence crash, Huddle's two dead queries, and the three lying comments.
2. **Scope:** `TodayActionsEngine.php` (`new_enquiry`→`new_lead`; `isOverdue()`→`is_overdue` accessor; `daysUntilExpiry()`→`days_remaining` accessor), `TreatmentController.php:126` (`price`→`unit_price`), `HuddleController.php:88` + "0 pending" pill, comment fixes in api.php/communication.php/relationship.php; **plus** a category-health record (last run/last error per category, small admin panel or log surface) so silent death is unrepeatable.
3. **Reuse:** existing per-category try/catch (kept).
4. **New:** health-signal recording only.
5. **Must not break:** existing working categories' output (characterize before touching); board performance.
6. **Data impact:** NO DATA CHANGE.
7. **Rollback:** revert commit.
8. **Tests:** feature tests per fixed category (seed a fresh lead/overdue opportunity/expiring membership → appears); regression snapshot of working categories; smoke.
9. **Manual (CEO):** add a test lead → appears under New Enquiries within 24h window; open Treatment → Intelligence tab (no error); Huddle shows plausible numbers.
10. **Freeze:** all three categories provably populate; health panel shows 13/13 OK. → 🔒

**🔒 PHASE 0 FREEZE GATE:** baseline tag + census are now the reference truth; the board's category set and health signal must not be casually altered by later phases.

# PHASE 1 — ACCESS CONTROL

**Objective:** close the audited permission holes before any new surface or remote-work exposure. **Staff-visible:** view-only accounts lose clinical write ability; any staff without patients-edit lose lead-conversion ability — CEO must confirm intended role grants first. **Data impact:** none. **Dependencies:** P0. **Exit:** permission matrix tests enforce the intended grants.

### Slice 1.1 — Permission characterization
1. **Purpose:** record what every role can currently do before changing it (the Appointments-module method).
2. **Scope:** tests only: matrix tests for consultation/plan/visit/prescription/lab/relationship routes, web + API.
3. **Reuse:** `AppointmentPermissionMatrixTest` pattern; `CheckModulePermission`/`EnsureApiRole`.
4. **New:** test files.
5. **Must not break:** n/a (tests only).
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** n/a.
8. **Tests:** the deliverable itself; smoke.
9. **Manual (CEO):** review the produced current-state matrix and mark the intended matrix (which roles hold patients-edit, who may convert leads, who may reject lab cases).
10. **Freeze:** intended matrix signed by CEO. → 🔒 **(CEO GATE: role grant decisions)**
### Slice 1.2 — Clinical write gates
1. **Purpose:** clinical writes require edit-level permission; API role coherence.
2. **Scope:** `routes/web.php` (both bare `module:patients` groups :196/:296 → `,edit` on writes, `,delete` on deletes), `routes/api.php` (consultation DELETE off view-gate; treatment-plan writes include doctor roles per 1.1 matrix); Blade button visibility aligned (`@can` equivalents).
3. **Reuse:** existing middleware; no new machinery.
4. **New:** none.
5. **Must not break:** doctors' and front-desk's legitimate daily flows (matrix-verified); frozen patient routes untouched.
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** revert route middleware.
8. **Tests:** 1.1 matrix flipped to intended-state assertions; route crawler per role; smoke.
9. **Manual (CEO):** log in as a view-only user: plan Accept button absent and direct POST rejected; as doctor on mobile: can write a treatment plan.
10. **Freeze:** intended matrix green web+API; zero staff complaints in 48h local trial. → 🔒
### Slice 1.3 — Prescriptions, relationship, lab gates
1. **Purpose:** module gates where none exist.
2. **Scope:** `routes/prescriptions.php` (module gate per matrix), `routes/relationship.php` (module gate; `convertToPatient` requires patients-edit), lab reject server-side admin check in `LabController::transition`/API.
3. **Reuse:** same middleware. 4. **New:** none.
5. **Must not break:** doctors prescribing; reception's lead pipeline work; PatientService::register invariant (conversion still routes through it).
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** revert.
8. **Tests:** matrix extensions; feature test: non-admin lab reject → 403; smoke.
9. **Manual (CEO):** as front-desk: pipeline works, convert works (if granted); as assistant: prescription pages denied per matrix.
10. **Freeze:** matrix fully green. → 🔒

**🔒 PHASE 1 FREEZE GATE:** the permission matrix tests become a permanent regression wall; later phases must add new routes *into* the matrix, never bypass it.

# PHASE 2 — CLINICAL TRUTH

**Objective:** decisions and progress become stored, owned, evented clinical facts (Patients-owned; PRE consumes only). **Staff-visible:** none yet (Reject/Defer buttons arrive with Board work in P4/P5 context; existing accept flow unchanged on the surface). **Data impact:** additive schema only; no backfill until P7. **Dependencies:** P1 (writes must be permission-safe first). **Exit:** four decision types + item progress emit consumable events; all existing flows intact.

> **⚖️ DECISION GATE B — Plan decision semantics (before Slice 2.2).**
> **Recommendation to approve/modify:** *Presented is a plan lifecycle event, not a patient decision.* `plan_decisions` stores patient decisions only — `accepted | rejected | deferred` (append-only: plan_id, decision, decided_at, recorded_by, reason, defer_until, source). Presentation is recorded as `presented_at` on the plan (first) + an Activity event per (re)presentation — because a plan can be presented many times but *decided* by the patient; and PRE's chase trigger is precisely "presented event exists AND no decision row". Consequences: `markPresented` stays a lifecycle action; decision service = 3 verbs; timeline shows presentations and decisions distinctly. **CEO must approve semantics before the table is created.**

### Slice 2.1 — ConsultationService
1. **Purpose:** one transactional write path for all consultation variants; kill the 9-site scatter.
2. **Scope:** new `app/Services/ConsultationService.php`; `ConsultationController` (web) + `Api/V1/ConsultationController` + `CohaController` become thin callers; shared validation; `doctor_id` fallback on standard store; typed-variant edit routing fix; consultation+COHA+specialty-module writes wrapped in one transaction.
3. **Reuse:** `StoreConsultationRequest`, existing controller logic (moved, not rewritten), TreatmentVisitService as the template.
4. **New:** the service class only.
5. **Must not break:** all 5 consultation workflows' current field behavior (characterize first); consultation JSON/encrypted casts; timeline `addConsultations`; frozen profile tabs.
6. **Data impact:** NO DATA CHANGE (schema untouched).
7. **Rollback:** controllers retain old code paths behind a flag for one release.
8. **Tests:** NEW feature tests per variant (create/update/COHA — the audit found zero); transaction-rollback test (fail module write → no consultation row); API parity test; smoke.
9. **Manual (CEO):** create one of each consultation type for a test patient (incl. emergency + COHA); verify show/print/profile timeline identical to before; pull the network cable mid-save (or simulated failure) → no half-saved consultation.
10. **Freeze:** all variants green via the service; old inline paths deleted. → 🔒
### Slice 2.2 — plan_decisions + decision service *(after Gate B)*
1. **Purpose:** Accept/Reject/Defer become recorded facts through one door; in-clinic reject finally syncs the opportunity.
2. **Scope:** migration `plan_decisions` (per Gate B) + `presented_at` column; `TreatmentPlanAcceptanceService` → extended (accept writes decision row + keeps `accepted_at` mirror; new `reject(reason)`, `defer(until, reason)`); `TreatmentPlanController::update` blocked from free `status='cancelled'` (redirects to reject verb); `syncStage('declined')` on reject; ActivityEngine events `plan.presented/accepted/rejected/deferred`; web UI: Reject/Defer actions where Accept lives today; API endpoints for the two new verbs (doctor-gated per P1).
3. **Reuse:** acceptance service (all 5 entry doors keep working), OpportunitySync, ActivityEngine.
4. **New:** table, two verbs, events.
5. **Must not break:** existing accept/revert flows incl. Smart Presentation + Case Acceptance public doors; billing-guarded revert; `accepted_at` consumers (mirror maintained); timeline's current derived display (unchanged until P7 backfill).
6. **Data impact:** ADDITIVE SCHEMA ONLY.
7. **Rollback:** feature flag `journey.plan_decisions`; mirrors mean consumers never depended on the table.
8. **Tests:** unit (decision transitions, one-live-chain invariant), feature (reject → opportunity declined; defer stores date), all 5 acceptance-door regression tests, event emission tests, smoke.
9. **Manual (CEO):** Ramesh scenario — reject his plan in-clinic: opportunity leaves the open pipeline as Declined with reason. Kavita — defer to next month: decision row visible with date. Accept still behaves exactly as before.
10. **Freeze:** three verbs live on web+API; Ramesh/Kavita scenarios pass; merge-manifest updated with `plan_decisions`. → 🔒
### Slice 2.3 — Item progress + one completion rule
1. **Purpose:** chairside work advances plan items; exactly one way a plan becomes clinically complete.
2. **Scope:** `TreatmentVisitService` (inside existing txn: visit items with `treatment_plan_item_id` set item status started/in_progress/completed from stage data; `mark_treatment_complete` routes through a single `completePlan()` rule that requires items complete or explicit override-with-reason); `TreatmentPlanBillingService` stops flipping clinical `status` (emits event; billing_progress untouched); `TreatmentPlanController::update` status whitelist narrowed.
3. **Reuse:** visit service transaction; existing item delete-guards (finally reachable).
4. **New:** none structural — a status writer and one rule.
5. **Must not break:** billing per-tooth invoicing math; visit save flow; recall task creation (still fires on completion); protected-item guards.
6. **Data impact:** NO SCHEMA CHANGE (existing `status` column gains a writer). Existing rows untouched.
7. **Rollback:** flag `journey.item_progress`.
8. **Tests:** unit (item transitions), feature (Prakash scenario: RCT visit 1 → item in_progress, crown pending, plan NOT complete), billing regression, completion-override path, smoke.
9. **Manual (CEO):** Prakash — log RCT visit against his plan: item shows In Progress; try Mark Treatment Complete with crown pending → warned/override; complete both items → plan completes once.
10. **Freeze:** Prakash scenario + billing regression green. → 🔒

**🔒 PHASE 2 FREEZE GATE:** decision and progress facts (tables, events, verbs) are now the *only* way clinical truth changes; later phases consume them and may not add parallel writers. The dead recall trigger's precondition now exists — but stays dead until P6 rewrites it.

# PHASE 3 — RELATIONSHIP SPINE

**Objective:** one work store with ownership, precise next-actions, source references, one write door, one outcome engine on both surfaces, visible history, and claim protection. **Staff-visible:** medium — outcomes on the existing board start scheduling retries instead of dead-ending; attempt history appears in the drawer; mobile and web finally agree. **Data impact:** additive columns; new rows business-as-usual; no historical row changes. **Dependencies:** P1 (gates), P0 census. P2 recommended-first (see dependency answer) but not technically required. **Exit:** parity contract tests green; every outcome resolved-or-scheduled.

### Slice 3.1 — Spine schema
1. **Purpose:** give obligations ownership, claim, precise timing, provenance, and tenancy.
2. **Scope:** one migration on `communication_queue`: `assigned_to_id` FK, `claimed_by`, `claimed_at`, `next_action_at`, `source_type`, `source_id`, `branch_id` (all nullable) + indexes; `CommunicationQueue` model fillable/casts/relations; forward dual-write of `assigned_to` name string for old readers.
3. **Reuse:** everything existing on the table (verified schema).
4. **New:** 7 columns.
5. **Must not break:** every existing reader/writer (11 writers, 6 surfaces) — columns nullable, no behavior change this slice.
6. **Data impact:** ADDITIVE SCHEMA ONLY. Existing rows: all new columns NULL (meaning preserved).
7. **Rollback:** drop-column down-migration (safe: nothing consumes yet).
8. **Tests:** migration up/down on seeded copy; full existing recall/board feature suites unchanged; smoke.
9. **Manual (CEO):** Recall Pipeline, Today's Actions, Missed Calls all render identically to yesterday.
10. **Freeze:** zero behavioral diff proven. → 🔒 **(CEO GATE: schema migration)**
### Slice 3.2 — RelationshipWorkService
1. **Purpose:** one write door for obligations (open/record/close/reconcile), with branch + source stamping.
2. **Scope:** new `app/Services/Relationship/RelationshipWorkService.php`; queue lifecycle methods (`autoClose/dismiss/ignore/logAttempt`) invoked only via the service; first producers routed: `RecallEngineService::createQueueItem`, manual create (`RecallPipelineController::store`, drawer, `CommunicationController::logForm`), Api/V1 creates. (Remaining producers migrate in P5/P7.)
3. **Reuse:** `CommunicationQueue` methods (wrapped, not rewritten), `hasOpenQueueItem` dedup.
4. **New:** service class.
5. **Must not break:** row shape old readers expect; existing dedup behavior; B2B/Lab writers (untouched this slice).
6. **Data impact:** NO DATA CHANGE (new rows richer: branch/source populated).
7. **Rollback:** flag `relationship.work_service` — producers fall back to direct writes.
8. **Tests:** unit (service invariants: every open row gets branch, source where known), feature regression on producers, smoke.
9. **Manual (CEO):** create a manual recall for Sunita — appears exactly as before, now with owner field settable and branch stamped.
10. **Freeze:** routed producers green; row audit shows new rows fully stamped. → 🔒
### Slice 3.3 — One outcome engine — WEB
1. **Purpose:** web outcomes stop dead-ending: every outcome → RESOLVED or NEXT ACTION; attempts counted on web.
2. **Scope:** `TodayController::logAction/closeAction` route through `OutcomeAutomationService` via RelationshipWorkService; `ActionOptionList` gains `outcome_key` mapping (seed + `SyncCallOutcomeClosesTask`-style sync command, dry-run default); engine enforces resolved-XOR-next_action_at; `waiting_for_patient` rows stay board-visible (ATTEMPTED semantics); `no_answer` increments `attempt_count` + sets retry per policy; 'will call back' schedules (stops closing).
3. **Reuse:** OutcomeAutomationService verbatim (+ invariant guard), option lists, drawer UI.
4. **New:** the mapping + invariant.
5. **Must not break:** mandatory-reason dismiss; birthday WhatsApp; done-lane annotation; option-list customizations (sync is additive).
6. **Data impact:** NO SCHEMA CHANGE. New behavior on new actions only; historical rows untouched.
7. **Rollback:** flag `relationship.web_outcomes` → old logAction path.
8. **Tests:** contract test per outcome key (state table: closes? schedules? attempt++?), feature (Amit scenario: 'No answer' → attempt_count 1, retry visible, card moves to attempted), smoke.
9. **Manual (CEO):** Amit — log No Answer on web: card shows "Attempt 1, retry <time>" instead of looking untouched; log Will Call Back: card schedules tomorrow instead of vanishing forever.
10. **Freeze:** outcome contract table green; Amit scenario passes. → 🔒
### Slice 3.4 — One outcome engine — MOBILE
1. **Purpose:** mobile uses the identical path; web/mobile drift structurally impossible.
2. **Scope:** `Api/V1/RelationshipController` outcome/log endpoints → same service calls; mobile option vocabulary served from `ActionOptionList`; `HuddleBoardApiService` onto `ClinicFlowRange`; delete mobile-only outcome divergences.
3. **Reuse:** everything from 3.3; existing Flutter screens (payload-compatible).
4. **New:** none beyond adapter code.
5. **Must not break:** existing mobile app versions (response shape preserved); OutcomeAutomation's appointment-booking path (now shared).
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** flag per endpoint.
8. **Tests:** THE parity suite — same outcome key via web route and API route asserts identical resulting state (per key); mobile contract tests; smoke.
9. **Manual (CEO):** Samiksha logs No Answer on mobile; Runali refreshes web — sees attempt 1 + retry time immediately (the shift-handover scenario).
10. **Freeze:** parity suite green and added to CI as permanent wall. → 🔒
### Slice 3.5 — History reader + strict-log guards
1. **Purpose:** attempts become visible where staff work; history becomes undeletable with auditable corrections.
2. **Scope:** drawer history panel reading `comm_activity_logs` for the obligation (all-time, not today-only — fixes `annotateCallState` blindness); model deletion guards on `CommActivityLog` (+`Activity`): no delete routes, `booted()` throw, `void()` correction rows (`meta.voids_id`) rendered struck-through; every service action logs actor+surface in meta.
3. **Reuse:** `comm_activity_logs` as-is (meta JSON carries surface/outcome_key/next_action); existing drawer.
4. **New:** panel + guards + void mechanism.
5. **Must not break:** the two legacy readers (manager/show, b2b/show) — they keep working until P8; log write performance.
6. **Data impact:** NO SCHEMA CHANGE; strictly more rows.
7. **Rollback:** hide panel; guards are pure code.
8. **Tests:** unit (delete throws; void preserves original), feature (history renders chronologically with actors), smoke.
9. **Manual (CEO):** open Amit's card — see both prior attempts with who/when/outcome; try to imagine deleting one — no UI exists; log a correction — original remains visible struck-through.
10. **Freeze:** handover story reconstructable entirely from the drawer. → 🔒
### Slice 3.6 — Claim protection
1. **Purpose:** two staff (incl. remote) can't unknowingly work the same obligation.
2. **Scope:** claim/release endpoints (web+API) via the service; TTL auto-release (~30 min); board card shows "Samiksha is on this" ; claim conflict returns who/when.
3. **Reuse:** 3.1 columns; both surfaces' shared service.
4. **New:** endpoints + indicator.
5. **Must not break:** ability to act on unclaimed items exactly as today (claim is protective, not mandatory gatekeeping).
6. **Data impact:** NO SCHEMA CHANGE (columns exist). 7. **Rollback:** flag; columns ignored.
8. **Tests:** unit (TTL, conflict), concurrency feature test (two users, one claim), parity, smoke.
9. **Manual (CEO):** open Sushil's card as Samiksha (claims); as Runali in another browser — see the claim badge; wait TTL — badge clears.
10. **Freeze:** concurrency test green; pilot staff confirm the badge reads clearly. → 🔒

**🔒 PHASE 3 FREEZE GATE:** the spine contract (service API, outcome invariant, ledger guards, parity suite) is now stable infrastructure. Later phases add producers/consumers through the service only; no direct `CommunicationQueue` writes may be introduced again.

# PHASE 4 — ACTION BOARD V2

**Objective:** the one operational surface: per-patient cards, three lanes, why-context, honest counts — pilot-validated before anyone else sees it. **Staff-visible:** pilot users only (flag per user); everyone else unchanged. **Data impact:** none (read model only). **Dependencies:** P3 frozen (board renders spine truth); P2 improves context quality. **Exit:** pilot signs off on real work.

> **⚖️ DECISION GATE C — Card vs Obligation vs Primary Next Action (before Slice 4.2).**
> **Recommendation to approve/modify:** *Relationship Obligation* = one spine row: one purpose, own owner, due, history — the unit of truth and of counting; never hidden. *Patient Card* = pure UI grouping of ALL open obligations for one patient; the card headline shows the *Primary Next Action* = the obligation ranked first by (overdue > due-today > priority > oldest `next_action_at`); every other obligation renders as its own actionable line on the same card ("Sushil — ▸ Crown scheduling (primary) · Payment follow-up"). Board counters count obligations, not cards. Claims are per-obligation; the card warns if a colleague holds any sibling obligation. Resolving the primary immediately promotes the next. **CEO approves these definitions before board build.**

### Slice 4.1 — ContextAssembler
1. **Purpose:** every action explains why it exists (directive Part 5) — composed live from facts.
2. **Scope:** new read-only `app/Services/Relationship/ContextAssembler.php`: obligation → source fact (plan/appointment/lead/lab via source_type/id), journey stage (derived), attempt history (3.5), previous outcome, today's objective (purpose-driven template); rendered into the existing drawer first.
3. **Reuse:** plan_decisions, appointments, comm_activity_logs, PatientJourneyService summaries; permission filtering via `canAccess`.
4. **New:** the service + drawer block. Structured output shape = the V1.2 AI contract (built, not consumed by AI).
5. **Must not break:** drawer performance (batched queries, cached per open); permission rules (no clinical leak to non-clinical roles).
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** hide block.
8. **Tests:** unit per source type (Sushil decision-followup context cites plan + presentation date + last attempt), permission-filter test, N+1 guard, smoke.
9. **Manual (CEO):** open Sushil's follow-up: drawer reads like directive Part 5's example — reason, previous interaction, objective, attempts, owner, due.
10. **Freeze:** context correct for all 8 purposes on seeded scenarios. → 🔒
### Slice 4.2 — Projector V2 *(after Gate C)*
1. **Purpose:** the board's data engine: lanes, per-patient grouping, honest counts, freshness.
2. **Scope:** extend `TodayActionsProjector` + `today_actions` (additive columns for lane, patient grouping key, obligation ref); rebuild triggers (afterCommit on outcome + scheduled sweep); staleness timestamp; counts = obligations ("50 of 1,810" style honest totals); DONE-today lane from resolved-today + existing annotation pattern.
3. **Reuse:** the entire existing projector architecture (disposable, transaction-rebuilt — its whole point).
4. **New:** projection shape + triggers.
5. **Must not break:** the flag-off default (old board untouched); projection rebuild cost bounded.
6. **Data impact:** ADDITIVE SCHEMA ONLY (projection table — disposable by design).
7. **Rollback:** flag off; table truncate-safe (it is a projection).
8. **Tests:** projector unit (lane rules per Gate C ranking), rebuild determinism (project twice = identical), freshness, `TodayActionsProjectorTest` regression, smoke.
9. **Manual (CEO):** seed Sushil with crown-scheduling + payment obligations → one card, two lines, primary correct; resolve primary → promotion instant.
10. **Freeze:** deterministic projection; counts reconcile with census queries. → 🔒
### Slice 4.3 — Board V2 UI + pilot
1. **Purpose:** the staff-facing three-lane board, shipped to 1–2 pilot users.
2. **Scope:** new board view (evolution of `relationship/today` blade) behind per-user flag: DUE / ATTEMPTED–NEXT / DONE-TODAY lanes, patient cards per Gate C, context drawer (4.1), claim badges (3.6), honest counters, handover strip (last attempt who/when/outcome on every attempted card); old board one click away.
3. **Reuse:** drawer, option lists, dismissal flow, board CSS patterns.
4. **New:** the view + lane interactions.
5. **Must not break:** the old board for non-pilot users (untouched route default); mobile board (unchanged this slice — reads same projector later).
6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** per-user flag off.
8. **Tests:** feature (lane rendering per state), route crawler, accessibility smoke (keyboard nav — clinic-friendly), smoke.
9. **Manual (CEO):** shadow a pilot receptionist for one real morning: she should never open Recall Pipeline/Missed Calls/Tasks to know her day; the Meena no-show and Amit retry both appear in the correct lanes with context.
10. **Freeze:** pilot works ≥3 full clinic days on V2 alone; feedback triaged; CEO observes one live session. → 🔒 **(CEO GATE: pilot sign-off)**

**🔒 PHASE 4 FREEZE GATE:** board V2's lane semantics, card model, and context contract are fixed; P5–P7 may add obligation types INTO it but not alter its grammar.

# PHASE 5 — JOURNEY INTEGRATION

**Objective:** close the loops: appointments reconcile relationship work, check-in flows to doctors, the two invisible queues get detectors. **Staff-visible:** doctor waiting queue appears; booked appointments start auto-resolving follow-ups; new obligation cards (treatment recovery, decision follow-up) appear. **Data impact:** additive (new obligations, new events). **Dependencies:** P2 + P3 frozen (detectors consume clinical facts, write through the service); P4 for display polish. **Exit:** the Sushil/Anita/Prakash/Rajesh personas all generate and resolve work correctly.

### Slice 5.1 — Appointment events + reconciliation
1. **Purpose:** booking/attending closes the loop that prompted it.
2. **Scope:** `AppointmentService` emits `appointment.booked` / `appointment.attended` via ActivityEngine (afterCommit); new `ReconcileRelationshipWork` listener: match patient's open obligations (recall/decision/recovery purposes) → resolve with outcome `appointment_booked`/`attended`, appointment id in meta; outcome-driven booking (mobile path, now shared) links ids both ways + dedup guard (patient+date).
3. **Reuse:** ActivityEngine spine, OutcomeAutomation's booking path, RelationshipWorkService closure.
4. **New:** two events + one listener.
5. **Must not break:** appointment booking flow/latency; missed-appointment rule; reminder generation; obligations of unrelated purposes (matching is purpose-scoped — payment follow-up does NOT close on booking).
6. **Data impact:** ADDITIVE (event rows; obligation closures are new state changes on live rows going forward — logged, reversible via void).
7. **Rollback:** listener flag off; closures identifiable by outcome key.
8. **Tests:** feature (Sunita: book from her recall → recall resolves, linked; Meena: attends rebooked slot → reschedule task resolves), negative test (payment obligation survives booking), parity, smoke.
9. **Manual (CEO):** book Sunita an appointment from the calendar (not the board) — her recall card moves to DONE-today with "appointment booked" and the link; her payment card (if any) stays.
10. **Freeze:** persona tests green; zero false closures in a week of local use. → 🔒
### Slice 5.2 — Doctor queue + Start Consultation
1. **Purpose:** check-in → doctor sees patient ready → one click starts a fully-linked consultation. Check-in never creates the consultation.
2. **Scope:** doctor waiting-queue view (Appointments-owned; `status='checkin'` by doctor, ordered by checked_in_at); Start Consultation button → existing `consultations.create` with `appointment_id` param; `ConsultationService::store` sets appointment_id/doctor_id/branch from the appointment; appointment optionally → `in_chair` on start; done-without-consultation surfaced on the queue at day end (reconciliation hint, not auto-anything).
3. **Reuse:** existing statuses (`checkin`/`in_chair` already in the enum), consultation create form (param prefill), P2.1 service.
4. **New:** one screen + one param path.
5. **Must not break:** walk-in flow; existing consultation-from-profile path (remains valid); appointment status flow tests.
6. **Data impact:** NO SCHEMA CHANGE (consultations.appointment_id finally gets set by workflow).
7. **Rollback:** hide screen; param path is additive.
8. **Tests:** feature (check-in → queue appears; start → consultation carries all 4 ids; check-in creates NO consultation row), status-flow regression, smoke.
9. **Manual (CEO):** check Sushil in at reception; log in as his doctor — he's in the queue; click Start Consultation — form opens pre-linked; verify a consultation was NOT created at check-in time.
10. **Freeze:** linkage rate on new consultations ~100% when started from queue; both entry paths coexist. → 🔒
### Slice 5.3 — Detectors + rules repair
1. **Purpose:** the two invisible queues become daily work; rules stop skipping silently.
2. **Scope:** `journey:detect-treatment-recovery` (items started, plan undecided-complete, no future appointment → obligation `treatment_recovery`) + `journey:detect-decision-followup` (presented, no decision, N days → `decision_followup`; replaces dead `approved_plan_no_appt` which is deleted here); OpportunitySync sets `follow_up_date` on quote + nudge rule stage fix (or retire nudge in favor of the detector — recommend retire, one mechanism); RulesEngine null-relationship fallback (link-or-loud-log); RulesEngine relationship outputs → spine obligations with owner instead of unassigned invisible tasks.
3. **Reuse:** RecallEngine command pattern, dedup guard, RelationshipWorkService, P2 facts.
4. **New:** two commands.
5. **Must not break:** existing rule outputs for non-relationship rules; task board (clinical tasks unaffected); detector idempotency (one open obligation per plan).
6. **Data impact:** ADDITIVE (new obligations; scheduled but rate-capped for launch).
7. **Rollback:** unschedule commands; obligations closable by tag.
8. **Tests:** Prakash (recovery obligation appears with context: "RCT done, crown pending, no appointment"), Rajesh (decision follow-up on day N with presentation context), Anita (accepted-unscheduled caught by recovery detector), idempotency, dedup-vs-recall, smoke.
9. **Manual (CEO):** run detectors on local data — Prakash and Rajesh cards appear in DUE with correct why; run twice — no duplicates.
10. **Freeze:** three personas generate exactly one correct obligation each; caps verified. → 🔒

**🔒 PHASE 5 FREEZE GATE:** journey wiring complete on *new* activity. Every audited leakage category now has a producer, an owner surface, and a closure path — for events from this point forward. Historical population waits for P7.

# PHASE 6 — RECALL & RECOVERY

**Objective:** recall becomes relationship work computed from true attendance with real exclusions — engine correct and idle-safe *before* any historical backfill. **Staff-visible:** low (recall cards gain due/overdue truth; volume unchanged until P7). **Data impact:** additive; **no backfill in this phase.** **Dependencies:** P2 (open-treatment exclusion), P3 (spine), P5 (reconciliation live so recall can close properly). **Exit:** engine provably correct on local data; production emission still on old population until P7.

> **⚖️ DECISION GATE A — Last-visit semantics (before Slice 6.2).**
> **Recommendation to approve/modify:** `patients.last_visit_date` = **date of the most recent ATTENDED clinical encounter** = MAX(consultation date, completed treatment-visit date, appointment date with status `done`). Explicitly excluded: scheduled/cancelled/no-show appointments, communications, payments, lab events. NULL remains meaningful = "never clinically attended" (and is EXCLUDED from no-visit recall — never-attended patients are lead/reactivation work, not recall). Written transactionally by ConsultationService, TreatmentVisitService, and AppointmentService(done). **CEO must approve before writers or backfill exist.**

### Slice 6.1 — Semantics decision pack
1. **Purpose:** freeze Gate A with evidence, not opinion.
2. **Scope:** docs + one read-only report command: for the VPS dataset, compute proposed `last_visit_date` per patient vs current column; distribution of diffs; projected recall-eligible population under new semantics + exclusions (expected: dramatic shrink from 1,810-class behavior).
3. **Reuse:** census command pattern. 4. **New:** report only.
5. **Must not break:** nothing (read-only). 6. **Data impact:** NO DATA CHANGE. 7. **Rollback:** n/a.
8. **Tests:** report math unit-tested on seeded cases (Sunita exactly-6-months; Prakash active-treatment; never-attended lead).
9. **Manual (CEO):** read the report; approve/adjust semantics.
10. **Freeze:** ⚖️ Gate A signed. → 🔒 **(CEO GATE: semantics)**
### Slice 6.2 — Attendance writers
1. **Purpose:** the column gains transactional producers (forward-only; history unchanged until P7).
2. **Scope:** hooks inside ConsultationService, TreatmentVisitService, AppointmentService per Gate A; monotonic guard (never move backwards).
3. **Reuse:** the three services' existing transactions.
4. **New:** one small writer trait/method.
5. **Must not break:** save latency; the three services' tests; existing recall behavior (engine still reads old values for old patients — unchanged until 6.3/P7).
6. **Data impact:** EXISTING ROW STATE CHANGE — but only for patients who attend from now on, via normal clinical workflow (this is the column doing its job, not a migration).
7. **Rollback:** remove hooks; values written remain (harmless, accurate).
8. **Tests:** unit (monotonic; each encounter type), feature (attend today → column = today), smoke.
9. **Manual (CEO):** complete a visit for a test patient — profile shows today's date as last visit.
10. **Freeze:** all three writers proven; diff report (6.1) re-run shows forward convergence. → 🔒
### Slice 6.3 — Unified recall engine
1. **Purpose:** one recall path with real eligibility, exclusions, aging, and bounded emission.
2. **Scope:** `RecallEngineService` rewrite-in-place: merge `RecallAutomationRunner`'s superior filters (deceased, invalid-contact, chunkById); exclusions on ALL triggers (future non-terminal appointment; open treatment via P2 facts; NULL last-visit); due/overdue derived from `next_action_at`; daily emission cap (config); `recall.general_days` honored or removed (no lying settings); inline 6-mo Task producer in TreatmentVisitService redirected to a spine obligation (purpose `recall`, due 6mo, background until due); runner/shadow classes deprecated behind the flag (deleted P8).
3. **Reuse:** effective_from, hasOpenQueueItem, no-phone flagging, cooldown stamps.
4. **New:** exclusion queries + cap.
5. **Must not break:** existing open recall rows (untouched); post-op/lab/birthday triggers' correct behavior; `recall:run` schedule contract.
6. **Data impact:** ADDITIVE (new obligations only; emission against OLD population unchanged until P7 reconciliation — cap protects regardless).
7. **Rollback:** flag to previous engine path (kept until P8).
8. **Tests:** characterization diff (old vs new candidate sets on seeded data — every difference explained by a named exclusion), Sunita (eligible), Prakash (excluded: open treatment), future-appointment exclusion, cap test, parity of the single path, smoke.
9. **Manual (CEO):** seed the personas; run `recall:run`; only Sunita queues; Prakash and future-appointment patients provably skipped with logged reasons.
10. **Freeze:** exclusion diff signed; cap verified; runner fork inert. → 🔒

**🔒 PHASE 6 FREEZE GATE:** recall semantics, exclusions, and emission discipline are law. P7 may change the *population* (backfill/reconcile) but not the *rules*.

# PHASE 7 — MIGRATION & CUTOVER *(the controlled data phase — nothing here ships casually)*

**Objective:** convert live VPS history onto the new spine with reports, backups, reversibility — then make Board V2 primary. **Staff-visible:** HIGH at 7.6 (board switch); zero before it. **Data impact:** BACKFILL + EXISTING ROW STATE CHANGE + PRODUCTION CUTOVER. **Dependencies:** P2–P6 all frozen. **Exit:** clinic running on the new spine, invariants green, observation window clean. **Every slice here is individually CEO-gated.**

### Slice 7.1 — Dry-run report suite
Purpose: every planned data change produces a reviewable report before it can run. Scope: report commands (dry-run default, `--apply` locked behind confirmation): last-visit backfill diff; follow_ups→spine mapping (+collisions); relationship-tasks→spine mapping; opportunity repair candidates; queue reconciliation candidates (rule 1: future appointment; rule 2: attendance after row creation) with 50-row manual review samples; per-patient open-work visibility invariant checker; ledger row-count invariance checker. Reuse: SyncCallOutcomeClosesTask pattern, census. Data impact: NO DATA CHANGE. Tests: each report unit-tested on seeded fixtures containing known duplicates/edge cases. Manual (CEO): review every report + the 50-row samples. Freeze: all reports approved in writing. → 🔒 **(CEO GATE ×5 — one per report)**
### Slice 7.2 — Backup + rollback rehearsal
Purpose: prove we can come back. Scope: VPS snapshot procedure; restore rehearsal on local from prod snapshot; reversal command for each backfill executed against a rehearsal copy and verified. Data impact: NONE on prod. Freeze: restore + every reversal demonstrated. → 🔒
### Slice 7.3 — Attendance backfill
Purpose: apply Gate A values to history. Scope: `--apply` of the last-visit backfill; recall emission paused for affected population during apply; post-apply diff re-report. Data impact: BACKFILL (column values; old values preserved in report artifact). Rollback: restore-from-report command (rehearsed in 7.2). Tests: invariant checkers green. Manual (CEO): spot-check 10 known patients (incl. Sunita/Prakash types) against their real charts. Freeze: diff matches approved report exactly; count mismatches = 0. → 🔒 **(CEO GATE)**
### Slice 7.4 — follow_ups + relationship-tasks backfill
Purpose: open legacy work becomes spine obligations; originals preserved-closed as `migrated`. Scope: `--apply` both mappings; collision policy per 7.1 report (link, never duplicate); origins in meta. Data impact: BACKFILL + EXISTING ROW STATE CHANGE (originals closed, never deleted). Rollback: reversal reopens originals, removes tagged obligations. Tests: per-patient visibility invariant (no patient loses sight of open work); board counts reconcile. Manual (CEO): pick 5 real patients with known pending follow-ups — each appears once on Board V2 with history intact. Freeze: invariants green; sample verified. → 🔒 **(CEO GATE)**
### Slice 7.5 — Opportunity repair + queue reconciliation (the 1,810)
Purpose: pipeline stops lying; the backlog becomes truthful work. Scope: `--apply` opportunity repair (cancelled plans → declined, tagged); queue reconciliation passes 1 & 2 (close `reconciled_returned`, re-date survivors' `next_action_at` per due semantics). Data impact: EXISTING ROW STATE CHANGE (status/outcome only — rows and logs never deleted; every closure logged per-row). Rollback: reopen-by-outcome-tag (rehearsed). Tests: invariance of ledgers; survivor set matches report; board shows honest "N of M". Manual (CEO): Recall Pipeline totals vs board counts now reconcile; spot-check 10 closed-as-returned patients really did return/book. Freeze: post-apply counts == approved report ±0. → 🔒 **(CEO GATE)**
### Slice 7.6 — Staff cutover
Purpose: Board V2 primary; one workflow for everyone. Scope: flag flip all users; old board + Follow-up Engine + Communication Recall/Missed Calls demoted to read-only (banner + link back); legacy schedulers decision executed (auto-escalate + digests → board-native equivalents or off — per CEO choice at the gate); mobile pointed at projector feed; SOP one-pager per lane distributed. Data impact: PRODUCTION CUTOVER (no data change — surface change). Rollback: flag back to old board (proven safe — old board still reads live spine). Tests: route crawler all roles; full regression; parity. Manual (CEO): work a real morning on V2 with staff; verify Meena/Sunita/Amit flows end-to-end on prod data. Freeze: 3 consecutive clinic days, zero blocking complaints, invariant reports clean daily. → 🔒 **(CEO GATE)**
### Slice 7.7 — Observation window
Purpose: prove stability before touching anything else. Scope: 2 weeks: daily invariant/census diffs, outcome-volume counters (attempts logged, obligations resolved, duplicates reported = 0), complaint log; NO retirements, NO new features. Freeze: window ends clean. → 🔒

**🔒 PHASE 7 FREEZE GATE:** the clinic runs on the spine. Historical data is converted, preserved, and reconciled. From here, legacy surfaces are documentation, not workflow.

# PHASE 8 — STABILISATION & RETIREMENT

**Objective:** remove what the cutover proved redundant; final audit; freeze. **Staff-visible:** old screens disappear (already unused). **Data impact:** code deletion only — **no data deleted, ever** (ledgers and closed legacy rows remain permanently). **Dependencies:** P7 observation clean. **Exit:** V1.1 frozen.

### Slice 8.1 — Safe-now dead code
Scope: the legacy audit's D-class list (unrouted controllers, dead partials/fake badges, navBadges, crm stub view, orphaned consultation partials, labs stub, OCR service, retired TV controller stub, dead config keys). Each deletion: route/name/class grep proof attached. Tests: route crawler + smoke. Rollback: git revert. Freeze: crawler green. → 🔒 **(CEO GATE: deletions)**
### Slice 8.2 — Post-cutover legacy retirement
Scope (order matters): PRE attempt-history parity confirmed → retire manager/show + b2b/show reader blades and remaining Communication screens (redirect stubs per agreed bookmark window); follow_ups production fully off → screens removed (table retained read-only for history); runner/shadow recall classes + `automation.engine` fork deleted; ghost columns/models cleaned (visit money fields, Treatment intelligence fields, dead enum values, 'finalized' badge branch); `module:communication` grant tightened; webhooks decision executed (route in V1.5 scope or park with dated comment). Tests: full regression + smoke + crawler. Freeze: legacy audit re-run shows classes A/B only. → 🔒 **(CEO GATE)**
### Slice 8.3 — Final forensic audit + freeze
Scope: re-run the four audits' headline checks (permission matrix, write-path census, event production, queue semantics, persona walkthrough); update module master docs; memory/docs refreshed; tag `journey-v1.1`. Freeze: **🔒 PATIENT JOURNEY V1.1 FROZEN.**

---

## CRITICAL PATH

```
P0 → P1 → P2 → P3 → P4 → P5 → P6 → P7 → P8
          (Gate B)  ↑        (Gate C in P4)  (Gate A in P6)
                    └─ P3.1/3.2 may be branch-prepared during P2 review stalls (merge in order only)
```
Technically independent (may be scheduled early if a phase stalls, each still individually approved): 0.3 board fixes (anytime after 0.1); 5.2 doctor queue (needs only P1 + 2.1); 6.1 semantics report (anytime after 0.2); 3.1 schema (anytime after P1). Hard chains: 2.2→5.3 (detectors need decisions); 2.3→6.3 (open-treatment exclusion); 3.3→3.4 (web before mobile parity); 6.2→7.3→7.5 (writers before backfill before reconciliation); 4.3→7.6 (pilot before primary); 7.7→P8 (observation before deletion).

## CEO APPROVAL GATES (full stop points)

1. **1.1** — intended permission matrix (role grants).
2. **⚖️ Gate B (pre-2.2)** — plan-decision semantics (presented = lifecycle event vs decision).
3. **3.1** — first schema migration on live spine table.
4. **⚖️ Gate C (pre-4.2)** — Card / Obligation / Primary-Next-Action definitions.
5. **4.3** — pilot sign-off (board V2 validated).
6. **⚖️ Gate A (pre-6.2)** — last-visit semantics.
7. **7.1** — every dry-run report (×5, individually).
8. **7.3 / 7.4 / 7.5** — each production backfill / row-state change (individually).
9. **7.6** — Board V2 primary + legacy schedulers decision (disabling old workflows).
10. **8.1 / 8.2** — every code retirement batch.
11. Standing rule: ANY unplanned data touch, ANY new destructive-looking command, ANY scope addition → stop and ask.

## START HERE

**Phase 0, Slice 0.1 — Repo hygiene & baseline.** Reasons: it is the only slice with literally zero risk and everything depends on it — uncommitted Phase-4/WhatsApp/smoke work is currently unauditable, and no later slice's PASS/FAIL means anything without a tagged, smoke-green baseline to diff against. It also produces the rollback anchor (`pre-journey-v1.1` tag) that every subsequent rollback strategy references. One session, immediately reviewable, and it makes every future review honest.

---

*Roadmap only. Nothing implemented. On approval, execution follows the rule: ONE SLICE → STOP → report → CEO approval → next.*
