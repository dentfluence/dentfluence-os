# Patients Module — Master Reference

> **The permanent reference for the Dentfluence Patients Module.**
> Supersedes nothing; consolidates everything. Detailed phase records remain in:
> `patients-module-audit.md` · `patients-module-design.md` · `patients-module-phase2-freeze.md` ·
> `patients-module-phase3-family-guardian-design.md` · `patients-module-phase3-freeze.md` ·
> `patients-module-phase4-profile-timeline-design.md` (incl. Amendment 1) ·
> `patients-module-phase4-freeze.md` · `patients-module-phase4-acceptance-audit.md`.

---

## 1. Executive Summary

**Purpose.** The Patients Module is the identity spine of Dentfluence. It owns who a patient *is* — registration, deduplication, merge, family/guardian graph, validation, permissions, the profile screen, and the unified Journey Timeline ("what happened with this patient"). Every clinical, financial and communication module hangs off the patient identity this module mints and protects.

**Current status.** All four phases complete. Module **FROZEN as of the phases below**, with post-freeze hardening tracked separately — see Amendment 2 (2 August 2026).

| | |
|---|---|
| **Module** | Patients |
| **Version** | V1.0 |
| **Status** | ✅ FROZEN (Phases 1–4) — post-freeze hardening in progress, see Amendment 2 |
| **Freeze date** | 22 July 2026 |
| **Owner** | Dentfluence Core |
| **Phases** | 1 Merge ✅ · 2 Validation & Permissions ✅ · 3 Family/Guardian ✅ · 4 Profile Refactor + Journey Timeline ✅ |
| **Governance** | CEO Directive #003 (P1: polish existing, no new modules, no duplicate business logic) |

> **Amendment 2 (2 August 2026).** A full production-readiness audit (`Dentfluence_Patient_Module_Audit.docx`) and implementation blueprint (`Dentfluence_Patient_Module_Phase2_Blueprint.docx`) were completed and approved after this freeze. The audit found the module had continued to receive commits after the 22 July freeze date (Patient Journey V1.1 slices touching the treatment-plan/visit tabs), so "FROZEN" should be read as "Phases 1–4 architecturally complete," not "unmodified since 22 July." The following P0/P2/P3 hardening items from that audit have since been implemented: merge-reversal CLI messaging corrected to state merges are currently permanent; the Wallet tab's duplicate/contradictory rendering block removed (KD-1 resolved); the dead `PatientDocument` model and six confirmed-dead views removed (KD-2 resolved); `PatientSource` given an explicit `$fillable` guard; `PatientService::register()`/`softDelete()` secondary writes wrapped in transactions; `PatientProfileService::benefitLogs()`'s broad exception catch narrowed to the documented "table not migrated yet" case only. Items still open (policy decisions, permission-model changes, and larger effort items) remain tracked in the Phase 2 blueprint's task list (PM-003, PM-005, PM-006, PM-007, PM-008, PM-010, PM-011, PM-012, PM-016, PM-017, PM-019, PM-020).

