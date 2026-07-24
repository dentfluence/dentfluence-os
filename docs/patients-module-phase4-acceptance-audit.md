# Patients Module — Phase 4 ACCEPTANCE AUDIT
> Self-audit of the implementation against the frozen design
> (`patients-module-phase4-profile-timeline-design.md` + Amendment 1).
> Performed 2026-07-22. Two defects found during audit and fixed (D1, D2 — §9).
> Evidence basis: every claim below was verified by inspecting the current
> files, grep sweeps, tag/brace balance checks, and route-name lookups —
> not by re-reading the implementation report.

---

## 1. Architecture Walkthrough (request → render)

```
GET /patients/{patient}                       [module:patients, auth, web]
  → PatientController@show
      guards: merged→redirect to survivor · trashed→404 · AuditLog 'viewed'
      → PatientProfileService::coreProfile($patient)
            loads: relationshipNotes.author, opportunities.author,
                   invoices(+items,payments), wallet, activeMembership
      → PatientProfileService::familyPanel($patient, $activeMembership)
            → FamilyLinkService (Phase 3, read-only)
      → view patients.show  (328-line orchestrator)
            @include profile/styles, profile/header, profile/tab-profile
            10 empty lazy containers  ·  quick-pay modal  ·  edit prefill  ·  action modal
            Alpine root patientProfile(): tab state + lazy loader + notes/opps islands

[user clicks a tab]
GET /patients/{patient}/tab/{tab}             [module:patients]
  → PatientController@tab
      whitelist: PatientProfileService::LAZY_TABS · merged/trashed → 404
      → PatientProfileService::tabData($patient, $tab)   (only that tab's data)
      → view patients.tabs._fragment  → @include patients.tabs.{tab} → @stack styles/scripts
  ← HTML fragment; ensureTab() injects it, re-creates <script> tags,
    Alpine binds injected x-show="activeTab === '{tab}'" to the same root state

[Journey Timeline card on Profile tab, on load / filter / "Load older"]
GET /patients/{patient}/timeline?group=&before=   [module:patients]
  → PatientController@timeline
      merged/trashed → 404 · parses cursor
      → PatientJourneyService::for($patient, $viewer, $group, $before)   ← CANONICAL read model
            → UnifiedTimelineService::forPatient($patient, $before)      ← single aggregator
                  Activity ledger (if relationship) + 4 comms sources + 12 clinical adapters,
                  each guard()ed, each with per-source LIMIT and cursor WHERE
            ← merged entries, newest-first
            facade applies: group filter → permission filter (canAccess) → page cap → next_cursor
      → view patients.profile.journey-timeline-events (server-rendered rows)
  ← JSON { html, next_cursor, count } — card injects rows
```

## 2. Component Inventory

Eager = rendered with the page; Lazy = fetched fragment; AJAX = data fetched post-load. All are server-rendered Blade; Alpine only for interactivity. Line counts from `wc -l`.

