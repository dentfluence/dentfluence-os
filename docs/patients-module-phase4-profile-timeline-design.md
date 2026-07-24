# Patients Module — Phase 4 Design (FROZEN)
## Patient Profile Refactor + Journey Timeline

> **Status:** DESIGN ONLY — architecture frozen, no code written.
> **Predecessor:** Phase 3 (Family/Guardian) — COMPLETE & FROZEN 2026-07-22.
> **Author session:** 2026-07-22.
> **Governing directive:** CEO Directive #003 (P1 = polish existing, no new modules, quality > breadth, no duplicate business logic).
> **Scope guard:** Phase 4 delivers exactly two things — (1) refactor the Patient Profile into a modular architecture, (2) introduce a unified Journey Timeline. Nothing else.

---

## 0. Executive Summary

The Patient Profile is the single most-opened clinical screen in Dentfluence and its most dangerous monolith: `patients/show.blade.php` is **3,705 lines**, backed by **~8,500 lines** of tab partials, one **211-line** loader service that fires **~15 eager loads + ~8 standalone queries** on every open, and **one 190-line Alpine root component** that owns notes, opportunities, tab state and WhatsApp. It scores **7/10** in the product audit and is explicitly flagged as a "monolith hotspot."

The good news, discovered during audit: **Dentfluence already owns a timeline engine.** `Activity` (ledger) + `ActivityEngine` (writer) + `UnifiedTimelineService` (reader, 6 sources) + `ActivityRecorded` (domain event) + feature flag `activity.single_ledger_reads` already exist and already power the **PRE Relationship Profile** timeline. The clinical Patient Profile, however, has no journey view — only a hand-rolled "Visit Log" panel on the Profile tab.

**Therefore Phase 4 does NOT build a new timeline.** It (a) carves the monolith into server-rendered Blade components with a single read-model service, and (b) extends the *existing* `UnifiedTimelineService` with clinical source adapters and a scope parameter, so the clinical profile and the PRE profile share one aggregator and one normalized event shape. This is the only design consistent with "no duplicate business logic."

The whole of Phase 4 ships in **three slices**: (1) Profile refactor, (2) Journey Timeline, (3) Hardening/Regression/Freeze.

---

## 1. Architecture Audit

### 1.1 Files in scope

| File | Lines | Role |
|---|---:|---|
| `resources/views/patients/show.blade.php` | 3,705 | The monolith: header, 11 tabs, right rail, 6 inline `<script>` blocks, Alpine root |
| `app/Http/Controllers/PatientController.php` | 447 | `show()` + 12 other actions (list/create/store/update/delete/notes/opportunities/search/quickStore/scanForm/print) |
| `app/Services/PatientProfileService.php` | 211 | `loadProfile()` — assembles the entire view-model in one call |
| `app/Http/Controllers/Api/V1/PatientProfileController.php` | 691 | Mobile/API counterpart (parity surface — must not drift) |
| `partials/treatment-visits-tab.blade.php` | 2,259 | Treatment Visits tab |
| `partials/treatment-plan-tab.blade.php` | 1,959 | Treatment Plan tab |
| `partials/documents-upload-modal.blade.php` | 1,045 | Upload modal |
| `partials/documents-tab.blade.php` | 825 | Documents (ClinicalFile) tab |
| `partials/membership-tab.blade.php` | 583 | Membership tab |
| `partials/lab-tab.blade.php` | 474 | Lab Cases tab |
| `partials/edit-patient-drawer.blade.php` | 445 | **Dead** — replaced by shared add-patient modal (per in-file comment) |
| `partials/communications-tab.blade.php` | 323 | **Retired** — consolidated into PRE Relationship tab (see memory) |
| `partials/patient-tags.blade.php` | 318 | Tags widget |
| `partials/family-contacts.blade.php` | 275 | Phase 3 Family/Guardian section (Profile tab) |

### 1.2 Responsibility map — `show.blade.php`

**Header (lines ~92–493, sticky):** breadcrumb; action buttons (Edit, WhatsApp, Print, Book, New Visit, Consultation); admin-only deactivate/reactivate/delete dropdown (`auth()->user()?->isAdminRole()`); deactivation-reason banner; merged-record notice banner; **11-tab capsule nav** (`profile, consultation, treatment-plan, visits, lab, prescriptions, billing, wallet, membership, documents, notes`).

**Tab panels (all rendered server-side, toggled by Alpine `x-show="activeTab === '…'"`):**