> **Amendment 3 (3 August 2026) — Variants hardening (fix-only re-entry).** A four-track variant verification (entry paths, roles/permissions, data edge cases, UI/print) was run against this frozen module and the blocking defects fixed under a strict fix-only rule (no schema changes, no new endpoints, no behavior changes to working paths). Fixed: **PM-003** (`relationship-notes.destroy` + `opportunities.destroy` regated `,edit`→`,delete`); **PM-007+** (CSV import now requires `module:patients,edit` — it was reachable with settings *view*; export moved to `admin.only` middleware with proper 302 denial); the `routes/tags-routes.php` duplicate registration that silently **overrode the gated `patients.tags.*` routes with auth-only ones** (duplicates removed, settings/tags gated `module:settings`); four ungated API PHI reads (`GET /api/v1/patients`, `/patients/search`, `/patients/{p}/consultations/same-issue-context`, `/coha/{c}`) now require the patients View flag; **PM-006** (`findDuplicatesByPhone()` now matches on normalized last-10-digits — `+91`/spaces/hyphens/leading-zero variants no longer evade detection); `gender=prefer_not_to_say` removed from validation + form (DB enum rejects it → user-reachable 500; re-adding requires an additive enum migration); future DOB rejected (`before_or_equal:today` — was producing negative ages and false DPDP-minor flags); phone can no longer be blanked via partial update (`sometimes|filled`); the optimistic-lock conflict message now surfaces in the edit modal's banner (was silently discarding edits); the print view no longer crashes on array-cast `allergies`/`medical_conditions` and now prints the real medical fields (`medical_history`/`blood_group` were never columns), plus merged/trashed print guard; import summary now reports skips per reason instead of calling everything "duplicates"; dead `importForm()` removed. Regression suite: `tests/Feature/Patients/VariantHardeningTest.php` (includes a route-table guard test asserting every `patients.*` route declares a module/admin gate). Still open by design: PM-005 (minor/guardian registration policy — CEO/clinical decision), PM-008 (broader merge coverage), PM-010/PM-011 (P2, PM-011 is a schema change), PM-012/PM-016/PM-017/PM-019/PM-020 (out of freeze path).

> **Amendment 4 (3 August 2026, release pass).** Final production pass found and fixed one further P1: the Journey Timeline tagged money events `billing.view` and consent events `consent.view`, but **neither `billing` nor `consent` exists as a module slug** — `Role::can()` returns false for unknown slugs, so those events were invisible to every non-admin, including Accounts and Front Desk (the module catalogue's money slug is `finance`). Fixed: timeline money events → `finance.view`, consent events → `patients.view` (strictly tighter than the consent-capture UI's own gate); PRE scope untouched (permissions are metadata only the patients facade consumes; `TimelineParityTest` unaffected). The same `finance.view` rule is now enforced consistently on the money surfaces of the profile: Billing + Wallet tab fragments 403 without it, their pills and quick-actions render only with it — closing the §7.4 inconsistency where the timeline hid a payment the Billing tab showed. Note for owners: Doctor's default matrix has no finance grant, so doctors no longer see Billing/Wallet tabs unless the owner grants finance View — this is the owner-configured model working as designed. §9's permission table should be read with `billing.view` → `finance.view`. Regression tests added, incl. a slug-integrity guard asserting every timeline permission string resolves to a real module slug. Residual approved debt: eager header stat cards still show balance figures to all patients-view roles (compact chairside nudge, P3); API invoices/wallet reads remain `patients,view` per the frozen Slice 1.4 contract (aligning them to `finance,view` is a mobile-contract change — tracked as a dependency of the Mobile Parity program).

> **PM-005 — CLOSED (3 August 2026).** V1 policy: **guardian enforcement happens at consent capture**, not at registration. Registration accepts a minor without a guardian (front-desk intake stays fast); the profile shows the guardian nudge; `ConsentController` makes guardian name + relationship mandatory for minors — all covered by the green `ConsentGuardianAnchorTest` / `FamilyContactsSectionTest` suites. Adopted as the recommended default during the release pass; a registration-time warn or hard-block is an additive validation change and may be introduced later via a design amendment without breaking this freeze.

**The four invariants this module enforces:**

1. Patients are minted **only** through `PatientService::register()` (exceptions: seeders/factories/tests; known temporary debt: three appointment-booking paths, removed when Appointments is rebuilt).
2. `FamilyLinkService` is the **only** writer of the family/guardian graph.
3. `PatientJourneyService` is the **canonical patient-history read model** for every surface (web, mobile, AI, chairside, microsite).
4. All patient writes require `module:patients,edit` (deletes: `,delete`); the profile and its fragments require `module:patients` view.

---

## 2. Architecture Overview

The module is a classic thin-controller / fat-service Laravel vertical with no repository layer (Eloquent models are the data boundary, consistent with the rest of Dentfluence).