| File (resources/views/patients/…) | Lines | Responsibility | Inputs | Services (via controller) | Permissions | Mode |
|---|---:|---|---|---|---|---|
| `show.blade.php` | 328 | Orchestrator + Alpine root (tab state, lazy loader, notes/opps CRUD, WhatsApp fn) | core view-model | coreProfile, familyPanel | module:patients | eager |
| `profile/styles` | 87 | @push page styles | — | — | — | eager |
| `profile/header` | 394 | Breadcrumb, actions, identity, stat cards, clinical-alert + deactivation banners, tab nav | patient, invoices, wallet, activeMembership, opportunities | — | isAdminRole() gates destructive menu | eager |
| `profile/tab-profile` | 409 | Profile tab: details, rapport notes, tags, opportunities, quick actions; hosts timeline card | patient, family*, notes/opps (Alpine) | FamilyLinkService (read) | canEditFamily for family writes UI | eager |
| `profile/journey-timeline` | 86 | Timeline card: filter pills, load/older, error states | patient (route only) | PatientJourneyService (via endpoint) | per-event, server-side | eager shell + AJAX |
| `profile/journey-timeline-events` | 91 | Event rows renderer (icons, colors, links) | events collection | — | pre-filtered | server-rendered per request |
| `profile/quick-pay-modal` | 530 | Record Payment from any tab | invoices, patient | — | module:patients (page) | eager |
| `profile/edit-patient-prefill` | 59 | add-patient-modal include + prefill payload | patient | — | — | eager |
| `profile/action-modal` | 107 | Deactivate/Delete w/ password + reason | patient | — | routes gated ,edit/,delete | eager |
| `tabs/_fragment` | 7 | Fragment wrapper; flushes @push stacks | tab | tabData | module:patients | — |
| `tabs/consultation` | 389 | Consultation tab (verbatim) | consultations, treatmentVisits, prescriptions, recallTask | tabData | module:patients | lazy |
| `tabs/treatment-plan` | 1 | wraps partials/treatment-plan-tab (1,959 ln, untouched) | plans, treatments, consultations | tabData | module:patients | lazy |
| `tabs/visits` | 1 | wraps partials/treatment-visits-tab (2,259 ln, untouched) | visits, doctors, treatments, implantCatalog, prescriptions, appointments | tabData | module:patients | lazy |
| `tabs/lab` | 6 | Lab tab (self-computing @php, verbatim) | patient | — (inline queries, pre-existing) | module:patients | lazy |
| `tabs/prescriptions` | 190 | Rx tab + quick-form (verbatim) | prescriptions, patient | tabData | module:patients | lazy |
| `tabs/billing` | 721 | Billing tab + invoice drawer/delete modal (verbatim) | invoices(full), billingPrompts, wallet, activeMembership | tabData | module:patients (page-level, as before) | lazy |
| `tabs/wallet` | 234 | Wallet tab (both original panels, verbatim) | wallet | tabData | module:patients | lazy |
| `tabs/membership` | 5 | wraps partials/membership-tab (583 ln, untouched) | membership set | tabData | module:patients | lazy |
| `tabs/documents` | 5 | wraps partials/documents-tab (+upload modal, untouched) | clinicalFiles, treatmentVisits | tabData | module:patients | lazy |
| `tabs/notes` | 53 | Notes & Logs (Alpine root state, verbatim) | root state | — | writes gated ,edit | lazy |

**No duplicated UI:** the only duplicated block in the codebase (two Wallet panels) is a *pre-existing* duplicate preserved verbatim inside one fragment (§9). **No duplicated responsibility:** each tab's markup exists in exactly one file; `grep` confirms zero references to the deleted drawer/comms partials. **Blade business logic:** the extracted partials contain the same presentation-shaping `@php` blocks they always did (verbatim moves — rewriting them was explicitly out of scope, design §3); *no new* business logic was added to any Blade file, and the one Blade-owned data query added by Phase 4 is none — timeline and tab data all come from services.

## 3. Service Inventory