| Tab | Owner | Notes |
|---|---|---|
| Profile | inline (~495–1060) | 50/50 layout: **left** = Patient Details + Family/Guardian (`@include family-contacts`); **right rail** = Patient Snapshot, **Visit Log / Timeline** (proto-timeline: appointments+visits only), Recall Status, Active Opportunities, AOCP Membership, Total/Credit Balance, Quick Actions |
| Consultation | inline (~1061) | list + links to consultation module |
| Treatment Plan | `@include treatment-plan-tab` | 1,959-line partial |
| Treatment Visits | `@include treatment-visits-tab` | 2,259-line partial; listens for `open-visit-form` window event |
| Lab | `@include lab-tab` | receives `$labCases, $labDoctors, $labVendors` |
| Prescriptions | inline (~1460) + `@include prescriptions.partials.quick-form` (twice) | |
| Billing | inline (~1651) | invoices, billing prompts; `fetch('/billing/{id}/panel')` |
| Wallet | inline (~2374) | wallet balance + ledger |
| Membership | `@include membership-tab` | enroll modal, benefit logs |
| Documents | `@include documents-tab` + upload modal | ClinicalFile |
| Notes & Logs | inline | relationship notes + opportunities (Alpine-driven) |

**Alpine root — `patientProfile()` (lines 3488–3680):** single component owning `activeTab` (+ hash-routing on init), relationship-notes CRUD (`saveNote/deleteNote`), opportunities CRUD (`saveOpportunity/openOppEdit/saveOppEdit/deleteOpp`), opportunity colour/icon maps, note-tag toggling. Plus a free function `patientWhatsApp()`.

**Inline AJAX endpoints hit from the blade:**
- `POST /patients/{id}/relationship-notes`, `DELETE …/{noteId}`
- `POST /patients/{id}/opportunities`, `POST …/{oppId}` (spoofs PATCH/DELETE via `_method`)
- `POST /communication/whatsapp/link` (consent-gated)
- `GET /billing/{invoice}/panel` (XHR)
- `fetch(delete_url)` for a timeline/document entry (~line 968)

### 1.3 Controller & service responsibilities

`PatientController::show()` does five things: (1) redirect merged records to survivor, (2) 404 trashed, (3) write an `AuditLog` "viewed" event, (4) call `PatientProfileService::loadProfile()`, (5) bolt on Phase-3 family data (`familyLinks, familyGuardians, isMinor, householdCount, membershipFamilyName, canEditFamily`) **directly in the controller**.

`PatientProfileService::loadProfile()` returns a **26-key** view-model in one shot: eager-loads 11 relation paths on `$patient`, then runs standalone queries for `recallTask`, `clinicalFiles`, `billingPrompts`, `invoices`, `wallet`, `prescriptions`, `activeMembership`, `membershipPlans`, `membershipHistory`, `benefitLogs`, `activeFamilyHeads`, `implantCatalog`, `doctors`, `treatments` (with a consent-rule sub-query). Every tab's data is loaded on every profile open regardless of which tab the user views.

### 1.4 Permission surface (scattered)

- Route group `module:patients` (view) / `module:patients,edit` / `module:patients,delete`.
- In-blade: `auth()->user()?->isAdminRole()` gates the destructive dropdown.
- In-controller: `canAccess('patients','edit')` → `$data['canEditFamily']`.
- Note/opportunity AJAX writes are **only** protected by the group `module:patients` gate, not per-action edit — the write endpoints live outside the `,edit` sub-group.
- Cross-cutting audit finding: **no per-action permissions**; view/edit/delete is the whole vocabulary.

### 1.5 Duplicated / dead responsibilities

- **Two timelines, neither shared:** the Profile tab's inline "Visit Log / Timeline" (appointments+visits) vs. the PRE `UnifiedTimelineService`/`ProfileController::buildTimeline` (6 comms sources). Neither knows about the other.
- **Dead partials:** `edit-patient-drawer.blade.php` (superseded by shared modal), `communications-tab.blade.php` (retired into PRE).
- **Family data assembled in the controller**, not the service — breaks the "one loader" contract.
- **Web/API duplicate logic** (`PatientProfileController` 691 lines re-derives the same view-model) — a known cross-cutting debt, not unique to Phase 4 but aggravated by the monolith.

---

## 2. Problem Analysis (classified)