```
                         ┌────────────────────────────────────────────┐
                         │ routes/web.php  (module:patients group)     │
                         └───────────────┬────────────────────────────┘
                                         ▼
      ┌──────────────────────────────────────────────────────────────┐
      │ Controllers                                                   │
      │  PatientController        (list/CRUD/profile/tab/timeline)    │
      │  PatientMergeController   (admin-only merge)                  │
      │  Patient\FamilyController (family/guardian writes)            │
      │  PatientImportExport / PatientNote / PatientCommunication     │
      └───────┬───────────────┬──────────────────┬───────────────────┘
              ▼               ▼                  ▼
   ┌────────────────┐ ┌─────────────────────┐ ┌──────────────────────┐
   │ PatientService │ │ PatientProfileService│ │ PatientJourneyService│◄─ canonical
   │ register()     │ │ coreProfile()        │ │ for() / summarize()  │   history
   │ dedupe/update  │ │ tabData() familyPanel│ └──────────┬───────────┘   read model
   └───────┬────────┘ └──────────┬──────────┘            ▼
           │          ┌──────────┴──────────┐  ┌──────────────────────────┐
           │          │ FamilyLinkService   │  │ UnifiedTimelineService    │
           │          │ (family graph owner)│  │  for(Relationship) = PRE  │
           │          └─────────────────────┘  │  forPatient(Patient) =    │
           │          ┌─────────────────────┐  │  clinical scope, 12+4     │
           └─────────►│ PatientMergeService │  │  guarded source adapters  │
                      └─────────────────────┘  └──────────┬───────────────┘
                                                          ▼
                                     Activity ledger + 16 source models
                                                          │
      ┌───────────────────────────────────────────────────┴──────────┐
      │ Blade                                                         │
      │  patients/show.blade.php (328-line orchestrator + Alpine root)│
      │   ├─ patients/profile/*  (eager: header, profile tab, modals, │
      │   │                       journey-timeline card)              │
      │   └─ patients/tabs/*     (10 lazy fragments via @tab route)   │
      └──────────────────────────────────────────────────────────────┘
                                         ▼
                              Rendered Patient Profile UI
```

Two delivery paths exist beside the web UI: `Api/V1/PatientController` + `Api/V1/PatientProfileController` (mobile, independent derivation — known debt KD-8) and the assistant tools (to be repointed at `PatientJourneyService::summarize()` in V3).

---

## 3. Directory Structure

| Path | Why it exists |
|---|---|
| `app/Http/Controllers/PatientController.php` | Web entry point: list, register, profile, lazy tabs, timeline, notes/opportunities writes, search, quick-store |
| `app/Http/Controllers/PatientMergeController.php` | Admin-only duplicate merge (preview + execute) |
| `app/Http/Controllers/Patient/FamilyController.php` | Family link + guardian writes (Phase 3) |
| `app/Http/Controllers/PatientImportExportController.php` | CSV import/export with preview |
| `app/Http/Controllers/PatientNoteController.php` / `PatientCommunicationController.php` | Clinical notes; legacy comms endpoints (PRE is the comms front door) |
| `app/Services/PatientService.php` | **The only patient minting path** (`register()`), dedupe, update, deactivate, suggest |
| `app/Services/PatientProfileService.php` | Profile screen read model: `coreProfile()` / `tabData()` / `familyPanel()` |
| `app/Services/Patient/PatientJourneyService.php` | **Canonical patient-history read model** (Phase 4) |
| `app/Services/Patient/FamilyLinkService.php` | Canonical family/guardian graph reader/writer (Phase 3) |
| `app/Services/Patient/PatientMergeService.php` + `PatientMergeManifest.php` | Merge engine + table manifest (Phase 1) |
| `app/Services/Relationship/UnifiedTimelineService.php` | The ONE timeline aggregator — PRE scope + clinical scope |
| `app/Models/Patient.php`, `PatientLink.php`, `PatientRelationshipNote.php`, `TreatmentOpportunity.php` | Identity, family graph edge, rapport notes, opportunity tags |
| `app/Http/Requests/Patient/StorePatientRequest.php` / `UpdatePatientRequest.php` | Shared web+API validation, legacy-alias normalisation (Phase 2) |
| `resources/views/patients/show.blade.php` | 328-line profile orchestrator (was 3,705) |
| `resources/views/patients/profile/` | Eager pieces: `styles`, `header`, `tab-profile`, `journey-timeline`, `journey-timeline-events`, `quick-pay-modal`, `edit-patient-prefill`, `action-modal` |
| `resources/views/patients/tabs/` | `_fragment` wrapper + 10 lazy tab bodies (`consultation`…`notes`) |
| `resources/views/patients/partials/` | Large clinical partials wrapped by tabs (treatment-plan-tab, treatment-visits-tab, documents-tab(+upload-modal), membership-tab, lab-tab, family-contacts, patient-tags, detail-row) |
| `resources/views/patients/index / merge / import* / print / abha / _search` | List, merge UI, import, print view, ABHA capture, search dropdown |
| `tests/Feature/Patients/` | Phase 2–4 regression: family (3 files), consent anchor, `ProfileRefactorTest`, `JourneyTimelineTest` |
| `docs/patients-module-*.md` | Phase-by-phase design + freeze records; this master doc |

