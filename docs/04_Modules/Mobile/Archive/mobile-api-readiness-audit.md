# Dentfluence Web ↔ API/Mobile Readiness Audit

**Date:** 2026-07-25 · **Type:** Read-only forensic audit (parallel track to Patient Journey V1.1 Phase 1)
**Scope:** Every production module — Web stack vs `/api/v1` stack vs shared core.
**Production data changed:** NO · **Code changed:** NO (this document only).

---

## Executive Finding

**The OS is roughly half mobile-capable, and the half that works is genuinely good — but the API is not safe to put a mobile app on today.**

Three truths, in order of importance:

1. **The API's authorization layer is the single biggest blocker — bigger than any missing endpoint.** Of 238 `/api/v1` routes, **132 (~56%) have `auth:sanctum` and nothing else**. That includes `POST /api/v1/invoices` (create an invoice), `POST /patients/{p}/wallet/credit` (money into a wallet), `POST /patients/{p}/membership/enroll` (runs the full finance chain), clinic-wide revenue reads, all patient PHI tab reads, clinical-file upload/delete, and clinic-wide recall-settings + template writes. A further **78 routes gate by legacy role-name strings** (`api.role:admin,front_desk`) — a parallel permission truth that ignores the `role_module_permissions` table the Clinic Owner actually configures. Only **26 routes** consume the same permission truth as the web (`api.role:module:x,action`).

2. **Where a shared service exists, parity is real.** `PatientService::register()` (invariant holds on every path incl. walk-in), `AppointmentService` (overlap/blocked-slot guards), `TreatmentVisitService` (shared `rules()` — the model module), `TreatmentPlanAcceptanceService`, `MembershipBenefitService`, `InventoryService`, `LabCaseTransitionService`, `VendorInvoiceService`, `WalletService`/`ManualDiscountService`, `OutboundMessageService`+`CommunicationGuard` (DPDP), `ReportMetricsService`. The 5 "API money bugs" from the 07-14 parity audit are **all verified fixed**.

3. **Where no shared service exists, web and API have already forked.** The worst: PRE action outcomes have **three endpoints with three different behaviors** (`will_call_back` closes the row on web, schedules a +2-day follow-up on mobile); the API inventory dashboard uses **different low-stock thresholds** than `StockStatusService`; prescriptions are two unrelated implementations (web: no validation, no CDSS; API: full validation + CDSS); the API's consultation `chart_data` contract can **silently destroy tooth charts** via `EncryptedArray::set()`'s `array_values()`.

**A doctor on a tablet today could:** log in (2FA enforced server-side), search/read patients, write all 5 consultation types + COHA, log full treatment visits with vitals/lab/implant stock, prescribe with live CDSS, capture photos.
**They could not:** create or accept a treatment plan (**403** — role-name gate), view any x-ray or clinical photo (**no authenticated media read for token clients**), generate consent, send an audited Rx via WhatsApp, or close a recall the same way the desk does.

---

## Scoring method

🟢 = required workflows exist on API, shared logic, authz + validation match web, contract consumable.
🟡 = read or partial write parity; permission/validation drift; duplicated logic in sync today.
🔴 = important workflow web-only, or web/API behavior diverges dangerously.
⚫ = genuinely not mobile-V1 relevant.
A route existing is not counted as readiness; workflows were traced, not endpoints.

---

## Module Matrix