| ID | Problem | Severity | Why it matters |
|---|---|---|---|
| PR-1 | 3,705-line blade + 8.5k partial lines; impossible to review/test in isolation | **P0** | Every profile change risks the whole screen; Alpine scope is "fragile" (memory) |
| PR-2 | `loadProfile()` loads all 11 tabs' data on every open (~20+ queries, most unused) | **P0** | Slowest hot path in the app; scales badly with visit/invoice history |
| PR-3 | No unified Journey Timeline on the clinical profile (proto "Visit Log" only) | **P0** | Phase 4 core deliverable; blocks AI "what happened with this patient?" |
| PR-4 | Family view-model assembled in controller, not service | **P1** | Violates single-loader contract; drifts from API |
| PR-5 | Note/opportunity writes gated only by module-view, not `,edit` | **P1** | Permission leak: a view-only user may write |
| PR-6 | Single Alpine root owns unrelated concerns (tabs+notes+opps) | **P1** | Change coupling; hard to extract components |
| PR-7 | Web view-model and `Api/V1/PatientProfileController` duplicate derivation | **P1** | Parity drift risk (5 money bugs historically from parity gaps) |
| PR-8 | Dead partials still shipped (edit-drawer, comms-tab) | **P2** | Confusion, dead weight, false coupling |
| PR-9 | Inline `<script>` × 6 + inline styles; no component encapsulation | **P2** | CSP-hostile, untestable, duplicated markup |
| PR-10 | Tab data has no lazy/pagination (e.g. all invoices, 20 Rx, 50 benefit logs) | **P2** | Unbounded growth on long-tenured patients |
| PR-11 | `isAdminRole()` UI gate not mirrored by a policy | **P3** | Defence-in-depth; low real risk today |

**Non-goals (explicitly out of scope for Phase 4):** closing web/API duplication (PR-7 is *contained*, not solved), building per-action permissions (PR-11), rewriting the mobile profile. These are noted so implementation does not scope-creep.

---

## 3. Target Architecture — Component Tree

Design principle (per project UI philosophy + "Simple Not Busier" memory): **server-rendered Blade components, one shared read-model service, Alpine only for local interactivity, lazy-load heavy tabs.** No Livewire (not in the stack; would add a runtime dependency for no P1 benefit). No SPA.

```
Patient Profile  (patients/show.blade.php  — thin orchestrator, target < 300 lines)
│
├── <x-patient.header>                     ← breadcrumb, identity, action buttons, admin menu, tab nav
│
├── <x-patient.alerts>                     ← merged/deactivated/duplicate banners + clinical alerts
│
├── Tab: Profile  (default, eager)
│   ├── <x-patient.identity-card>          ← Patient Details (left)
│   ├── <x-patient.family-panel>           ← Phase 3 family/guardian (existing partial, wrapped)
│   └── Right rail:
│       ├── <x-patient.snapshot>           ← Patient Snapshot (age, since, doctor, tags)
│       ├── <x-patient.journey-timeline/>  ← NEW — unified timeline (lazy, paginated)
│       ├── <x-patient.recall-status>
│       ├── <x-patient.opportunities>      ← Alpine island (own component)
│       ├── <x-patient.membership-badge>
│       └── <x-patient.financial-summary>  ← total/credit/wallet balance
│
├── Tab: Consultation      (lazy)  → <x-patient.tab.consultation>
├── Tab: Treatment Plan    (lazy)  → existing partial, unchanged internally
├── Tab: Treatment Visits  (lazy)  → existing partial, unchanged internally
├── Tab: Lab               (lazy)  → existing partial
├── Tab: Prescriptions     (lazy)  → existing partial + quick-form
├── Tab: Billing           (lazy)  → existing partial
├── Tab: Wallet            (lazy)  → existing partial
├── Tab: Membership        (lazy)  → existing partial
├── Tab: Documents         (lazy)  → existing partial + upload modal
└── Tab: Notes & Logs      (lazy)  → <x-patient.notes-log>  (Alpine island)
```

**Refactor stance — deliberately conservative:** Phase 4 extracts the **header, alerts, Profile-tab sections, and the two Alpine islands (notes, opportunities)** into components, and makes tabs **lazy-loaded**. It does **not** rewrite the internal markup of the four giant existing partials (treatment-plan, treatment-visits, documents, membership) — those already work, are tested, and rewriting them is not required by the two Phase-4 goals. They are merely wrapped as lazy tab bodies. This keeps risk bounded (avoids re-testing 6k lines of working clinical UI) while still killing the orchestrator monolith.

**Lazy-load mechanism:** each non-Profile tab becomes a route-backed fragment — `GET /patients/{patient}/tab/{tab}` returns the rendered partial; Alpine fetches it on first activation and caches it in-component. This is the single biggest performance win (PR-2) and requires no new framework.

---

## 4. Data Flow

Per component: **Input · Owner · Services · Permissions · Events · Deps · Loading.**