| Service | Public methods | Responsibility | Depends on | Must NOT do |
|---|---|---|---|---|
| `PatientProfileService` (328) | `coreProfile`, `familyPanel`, `tabData`, `loadProfile` (BC, currently zero callers), `addRelationshipNote`, `saveOpportunity`, const `LAZY_TABS` | Profile screen read model + note/opp writes | FamilyLinkService, MembershipBenefitService, models | history aggregation (that's Journey) |
| `PatientJourneyService` (112) | `for()`, `summarize()`, consts `GROUPS`/`PAGE_SIZE` | **Canonical patient-history read model**: permission filter, group filter, cursor paging | UnifiedTimelineService (constructor-injected) | querying source models directly |
| `UnifiedTimelineService` (709) | `for(Relationship)` — PRE scope, byte-identical; `forPatient(Patient)` — clinical scope | The ONE aggregator: ledger + comms + clinical adapters, normalization, fault isolation | Activity + source models | permissions, pagination caps (caller-owned by design) |

**No duplicated business logic** — `loadProfile()` is pure composition of `coreProfile()+tabData()`; the plan-acceptance ledger event is *read*, not re-logged. **No circular deps** — Journey→UnifiedTimeline is one-directional; ProfileService doesn't know Journey. **No controller leakage** — `show()` is guards + 2 service calls + view; `tab()`/`timeline()` are whitelist/cursor parsing + 1 service call + view; controller is 498 lines across 15 actions. **No overlap** — profile "what's on screen" vs journey "what happened" are disjoint.

## 4. Timeline Inventory

All in `UnifiedTimelineService`, clinical scope only. Every adapter: wrapped in `guard()` (fault isolation), per-source `LIMIT`, `WHERE <ts> < :before` cursor support.

| Source | Model | Timestamp | Actor | Permission | Adapter | type |
|---|---|---|---|---|---|---|
| Ledger | `Activity` | occurred_at | actor morph | billing.view for payment.*, else patients.view | inline (forPatient) | `activity` |
| Registration | `Patient` | created_at | created_by | patients.view | addPatientCreated | `patient.created` |
| Appointments | `Appointment` | appointment_date | doctor_id | patients.view | addAppointments (shared) | `appointment` |
| Consultations/COHA | `Consultation` | consultation_date | doctor_id | patients.view | addConsultations | `consultation` / `coha` |
| Plan created | `TreatmentPlan` | plan_date | doctor/creator | patients.view | addTreatmentPlanEvents | `treatment_plan` |
| Plan accepted (A1) | ″ | accepted_at | doctor | patients.view | ″ | `treatment.accepted` |
| Plan rejected (A1) | ″ (status=cancelled, !accepted) | updated_at | creator | patients.view | ″ | `treatment.rejected` |
| Plan deferred (A1, derived) | ″ (pending >14d) | plan_date+14d | — | patients.view | ″ | `treatment.deferred` |
| Visits | `TreatmentVisit` | visit_date | doctor_id | patients.view | addTreatmentVisits | `treatment_visit` |
| Invoices | `Invoice` | invoice_date | created_by | **billing.view** | addInvoices | `invoice` |
| Payments | `InvoicePayment` | payment_date | created_by | **billing.view** | addPayments | `payment` |
| Media | `ClinicalFile` | captured_at | uploaded_by | patients.view | addClinicalFiles | `media` |
| Lab | `LabCaseEvent` (via labCase) | created_at | user_id | **lab.view** | addLabEvents | `lab` |
| Membership | `FinancePatientMembership` / `MembershipBenefitLog` | start_date / availed_at | created_by | patients.view | addMemberships | `membership` |
| Reviews | `Review` | requested_at / responded_at | requested_by_id | patients.view | addReviews | `review` |
| Consent | `ConsentLog` (append-only) | created_at | captured_by | **consent.view** | addConsentLogs | `consent` |
| Comms/Tasks/Notes | patient_communications / `Task` / `PatientNote` | sent_at / due_date / created_at | staff/assigned | patients.view | shared legacy adapters | `communication`/`task`/`note` |

- **Duplicate-event risk reviewed:** the ledger's `treatment_plan.accepted` Activity *and* the adapter's `treatment.accepted` can both appear for post-Phase-4 acceptances — different titles, same fact. Assessed as acceptable duplication-of-record (ledger row is the audit line; adapter row is the structured event); noted as debt item KD-5 with the V-flag path (`activity.single_ledger_reads`) as the eventual dedupe.
- **Ordering:** single `sortByDesc('date')` after merge; test `test_timeline_orders_newest_first` covers it.
- **Permission filtering:** facade-level `canAccess()`; covered by `test_permission_filtering_drops_events_viewer_cannot_see`.
- **Cursor pagination:** `before` applied per-source in SQL + post-merge; covered by `test_group_filter_and_cursor_pagination`.
- **PRE unchanged:** `for(Relationship)` body verified identical this session (§1 evidence); the only touch to shared adapters is a trailing optional param defaulting to null.

## 5. Route Inventory

**Added (2):** `GET patients/{patient}/tab/{tab}` → `patients.tab` · `GET patients/{patient}/timeline` → `patients.timeline`. Both inside the existing `module:patients` group (auth + web + module view gate). Names verified collision-free.

**Modified (5):** `patients.relationship-notes.store/.destroy`, `patients.opportunities.store/.update/.destroy` — now `->middleware('module:patients,edit')`. URLs, names, verbs unchanged → backwards compatible for any legitimate (edit-capable) client, including the mobile app paths (which use `/api/v1`, untouched).

**Removed: none.**

## 6. Permission Audit (write endpoints)

| Endpoint | Gate | Status |
|---|---|---|
| notes store/destroy | module:patients,**edit** | **FIXED this phase** (was view-only) + test |
| opportunities store/update/destroy | module:patients,**edit** | **FIXED this phase** + test |
| timeline / tabs | GET-only, module:patients; timeline additionally per-event filtered | read-only by construction |
| family links/guardians | module:patients,edit | unchanged (Phase 3) |
| documents (clinical-files store/update/destroy) | module:patients group | unchanged (pre-existing posture; per-action gates are the known cross-cutting Wave-2 item, out of Phase 4 scope) |
| billing writes (payment, delete-auth) | billing module group + password/reason modals | unchanged, outside Phase 4 scope |
| membership enroll | billing group route | unchanged |
| patient store/update/delete/deactivate | ,edit / ,delete + password | unchanged |

No endpoint became writable at view-only permission; two endpoints that *were* are now closed.

## 7. Performance Comparison

| | BEFORE | AFTER |
|---|---|---|
| Eager page datasets | 26-key view-model: everything (all invoices+4 relations, 20 Rx, all clinical files, implant catalog+stocks, membership plans+history+50 benefit logs, family heads, treatments+consent rules, doctors, billing prompts, visits+items+implants, plans+items, consultations…) | 8-key core: notes, opportunities, invoices(+items,payments), wallet, activeMembership, family panel |
| Queries on profile open | ~20+ before framework overhead | ~9 profile-data queries; test asserts **zero** touches on prescriptions / clinical_files / implant_catalog / membership_benefit_logs / billing_prompts / lab_vendors |
| Render size | 3,705-line blade + all 11 tab bodies every open | orchestrator + header + profile tab only; 10 tabs on demand (once, then client-cached) |
| Timeline | none (2-source inline Visit Log, unbounded, in-blade queries) | paginated 20/page, per-source LIMIT + cursor, N+1-free (single-table reads; actor names via scalar lookups consistent with existing PRE implementation) |
| Duplicate queries removed | wallet loaded once but invoices loaded once *and* recomputed in header/quick-pay from same collection | invoices loaded once, reused by header + quick-pay; billing tab re-queries only when opened |
| New N+1 | — | none introduced: adapters do flat WHERE patient_id reads; `userName()` per-actor scalar lookup is the pre-existing PRE pattern, bounded by page size |

## 8. Regression Summary (every touched feature)

| Feature | Status |
|---|---|
| Profile header, stat cards, clinical alerts, banners | UNCHANGED (verbatim move) |
| Patient details / rapport notes / tags / opportunities UI | UNCHANGED (verbatim; Alpine islands same root) |
| Opportunity delete button on Profile tab | **FIXED** — called undefined `deleteOpportunity()`; alias added |
| Visit Log card | **MODIFIED (by design)** — replaced by Journey Timeline; row edit/delete lives in Visits/Consultation tabs |
| 10 non-profile tabs (consultation…notes) | MODIFIED delivery (lazy fragment), UNCHANGED content — verbatim, tag-balance-verified |
| "New Visit"/"Add Follow-up"/"Membership" quick actions | **FIXED** — awaited fragment replaces 50 ms race (+ in-flight promise fix D1) |
| Quick Pay modal / Edit Patient modal / action modal / Print | UNCHANGED (eager, verbatim) |
| Notes & opportunities writes for view-only roles | **FIXED** (now denied) — intended behavioral change |
| Deep links `#billing` etc. | UNCHANGED (hash routing preserved; `#membership` was never in validTabs — preserved as-is, KNOWN ISSUE KD-6) |
| PRE Relationship Profile timeline | UNCHANGED (method body identical) |
| Mobile API (`/api/v1/*`) | UNCHANGED (no shared code modified; see §API) |
| Family & Contacts (Phase 3) | UNCHANGED behavior; view-model derivation moved controller→service |
| Dead partials (drawer, comms tab) | REMOVED (verified unreferenced) |
| Documents dead stub (`x-show="false"`, self-balanced 23/23 divs) | REMOVED |

**API parity, stated plainly:** `Api/V1/PatientProfileController` (691 lines) is untouched and never consumed `PatientProfileService` — web/API duplicate derivation **remains**, exactly as before Phase 4. The design scoped this as *contained, not solved* (design §2, PR-7 non-goal). The mobile app has **no journey timeline endpoint yet**; when built, it must consume `PatientJourneyService` (binding Amendment-1 decision). This is unfinished by design, not hidden.

## 9. Known Technical Debt (intentional)

- **KD-1 Duplicate Wallet panel** — two `wallet` panels existed pre-Phase-4; preserved verbatim (behavior parity mandate). Removal = visible change → needs CEO sign-off.
- **KD-2 `treatment-plan-tab.blade.php.bak_v1`** — dead backup; deletion not authorized in scope.
- **KD-3 Timeline caching deferred** — bounded per-source LIMITs make it cheap; caching adds staleness risk with the domain-event bus not yet wired for busting.
- **KD-4 Assistant tools not yet on `PatientJourneyService::summarize()`** — V3 Copilot work per design §8.
- **KD-5 Ledger/adapter double-entry for plan acceptance** — resolved permanently when `activity.single_ledger_reads` cutover lands.
- **KD-6 `#membership` missing from hash `validTabs`** — pre-existing quirk preserved (identical-behavior rule).
- **KD-7 `loadProfile()` has zero callers** — kept as documented BC composition surface (design decision), trivially removable later.
- **KD-8 Web/API duplicate profile derivation** — pre-existing cross-cutting debt, explicitly out of Phase 4 scope.
- **Defects found & fixed during THIS audit:** **D1** in-flight tab fetch stored `'loading'` string → concurrent `openTabThen` could dispatch before load; now stores/awaits the shared Promise. **D2** stale controller comment referencing the deleted drawer partial; corrected.

## 10. Final Acceptance Checklist

| # | Criterion | Answer |
|---|---|---|
| 1 | Architecture matches frozen design (incl. Amendment 1) | **YES** |
| 2 | No duplicate business logic | **YES** |
| 3 | No duplicate timeline implementation | **YES** — one aggregator, two scopes |
| 4 | No Blade business logic *added* (verbatim partial `@php` preserved per design §3/§9) | **YES** |
| 5 | No controller bloat | **YES** — thin actions, derivations in services |
| 6 | No service overlap | **YES** |
| 7 | No permission regressions | **YES** — two leaks closed, none opened |
| 8 | No routing regressions | **YES** — 2 added, 5 tightened, 0 removed/renamed |
| 9 | No API regressions | **YES** — API untouched; parity debt pre-existing & disclosed |
| 10 | No performance regressions | **YES** — strictly fewer eager queries; contract test in place |
| 11 | Journey Timeline is canonical | **YES** — sole history UI on the profile |
| 12 | PatientJourneyService is the canonical history read model | **YES** — documented binding + enforced as only consumer path |
| 13 | PRE timeline unaffected | **YES** — `for()` byte-identical |
| 14 | Follows CEO Directive #003 | **YES** — polish existing, no new module, no dup logic, quality>breadth |

---

**Final recommendation:** the implementation is fully supported by the evidence above; both audit-discovered defects are fixed. Phase 4 is **ACCEPTED AT CODE LEVEL**, conditional only on the runtime gate I cannot execute from this environment: `php artisan optimize:clear && php artisan test tests/Feature/Patients && php artisan app:crawl-routes` plus the freeze doc's 8-point manual QA. When that run is green, declare **PHASE 4 FROZEN** and commit per the three-slice plan.