| Module | Web | API Read | API Write | Shared Core | Permission Parity | Validation Parity | Mobile Readiness | Major Gap |
|---|---|---|---|---|---|---|---|---|
| Authentication (login/2FA/me) | ✅ | ✅ | ✅ | n/a | ✅ | ✅ | 🟢 | Token abilities issued but never enforced; no token revoke on password change; Flutter 2FA field still open |
| Dashboard | ✅ | ✅ | n/a | ❌ copy-pasted queries | ✅ (both ungated) | n/a | 🟢 | No `DashboardService` — two hand-maintained copies of KPI/alert queries |
| Patients (CRUD/search) | ✅ | ✅ | ✅ | ✅ `PatientService` + shared rules trait | 🔴 reads ungated; writes role-name vs `module:patients,edit` | ✅ (identical trait) | 🟡 | API create skips duplicate-phone guard; no merged-record guard on API |
| Patient journey/timeline | ✅ `PatientJourneyService` | ❌ | n/a | ❌ | — | — | 🔴 | Canonical read model has zero API surface |
| Family / Guardian | ✅ | ✅ (detail) | ❌ | ✅ `FamilyLinkService` (read) | — | — | 🟡 read / 🔴 write | No family-link write API |
| Appointments | ✅ | ✅ | ✅ partial | ✅ `AppointmentService` | 🟡 writes ✅ same table; reads ungated | 🟡 (type/time/block-slot deltas) | 🟡 | No full update, no operatory-assign (check-in chair), no revert; transition rules live only in Blade/Alpine |
| Check-in / status | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ | 🟡 | No server-side state machine on either stack; two-step check-in is JS-only |
| Consultations + COHA | ✅ | ✅ | ✅ (all 5 workflows) | 🟡 same tables, duplicated controllers | 🟡 API stricter (role list) | 🔴 | 🟡 | `chart_data` contract mismatch + `EncryptedArray::array_values()` = silent tooth-chart corruption; no `appointment_id` link; hard-delete of specialty modules |
| Treatment Plans / Acceptance | ✅ | ✅ | ✅ but 403 for doctors | ✅ `TreatmentPlanAcceptanceService`; ❌ item sync duplicated | 🔴 `api.role:admin,front_desk` locks out doctors | 🔴 API drops `material_variants`, discounts, doctor_id, plan_date | 🔴 | Doctors cannot create/accept plans on mobile; no mark-presented, consent doc, delete |
| Treatments / Visits / Vitals | ✅ | ✅ | ✅ | ✅ `TreatmentVisitService` shared static `rules()` | 🟡 role list vs module:patients | ✅ identical | 🟢 | `format()` omits vitals in save responses; visit print Blade-only |
| Prescriptions | ✅ | ✅ | ✅ | ❌ two unrelated implementations | 🔴 web ungated entirely (no `prescriptions` module); API doctor-gated | 🔴 web has zero validation | 🔴 | CDSS API-only (web never calls it); API lacks weight + audited print/WhatsApp (bypasses DPDP audit trail); pedo dosing Blade-only |
| Clinical Media / Library | ✅ | 🟡 (no URLs) | ✅ (thin) | ✅ `ClinicalFileUploadService` | 🔴 (`clinical-library.php` auth-only on web too) | 🔴 API drops visit_id/consent/tags/types | 🔴 | **No authenticated media read for token clients** — mobile can upload photos it can never display |
| Billing / Payments / Wallet | ✅ | ✅ | ✅ | 🟡 API uses `InvoicePaymentService`; **web still runs its own 390-line copy** | 🔴 `POST /invoices` + wallet credit ungated; reads ungated; writes role-name | 🟡 (tooth_number 20 vs 100 etc.) | 🟡 | Money math equivalent + 07-14 bugs fixed, but authz absent and billing prompts not branch-scoped on API |
| Estimates (= Treatment Plan layer) | ✅ | ✅ | ✅ | ✅ acceptance/billing services | 🔴 (as above) | 🟡 | 🟡 | mark-presented → Opportunity conversion is web-only |
| Presentations / Case Journeys | ✅ + public token microsite | ❌ | ❌ | — | — | — | ⚫ (webview via public token acceptable for V1) | Zero API; revisit post-V1 |
| Membership | ✅ | ✅ | ✅ enroll | ✅ `MembershipBenefitService` (transactional) | 🔴 enroll endpoint fully ungated | ✅ identical | 🟡 | Plan CRUD + cancel enrollment web-only |
| Inventory | ✅ | ✅ | ✅ | 🟡 `InventoryService` partly; stock-in/out/PO duplicated | 🟡 role-name vs `module:inventory` | 🟡 | 🟡 | **API bypasses `StockStatusService` with different thresholds** — mobile and desk disagree on low/critical stock |
| Purchase / GRN / AP invoices | ✅ | ✅ | ✅ full chain | ✅ GRN atomic both sides; `VendorInvoiceService` fully shared | 🟡 role-name writes | ✅ (vendor invoices share `rules()`) | 🟢 | PO edit/delete/print web-only |
| Lab | ✅ | ✅ | ✅ | ✅ `LabCaseTransitionService`; create duplicated | ✅ **same table both sides** (`module:lab`) | 🟡 | 🟡 | Transition non-transactional (both channels); reconciliation + vendor master web-only |
| Sterilization | asset-flag only | ✅ | ✅ | ❌ verbatim duplicate | 🟡 | ✅ | ⚫ | No sterilization module exists on either channel |
| PRE — Today's Actions board | ✅ | ✅ | ✅ | ✅ `TodayActionsEngine`; option-builders copy-pasted | 🔴 sanctum-only (web auth-only → Phase 1) | 🟡 | 🟡 | API misses includeDone, call-state annotation, appt today/tomorrow split, date picker, /summary |
| PRE — Action outcomes | ✅ | — | ✅ | ❌ **three endpoints, three behaviors** | 🔴 | 🔴 two outcome vocabularies | 🔴 | `closes_task` unread on API; `OutcomeAutomationService` unreachable from web; `will_call_back` does opposite things |
| PRE — Recall → rebook | ✅ list/bulk | ✅ board | 🟡 | ❌ | 🔴 | 🔴 | 🔴 | Web can't complete-with-outcome; API can't ignore/bulk/convert — loop only closes on mobile |
| Leads | ✅ | ✅ | ✅ | ✅ `PatientService::register` + `PrmRelationshipAdapter` on convert/move | 🔴 both ungated (web → Phase 1) | 🟡 | 🟡 | `leadQuickAdd` skips spine adapter; full add/edit + AI helpers web-only |
| Opportunities | ✅ | ✅ | ✅ | ❌ duplicated per controller (in sync) | 🔴 ungated | 🟡 | 🟡 | Stage notes write missing on API |
| Follow-ups | legacy shell only | ❌ | ❌ | — | — | — | 🔴 | No API at all; only web surface is retired `communication.php` — needs PRE-native design |
| Tasks | ✅ `module:tasks` | 🟡 huddle slice | 🟡 | ❌ `todayAddCall` duplicates `TaskController::store`, ungated | 🔴 | 🟡 | 🟡 | markDone/evidence/escalate no API |
| Reviews | ✅ (in retired comm shell) | ❌ | ❌ | `ReviewService` clean, unbound | — | — | 🔴 | Zero API; future controller must bind to `ReviewService`, not `Communication/*` |
| WhatsApp (PRE) | ✅ | 🟡 thread only | ✅ send/link | ✅ `OutboundMessageService` + `CommunicationGuard` (DPDP) — cleanest shared core | 🔴 different module gates (communication vs patients) | ✅ | 🟡 | No conversation list, no template-send, no birthday one-click |
| Huddle | ✅ | ✅ | ✅ | 🟡 shared `TodayActionsEngine`, **two parallel aggregators** | 🔴 reads ungated; writes role-list vs `module:daily_huddle` | 🟡 | 🟡 | Accountability/report/notes/comments/proof no API |
| Reports | ✅ | ✅ | n/a | ✅ `ReportMetricsService` | ✅ **same table — the model to copy** | n/a | 🟡 | Narrower surface by design (fine for V1) |
| Settings — PRE (recall/templates) | ✅ | ✅ | ✅ | ✅ same `AppSetting` keys | 🔴 **API writes fully ungated** (clinic-wide config) | ✅ | 🟡 | Call-outcome/dismiss-reason CRUD + flag toggle web-only |
| Settings — global | ✅ | ❌ | ❌ | — | — | — | ⚫ | Desk-bound admin; correct exclusion |
| Users / Role Management | ✅ (`admin.only`) | ❌ (own profile ✅) | ❌ | — | — | — | ⚫ | Role-grid on a phone is a surface to avoid, not a gap |
| Marketing / Blog Hub | ✅ | ❌ | ❌ | — | — | — | ⚫ | Desktop authoring work |
| Notifications | ✅ | ✅ | ✅ | ✅ | ✅ (both ungated) | ✅ | 🟢 | — |