| Component | Input | Owner | Services | Perms | Loading |
|---|---|---|---|---|---|
| header | `$patient` | `PatientController::show` | — | `module:patients`; admin menu = `isAdminRole()` | eager |
| alerts | `$patient`, flash | controller | — | view | eager |
| identity-card | `$patient` | `PatientProfileService::coreProfile()` | — | view | eager |
| family-panel | `familyLinks, familyGuardians, isMinor, householdCount, canEditFamily` | **move to** `PatientProfileService::familyPanel()` (out of controller) | `FamilyLinkService` (read) | view; edit = `canAccess('patients','edit')` | eager |
| snapshot | `$patient`, counts | `PatientProfileService::snapshot()` | — | view | eager |
| **journey-timeline** | `$patient`→`relationship` | **`PatientJourneyService`** (thin wrapper) → `UnifiedTimelineService::for($rel, scope:'clinical')` | UnifiedTimelineService + source adapters | view; per-event `permission` key (§5) | **lazy + paginated** (cursor) |
| recall-status | `recallTask` | `PatientProfileService::recall()` | RecallEngine (read) | view | eager |
| opportunities | `opportunities` | `PatientProfileService` | `saveOpportunity()` | **edit** (fix PR-5) | eager list, AJAX writes |
| membership-badge | `activeMembership` | `MembershipBenefitService::getActive` | — | view | eager |
| financial-summary | `wallet, invoices(sum)` | `PatientProfileService::financials()` | — | view | eager (aggregate only, not full lists) |
| notes-log | `relationshipNotes` | `PatientProfileService` | `addRelationshipNote()` | **edit** (fix PR-5) | lazy tab, AJAX writes |
| tab.* (clinical) | per-tab | tab fragment endpoint | existing per-tab services | view (+ module gates for lab/billing) | **lazy** |

**Query-duplication rules (binding):**
- `loadProfile()` splits into **`coreProfile()`** (always) + **per-tab loaders** invoked only by the lazy fragment endpoint. The Profile tab's eager set drops from ~20 queries to the identity/snapshot/family/financial-aggregate/recall/opportunity/membership set (~8, all indexed by `patient_id`).
- Financial summary uses **aggregates** (`SUM`), never hydrates full invoice/payment collections on the Profile tab.
- The timeline never triggers N+1: each source adapter eager-loads its actor/relation and returns the normalized shape; actor names resolved through `UnifiedTimelineService::userName()` cache.

---

## 5. Journey Timeline — Event Catalog

Single normalized event shape (already established by `UnifiedTimelineService`):
`['date','type','icon','title','description','actor','link','color','permission','meta']`.

| # | Event `type` | Source model → timestamp | Title / Description | Actor | Link | Icon | Colour | Permission |
|---|---|---|---|---|---|---|---|---|
| 1 | `patient.created` | `Patient.created_at` | "Patient registered" · TDC no. | `creator()` | `patients.show` | user-plus | slate | patients.view |
| 2 | `appointment` | `Appointment.appointment_date` (+ `checked_in_at`, `completed_at`) | "Appointment — {status}" · treatment | `doctor()` | `appointments.show` | calendar | blue | patients.view |
| 3 | `consultation` | `Consultation.consultation_date` | "Consultation — {type}" · chief complaint | `doctor_id` | `patients.consultations.show` | stethoscope | teal | patients.view |
| 4 | `coha` | `ConsultationCohaReport.report_date` | "COHA report" | `doctor_id` | `coha.report` | clipboard | teal | patients.view |
| 5 | `treatment_plan` | `TreatmentPlan.plan_date`; `accepted_at` | "Plan created" / "Plan **accepted**" · ₹ total | `doctor()` / `creator()` | `treatment-plans.items` | plan | violet | patients.view |
| 6 | `consent` | `ConsentLog.created_at` (append-only) | "Consent {granted/withdrawn} — {purpose}" | `captured_by` | — (internal) | shield | amber | consent.view |
| 7 | `invoice` | `Invoice.invoice_date` | "Invoice #{no} — ₹{total}" | `created_by` | `billing.print` | receipt | indigo | billing.view |
| 8 | `payment` | `InvoicePayment.payment_date` | "Payment ₹{amt} — {mode}" | `created_by` | `billing.receipt` | rupee | green | billing.view |
| 9 | `treatment_visit` | `TreatmentVisit.visit_date` | "Treatment visit" · procedures done | `doctor_id` | `visits.print` | tooth | violet | patients.view |
| 10 | `clinical_note` | `PatientNote` / `PatientRelationshipNote.created_at` | note excerpt | `createdBy()`/`author()` | — | note | slate | patients.view |
| 11 | `media` | `ClinicalFile.captured_at` | "{n} file(s) captured" | `uploadedBy()` | `clinical-files.show` | image | cyan | patients.view |
| 12 | `lab` | `LabCaseEvent.created_at` (+ `LabCase` milestones) | "Lab — {event}" | `LabCase.creator()` | `lab.show` | flask | orange | lab.view |
| 13 | `membership` | `FinancePatientMembership.start_date`; `MembershipBenefitLog.availed_at` | "Membership enrolled" / "Benefit availed" | `created_by`/`createdBy()` | — | crown | gold | patients.view |
| 14 | `review` | `Review.requested_at` / `responded_at` | "Review {requested/received ★n}" | `requestedBy()` | — | star | yellow | patients.view |
| 15 | `recall` | `Task(category=follow_up).due_date` / `Activity recall.queued` | "Recall {due/queued}" | `assigned_to` | — | bell | rose | patients.view |
| 16 | `task` | `Task.created_at` | "Task — {title}" | `assigned_to` | — | check | slate | patients.view |
| 17 | `communication` | `PatientCommunication` | existing comms rows | — | — | chat | green | patients.view |
| 18 | `treatment.accepted` | `TreatmentPlan.accepted_at` (ledger event `treatment_plan.accepted` already emitted by `TreatmentPlanAcceptanceService`) | "Treatment plan accepted" · plan name + ₹ total | `accepted via` actor from Activity / plan `doctor()` | `treatment-plans.items` | check-circle | green | patients.view |
| 19 | `treatment.rejected` | `TreatmentPlan.updated_at` where `status='cancelled'` and `accepted_at` is null | "Treatment plan rejected/cancelled" · plan name | plan `creator()` | `treatment-plans.items` | x-circle | red | patients.view |
| 20 | `treatment.deferred` | **derived at read time**: plan `status='pending'`, `accepted_at` null, `plan_date` > 14 days old | "Treatment plan pending decision (deferred)" · days waiting | plan `doctor()` | `treatment-plans.items` | pause-circle | amber | patients.view |
| — | **future AI events** | `Activity.event = 'ai.*'` | any AI-authored action | `actor` (morph) | contextual | spark | purple | patients.view |

