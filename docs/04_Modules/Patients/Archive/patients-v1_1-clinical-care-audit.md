# Patients V1.1 — Clinical Care Workflow Forensic Audit

**Date:** 2026-07-24 · **Directive:** CEO Patients V1.1 (audit + discussion only)
**Scope:** Patient → Consultation → Diagnosis → Treatment Plan → Accept/Reject/Defer → Treatment Visits → Clinical completion → Lab boundary. Finance inspected at the boundary only.
**Method:** Six parallel read-only code audits (workflow map, loose ends/dead files, DB/source-of-truth, write paths/transactions, permissions/events, UI/tests/boundaries), followed by independent spot-verification of every headline claim. **No code was changed.**

Spot-verified directly (not just sub-agent claims): the view-level permission group at `routes/web.php:196` and `:296`; absence of `cost`/`amount_paid` migrations for `treatment_visits`; absence of `DB::transaction` in `LabCaseTransitionService`; the nonexistent `price` column selected at `TreatmentController.php:126`; no business-code writer of `TreatmentPlanItem.status='completed'`; Case Acceptance services existing on disk; `PresentationNarrativeService` still live.

---

## SECTION A — Current workflow map (as the code actually behaves)

```
Patient ──► Consultation ─────────────► TreatmentPlan (consultation_id nullable FK)
             │ diagnosis = COLUMNS          │ hasMany treatment_plan_items
             │ on consultations table       │ items hasMany treatment_plan_item_teeth (lazily created at BILLING time)
             │ (diagnoses / clinical_       ▼
             │  findings tables are DEAD)  Accept → accepted_at=now, status='ongoing'  (TreatmentPlanAcceptanceService — single door)
             │                             Reject → status='cancelled' via generic update() (no dedicated endpoint)
             │                             Defer  → NOT STORED — derived at read time by the timeline
             ▼                              │ accept also syncs 1 TreatmentOpportunity
TreatmentVisit (treatment_plan_id nullable) ◄┘
   │ TreatmentVisitService (single brain, web+mobile, DB::transaction)
   ├─► treatment_visit_items (billing_status='pending') + 1 BillingPrompt  → front-desk billing hand-off
   ├─► LabCase draft (teeth COPIED from visit)                              → Lab module state machine
   ├─► mark_treatment_complete → flips WHOLE TreatmentPlan.status='completed' + 6-month recall Task
   └─► implant → ImplantPlacement + StockMovement (idempotent)

Billing (boundary): TreatmentPlanBillingService invoices per-tooth (plan path)
                    OR front desk acts on BillingPrompt (visit path) — the two paths are NOT reconciled.

Journey Timeline: PatientJourneyService → UnifiedTimelineService — mostly DIRECT table queries
                  (consultations, plans, visits, invoices, files, lab, consent-DPDP) + Activity ledger merge.
```

Key structural facts:

1. **There is no "case" entity.** The chain is loosely FK-linked tables; `consultation_id` and `treatment_plan_id` are nullable everywhere downstream.
2. **Diagnosis lives as ~8 columns on `consultations`** (`primary_diagnosis`, `diagnosis_risk`, etc.). The `diagnoses` and `clinical_findings` child tables are dead; `Consultation::diagnoses()` is a *broken* relation (the `Diagnosis` model points at `diagnosis_masters`, so the relation queries a column that doesn't exist).
3. **Consultation → plan hand-off copies nothing.** "Save & Start Treatment Plan" redirects with a flash `from_consultation_id`; the doctor re-enters everything in the plan builder.
4. **Chairside progress and plan-item progress are disconnected.** Visits never write `TreatmentPlanItem.status`; item progress advances only via the billing path (`billing_progress`). `mark_treatment_complete` is an all-or-nothing plan flag.
5. **Consultation `status` is always `'completed'`** — hardcoded on every store path (`ConsultationController.php:56,265,404,465,519,563`; API mirrors). `draft` is unreachable.
6. Four consultation workflows (New / Same-Issue / Minor-Visit / Emergency) + COHA are **intentional and live**, but each re-implements validation and field markup independently.

Route/controller/service inventory per stage is in the working notes below (Sections I, K); the load-bearing files are: `ConsultationController.php` (689 lines, no service), `TreatmentPlanController.php` (680 lines), `TreatmentVisitService.php` (the model citizen), `TreatmentPlanAcceptanceService.php`, `Billing/TreatmentPlanBillingService.php`, `LabCaseTransitionService.php`, `Relationship/UnifiedTimelineService.php`, `routes/web.php:183-262,296-299`, `routes/api.php:96-264`, `routes/prescriptions.php`.

---

## SECTION B — What is already good and should NOT be rewritten

1. **`TreatmentVisitService`** — one brain for web + mobile, shared `rules()`, all side-effects (visit items, billing prompt, lab case, plan complete + recall, implant stock) inside one `DB::transaction`. This is the architecture V1.1 should copy, not replace.
2. **`TreatmentPlanAcceptanceService`** — single accept/revert door used by all five entry points (web, API, Smart Presentation, Case Acceptance public page), transaction-wrapped, revert blocked once invoiced, audited. Genuinely done right.
3. **Plan `update()` protected-item guard** (`TreatmentPlanController.php:275-291`, mirrored in API) — completed/billed/invoiced items survive plan revisions. Sound.
4. **`LabCase` state machine** — `STATUSES`/`STATUS_FLOW`/labels/colors on the model are a real single source of truth; append-only `lab_case_events` written in `booted()`. (Transition *atomicity* is the gap — Section J.)
5. **`UnifiedTimelineService` defensive design** — every source individually guarded so one failure can't blank the timeline; permission-filtered via the same `canAccess` the rest of the app uses.
6. **`config/dental_notation.php`** — declared FDI source of truth, exposed to JS once. (The problem is the five chart *UIs* that don't all use it — Section E/H.)
7. **`ConsentDocumentService`** — immutable per-plan consent snapshot, correctly and deliberately separate from DPDP consent.
8. **`EnsureApiRole` reusing `User::canAccess`** — permission *resolution* is not duplicated; only the *assignments* are inconsistent.
9. **Billing separation intent** — visits deliberately carry no money fields; `BillingPrompt` is a clean clinical→finance hand-off contract; `TreatmentPlanBillingService` is a well-isolated finance service.

---

## SECTION C — P0 findings (data / safety / invariant risk)

**C1. Every clinical write on web is gated at VIEW level.** `routes/web.php:196` and `:296` open bare `module:patients` groups (middleware default action = `view`, `CheckModulePermission.php:22`) containing consultation store/update/destroy, plan store/update/**accept/revert**/destroy, visit store/update/destroy, COHA. Sibling patient routes in the same file correctly append `,edit`/`,delete` (`:132-166` — verified). No controller-level `canAccess`/Gate checks exist in `ConsultationController`, `TreatmentPlanController`, or `TreatmentVisitController`, so route middleware is the only gate. **A view-only account can create, accept, revert, and delete clinical records.** Same disease as the Appointments audit found.

**C2. Prescriptions have no module permission at all.** `routes/prescriptions.php` is wrapped only in `auth` (`routes/web.php:554`); zero `module:` middleware inside the file. Any authenticated user can write/cancel prescriptions and edit the drug master. Compounding medico-legal exposure on the web path:
- The CDSS safety engine (`PrescriptionAlertService`: allergy, interaction, duplicate-molecule, stewardship) is wired only to `POST /api/rx/check-alerts`; **no web view ever calls it** — dentists on web get no allergy/interaction warnings.
- Two of five CDSS rule tables (`rx_allergy_rules`, `rx_drug_interaction_rules`) have no admin UI or seeder — those checks can only ever return empty.
- Web edits an **issued** prescription in place (`Prescription/PrescriptionController.php:151-171`), while the API correctly version-branches (draft→issue→revised with `parent_id`). What the patient was handed and what the record says can diverge with no prior version retained.
- `PrescriptionOverride` (alert-acknowledgement audit) is persisted only by the API path — the "CDSS Overrides" block on web-created prescriptions is structurally always empty.

**C3. TreatmentVisit has no branch/tenant scoping on web.** `TreatmentVisitController` update/destroy/print use plain route-model binding; `TreatmentVisit` lacks the `BelongsToBranch` trait (unlike Appointment/Patient/Consultation/LabCase). The API controller branch-checks (`Api/V1/TreatmentVisitController.php:260-269`); web does not. More broadly, **8 clinical tables have no `branch_id` at all** (`treatment_plans`, `treatment_visits`, `treatment_visit_items`, `treatment_plan_items`, `treatment_plan_item_teeth`, `prescriptions`, `lab_case_items`, `clinical_findings`) and `BranchScope` is self-documented inert — the concrete blocker for clinic #2 already flagged in the encryption/hardening memo.

**C4. Lab write-path integrity failures.**
- API `LabController::store` validates and accepts `shade`/`notes`, but `LabCase::$fillable` (`LabCase.php:253-268`) has neither — **mass assignment silently drops both and returns 201.** Clinical instructions to the lab are lost.
- Web vs mobile write **incompatible priority vocabularies** to the same column: `routine|urgent|express` (web `LabController` L208) vs `normal|urgent|asap` (API L241). Urgent mobile cases miss the urgent queue; badges lie.
- "Reject case" is admin-gated **in Blade only** (`lab/show.blade.php:127-133`); the server route is the same `lab.transition` gated `module:lab` view-level — any lab-view user can reject via POST.
- Web `attachmentStore` reportedly writes `file_name`/`file_size` where the model/migration expect `original_name`/`size_bytes` (NOT NULL) — web lab-attachment upload likely broken. *(Sub-agent finding; re-verify before acting.)*

**C5. Ghost columns that lie or crash.**
- `treatment_visits`: `cost`, `amount_paid`, `payment_mode`, `payment_reference` are fillable and drive `balance_due`/`is_fully_paid` accessors, but **no migration ever created them** (verified across all 13 treatment-visit-touching migrations). `balance_due` is structurally always 0.
- `Treatment` model: 5 fillable+cast fields (`chief_complaint_variations`, `differential_diagnosis`, `red_flags`, `hopi_template`, `suggested_treatment_options`) whose migration (`2026_06_10_000001`) is a **no-op** — mass-assigning any of them throws SQL errors.
- `TreatmentController.php:126` selects a **nonexistent `price` column** from `treatment_plan_items` (real column: `unit_price`) — opening the Treatment Intelligence tab errors. Verified.

**C6. `LabCaseTransitionService::transition` is not transactional.** Verified: no `DB::transaction`/`beginTransaction` in the file, yet a transition fans out to 5+ writes (status, close task, create task, `active_task_id`, notification, AP expense) plus an observer. Status can advance while the follow-up task or expense is never written — the only centralized clinical workflow that is non-atomic.

**C7. Clinical media consent gap.** `ClinicalFile` eligibility scopes used by CMS search (`CmsSearchController.php:31-37,99-109`) do not check `consent_status`; the one correctly consent-gated scope (`scopeMarketingReady`) has zero callers. Patient photos can surface in content tooling before consent is confirmed.

---

## SECTION D — P1 findings (workflow / architecture defects)

**D1. Visit ↔ plan-item disconnect (the core clinical-truth defect).** `TreatmentPlanItem.status` is never written to `'completed'` by any business code (verified by grep — the only plan-side `'completed'` writers hit `TreatmentPlan.status`). `visit_items.treatment_plan_item_id` links but never writes back. Consequences: per-item clinical progress does not exist; the `status==='completed'` delete-guards on items are unreachable via normal workflow; "what's left to do on this plan" cannot be answered from data.

**D2. Three contradictory writers of `TreatmentPlan.status='completed'`.** (a) `TreatmentVisitService.php:354` — checkbox, no billing check; (b) `TreatmentPlanBillingService.php:139-143` — only when every item fully invoiced; (c) `TreatmentPlanController::update` accepts arbitrary status from the client. One column, three rules. Also `status` and `accepted_at` are separately writable representations of acceptance that can desync.

**D3. Rejection/deferral are not recorded facts.** No dedicated reject endpoint (reject = generic update to `cancelled`); deferral is derived at read time by the timeline (pending + un-accepted + ≥14 days). No audit trail of the actual decision, by whom, or why — the exact data the Case Acceptance Engine will need.

**D4. Consultation writes are scattered and non-transactional.** Nine `Consultation::create` sites across three controllers (web, API, COHA), each with its own inline validation (the typed variants bypass `StoreConsultationRequest`). Consultation + COHA report + specialty-module writes are **not** wrapped in a transaction. No `ConsultationService` exists. Also: typed consultations (same-issue/minor-visit/emergency) have no matching edit UI — edit links land on the generic New Consultation form which lacks their fields.

**D5. Cross-surface RBAC incoherence.** Mobile treatment-plan writes are gated `api.role:admin,front_desk` — **doctors are excluded** — while mobile consultation/visit/prescription writes are doctor-gated (`routes/api.php:155-223`). Meanwhile web lets view-only users do everything (C1). API consultation DELETE is gated by a *view* permission (`api.php:122-123`). Same clinician, three different capability sets by surface.

**D6. Web/API plan-item rule divergence.** API `syncItems` hardcodes `disc_pct=0, gst_pct=0, option_rank='best'` and drops `material_variants` (`Api/V1/TreatmentPlanController.php:277-281`); web writes them from the payload. A plan line edited on mobile silently zeroes discounts/GST set on web.

**D7. No server-side pricing authority.** `unit_price`/`disc_pct`/`gst_pct` on plan items are client-supplied verbatim; `treatments.default_price/gst_pct` only pre-fill UI; `treatment_options.price` feeds only the Case Acceptance journey. Three free-input price writers, no master enforcement — a direct prerequisite gap for both the Finance phase and the Case Acceptance Engine.

**D8. Visit `status` is dead in the web UI.** No control in the Add/Edit modal sets it, so every web visit stays `scheduled` forever; the "Completed" KPI reads 0 and 4 of 5 filter chips are dead (`treatment-visits-tab.blade.php:1499-1590`). Relatedly, `HuddleController.php:88` queries `status='ongoing'` — a value retired from the enum that can never match.

**D9. Prescription data exists in three unreconciled stores.** Structured `prescriptions` table (+items, versioning, audit) vs `consultations.prescriptions` JSON vs `treatment_visits.prescription_drugs` JSON. A consultation-JSON Rx and a structured Rx can diverge on the same encounter. Plus `generateNumber()` uses `count()+1` with no lock (race).

**D10. Tooth-identifier type bug.** `Consultation::chartToothNumbers()` casts to int (`Consultation.php:170`) while `ConsultAssistController::toothTimeline` (`:184`) does strict string `in_array(..., true)` — charted teeth never match in the tooth timeline; region labels (`UL`, `Full Arch`) collapse to 0. It also still reads the legacy `tx_teeth` column.

**D11. Consent-required silently dropped.** If the client omits `consent_required`, the server coerces `false` (`TreatmentPlanController.php:626`) even when the Treatment master rule says consent is required. Also `treatment-plans/{plan}/consent` **persists a TreatmentConsent row on every GET** — a write-on-read side effect.

**D12. Knowledge Bank with zero consumers.** `DiagnosisTreatmentOption` has full CRUD UI but nothing reads it; meanwhile three parallel suggest-treatment mechanisms exist (`Treatment::matchesComplaint`, `TreatmentKnowledge::matchComplaint`, `TreatmentPlanController::aiSuggest` — the latter routed but with no UI caller and stale hardcoded prices). Staff can enter data that goes nowhere.

**D13. `LabCase::labVendor()` doesn't exist** — referenced by `LabCaseObserver.php:115` (silent null) and `B2BController.php:96` (hard crash); the model defines `vendor()`. Also `LabVendor` has `branch_id` but no scoping (vendor lists leak across branches), and web `LabController::store` never sets `branch_id`.

---

## SECTION E — P2 / P3 debt

P2 (tech debt, ugly but mostly harmless):
- `consultations` legacy `tx_*`/`treatment_plan_best*`/`aocp_*` columns — removed from fillable, kept for historical display; the planned drop migration was never run. `tx_teeth` still read by ConsultAssist (see D10).
- `treatment_plans.rows` legacy JSON (superseded by items; only `MasterDemoSeeder` writes it); `risk_assessment` vs `diagnosis_risk` duplicate fact; `visit_type` vs `consultation_type` dual axes reconciled only by `typeLabel()`.
- `treatment_visits`: orphaned `clinical_notes`/`next_visit_plan`/`visit_number`; dead enum values `started|ongoing|abandoned`; 30+ sparse per-procedure columns (wide-sparse anti-pattern); two migrations both named `create_treatment_visits_table`.
- `lab_cases`: duplicate date columns (`received_date` + `final_received_date` are both written and even **summed together** in the dashboard, `LabController.php:61-62`); dead `delivered_date`; `is_remake` vs dormant `repeat_reason` vs never-created `is_repeat_work`; legacy shims (`LEGACY_CATEGORY_MAP`, `work_type`, `notes`→`internal_notes` aliases); two attachment stores on two disks (web `LabCaseAttachment`/public vs API `ClinicalFile`/private).
- Prescriptions: dead `'finalized'` status branch in `prescriptions/index.blade.php:54,68` (badge always falls to grey); `follow_up_date` stored as string; `@deprecated scopeFinalized()`.
- `clinical_media` vs `clinical_files` — dropped, restored, still duplicated (`tags` vs `searchable_tags`, three date columns); confirm `phase8:migrate-clinical-media` ran before retiring.
- Dead validation rules in `StoreConsultationRequest` for already-stripped columns; duplicate route registration (`patients.consultations.create` twice) and dual naming schemes (`consultations.*` vs `patients.consultations.*`) — root cause of the typed-edit routing drift (D4).
- `treatment_plan_item_teeth` lazily created at billing time only — the promised backfill never shipped, so never-billed plans have zero tooth rows.
- Three competing material-choice stores on plan items (`material_variants` JSON vs unused `material_id`/`brand_id` FKs vs `treatment_options`).
- Note-editor UI pattern re-implemented 3× for 3 domains; stray `.fuse_hidden…` artifact in `resources/views/consultations/`.

P3 / non-issues confirmed: Huddle "Visit Logged" works as documented. Visit Vitals are fully wired end-to-end (the "needs migrate" memory note is stale). Pediatric dose helper and consent-doc separation are good examples.

**Memory/roadmap drift corrections (verified):** Case Acceptance Engine is *implemented* (services + controllers + routes, gated OFF via `case_acceptance.enabled`), not design-only. `PresentationNarrativeService`/Smart Presentation is still the live default (`PresentationController.php:113-114,453`) despite a stale "retired" comment at `TreatmentPlanController.php:58`. Unaccepted-Plans→Opportunities is live (called from accept/markPresented), not parked.

---

## SECTION F — Dead / old / duplicate file inventory

| File(s) | Originally | Replacement | Verdict |
|---|---|---|---|
| `app/Http/Controllers/ContentManagement/TreatmentVisitController.php` | Retired 2026-07-18 namespace-collision stub (comment-only body) | root `TreatmentVisitController` | **SAFE TO DELETE** |
| `resources/views/consultations/partials/` — 13 of 14 files (`chief-complaint`, `diagnosis`, `dbm-checklist`, `photographs`, `intraoral-scans`, `radiographic-findings`, `clinical-findings`, `treatment-advised`, `_tx-column`, `treatment-plan`, `_tp-table`, `visit-type`, `finishing-section`, `_cip-checklist`) | Abandoned componentization of the consultation form | `create.blade.php` monolith (only `investigations.blade.php` is still `@include`d, at `create.blade.php:1167`) | **SAFE TO DELETE** (13 files) |
| `resources/views/labs/index.blade.php` (9-line stub) | Placeholder | `resources/views/lab/index.blade.php` | **SAFE TO DELETE** |
| `app/Services/Assistant/LabPriceListScanService.php` + `LabVendorPriceList` model + migration | Lab OCR (explicitly reverted per CEO decision) | Manual vendor entry | **SAFE TO DELETE** |
| `app/Models/ConsultationPhotograph.php` / `ConsultationScan.php` (+tables) | Per-slot photo/scan rows | `ClinicalFile` pipeline (deliberate 2026-07-09 switch, `ConsultationController.php:125-131`) | **PROBABLY DEAD — VERIFY** prod row counts first |
| `diagnoses` + `clinical_findings` tables + broken `Consultation::diagnoses()` relation + mismatched `ClinicalFinding` model | Normalized diagnosis storage | Diagnosis columns on `consultations` | **PROBABLY DEAD — VERIFY** prod rows; relation is broken either way |
| `LabNotificationService::fireOverdueAlerts()` | Overdue alerts | `LabAlertService::createOverdueTasks()` (scheduled, live) | **PROBABLY DEAD — VERIFY** |
| `TreatmentPlanController::aiSuggest()` + route | Regex "AI" suggester | none (zero UI callers) | **PROBABLY DEAD — VERIFY** (stale hardcoded prices make it worse than dead) |
| `Prescription::scopeFinalized()` | Old status | `scopeIssued()` | SAFE TO DELETE (low priority) |
| `ClinicalFile::scopeMarketingReady()` | Consent-gated marketing scope | — | **NOT DEAD — SHOULD BE WIRED IN** (see C7) |

Confirmed NOT dead despite appearances: the 4 typed consultation workflows; Smart Presentation + Case Acceptance coexisting behind one flag; both live TreatmentVisitControllers (web+API thin wrappers over the shared service).

---

## SECTION G — Suspicious database fields / tables (summary)

Full detail in Section C5/E; the ones demanding a decision in V1.1:

| Table.column | Problem | Recommendation |
|---|---|---|
| treatment_visits.cost / amount_paid / payment_mode / payment_reference | Ghost — fillable + accessors, columns never created | Remove from model & accessors (or decide visit-level payments are a real feature — they aren't, BillingPrompt is the design) |
| treatments.{5 intelligence fields} | Ghost — fillable+cast, no-op migration | Remove from model or ship the migration |
| treatment_plan_items.price (referenced) | Never existed — real column `unit_price` | Fix `TreatmentController.php:126` |
| treatment_plans.status vs accepted_at | Two acceptance representations, desyncable | `accepted_at` = truth; status becomes derived/constrained |
| consultations.treatment_acceptance | Third acceptance vocabulary (accepted/pending/refused/deferred), never reconciled | Fold into the plan-decision record (D3) or retire |
| consultations legacy tx_*/aocp_* block | Unwritten, display-only, encrypted PHI | Keep until archival drop migration is deliberately run |
| treatment_visits.status enum extras (started/ongoing/abandoned) | Dead values; Huddle still queries 'ongoing' | Clean enum after fixing Huddle |
| lab_cases.received_date + final_received_date | Both written, summed together in dashboard | Pick one; fix dashboard math |
| treatment_plan_item_teeth | Lazily created at billing only; no backfill | Backfill or document that pre-billing plans have no tooth rows |
| clinical_findings / diagnoses tables | Dead + model/schema mismatch | Verify prod rows → drop, and fix/remove broken relation |
| branch_id | Missing on 8 clinical tables; no FK on consultations.branch_id; lab default 1 | Prerequisite for clinic #2 — schedule with the encryption/isolation phase, not ad hoc |

---

## SECTION H — Competing sources of truth

| Concept | Truth today | Verdict |
|---|---|---|
| Consultation status | None — hardcoded `'completed'` everywhere; `visit_type` vs `consultation_type` vs `treatment_acceptance` all coexist | No SoT; needs an enum + one writer |
| Plan status / acceptance | `accepted_at` (declared canonical) but `status` independently writable by 3 workflows; `TreatmentOpportunity.status` mirrors it; consultation `treatment_acceptance` is a 4th vocabulary | Fragmented — worst offender |
| Plan-item status | `status` (never written) vs `billing_progress` (billing-owned) sharing tokens | Billing side has a SoT; clinical side is fictional |
| Visit status | One live vocabulary, one shared validator (good) — but dead enum values + dead web UI control | Nearly single-sourced; fix UI + enum |
| Clinical completion | Four unrelated definitions (visit status, huddle heuristic, plan status, billing progress) | No SoT |
| Tooth/procedure IDs | `config/dental_notation.php` (good) but VARCHAR columns mix FDI + region labels; int/string bug D10; 5 independent chart UIs | Config SoT, storage/UI drift |
| Pricing | None server-side — 3 free-input writers; `treatment_options` catalog only feeds Case Acceptance | No SoT — Finance prerequisite |
| Doctor ownership | `consultations.doctor_id` NOT NULL; nullable downstream; ad-hoc fallback chains in print views | No shared resolver |
| Branch ownership | `BelongsToBranch` on 5 models; inert scope; 8 tables unscoped | Deferred by design; blocks clinic #2 |
| Consent | Two systems, deliberately separate (clinical snapshot vs DPDP) — correct; gap is D11 silent-false | OK, one bug |
| Lab status | `LabCase` constants — genuine SoT | Good |
| Prescription lifecycle | Two contracts (web mutable, API immutable/versioned) + 2 JSON stores | Fragmented |

---

## SECTION I — Scattered write paths

- **Consultation: SCATTERED.** 9 create sites / 3 controllers / 5 validation rule-sets / no service / no transactions. The single biggest consolidation target.
- **TreatmentPlan: HALF-CENTRALIZED.** Accept/revert fully centralized (good); create/update/syncItems duplicated web vs API with divergent rules (D6).
- **TreatmentVisit: CENTRALIZED.** One service, thin wrappers. The template.
- **Prescription: SCATTERED + RULE-DIVERGENT.** Two full implementations enforcing different lifecycles on the same table (C2), plus 2 embedded JSON stores.
- **LabCase: transition centralized, create/edit forked** with conflicting enums and forked attachment stores (C4).
- **Clinical notes/media: 4 stores** (`ClinicalMedia`, `ClinicalFile`, `LabCaseAttachment`, `PatientNote`).
- **AI ToolRegistry: writes NO core clinical entity.** Only clinical write is `AddPatientNoteTool` → `PatientNote::create` (confirm-gated but no permission check, no branch_id). Blast radius = a note. Known V3 debt, not a V1.1 emergency.

---

## SECTION J — Transaction / invariant risks

Transactional (verified): plan create/update, acceptance, visit save (all side-effects), partial-tooth invoicing, prescription save (both paths internally).
NOT transactional: **consultation + COHA + specialty modules** (D4); **LabCaseTransitionService** (C6); activity/observer events fire outside transactions (`treatment_plan.created` logged after commit; observers on Eloquent hooks).

Invariants V1.1 should own, with current enforcement status:

1. One clinical record per real-world decision (accept/reject/defer recorded as facts) — NOT enforced (D3).
2. A completed visit's procedures reflect onto plan items — NOT enforced (D1).
3. `TreatmentPlan.status='completed'` has exactly one rule — NOT enforced, 3 writers (D2).
4. Accepted-plan totals immutable (or amendments versioned) — PARTIAL (only billed/completed items protected).
5. Prescription immutable once issued — ENFORCED on API only (C2).
6. Revert/delete blocked once invoiced — ENFORCED (good).
7. Lab status follows STATUS_FLOW — ENFORCED app-level; atomicity NOT (C6).
8. Branch isolation on clinical writes — INCONSISTENT (C3).
9. One prescription representation per encounter — NOT enforced (D9).

No DB-level constraints back any invariant; everything is service-layer or absent.

---

## SECTION K — Permission gaps

Confirmed matrix (evidence in C1/D5): web clinical writes = view-level `module:patients` (C1); prescriptions web = `auth` only (C2); lab web = view-level `module:lab` incl. reject (C4); API plan writes exclude doctors (D5); API consultation delete gated by a view permission; zero `@can`/`canAccess` in consultation & prescription Blades (buttons render for everyone); lab reject admin-gated in Blade only. Permission *resolution* itself (`User::canAccess` + `EnsureApiRole`) is clean and single-sourced — the fix is assignment, not machinery.

---

## SECTION L — Journey Timeline / event gaps

| Event | Status |
|---|---|
| Consultation created | DUPLICATED (direct query + `consultation.completed` ledger row from observer — mislabeled: fires on *create*, actor always null) |
| Diagnosis recorded | NOT produced (folded into consultation text) |
| Plan created | DUPLICATED (direct query + ledger, logged outside the transaction) |
| Plan accepted | DUPLICATED (direct query + ledger inside transaction) |
| Plan rejected / deferred | Derived read-time heuristics only — no recorded event; history not faithful after revert |
| Treatment started | NOT produced |
| Visit completed | Direct query only (deliberate, to avoid duplicate recall) |
| Procedure completed | NOT produced (bundled in visit) |
| Clinical note | Direct query; actor hardcoded null (`UnifiedTimelineService.php:192`) |
| Prescription issued | **NOT produced** — `addPrescriptionless()` is an explicit no-op stub |
| Lab events | Direct query of `lab_case_events`; `lab.received` DUPLICATED via observer (actor null) |
| Clinical TreatmentConsent | NOT produced (only DPDP ConsentLog has an adapter) |

Also: per-source caps truncate deep histories; derived plan events reflect current column state, not historical transitions.

---

## SECTION M — Test coverage gaps

Covered: Journey timeline aggregator (2 test files), Lab→Recall/auto-close side effects, one Dusk smoke test each for plan and visit creation, appointments permission matrix (not clinical).

NOT covered — ranked by danger:
1. Plan accept/revert + `TreatmentPlanBillingService` billing-progress state machine (money-adjacent, zero tests).
2. `TreatmentVisitService` (validation, side-effects, rollback) and `createLabCase()` path.
3. Consultation create/update — the highest-traffic clinical write, zero Feature tests.
4. Clinical permissions (no consultation/plan/visit/lab equivalent of the Appointments matrix tests) — required *before* fixing C1, to characterize then lock the new gates.
5. Mobile API clinical parity (`Api\V1\TreatmentVisit/Lab/Coha/TreatmentPlan` controllers untested — the known web/mobile drift class).
6. Treatment-consent generation; transaction rollback verification; plan↔visit consistency.

---

## SECTION N — Lab integration boundary

Belongs to Patients V1.1: the *creation contract* — `TreatmentVisitService::createLabCase()` (currently direct `LabCase::create` + validation rules living in the visit service) should call a small Lab-owned intake API/service instead of writing Lab tables directly. The embedded lab form inside `treatment-visits-tab.blade.php` stays as UI but posts through that contract.

Stays owned by Lab: state machine, transitions, notifications (`LabNotificationService` is a clean one-way boundary — notifies, never mutates clinical state), reconciliation, vendors, expenses. Lab hardening items discovered here (C4, C6, D13, priority vocab, attachment fork) belong to the **Lab module's own completion work**, scheduled alongside V1.1 — not absorbed into Patients.

`RecallEngineService` reading `LabCase` directly (final_received → recall) is acceptable read-mostly coupling; note it, don't rebuild it.

---

## SECTION O — Finance boundary (for the NEXT phase)

What Finance will inherit — inspect now, redesign later:
1. Two unreconciled billing entry points: visit → `BillingPrompt` vs plan → `TreatmentPlanBillingService` per-tooth invoicing. The same procedure can be prompted AND separately invoiced; nothing links `treatment_visit_items` to plan-tooth invoices. This is the #1 Finance-phase design problem.
2. Plan-total recomputation inlined 3× in `TreatmentPlanController` (store/update/destroyItem).
3. No server-side pricing authority (D7) — Finance cannot trust any billed line until write-time price snapshotting from a master exists.
4. `TreatmentPlanBillingService` mutating clinical `status='completed'` as a billing side effect — keep explicit or move to an event.
5. `billing_progress`/`invoiced_units` live on the clinical item model but are computed by finance code — ownership decision needed.
6. `BillingPrompt` consumption path (prompt → invoice) is untested and untraced — audit it at Finance-phase kickoff.

V1.1 must NOT start billing/wallet/membership refactoring. The only finance-adjacent V1.1 work is D2 (one completion rule) because it corrupts *clinical* truth today.

---

## SECTION P — Frozen Patients V1.0 regression risks

V1.1 touches the frozen foundation at exactly these seams — extend, don't reopen:

- **Journey Timeline**: V1.1 will add/fix event producers. MUST NOT restructure `UnifiedTimelineService`'s aggregation or `PatientJourneyService`'s contract; only add adapters/producers behind the existing pattern. `JourneyTimelineTest` + `JourneyServiceTest` must stay green.
- **Patient Profile (`patients/show.blade.php` + lazy tabs)**: the plan/visit tabs live inside it. Fix tab *content* files; do not touch the Alpine scope of the shell (known fragile) or the lazy-tab loader.
- **PatientService::register() invariant**: untouched — no clinical flow mints patients. Any V1.1 code must keep it that way.
- **Merge / Family-Guardian / Consent anchor**: clinical tables are merge-relinked by patient_id; if V1.1 adds new clinical child tables (e.g., a plan-decision table), they must be added to the merge service's relink map — this is the single most likely silent V1.0 regression.
- **Patient API envelope**: adding clinical fields to API resources must not alter existing patient endpoints' shapes.
- **Route permission changes (C1 fix) will alter behavior for existing roles** — characterize current access with tests first, then tighten deliberately with CEO sign-off on which roles keep write access.
- Phase 4 profile files are still uncommitted locally (per project state) — commit before V1.1 branches, or the diff becomes unauditable.

---

## SECTION Q — Recommended Patients V1.1 scope

1. **Permission hardening of the clinical chain** (C1, C2 route gating, C4 lab reject, D5 API role coherence) — the cheapest, highest-risk-reduction work in the module.
2. **Clinical truth model**: recorded accept/reject/defer decisions (D3), one plan-completion rule (D2), a real writer for item-level progress from visits (D1), visit-status UI fix (D8).
3. **ConsultationService**: one transactional write path for all 4 workflows + COHA, shared validation, typed-edit routing fix (D4).
4. **Prescription lifecycle unification** on the API contract (immutable-once-issued, versioning, CDSS wired on web, overrides persisted) (C2).
5. **Lab intake contract + lab write-path fixes** at the boundary (C4, C6, D13) — jointly with Lab-module hardening.
6. **Journey event completeness** for the above (rejected/deferred/prescription/consent producers, actor fields) — additive only (Section P).
7. **Ghost-column and dead-file cleanup** (C5, Section F) — end of phase.
8. **Characterization + feature tests** for all of the above — start of phase.

## SECTION R — Explicitly NOT in V1.1

- Billing/Wallet/Membership refactoring; reconciling BillingPrompt vs plan invoicing (Finance phase — Section O).
- Server-side pricing authority *implementation* (Finance phase; V1.1 only fixes the D2 clinical-status corruption). Note it as a Finance prerequisite.
- branch_id/clinic_id isolation rollout (belongs to the parked Encryption/Access-Hardening level-1 work; do it once, properly, pre-clinic #2 — don't half-do it here).
- Case Acceptance Engine activation (V-later; but V1.1's D3 decision-recording is its prerequisite — build the data, not the engine).
- AI ToolRegistry permission plumbing (V3 debt; blast radius today is a patient note).
- Consultation form UI redesign / componentization of the giant Blades (fix content bugs only; a rewrite is its own project).
- ABDM/FHIR work (flags off; but do NOT build new features on `clinical_findings`/`diagnoses` — they're dead).
- Rebuilding the timeline aggregator, patient profile shell, merge, family, or anything else frozen.

## SECTION S — Proposed corrective phases (4 slices)

**Slice 1 — Characterize & Lock the Doors** (lowest regression risk, do first)
Characterization tests for current clinical permissions + core write paths (mirror the Appointments permission-matrix approach); then: `,edit`/`,delete` on web clinical routes (both groups, :196 and :296), module gate on prescriptions routes, server-side lab reject check, API role coherence (doctors get plan writes; delete not gated by view), Blade button visibility to match. CEO decision input: which roles hold `patients,edit` vs a new finer-grained clinical permission (recommend staying with module-level edit — no per-action permission system in V1.1).

**Slice 2 — One Truth per Clinical Fact** (the heart of V1.1)
`ConsultationService` (transactional, all 5 write paths, shared validation, typed-edit fix). Plan-decision recording: accept (exists) + reject + defer as stored, audited facts via `TreatmentPlanAcceptanceService`; retire read-time derivation gradually. One rule for plan completion; visits write item progress; visit-status UI + Huddle fix. Prescription lifecycle unification behind one service (web adopts API contract); CDSS wired to web form. Web/API syncItems convergence (D6). Status enums (PlanStatus/VisitStatus/ItemProgress) as PHP enums referenced everywhere.

**Slice 3 — Boundaries & Events**
Lab intake service (visit → lab via Lab-owned contract); `LabCaseTransitionService` transaction; priority vocabulary + attachment store unification; `labVendor()` fix. Journey producers added inside transactions: plan rejected/deferred, prescription issued, treatment consent, actor on notes/observer events; timeline adapters only added, never restructured. Consent: D11 silent-false fix; C7 consent-gated media scope wired into CMS search.

**Slice 4 — Cleanup, Forensic Re-audit & Freeze**
Ghost-column removal from models (C5), `TreatmentController.php:126` fix, dead-file deletion per Section F (SAFE list first; VERIFY list only after prod row-count checks), dead enum/status branches, legacy shims. Full regression run incl. V1.0 suites (44 Patients tests + guards). Final forensic pass, freeze doc `docs/patients-module-master.md` amendment, V1.1 tag.

Ordering rationale: tests before permission tightening (behavior change) and before consolidation (Slice 2 rewires the most dangerous paths); boundaries/events after truth model exists (events describe facts — record the facts first); deletion last, after everything that might secretly depend on "dead" code has been rebuilt and tested.

---

*Audit complete. No code, files, migrations, or permissions were changed. All P0/P1 headline claims were independently spot-verified against the repository; items marked "sub-agent finding — re-verify" (C4 web attachment mismatch) should be confirmed before execution planning treats them as fact.*