**Tally (mobile-relevant modules only): 5 🟢 · 15 🟡 · 9 🔴 · 5 ⚫.**
Interpretation caution: the 🟡 band is wide — Billing 🟡 (near-ready, authz broken) is much closer to green than Consultations 🟡 (data-corruption risk). Do not read this as "50% done".

---

## Workflow Matrix

| Workflow | Web Works | API Works | Same Business Logic | Same Authorization | Mobile Ready |
|---|---|---|---|---|---|
| Lead → Patient | ✅ | ✅ | ✅ `PatientService::register` + `PrmRelationshipAdapter` | ❌ both ungated (web → Phase 1; API mirror unowned) | 🟡 |
| Appointment create (incl. walk-in) | ✅ | ✅ | ✅ `AppointmentService` + overlap guards | ✅ writes / ❌ reads | 🟡 |
| Appointment → Check-in | ✅ (operatory + status) | 🟡 status only | 🟡 two-step flow is Alpine-only | 🟡 | 🟡 |
| Appointment reschedule / cancel | ✅ | ✅ | ✅ | ✅ | 🟢 |
| Appointment full edit | ✅ (+ optimistic lock) | ❌ | — | — | 🔴 |
| Check-in → Consultation | manual on both | 🟡 | 🟡 API lacks `appointment_id` link | 🟡 | 🟡 |
| Consultation → Treatment Plan | ✅ | ✅ create | 🟡 item sync duplicated; variants API-dropped | ❌ doctors 403 on API | 🔴 |
| Treatment Plan → Acceptance | ✅ | ✅ | ✅ `TreatmentPlanAcceptanceService` | ❌ doctors 403 on API | 🔴 |
| Accepted Plan → Treatment/Visit | ✅ | ✅ | ✅ `TreatmentVisitService` (all side-effects identical) | 🟡 | 🟢 |
| Visit → Billing prompt → Invoice | ✅ | ✅ | ✅ prompt / ❌ invoice creation duplicated | ❌ `POST /invoices` ungated; prompts not branch-scoped | 🟡 |
| Billing → Payment (wallet/EMI/partial) | ✅ | ✅ | ❌ web bypasses `InvoicePaymentService`; math currently equivalent | ❌ role-name gates | 🟡 |
| Rx write → CDSS → issue → share | 🟡 (no CDSS, no validation) | 🟡 (CDSS ✅, no audited share) | ❌ two implementations | ❌ web ungated | 🔴 |
| Clinical photo capture → view | ✅ | ❌ view | ✅ upload service | ❌ | 🔴 |
| PRE Action → Outcome → Next Action | 🟡 closes only | 🟡 automates only | ❌ **three behaviors, two vocabularies** | ❌ | 🔴 |
| Recall → Contact → Rebook | 🟡 | 🟡 | ❌ rebook exists only on mobile | ❌ | 🔴 |
| Inventory PO → GRN → Stock → AP bill | ✅ | ✅ | ✅ atomic both sides; AP fully shared | 🟡 | 🟢 |
| Lab Case → Status → Completion → Expense | ✅ | ✅ | ✅ shared transition (non-transactional on both) | ✅ same table | 🟡 |
| Membership → Enroll (→ renewal = re-enroll) | ✅ | ✅ | ✅ `MembershipBenefitService` | ❌ enroll ungated | 🟡 |
| Review request send | ✅ (legacy shell) | ❌ | — | — | 🔴 |
| WhatsApp send (consent-gated) | ✅ | ✅ | ✅ `CommunicationGuard` unconditional | ❌ different module gates | 🟡 |