> **Schema note (Amendment 1):** the `treatment_plans` schema has no `rejected`/`deferred` columns (status enum = `pending|ongoing|completed|cancelled`; acceptance = `accepted_at`). Amendment events therefore map to existing data — no migration. `treatment.deferred` is the first *derived* event (§8 recommendation 3) and proves the derivation pattern for V3.

**Filter groups (mirrors existing PRE filter bar):** All · Clinical (3,4,5,9,11) · Financial (7,8,13) · Comms (10,15,16,17) · Consent (6) · Reviews (14).

Events with no per-record route render as non-clickable cards (consistent with today's PRE timeline). Every event carries a `permission` key so the aggregator can drop rows the viewer may not see (e.g. a receptionist without `billing.view` never sees invoice/payment rows).

---

## 6. Timeline Architecture — Decision

**Options considered:**

- **A. New event repository / dedicated `patient_timeline` table.** Rejected — duplicates the existing `activities` ledger and its writer; violates "no duplicate business logic"; requires backfill of all history; two writers to keep in sync.
- **B. Aggregator service (read-time fan-out over source models).** This is what `UnifiedTimelineService` already is.
- **C. Direct model queries in the blade/controller.** Rejected — that is exactly today's fragile inline "Visit Log"; untestable, N+1-prone, unshareable with mobile/AI.
- **D. Pure event-sourcing.** Rejected as over-engineering for P1.

**Decision: extend Option B — reuse and grow `UnifiedTimelineService`, backed long-term by the `Activity` ledger (hybrid).**

Concretely:
1. Add a **`scope`** parameter to `UnifiedTimelineService::for(Relationship $rel, string $scope = 'relationship', int $limit)`. Scopes: `relationship` (today's 6 comms sources — PRE profile, unchanged), `clinical` (adds sources 3–14 above), `all`.
2. Add the 8 missing sources as **source adapters** following the existing private `addX()` + `guard()` fault-isolation pattern (a failing source degrades to "section unavailable," never 500s the timeline).
3. Introduce **`PatientJourneyService`** — a *thin* patient-facing facade that resolves `patient → relationship` (via existing `findOrCreateForPatient()`) and calls `UnifiedTimelineService::for($rel,'clinical')`. This gives the clinical profile, mobile API, and AI one stable entry point without leaking the relationship abstraction.
4. **Forward path (already scaffolded):** producers increasingly write to the `Activity` ledger via `ActivityEngine::log()` (the `ActivityRecorded` domain event + `RulesEngine` already fire). When enough producers emit ledger rows, flipping `activity.single_ledger_reads` collapses the fan-out into a single indexed `activities` read — **without changing the timeline's public API or the blade component.** New modules then only need to `ActivityEngine::log()`; they never touch the timeline again.

**Why this satisfies "support future modules without rewriting the timeline":** the component and `PatientJourneyService` API are stable; a new module integrates by either emitting a ledger `Activity` (preferred) or registering one `addX()` adapter. Neither path touches the blade, the controller, or the mobile endpoint.

---

## 7. Performance Review

| Concern | Today | Phase 4 target |
|---|---|---|
| Profile-open query count | ~20+ (all tabs loaded) | ~8 (core only); tabs lazy | 
| N+1 risk | present in inline visit-log + partials | adapters eager-load actor/relation; `userName()` memoised |
| Heavy collections on open | all invoices, 20 Rx, 50 benefit logs, implant catalog | moved behind lazy tab endpoints; Profile tab uses `SUM` aggregates |
| Timeline paging | none (proto-log unbounded) | **cursor pagination**, default 20 events, "load older" |
| Caching | none | short-TTL cache of the assembled timeline page per patient, busted on `ActivityRecorded`; `implantCatalog`/`membershipPlans`/`doctors` cached (reference data) |
| Rendering | 3,705-line blade parsed every open | thin orchestrator + lazy fragments; only active tab rendered |
| Scalability | degrades with tenure (10-yr patient = huge payload) | bounded by pagination + aggregates regardless of tenure |

Non-negotiable perf guardrails for implementation: (1) a Dusk/PHPUnit assertion that the Profile tab issues **≤ 10 queries**; (2) timeline endpoint asserts a fixed query count independent of event volume; (3) no `->get()` of unbounded clinical collections on the eager path.

---

## 8. AI Readiness Review

Target questions and whether the design answers them:

| AI question | Answerable? | Mechanism |
|---|---|---|
| "What happened with this patient?" | ✅ | `PatientJourneyService::for($patient)` returns the full normalized, chronological event stream — one call, structured JSON |
| "What treatments are pending?" | ✅ | timeline exposes `treatment_plan` (accepted vs not) + `treatment_visit` completion; plan items already model `billing_status`/acceptance |
| "Why was treatment delayed?" | ⚠️ Partial | inferable from gaps (plan accepted → no visit; recall queued → no appointment) but not explicitly labelled |
| "What changed since last visit?" | ✅ | filter timeline by `date > lastVisit.visit_date`; `visit_date` is a first-class source |

**Recommendations (design-level, no code):**
1. Make `PatientJourneyService` the **single AI read model** — the existing `PatientSummaryTool` / `PatientBalanceTool` assistant tools should consume it rather than re-querying models, eliminating a third derivation path.
2. Give every event a stable machine `type` + `event` dot-code (already present in `Activity.event`) and a compact `meta` payload, so AI can reason without re-hydrating models.
3. For "why delayed," emit lightweight **derived events** at read time (e.g. `plan.stalled` when an accepted plan has no linked visit after N days) inside the aggregator — cheap, no schema change, and it directly powers a future V3 copilot without a timeline rewrite. *(Design-noted for V3; not built in Phase 4.)*

The design is AI-ready precisely because it funnels every domain through one normalized ledger/aggregator instead of 14 bespoke queries.

---

## 9. Implementation Plan — Three Slices

### Slice 1 — Patient Profile Refactor
**Scope:** thin `show.blade.php` orchestrator; extract `<x-patient.header/alerts/identity-card/snapshot/recall-status/financial-summary/membership-badge>` and the two Alpine islands (`opportunities`, `notes-log`); move family view-model out of the controller into `PatientProfileService::familyPanel()`; split `loadProfile()` into `coreProfile()` + per-tab loaders; add the lazy tab-fragment endpoint `GET /patients/{patient}/tab/{tab}`; **delete dead partials** (`edit-patient-drawer`, `communications-tab`) after confirming zero references; fix PR-5 by moving note/opportunity write routes under `module:patients,edit`.
**Files (expected):** `patients/show.blade.php` (shrunk), new `resources/views/components/patient/*.blade.php` (~8), `PatientProfileService.php`, `PatientController.php` (show + new `tab()` action), `routes/web.php` (1 route + move 4 write routes into the `,edit` group). No migrations.
**Risk:** Medium — touches the most-opened screen; Alpine scope is fragile (memory).
**Test strategy:** existing profile Dusk crawl (`app:crawl-routes`) must stay green; new tests — each extracted component renders in isolation; lazy tab endpoint returns each partial; note/opportunity write is 403 for view-only user; `≤10 queries` assertion on Profile tab. Snapshot-compare rendered Profile tab before/after.
**Rollback:** feature flag `patients.profile_v2` gating the new orchestrator; flag off = original blade path retained until slice proven. Revert is a single flag flip + git revert of the view.

### Slice 2 — Journey Timeline
**Scope:** add `scope` param + 8 clinical source adapters to `UnifiedTimelineService`; add `PatientJourneyService` facade; `<x-patient.journey-timeline>` component with filter groups + cursor pagination; timeline fragment endpoint `GET /patients/{patient}/timeline`; replace the inline Profile-tab "Visit Log" with the component; per-event permission filtering; cache-bust on `ActivityRecorded`.
**Files (expected):** `UnifiedTimelineService.php` (+adapters), new `app/Services/Patient/PatientJourneyService.php`, `components/patient/journey-timeline.blade.php`, `PatientController::timeline()`, `routes/web.php` (1 route), small JS for "load older." No migrations (reads existing tables). Optionally wire `PatientSummaryTool` to the facade.
**Risk:** Medium — many source models; mitigated by the `guard()` per-source fault isolation (one bad source never breaks the page).
**Test strategy:** unit test each adapter maps to the normalized shape; permission test (receptionist without `billing.view` sees no invoice/payment rows); fixed-query-count test independent of event volume; **PRE relationship timeline must be byte-identical** after refactor (scope `relationship` unchanged) — reuse existing `TimelineParityService`.
**Rollback:** timeline behind `patients.journey_timeline` flag; off = Profile tab shows the legacy Visit Log. PRE timeline untouched by default scope.

### Slice 3 — Hardening · Regression · Freeze
**Scope:** full regression of Patients module (Phases 1–4); confirm web ↔ mobile API parity (`Api/V1/PatientProfileController` unaffected or updated in lockstep); performance guardrail assertions; delete-dead-code verification; documentation freeze; flip flags on in staging → prod.
**Files:** tests only + this doc's freeze addendum. No new feature code.
**Risk:** Low.
**Test strategy:** run the Patients Phase 3 regression suite (44 tests + 4 guards, per memory) + new Phase 4 tests; `app:crawl-routes`; manual smoke on a long-tenured patient (perf) and a minor/guardian patient (Phase 3 interaction).
**Rollback:** both flags remain the kill-switch through the freeze window; freeze declared only when green on staging + prod smoke.

---

## 10. Risk Register

| ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Refactor regresses the busiest clinical screen | Med | High | `patients.profile_v2` flag; keep legacy path; snapshot + Dusk crawl before cutover |
| R2 | Fragile Alpine scope breaks on extraction | Med | Med | Extract islands one at a time; each has its own `x-data`; no shared root state beyond `activeTab` |
| R3 | A timeline source model throws (missing column/migration) | Med | Low | Existing `guard()` degrades that source to "unavailable"; never 500 |
| R4 | Lazy tabs change perceived behaviour (deep-link `#hash`) | Low | Low | Preserve hash-routing; first activation fetches + caches; retain `?tab=` support |
| R5 | Web/API parity drift (PatientProfileController) | Med | High | Slice 3 parity gate; `PatientJourneyService` reused by API to *reduce* divergence |
| R6 | PRE relationship timeline changes unintentionally | Low | Med | Default scope `relationship` frozen; `TimelineParityService` asserts identity |
| R7 | Permission filtering hides events a role *should* see | Low | Med | Explicit per-event `permission` map reviewed in Slice 2; default to `patients.view` |
| R8 | Scope creep into rewriting the 4 giant partials | Med | Med | Design forbids it; partials are wrapped, not rewritten |
| R9 | Caching serves stale timeline | Low | Low | Short TTL + bust on `ActivityRecorded`; read-through |

---

## 11. Technical Decisions (binding)

0. **`PatientJourneyService` is the canonical patient-history read model** *(Amendment 1 — binding)*. Any surface that needs "what happened with this patient" consumes it rather than querying source models: **Patient Profile, Mobile App API, AI Copilot tools, future Chairside App, future Patient Microsite.** New modules integrate by emitting `Activity` ledger events (preferred) or one source adapter — never by building a parallel history query.
1. **No new timeline store.** Extend `UnifiedTimelineService` + `Activity` ledger. (Rejects a `patient_timeline` table.)
2. **Aggregator now, ledger-backed later.** Hybrid via `activity.single_ledger_reads`; public API stable across the switch.
3. **`PatientJourneyService` is the single read model** for clinical profile + mobile + AI.
4. **Server-rendered Blade components, no Livewire/SPA.** Alpine only for local islands (notes, opportunities, tab-lazy-load).
5. **Lazy-load non-Profile tabs** via a fragment endpoint; `coreProfile()` vs per-tab loaders.
6. **Do not rewrite the four large working partials** — wrap them.
7. **Family view-model belongs in the service**, not the controller.
8. **Fix the note/opportunity permission leak** (move writes under `,edit`) as part of the refactor.
9. **No new feature flags** *(Amendment 1 — supersedes the original flag plan)*. Dentfluence is a single-tenant deploy (local Laragon → one Docker VPS via `deploy.sh`); rollback = `git revert` + redeploy, minutes end-to-end. Temporary flags would force keeping the 3,705-line legacy blade alive in-tree beside the new components — exactly the duplicate render path this phase exists to delete — plus flag-cleanup debt. Safety instead comes from: verbatim extraction, local verification before deploy, and slice-boundary commits (one revertable commit per slice). The existing `activity.single_ledger_reads` flag is untouched.
10. **Per-event permission keys** so the timeline respects role visibility.

---

## 12. Final Recommendation

Proceed with Phase 4 as three slices, in order, each behind its own flag. The decisive architectural call is **reuse, not rebuild**: Dentfluence already owns the timeline engine, and the correct Phase-4 move is to (a) dismantle the profile monolith into components with a lean loader, and (b) extend the existing aggregator with clinical sources behind a `PatientJourneyService` facade. This gives the clinic a genuinely useful "what happened with this patient" view, cuts the hottest screen's query load by ~60%, removes a 3,700-line liability, and — because everything funnels through one normalized ledger — leaves the app AI-ready for V3 without another rewrite. It adds **no new module**, honours CEO Directive #003, and keeps the UI simple while making the backend more powerful.

**Recommended sequence:** Slice 1 → prove on staging → Slice 2 → parity-gate against PRE → Slice 3 freeze.

> **This document is the frozen Phase 4 architecture. Implementation may begin against it; deviations require an explicit design amendment.**

---

## 13. Amendment 1 (2026-07-22) — Final Design Validation & Certification

Applied before implementation, per CEO instruction. Four changes:

1. **`PatientJourneyService` promoted to canonical patient-history read model** — see Technical Decision 0. Consumers: Patient Profile, Mobile App, AI Copilot, Chairside App, Patient Microsite. Binding.
2. **Three treatment-decision events added** to the event catalog (§5, events 18–20: `treatment.accepted`, `treatment.rejected`, `treatment.deferred`) mapped to existing schema — no migration; `treatment.deferred` is read-time derived.
3. **Feature flags dropped** (Technical Decision 9 rewritten). Rollback strategy = slice-boundary git commits + revert; slices' individual "rollback" entries in §9 are superseded accordingly.
4. **Final validation findings:**
   - *No duplicate timeline logic:* one aggregator (`UnifiedTimelineService`), scope-parameterised; PRE `relationship` scope byte-identical; clinical scope additive. `TreatmentPlanAcceptanceService` already emits `treatment_plan.accepted` to the ledger — the adapter reads it, does not re-log it. ✓
   - *No duplicated read model:* `PatientJourneyService` is a facade over the aggregator, not a second implementation; assistant tools to be repointed at it (V3 work, noted). ✓
   - *No unnecessary services:* two new classes only (`PatientJourneyService`; adapters live inside `UnifiedTimelineService` as private methods, consistent with its existing pattern). ✓
   - *Hidden coupling check:* audit found the Quick Pay modal lives outside all tab panels (used from any tab) and the sticky header computes financial totals from the full `$invoices` collection — therefore `$invoices` stays in `coreProfile()` for Slice 1 (identical behaviour) and the perf win comes from the other ~10 heavy loads moved behind lazy tabs. Also found: **duplicate Wallet tab panel** (two `x-show === 'wallet'` blocks) — preserved verbatim in Slice 1, flagged for removal in Slice 3 hardening with CEO sign-off.
   - *Extraction mechanics:* components implemented as **scoped Blade partials** (`patients/profile/*`, `patients/tabs/*`) rather than `<x-…>` anonymous components — includes inherit parent scope, guaranteeing behavioural fidelity of verbatim-moved markup; three tab partials use `@push`, so the lazy-fragment wrapper flushes `@stack('styles')`/`@stack('scripts')`. The §3 component tree stands logically; only the file mechanism differs.
   - *AI limitations:* none found beyond §8's noted derived-event roadmap; Amendment item 2 implements the first derived event. ✓

**Certification: PHASE 4 DESIGN FINAL.** Implementation proceeds against this document as amended.