---

## 4. Controllers

**`PatientController`** — the workhorse (≈500 lines, 15 actions, no business logic).
Actions: `index` (filtered list via `PatientService::filteredQuery`), `create` (redirects to add-patient modal), `scanForm` (vision pre-fill, extraction only), `store` (validation + duplicate-phone soft guard + `register()` + optional family link), `show` (guards + `coreProfile` + `familyPanel`), `tab` (lazy fragment, whitelisted), `timeline` (Journey JSON via `PatientJourneyService`), `edit`/`update` (optimistic-lock via `ChecksStaleUpdates`), `destroy`/`deactivate`/`reactivate` (password + reason), `print`, `storeRelationshipNote`/`destroyRelationshipNote`, `storeOpportunity`/`updateOpportunity`/`destroyOpportunity`, `search` (JSON suggest), `quickStore` (appointment-modal quick add — carries the documented minting tech-debt note).
Dependencies: `PatientProfileService`, `PatientService`, `PatientJourneyService` (method-injected), `PatientScanService` (method-injected), `FamilyLinkService` (resolved for store-time linking).

**`PatientMergeController`** — admin-only: `create` (candidate picker), `preview` (dry-run manifest), `store` (execute merge). Delegates entirely to `PatientMergeService`.

**`Patient\FamilyController`** — `storeLink`, `updateLink`, `destroyLink`, `storeGuardian`; every action delegates to `FamilyLinkService`; all gated `module:patients,edit`.

**Others** — `PatientImportExportController` (import preview/commit, export), `PatientNoteController` (clinical notes store/destroy), `PatientCommunicationController` (legacy comms index/store/destroy), `Abdm\PatientAbhaController` (ABHA capture), `Api/V1/PatientController` + `Api/V1/PatientProfileController` (mobile; independent, untouched by Phase 4).

---

## 5. Services

**`PatientService`** — identity owner. Public: `register()` (THE minting path: alias mapping, display-name assembly, TDC, tags, relationship link), `updateFromInput()`, `filteredQuery()`, `distinctAreas()`, `findDuplicatesByPhone()`, `suggest()`, `softDelete()`, `deactivate()`, `reactivate()`. Boundary: identity lifecycle only — no profile composition, no history.