---

## Strongest Areas (closest to mobile-ready)

1. **Treatment Visits** — the architectural reference. One service, one shared `rules()` contract, identical side-effects (billing prompt, draft lab case, recall task, implant stock) from both doors, plus a server-side `form-options` that replaces Blade assembly.
2. **PO → GRN → Stock → AP** — full chain over API, transactional on both sides, vendor invoices share service + validation.
3. **Membership enrollment, plan acceptance, WhatsApp send, Lab transitions, Reports** — genuine shared cores. Reports + Lab are the only modules where API and web consume the identical permission table; they are the pattern to replicate.
4. **Auth** — API 2FA correctly enforced server-side (the 07-14 bug is fixed in code; the remaining lockout is the Flutter client's missing code field).
5. **Money math** — all five 07-14 API money bugs verified fixed; wallet/EMI/partial/overpayment behavior now equivalent.

## Weakest Areas

1. **PRE outcome path** — three endpoints, three behaviors, two vocabularies. The core loop of the product's flagship module behaves differently per device.
2. **Prescriptions** — two unrelated implementations; the safety layer (CDSS) is unreachable from the web UI, and mobile shares would bypass the DPDP audit trail.
3. **Clinical media** — write-only from mobile; no Sanctum media streaming route exists (`ClinicalFile` URLs hard-code the session-guarded `secure.media.file`).
4. **Treatment plans** — the doctor, the primary mobile user, is 403'd from every plan write by a role-name gate.
5. **Follow-ups & Reviews** — no API at all, and their only web surfaces live inside the retired Communication shell.

---

## Security / Permission Parity — Key Findings

The Role Management principle ("Owner configures Settings → Role → Module → View/Edit/Delete; both surfaces consume it") is currently true for **26 of 238 API routes**.

Three tiers on the API today:
- **Same truth (26 routes):** `api.role:module:x,action` → `User::canAccess()` → `role_module_permissions`. Lab, Reports, Appointment writes, patient notes/comms, inventory settings. ✅
- **Parallel truth (78 routes):** `api.role:admin,front_desk,...` → `User::hasRole()` matching the legacy `users.role` string or slug — never consults the permission grid. Cuts both ways: a doctor with grid-granted finance edit is 403'd; a legacy `front_desk` string with zero grants passes. Already test-locked as a known defect in `tests/Feature/Access/ApiAccessParityCharacterizationTest.php` (which is itself now stale vs `routes/api.php:483`).
- **No truth (132 routes):** `auth:sanctum` only. Includes, most seriously:
  - `POST /api/v1/invoices` (api.php:459), `POST /patients/{p}/wallet/credit` (:458), `POST /patients/{p}/membership/enroll` (:233) — **money mutations with zero authorization**
  - `GET /billing/summary`, `/billing/invoices`, wallet ledgers — clinic revenue readable by any token
  - 15 patient PHI reads + 4 clinical-file writes (upload/edit/delete a radiograph with only a token)
  - `POST /inventory/products` (:591) — ungated write
  - Recall settings + message template writes (:425-455) — **any token can rewrite clinic-wide automation config**
  - Appointment/huddle/inventory reads that web gates by module view
- Cross-cutting: token abilities are issued at login but **never enforced** anywhere; no token revocation on password change; 30-day default TTL. Billing prompts endpoints are not branch-scoped (cross-branch read/mutate).
- Web side (for completeness, flagged to Phase 1): `routes/relationship.php` auth-only → **COVERED BY PATIENT JOURNEY PHASE 1**; `routes/prescriptions.php` auth-only with no `prescriptions` module row to gate with; `routes/clinical-library.php` auth-only duplicate registration; clinical write block under `module:patients` *view* (`web.php:196`) — view-only roles can accept treatment plans.

> **HANDOFF TO PATIENT JOURNEY PHASE 1:** Phase 1 as scoped flips `routes/relationship.php` to module gates. **It does not touch `routes/api.php`.** Closing the web hole while leaving the identical API hole open produces a false sense of security — the API mirror must be explicitly added to Phase 1's scope (recommended) or immediately follow it. See Critical Path P0-1.

---

## Business Logic Duplication (drift inventory)

Verbatim or near-verbatim duplicates currently in sync (each is a future drift bug):
invoice creation, refund/void math + 2.5% card-refund rate, retail stock movements (web `BillingController` vs `Api/V1/BillingController`); web `recordPayment` vs `InvoicePaymentService` (web comment justifying the split is now false); stock-in/out, PO create, stock count `complete()`, reusable-asset lifecycle (web vs API inventory); Lab case create; Dashboard KPI/alert queries; PRE option-builders, `closeUnderlyingRecord`, `DISMISSIBLE_MODELS`; `todayAddCall` vs `TaskController::store`; Huddle aggregators; membership enroll guards.

Already-diverged (not just duplicated):
- **Outcome engine:** `closes_task` (web) vs `OutcomeAutomationService` (API-only) vs plain log (API today/action). Two outcome vocabularies inside the API itself.
- **Inventory status:** API dashboard thresholds ≠ `StockStatusService` (an item between min/2 and min is "Low" on desk, "Critical" on mobile; a 1.5×min band exists only on mobile).
- **Prescriptions:** lifecycle (draft/finalize/versioning API vs issue-in-place web), validation (full vs none), CDSS (API-only), weight (web-only), audited share (web-only).
- **Consultations:** `chart_data` shape (web string-list vs API-documented assoc map that `EncryptedArray::set()` corrupts via `array_values()`); specialty-module soft-reject (web) vs hard-delete (API); future-date guard web-only.
- **Block-slot:** `block_type` default `unavailable` (web) vs `personal` (API); time validation looser on API.
- **Patient notes:** different max length and type vocabulary.
- Misc: `tooth_number` max 20 (API) vs 100 (web) on invoice items; API invoice creation fires no `invoice.created` ActivityEngine event (mobile invoices invisible to Insights).
- **Lying docblocks** (flag for correction whenever touched): `OutcomeAutomationService.php:17-19` claims web parity that doesn't exist; `Api/V1/ConsultationController.php:45-47` documents the wrong chart shape; web `BillingController.php:1118-1121` claims the shared service lacks wallet logic it now has.

Client-side-only business rules (mobile must reimplement from scratch today): appointment status transition graph + queue sort (Blade/Alpine, and two web views already disagree); two-step check-in; pedo dose helper (hard-coded drug table + mg/kg→ml formula); treatment-plan variant→unit_price rule; consent-row picker; tooth chart notation/mixed dentition.

---

## Mobile V1 Critical Path

### P0 — BLOCKER (mobile V1 must not ship without these)
1. **API authorization mirror.** Convert the 132 ungated routes and 78 role-name routes to `api.role:module:x,action` against `role_module_permissions`. Priority order inside P0: money mutations (invoices/wallet/membership) → clinic-wide settings writes (recall/templates) → PHI reads + clinical-file writes → module reads. Mechanically small (route-middleware edits + the `EnsureApiRole` module form that already exists) but must be characterization-tested like Phase 1 is doing for web. **Recommend folding into Phase 1 as an explicit slice — it is the same access-control workstream.**
2. **One permission truth.** Retire the role-name form of `EnsureApiRole` (or reduce it to admin-only escape hatch). This single change also unblocks doctors from treatment-plan writes (api.php:171-181) — the largest functional hole, fixable in one line per route once the module form is the standard.
3. **One outcome engine (Roadmap Slice S4 — COVERED BY PATIENT JOURNEY V1.1).** Do not build mobile PRE against today's three-behavior split. Mobile V1 PRE should block on S4 (`RelationshipWorkService`), not ship around it. One outcome vocabulary comes with it.
4. **Sanctum media read route.** A token-guarded twin of `secure.media.file` + URL emission in API document/clinical-file payloads. Without it the entire clinical photo story is write-only on mobile.
5. **Fix the `chart_data` contract** (validate the object shape; stop `array_values()` on assoc arrays in `EncryptedArray::set` or normalize before cast). Silent clinical-data corruption is a P0 by definition, and it's cheap.

### P1 — IMPORTANT (build during mobile V1 development)
6. Appointment full-update + operatory-assign endpoints; move the status transition graph server-side into `AppointmentService` (both web views already disagree — the server should own it).
7. Duplicate-phone guard in API patient create (call the same `findDuplicatesByPhone`, return 409 like web); merged-record guard in API `findInBranch`.
8. `PatientJourneyService` API endpoint (canonical timeline for the mobile profile).
9. Prescription shared write service (extract like `TreatmentVisitService`), + `weight` in API validation, + audited print/WhatsApp endpoints through `CommunicationGuard`. (Also wire CDSS into web — same work item, web benefits.)
10. Reviews API bound to `ReviewService`; Follow-ups need a PRE-native design first (no non-legacy surface exists to port).
11. `StockStatusService` in the API inventory dashboard/alerts (delete the hand-rolled thresholds).
12. `invoice.created` ActivityEngine event in API invoice creation; branch-scope billing-prompt endpoints.
13. Web `recordPayment` cut over to `InvoicePaymentService` (ends the most dangerous duplication; API already proved the service).

### P2 — CAN FOLLOW
14. Family/guardian write API; treatment-plan `material_variants` + mark-presented + consent doc via API; consultation `appointment_id` link; PRE recall list writes (ignore/bulk/convert) + stage notes + birthday one-click; WhatsApp conversation list + template send; Huddle aggregator consolidation; Dashboard service extraction; API Resource/pagination standardization (2 Resource classes and an unused `ApiPagination` trait today); enforce or remove token abilities; token revoke on password change; Lab transition wrapped in `DB::transaction` (fixes web too); Lab reconciliation/vendor-master APIs.

---

## Patient Journey Impact

- **Phase 1 (Access Control):** extend scope to `routes/api.php` (P0-1/2 above) or schedule an immediate mirror slice. The characterization-test pattern already built for web (`RelationshipAccessCharacterizationTest`, `ApiAccessParityCharacterizationTest`) is the right harness; note the API test is stale vs current routes and needs refresh in that slice.
- **Slice S4 (`RelationshipWorkService`):** now carries mobile V1's PRE on its back. Its Definition of Done should explicitly include: web `today/action`, API `today/action`, and API `recalls/{id}/complete` all routed through the one service with one vocabulary, plus parity tests.
- **ContextAssembler / Board v2 / detectors (P2+ of roadmap):** design each with an API adapter from day one (see DoD below). The Board v2 projector should replace the copy-pasted option-builders on both surfaces.
- **No roadmap reordering is proposed.** The recommendation is scope clarification inside already-approved slices, plus one appended API-mirror slice in Phase 1.

---

## Recommended Development Strategy: HYBRID (Option C)

Grounded in code volume and risk, not elegance:

1. **Dedicated, small: the authorization mirror (P0-1/2).** ~130 route-middleware lines + `EnsureApiRole` cleanup + test refresh. High severity, low volume, zero product design needed. Doing this "gradually while touching modules" would leave money endpoints open for months. Fold into Phase 1.
2. **Gradual: functional parity (P1/P2), pulled by the mobile V1 build order.** The functional gaps span ~15 modules; a big-bang "API phase" would be a multi-week rewrite that duplicates work Patient Journey slices will redo anyway (S4 replaces the outcome endpoints; Board v2 replaces the option-builders). Fix each module's parity when mobile V1 actually needs that screen, using the side-by-side rule below.
3. **Never: a mass endpoint-generation sprint.** 56% of the current API being ungated is what "build endpoints first, wire permissions later" produces. Don't repeat it at 2× scale.

---

## Proposed Definition of Done (every mobile-relevant feature, from now on)

A slice is DONE only when:
1. **Business logic canonical** — one service/domain class; controllers (web and API) are transport only.
2. **Web adapter complete.**
3. **API adapter complete where mobile-relevant** — or an explicit written "⚫ not mobile-relevant" decision in the slice doc.
4. **Same permission truth** — both adapters gate via `role_module_permissions` (`module:` / `api.role:module:` forms). No new role-name gates. New modules get a module row.
5. **Same validation** — one shared rules source (trait or service `rules()`, per `TreatmentVisitService` / `ProvidesPatientRules` precedent).
6. **Parity tests** — at minimum: same actor + same input → same domain outcome via both adapters; permission-denial parity for a zero-grant role.
7. **Contract sanity** — stable IDs, ISO timestamps, enum status vocabulary from the model/enum (never re-declared client-side), paginated lists, standard error envelope.

---

## Production Data Changed: NO
## Code Changed: NO (this document only)

*Evidence basis: five parallel read-only code traces (routes, controllers, requests, services, Blade, tests) on 2026-07-25; key claims (ungated money endpoints, treatment-plan role gates, EnsureApiRole dual mode, 07-14 money-bug fixes) independently spot-verified against source. File:line citations preserved in the audit working notes; representative citations retained inline above.*
