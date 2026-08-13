# API Authorization Characterization Report

**Date:** 2026-07-26 · **Step 1 — forensic verification only. No production code changed.**
**Basis:** direct line-by-line read of `routes/api.php` (793 lines, current working tree), `EnsureApiRole`, `User::canAccess()/hasRole()/isAdminRole()`, `RolePermissionSeeder`, `routes/relationship.php`, `routes/prescriptions.php`, and all four `tests/Feature/Access` files.

---

## ⚠️ Headline: the audit's numbers are ALREADY STALE — the codebase moved

The readiness audit (07-25) counted **132 sanctum-only / 78 role-name / 26 canonical**. Those numbers described the tree *before* Patient Journey Phase 1 Slices **1.2, 1.3, 1.4 and "Slice 3"** landed. The current `routes/api.php` carries slice comments throughout and has already converted large blocks to the canonical form. This report characterizes the **current** tree.

**Verified current counts (238 route definitions; `Route::match(['put','patch'])` counted once):**

| Category | Count | Share |
|---|---|---|
| 1. `auth:sanctum` only (no `api.role`) | **115** | 48% |
| 2. Legacy role-NAME lists (`api.role:admin,front_desk[,doctor]`) | **35** | 15% |
| 3. Canonical `api.role:module:<module>,<action>` | **63** | 26% |
| 4. `api.role:admin` (admin-only) | **23** | 10% |
| Public (`/ping`, `/auth/login`) | **2** | 1% |

**Already converted by Phase 1 slices (do NOT redo):** clinical writes (consultations, COHA, notes/comms, clinical files, treatment plans, visits) → `module:patients,edit/delete` (Slice 1.2) · all prescription routes → `module:prescriptions,view/edit` (Slice 1.3/1.4) · membership reads/enroll → `module:finance,view/edit` (1.4) · `/billing/invoices` + `/billing/summary` + coupon/benefit-preview → `finance,view` (1.4) · lab reads/writes → `lab,view/edit` (1.4) · reports → `reports,view` · appointment writes → `appointments,edit/delete` (Slice 3) · WhatsApp send/link/thread → `patients,edit/view`.

**Also new since the audit:** Slice 1.3 registered `relationship` and `prescriptions` module rows (migration `2026_07_25_120000_register_relationship_and_prescriptions_modules`) with per-role seeder defaults, and flipped **web** `routes/relationship.php` to `module:relationship` (group view; `,edit` on mutations; `,delete` on bulk/destructive/settings) and **web** `routes/prescriptions.php` to `module:prescriptions`. CEO-approved semantic already in force on web: **lead conversion requires relationship,edit AND patients,edit** (stacked middleware).

---

## A. Route counts

As table above. Composition of the 115 sanctum-only routes:

| Block | Routes | Notes |
|---|---|---|
| PRE `/relationships/*` | 24 | all reads AND all mutations (today/action, close, dismiss, notes, add-call, recalls store/**complete**, opportunity store/stage/convert) |
| `/leads/*` | 5 | incl. `leadConvert` — mints a Patient |
| `/relationship/missed-calls/*` | 4 | incl. bulk-dismiss |
| `/relationship/recall-settings/*` | 4 | **3 clinic-wide config WRITES** |
| `/templates/*` | 6 | **3 writes** incl. delete |
| Billing block | 9 | incl. **`POST /invoices`** (:490) and **`POST /patients/{p}/wallet/credit`** (:489), billing-prompts ×3 |
| Patient reads | 14 | search/index/show + 11 profile tabs |
| Detail reads | 3 | prescription show, invoice detail, consultation show |
| Clinical reads | 8 | same-issue-context, coha show, treatments, pricing, plan show, billable-teeth, visit form-options, visit show |
| Appointments reads | 5 | today, form-options, blocked-slots, index, show |
| Huddle reads | 3 | board, tasks, staff |
| Inventory reads + **1 write** | 21 | 14 core reads + stock-count ×2 + reusable ×1 + vendor-invoice ×3 + **`POST /inventory/products`** (:625) |
| Own-scope / parity-by-design | 9 | auth me ×2, logout ×2, notifications ×4, dashboard ×1 |

## B. Critical exposures (authentication-only today)

Ranked. Any valid Sanctum token — zero owner-configured grants — can currently:

1. **Create an invoice** — `POST /api/v1/invoices` (api.php:490). Web: `module:finance`.
2. **Credit a patient wallet** — `POST /patients/{p}/wallet/credit` (:489). Web: `module:finance`.
3. **Run the entire PRE engine** — 33 routes (:313-450): log outcomes, close queue rows, complete recalls via `OutcomeAutomationService` (which **books real appointments**), create/convert opportunities, and **convert leads into Patients** (:423). ⚠️ **Parity is now INVERTED here:** Slice 1.3 locked web PRE behind `module:relationship`, so a zero-grant user is denied on web but retains full PRE read+write through the API — the exact "close web, leave API open" scenario the readiness audit warned about.
4. **Rewrite clinic-wide automation config** — recall-settings ×3 writes (:459-465) and message-template create/update/delete (:478-484). Web equivalents now carry `relationship,delete` semantics.
5. **Read all patient PHI** — 14 patient reads + 3 detail reads + 8 clinical reads. Web: `module:patients` / `module:prescriptions`.
6. **Create inventory products** — `POST /inventory/products` (:625) — the only remaining fully ungated non-PRE write.
7. **Billing prompts** — reads + dismiss (:530-532), **and** `billingPromptFormOptions`/`dismiss` are not branch-scoped in the controller (middleware cannot fix that part).
8. Patient-scoped financial reads (open-invoices, payment-options, receipt, emi-schedule), appointment reads ×5, huddle reads ×3, inventory reads ×20 — all module-view-gated on web, open on API.

## C. Legacy role-name gates (35 routes) → canonical targets

| Block | Routes | Current gate | Canonical target (web equivalent) |
|---|---|---|---|
| Patients store / update | 2 (:84-88) | `admin,front_desk` | `module:patients,edit` (web.php patients,edit) |
| Billing: recordPayment, markProviderPaid, payment date, EMI mark-paid, wallet refund, manual-discount ×2 | 7 (:494-521) | `admin,front_desk` | `module:finance,edit` — **keep** the in-controller `RoleBillingPermission` checks (WALLET_REFUND, discount caps); they are the web's second layer, not redundancy |
| Huddle: comms push, task store/status/assign, instruction, yesterday-flow | 6 (:587-609) | `admin,front_desk[,doctor]` | `module:daily_huddle,edit` — note web huddle group is view-only-gated (`huddle.php:11`), so this makes API *stricter* than web; web should follow (HANDOFF, separate) |
| Inventory writes: item update/adjust, vendor store/toggle, stock-in/out, PO store/mark-ordered/receive, implant catalog ×2 + placement ×2 | 13 (:648-699) | `admin,front_desk` | `module:inventory,edit` |
| Stock count start/save/complete | 3 (:709-715) | `admin,front_desk` | `module:inventory,edit` |
| Reusable assets store/update/status | 3 (:727-732) | `admin,front_desk` | `module:inventory,edit` |
| Vendor invoice store | 1 (:749) | `admin,front_desk` | `module:inventory,edit` |

## D. Permission mapping for the 115 sanctum-only routes

| Group | Proposed gate |
|---|---|
| PRE reads (today, notes list, search, list, pipelines ×3, opportunity show/patient-search, call-outcomes, {id}, timeline, journeys, missed-calls index, recall-settings read, templates read ×3, lead detail) | `module:relationship,view` |
| PRE operational mutations (today/action, close, dismiss, notes add, add-call, recalls store, **recalls/{id}/complete**, opportunity store/stage/convert, {id}/activity, lead quick-add/move/activity, missed-calls ignore/unignore) | `module:relationship,edit` |
| PRE destructive/admin (missed-calls bulk-dismiss, template store/update/delete*, recall-settings writes ×3) | `module:relationship,delete` (mirror web Slice 1.3; *check exact web template gates before copying — store/update may be `,edit`) |
| `leads/{lead}/convert` | **stacked**: `api.role:module:relationship,edit` **+** `api.role:module:patients,edit` (see E-4) |
| Patient reads (search, index, show, 11 profile tabs, consultation show, same-issue-context, coha show, visit form-options/show, treatments, treatment-pricing, plan show, invoice detail†) | `module:patients,view` |
| `prescriptions/{prescription}` show | `module:prescriptions,view` |
| `treatment-plans/{plan}/billable-teeth` | `module:finance,view` (feeds the bill flow, whose write is already `finance,edit`) |
| Billing: `POST /invoices`, `POST wallet/credit`, billing-prompt dismiss | `module:finance,edit` |
| Billing reads: open-invoices, payment-options, receipt, emi-schedule, billing-prompts ×2 | `module:finance,view` |
| Appointments reads ×5 | `module:appointments,view` |
| Huddle reads ×3 | `module:daily_huddle,view` |
| Inventory reads ×20 (core, stock-count, reusable, vendor-invoice) | `module:inventory,view` |
| `POST /inventory/products` | `module:inventory,edit` |
| auth/me ×2, logout ×2, notifications ×4, dashboard | **leave as-is** (see E) |

† `GET /invoices/{invoice}` (`invoiceDetail`) sits between the patients profile tab (web: `module:patients`) and billing detail (web: `module:finance`). Proposed `patients,view` since it serves the profile tab; flag for CEO if finance-view is preferred.

## E. Special cases — do NOT convert mechanically

1. **Public:** `/ping`, `/auth/login` — stay public. Login keeps `throttle:5,1`.
2. **Own-scope:** `auth/me` GET/PUT, logout, logout-all, notifications ×4 — user-scoped, web equivalents ungated. Leave.
3. **Dashboard:** web dashboard is also ungated (no `dashboard` gate on `web.php:82`). Gating API-only would break parity in the opposite direction. Leave; revisit as a pair if ever.
4. **AND-semantics:** `EnsureApiRole` treats its tokens as **OR** — `api.role:module:a,edit,module:b,edit` passes if EITHER grants. Lead-convert's CEO-approved "relationship,edit AND patients,edit" needs **two stacked middleware entries**, exactly how web does it. Any future AND case, same pattern.
5. **True admin-only (23 routes):** admin-check, invoice cancel/void (web: inline `isAdminRole`), product archive, adjustment reverse, GRN undo, vendor-invoice delete, inventory settings ×14, vendor update — web parity is `admin.only`/inline admin. **Keep `api.role:admin`.**
6. **Patient deactivate** (`api.role:admin`): web is `module:patients,edit` **plus password re-confirmation**. The API has no re-auth step, so loosening admin → patients,edit would make API *weaker* than web. Keep admin until an API re-auth pattern exists. Flag.
7. **`recalls/{id}/complete`** runs `OutcomeAutomationService`, which **creates Appointments** as a side effect. Proposed `relationship,edit`; whether it should *also* require `appointments,edit` is a semantics question for the CEO gate (web has no equivalent endpoint to mirror — this is API-only surface pending roadmap Slice S4).
8. **Branch scoping is NOT solved by this workstream:** billing-prompt form-options/dismiss unscoped in controller; API lab store accepts cross-branch `patient_id`; PRE is branch-blind (Slice 1.1 finding). Route middleware cannot fix these — record, don't bundle.
9. **Second-layer checks stay:** `RoleBillingPermission` (WALLET_REFUND, ADVANCE_ADJUSTMENT, discount caps) inside controllers/services must survive the route-gate conversion.
10. **Legacy admin bypasses remain live and are OUT of scope here:** `User::canAccess()` returns true for `role === 'admin' && !role_id` (User.php:204); `EnsureApiRole` short-circuits on `isAdminRole()` (legacy string OR slug). Known from Slice 1.1; do not change in this pass.
11. **Unknown module slugs fail CLOSED** for non-admins (`Role::can` → false when no module row) — safe direction, but a typo silently 403s everyone but admin. Note: `EnsureApiRole`'s own docblock example uses `module:billing`, a module that **does not exist** (slug is `finance`) — fix the comment when the file is next touched.
12. **Dead webhook imports** (api.php:29-32) route nothing — ignore in this workstream.
13. **Sanctum hardening** (abilities issued but never enforced; no token revoke on password change; 30-day TTL) — real, separate hardening item. Not this workstream.

## F. Test state

**Current and green (updated in Slice 1.3, web-side enforcement):**
- `RelationshipAccessCharacterizationTest` — zero-grant denied all PRE surfaces; view opens; edit gates mutations; delete gates bulk-dismiss; lead-convert requires BOTH grants (arbitrary role names).
- `PrescriptionsAccessCharacterizationTest` — same pattern for Rx settings/masters.
- `RoleManagementCharacterizationTest` — role grid.
- Appointments module has its own permission tests incl. API (`tests/Feature/Appointments/AppointmentPermission*`).

**STALE — `ApiAccessParityCharacterizationTest` (still at Slice 1.1) contradicts current routes:**
- `test_module_form_api_gate_obeys_owner_settings_where_applied` (:56-69) asserts a **view-only** grant opens `POST /patients/{p}/notes` — that route is now `patients,edit` (Slice 1.2). **Expected RED.**
- `test_billing_discount_preview_reads_are_ungated` (:95-107) asserts coupon/benefit-preview return non-403 for a zero-grant user — both now carry `finance,view` (Slice 1.4). **Expected RED.**
- Docblock says "55+ role-name routes" — now 35.
- Tests 1–2 (PRE API ungated read + mutation) still describe reality and pass — and are now the proof of the web↔API inversion.
- (Not executed in this pass — no test run performed; assessment is from reading assertions against current middleware.)

**Missing coverage:**
- No API *enforcement* tests for the Slice 1.2/1.4 conversions (patients-clinical writes, prescriptions, finance reads, membership, lab) — the conversions shipped with route comments but I find no matching Access tests.
- No test pinning the remaining 35 legacy role-name routes (beyond the mechanism-level test).
- No branch-scope characterization for billing prompts / lab store / PRE.
- No per-category matrix test asserting "route X carries gate Y" (a route-middleware inventory test would catch silent regressions cheaply — the pattern that already exists for web in the Slice 1.1 tests).

## G. Proposed implementation slices (smallest safe sequence)

Per-slice discipline (matches Phase 1 practice): characterize current behavior in tests → convert routes → flip tests to enforcement → run `dentfluence:smoke` + `app:crawl-routes` → STOP, report.

- **Slice M1 — PRE API mirror (~43 routes, highest urgency).** `relationships/*`, `leads/*`, `missed-calls/*`, `recall-settings/*`, `templates/*` → `relationship` module semantics copied from web Slice 1.3, incl. stacked AND on lead-convert. Rationale: this closes the *inversion* — web is enforced today, API is wide open; also kills the ungated clinic-config writes. Reuse `BuildsAccessPersonas`.
- **Slice M2 — Money (~19 routes).** `POST /invoices`, wallet credit, billing-prompt trio → `finance,edit/view`; the 7 legacy billing writes → `finance,edit` (retain `RoleBillingPermission` layers); patient-scoped billing reads → `finance,view`; billable-teeth → `finance,view`.
- **Slice M3 — PHI reads (~25 routes).** Patient reads, profile tabs, clinical detail reads → `patients,view`; Rx show → `prescriptions,view`; decide `invoiceDetail` (patients vs finance) at the gate review.
- **Slice M4 — Patients writes (2) + deactivate decision.** store/update → `patients,edit`; deactivate stays admin unless re-auth parity is built.
- **Slice M5 — Appointments/Huddle/Dashboard reads (~14).** Appointment reads → `appointments,view`; huddle reads → `daily_huddle,view`, huddle writes → `daily_huddle,edit` (note: stricter than web's view-gated group — deliberate, with web HANDOFF).
- **Slice M6 — Inventory (~41 routes, biggest but lowest risk).** Reads → `inventory,view`; `POST products` + 20 legacy writes → `inventory,edit`; admin set unchanged.
- **Slice M7 — Cleanup.** Refresh `ApiAccessParityCharacterizationTest` from characterization → enforcement; add the route-gate inventory test; grep-gate CI check that no `api.role:` role-name form remains outside an allowlist (`admin`); only then consider deleting the role-name branch of `EnsureApiRole`.

Every slice is route-middleware + tests only. Zero controller/service/business-logic changes. Zero migrations (module rows already exist). Blast radius per slice = one module family, one test file.

---

**Production data changed: NO · Production code changed: NO.**
Stopped after Step 1, awaiting review before any conversion.