**`PatientProfileService`** — profile screen read model + light writes. Public: `coreProfile()` (eager page: notes, opportunities, invoices+items+payments, wallet, active membership), `familyPanel()` (Phase 3 view-model, moved from controller in Phase 4), `tabData($patient, $tab)` (exactly one lazy tab's variables), `loadProfile()` (BC composition of the two — zero current callers, kept as documented surface), `addRelationshipNote()`, `saveOpportunity()`, const `LAZY_TABS`. Dependencies: `FamilyLinkService`, `MembershipBenefitService`, models. Boundary: what the profile *screen* needs — never history aggregation.

**`PatientJourneyService`** — **canonical patient-history read model** (binding, Amendment 1). Public: `for(Patient, ?User $viewer, string $group, ?Carbon $before, int $limit)` → `{events, next_cursor, group}`; `summarize(Patient, int $limit)` → plain arrays for AI; consts `GROUPS`, `PAGE_SIZE`. It owns the three caller-facing concerns: per-event permission filtering (`module.action` → `User::canAccess`), group filtering (all/clinical/financial/comms/consent/reviews), cursor pagination. Dependency: `UnifiedTimelineService` only. Boundary: it never queries source models — and nothing else may aggregate patient history.

**`UnifiedTimelineService`** — the ONE aggregator, two scopes. `for(Relationship)` = PRE scope, byte-identical to pre-Phase-4 (parity-verified). `forPatient(Patient, ?Carbon $before)` = clinical scope: Activity ledger + 4 shared comms sources + 12 clinical adapters, each `guard()`ed (a failing source degrades silently, never 500s), each with a per-source LIMIT and cursor WHERE. Boundary: normalization and merging only; permissions/pagination caps deliberately belong to the caller.

**`FamilyLinkService` interactions** — Phase 3's canonical graph owner: `addLink` (never demotes guardian), `updateLink` (explicit edit may, F1), `removeLink`, `linksFor` (inverse-label map), `guardiansFor`/`wardsFor`, `attachGuardian` (new guardians minted via `PatientService::register()` in one transaction). Consumed by: profile Family panel (via `familyPanel()`), duplicate-registration linking (`PatientController@store`), consent guardian prefill, merge reconciliation (`PatientMergeService`), read-only API detail.

No circular dependencies: Journey→UnifiedTimeline and Profile→FamilyLink are one-directional; PatientService knows none of them.

---

## 6. Blade Architecture

Extraction principle (Amendment 1): **scoped `@include` partials, not `<x-…>` components** — includes inherit parent scope, which guaranteed behavioural fidelity for verbatim-moved markup. Alpine is used only for islands (tab state, notes, opportunities, timeline card).

**Eager (rendered with the page):**

| Component | Responsibility | Inputs | Output |
|---|---|---|---|
| `show.blade.php` | Orchestrator + Alpine root `patientProfile()` (activeTab, lazy loader `ensureTab`, notes/opps CRUD, `openVisitForm`, `openMembershipEnroll`, WhatsApp) | core view-model | full page shell |
| `profile/styles` | page CSS via `@push` | — | style stack |
| `profile/header` | breadcrumb, action buttons, admin menu, identity row, financial stat cards, clinical-alert/deactivation banners, 11-tab nav | patient, invoices, wallet, activeMembership, opportunities | sticky header |
| `profile/tab-profile` | Profile tab: details+rapport left, family panel, timeline card + quick actions right | patient, family*, Alpine root state | default tab |
| `profile/journey-timeline` | timeline card shell: filter pills, load-older, error states (Alpine `journeyTimeline()`) | patient (route) | AJAX-filled card |
| `profile/journey-timeline-events` | server-side event-row renderer (icons/colors/links) | events collection | HTML rows (per request) |
| `profile/quick-pay-modal` | Record Payment from any tab | invoices, patient | modal + JS |
| `profile/edit-patient-prefill` | shared add/edit patient modal + prefill payload | patient | modal + `window.__editPatientPrefill` |
| `profile/action-modal` | deactivate/delete with password+reason | patient | modal + JS |

**Lazy (fetched once on first activation via `GET …/tab/{tab}`, wrapped by `tabs/_fragment` which flushes `@stack('styles'/'scripts')`):** `consultation`, `treatment-plan` (wraps 1,959-line partial), `visits` (wraps 2,259-line partial), `lab` (self-computing), `prescriptions`, `billing`, `wallet`, `membership` (wraps partial), `documents` (wraps partial + upload modal), `notes`. Injected markup keeps its own `x-show="activeTab === '…'"`, binding to the same Alpine root; injected `<script>` tags are re-created so they execute.

---

## 7. Journey Timeline

**Architecture.** Aggregator pattern over existing tables — no new event store, no backfill, no second writer. `PatientJourneyService` (facade) → `UnifiedTimelineService::forPatient()` (fan-out) → 16 sources + Activity ledger. Forward path: as more producers write to the `Activity` ledger via `ActivityEngine::log()`, the `activity.single_ledger_reads` flag can collapse the fan-out into one indexed read **without changing any public API**.

**Event model** (normalized entry):
`date` (Carbon) · `type` · `icon_type` · `title` · `description` · `actor` · `meta` · `group` (clinical|financial|comms|consent|reviews|milestone) · `permission` ("module.action") · `link` (?URL) · `color` (accent key).

**Sources** (adapter → type → timestamp → permission): registration → `patient.created` → created_at → patients.view · appointments → appointment_date → patients.view · consultations/COHA → consultation_date → patients.view · treatment plans → plan_date + **`treatment.accepted`** (accepted_at) / **`treatment.rejected`** (cancelled ∧ never accepted) / **`treatment.deferred`** (derived: pending > 14 days) → patients.view · visits → visit_date → patients.view · invoices → invoice_date → **billing.view** · payments → payment_date → **billing.view** · clinical files → captured_at → patients.view · lab events → created_at → **lab.view** · memberships + benefit logs → start_date/availed_at → patients.view · reviews → requested_at/responded_at → patients.view · consent logs (append-only) → created_at → **consent.view** · plus ledger activities, communications, tasks, notes.

**Permissions.** Filtered server-side in the facade per viewer — a user without billing access never receives invoice/payment rows.

**Pagination.** Cursor-based: page of 20; `next_cursor` = ISO date of the oldest returned event; `before` applied per-source in SQL and post-merge. Filters never shrink the page (filter first, cap last).

**Future extension strategy.** A new module appears on the timeline by (preferred) emitting `ActivityEngine::log()` events, or by adding ONE guarded adapter method. Neither touches the facade, the endpoint, the blade card, or any consumer.

---

## 8. Data Flow

**Profile render.** `GET /patients/{id}` → guards (merged→redirect to survivor; trashed→404) → audit "viewed" → `coreProfile()` (≈9 data queries) + `familyPanel()` → orchestrator renders header + profile tab + empty lazy containers + modals. Alpine root seeds notes/opportunities state from JSON.

**Journey timeline.** Card's `x-init` fetches `GET …/timeline?group=all` → facade builds/filters/caps page → server-rendered rows returned as JSON `{html, next_cursor, count}` → injected. Filter pill = re-fetch with `group=`; "Load older" = re-fetch with `before=next_cursor`, appended.

**Lazy tab.** First activation of tab T → `$watch('activeTab')` → `ensureTab(T)` (stores the in-flight promise; concurrent callers share it) → `GET …/tab/T` → `tabData()` → `_fragment` renders body + flushed stacks → injected; scripts re-executed; Alpine binds; `patient-tab-loaded` dispatched; cached client-side. Helpers `openVisitForm()` / `openMembershipEnroll()` await the fragment before dispatching their open-form events.

**Writes.** Notes/opportunities: Alpine fetch → `,edit`-gated routes → `PatientProfileService` writes → JSON → local state update. Family: forms → `FamilyController` → `FamilyLinkService`. Registration: modal → `store` → duplicate soft-guard → `PatientService::register()` → optional family link (reported, never silent).

---

## 9. Permissions

| Surface | Gate |
|---|---|
| All patient routes | `module:patients` group (denial = redirect 302, not 403 — test accordingly) |
| Profile, tab fragments, timeline, search, list | module view |
| store / quick-store / update / deactivate / reactivate | `module:patients,edit` |
| destroy | `module:patients,delete` + password + reason |
| Relationship-notes + opportunities writes (5 routes) | `module:patients,edit` (**closed in Phase 4** — was view-only) |
| Family links + guardians (4 routes) | `module:patients,edit` (Phase 3) |
| Merge (3 routes) | `admin.only` |
| Timeline events | per-event `module.action` filtering (billing.view / lab.view / consent.view / patients.view) |
| Destructive header menu | `isAdminRole()` UI gate + route gates |

Role expectations: reception/assistant roles need patients view+edit for daily work; billing rows on the timeline require billing view; deletion and merge are admin-tier. Per-action permissions beyond view/edit/delete remain a platform-wide Wave-2 item (not Patients-specific).

---

## 10. Performance

**Core profile loading.** `coreProfile()` loads only what the eager page shows: notes, opportunities, invoices(+items,payments — shared by header stat cards and Quick Pay), wallet, active membership, family panel. ≈9 data queries versus ~20+ pre-Phase-4.

**Lazy loading.** Ten tab datasets (prescriptions, clinical files, implant catalog+stocks, membership plans/history/benefit logs, billing prompts, lab lists, plans+items, visits+items+implants, consultations, appointments) load only when their tab opens, once per page view.

**Query strategy.** Per-tab loaders eager-load exactly the relations their blade chain consumes; the consent-required flag for treatments resolves in one query for the whole set; reference data queries live only in the tabs that need them.

**Pagination.** Timeline: per-source LIMIT (10–40) + cursor; page cap 20 — cost is bounded regardless of patient tenure.

**N+1 avoidance.** Adapters read flat patient_id-indexed tables; actor names resolve via scalar `userName()` lookups bounded by page size (pre-existing PRE pattern). Guard test: `ProfileRefactorTest::test_profile_open_does_not_query_lazy_tab_tables` asserts the eager page touches none of the lazy tables.

---

## 11. Public Extension Points (integrate WITHOUT modifying Patients code)

- **Appointments (rebuild).** Consume `PatientService::register()` at *registration/arrival* (per the locked lifecycle, booking must stop minting patients — removes debt); appointments already appear on the timeline via the shared adapter; new lifecycle moments should emit `ActivityEngine::log()` events.
- **Billing.** Already surfaced (invoices/payments adapters + billing.view filtering). New billing events (refunds, EMI milestones): emit ledger events — they appear automatically under the Financial filter.
- **Membership.** Enrollments + benefit logs already adapted; new benefit types appear with no timeline change.
- **Media / Clinical Library (V2).** Keep writing `ClinicalFile` with `captured_at` — the adapter picks it up; richer grouping is a facade/view concern only.
- **Lab.** Keep appending `LabCaseEvent` rows — the adapter reads them; per-event links go to `lab.show`.
- **AI Copilot (V3).** Consume `PatientJourneyService::summarize()` as the single history context; repoint `PatientSummaryTool`/`PatientBalanceTool` to it (KD-4). Derived events (e.g. `plan.stalled`) belong in the aggregator, following the `treatment.deferred` pattern.
- **Patient Microsite / Chairside.** Read history exclusively through `PatientJourneyService::for()` with the viewer's permission context; add API transport, never a new aggregator.
- **Any new module.** Two sanctioned hooks: emit `Activity` ledger events (preferred) or add one `guard()`ed adapter. Family data: read via `FamilyLinkService`, write never (except through it).

---

## 12. Known Technical Debt (approved at freeze)

| ID | Item | Why it remains |
|---|---|---|
| KD-1 | ~~Duplicate Wallet tab panel (two `wallet` blocks, preserved verbatim)~~ **RESOLVED 2 Aug 2026** — the undocumented editable block was removed; the read-only block (which already carried an in-file rationale comment pointing staff to Finance → Wallet Management) was kept. No route or capability was removed — Add Credit / full ledger remain available there. | — |
| KD-2 | ~~`treatment-plan-tab.blade.php.bak_v1` dead backup file~~ **RESOLVED 2 Aug 2026** — removed along with five other confirmed-dead view files (`patients/create.blade.php`, `patients/edit.blade.php`, `patients/import.blade.php`, `partials/patient-tags.blade.php`, `partials/detail-row.blade.php`). | — |
| KD-3 | No timeline caching | Bounded per-source LIMITs make it cheap; caching adds staleness risk while the domain-event bus has no subscribers to bust it |
| KD-4 | Assistant tools query models directly | Repointing to `summarize()` is V3 Copilot work |
| KD-5 | Ledger + adapter double-entry for plan acceptance | Resolved permanently by the `activity.single_ledger_reads` cutover |
| KD-6 | `#membership` missing from hash-routing `validTabs` | Pre-existing quirk preserved under the identical-behaviour rule |
| KD-7 | `loadProfile()` has zero callers | Kept as documented BC composition surface; trivially removable |
| KD-8 | Web/API duplicate profile derivation (`Api/V1/PatientProfileController`) | Pre-existing cross-cutting debt, explicitly out of Phase 4 scope |
| KD-9 | Booking mints patients in 3 appointment paths | Deferred by decision (2026-07-20) to the Appointments rebuild; no interim logic in Patients |

---

## 13. Future Enhancements (intentionally outside the frozen module)

Mobile Journey Timeline endpoint (must consume `PatientJourneyService`) · assistant-tool repointing (KD-4) · timeline caching with `ActivityRecorded` busting (KD-3) · `activity.single_ledger_reads` cutover once producers cover the clinical sources · per-action permission vocabulary (platform Wave 2) · Appointment→Arrived→Registration lifecycle completion (KD-9) · Wallet panel dedupe (KD-1) · derived AI events (`plan.stalled` etc., V3) · patient-facing microsite/chairside transports.

---

## 14. Lessons Learned

**Reuse beat rebuild.** The decisive Phase 4 call: Dentfluence already owned a timeline engine (Activity ledger + UnifiedTimelineService powering PRE). Extending it with a second scope cost a fraction of a new event store, avoided backfill entirely, and honoured "no duplicate business logic."

**Verbatim extraction de-risked the monolith.** Moving 3,400 lines of working clinical UI byte-for-byte (tag-balance-verified) instead of rewriting it meant the refactor changed *delivery*, not *behaviour* — the whole regression surface collapsed to the loader mechanics.

**Scope-inheriting partials over components.** Blade `@include` inherits parent scope; `<x-…>` components don't. For verbatim moves, partials made "identical behaviour" provable rather than hoped-for.

**Facade at the boundary, mechanics in the aggregator.** Splitting caller concerns (permissions, filters, pagination) from source mechanics (adapters, guards, limits) produced a public API that survives the future ledger cutover unchanged.

**Design-freeze-then-build works.** Every phase (merge, validation, family, profile) ran audit → frozen design → sliced implementation → hardening → freeze doc. Amendment 1 showed the process absorbs late decisions (flag removal, new events) without redesign.

**No flags for a single-tenant deploy.** Git-revertible slice commits beat temporary feature flags that would have kept the 3,705-line legacy blade alive — the exact duplication the phase existed to delete.

**Audit your own work with fresh evidence.** The acceptance audit (not the implementation pass) caught the in-flight-promise race and a stale comment. Reports are claims; greps are evidence.

---

## 15. Final Status

```
PATIENTS MODULE
  Phase 1 — Duplicate Merge ........................ COMPLETE ✅
  Phase 2 — Validation & Permissions ............... COMPLETE ✅
  Phase 3 — Family / Guardian ...................... COMPLETE ✅  (frozen 2026-07-22)
  Phase 4 — Profile Refactor + Journey Timeline .... COMPLETE ✅  (accepted 2026-07-22)

  ═══════════════════════════════════════════════════════════
  PATIENTS MODULE — FROZEN · V1.0 · 22 July 2026
  ═══════════════════════════════════════════════════════════
```

Changes to patient identity, validation, family graph, profile architecture, or the Journey Timeline now require a design amendment against this document. Standard verification suite: `php artisan test tests/Feature/Patients` + `php artisan app:crawl-routes` + the Phase 4 freeze QA checklist.
