# Product Audit Dashboard — Dentfluence Phase 1

*"Does Dentfluence deserve to be called a product a real clinic runs on daily?"* (CEO Directive #003).
26 modules scored /10 on four axes → Overall. Scores are **evidence-based** from code review;
*(rt)* items still need live runtime confirmation. Fix priority order:
**Stability › Completeness › Polish › Canonical/AI-readiness.**
Prioritized fix sequence: **`docs/execution-backlog.md`**.

**Axes** — Stability (crash / data-loss / money-integrity / silent-fail) · Completeness (staff can finish
the workflow) · Polish (professional, consistent, finished) · AI-Readiness (canonical single service,
reusable web/mobile/API, permission-aware).

---

## Scoreboard (all 26 modules)

| Module | Stab | Compl | Polish | AI-Rdy | **Overall** |
|---|:--:|:--:|:--:|:--:|:--:|
| Treatment Visits | 7 | 9 | 7 | 9 | **8** |
| Appointments | 8 | 9 | 7 | 4 | **7** |
| Patients | 7 | 8 | 5 | 8 | **7** |
| Prescriptions | 7 | 9 | 8 | 5 | **7** |
| Clinical Library *(P1 scope)* | 8 | 8 | 7 | 6 | **7** |
| Relationships / PRE (Action Board) | 8 | 8 | 7 | 6 | **7** |
| Wallet / Finance | 7 | 7 | 7 | 7 | **7** |
| Procurement (PO→GRN→AP) | 8 | 7 | 8 | 6 | **7** |
| Memberships | 7 | 7 | 7 | 6 | **7** |
| Blog Marketing Hub | 8 | 8 | 7 | 2 | **7** |
| HR / Roles | 7 | 7 | 7 | 4 | **6.5** |
| Consultations | 6 | 8 | 7 | 4 | **6** |
| Treatment Plans | 6 | 8 | 6 | 5 | **6** |
| Tooth Charting | 7 | 8 | 7 | 4 | **6** |
| Automation Engine | 6 | 5 | 7 | 6 | **6** |
| Daily Huddle | 6 | 8 | 7 | 3 | **6** |
| Marketing Engine | 7 | 6 | 6 | 3 | **6** |
| Reports / Analytics | 7 | 8 | 6 | 4 | **6** |
| Auth / Security | 7 | 5 | 6 | — | **6** |
| Settings | 7 | 7 | 5 | 3 | **5.5** |
| Billing / Invoicing | 6 | 8 | 7 | 3 | **5** |
| Dashboard | 7 | 6 | 5 | 3 | **5** |
| DPDP Consent | 7 | 5 | 6 | — | **5** |
| Inventory ✅ CLOSED | 8 | 9 | 9 | 6 | **8** |
| Lab | 3 | 4 | 6 | 5 | **4** |
| Reviews / Reputation | 4 | 4 | 7 | 5 | **4** |

**Verdict:** the clinical daily spine (appointments, patients, prescriptions, visits, PRE) is genuinely
solid. The gap between "impressive project" and "dependable product" is concentrated in **financial
integrity** (Inventory, Lab, Billing), **security/permissions** (Settings writes, auth rate-limiting,
consent enforcement), **silent comms failures**, and pervasive **web/API duplicate logic + no per-action
permissions**. Those are exactly the P0/P1 items in the execution plan.

---

## Critical findings

**Money / data integrity (highest — a clinic's money must be safe):**
- **Inventory** — ✅ web `receivePO()` GRN-receive now wrapped in `DB::transaction()` (was a partial-write corruption risk; ⚠ runtime smoke-test pending). Remaining: `adjustQty()` silent negative-stock clamp; **fake** dashboard KPI (`$implantLow = 0` hardcoded, backlog P1 #11).
- **Lab** — `getEligibleCases()` filters `received_date`, but the normal lifecycle only stamps `final_received_date` → cases that go through the standard workflow **never become reconciliation-eligible** ("done in workflow, not-done in reports"). `transition()` writes status+task+expense non-transactionally.
- **Billing** — web `recordPayment()` has drifted from `InvoicePaymentService` (its own comment admits it); coupon redemption has no lock → concurrent submits can exceed max-uses.

**Security affecting Tulip (in-scope per CEO Decision 1):**
- **Settings** — the whole `/settings/*` group is gated only by `module:settings` defaulting to *view*; feature-flag toggle, role changes, billing/masters saves are **not restricted to `can_edit`**, no controller-level `authorize()` backstop → privilege escalation.
- **Auth** — forgot-password PIN + mobile OTP verify endpoints have **no rate-limiting/lockout** → 6-digit code is brute-forceable *(rt at WAF)*.
- **DPDP Consent** — mobile WhatsApp actions are raw deep-links that **bypass CommunicationGuard entirely** (no consent, no audit); consent isn't captured at registration; the guard is "a plain method any code can skip."

**Silent failures (erode daily trust):**
- **Reviews** ✓verified — sends via the parked Meta Cloud API, not the P1 wa.me engine → review requests can't reach patients. *(Re-point is a workflow decision — the automated `reviews:request` command can't auto-send under WhatsApp-Web-only.)*
- **WhatsApp** — wa.me button marks rows *sent* when the link is returned, not when staff press Send.
- **Automation** — `RulesEngine` skips a rule with only `Log::debug` if `relationship_id` is unresolved.
- **Treatment Plans** *(rt)* — `doctor_id`/`plan_date` written with no `hasColumn` guard; migrations flagged unrun → may throw SQL on save.

## Cross-cutting themes (the canonical / AI-readiness work)
- **No per-action permission checks anywhere** (all modules) — only coarse route `module:*` middleware. The single most common finding; blocks true permission-awareness (AI-readiness) and is a security gap on write actions.
- **Duplicate web-vs-API business logic** — Billing, Inventory, Consultations, Treatment Plans, Prescriptions, Dashboard, Huddle-revenue. Directly violates the "one canonical implementation" principle → this is the core AI-readiness effort.
- **Dead/legacy duplicate paths** — legacy `clinical_media` upload/serve, `ClinicalFinding`, retired `marketing/blog/calendar.blade.php`, the (now tombstoned) `ContentManagement/TreatmentVisitController.php`.
- **Monolith hotspots** — `patients/show.blade.php` (~3,674 lines), Huddle controller (~1,300 lines).

## Model to copy (protect it)
**Treatment Visits, Procurement, Wallet, Memberships** — thin controller → one shared service used by web+API,
transactional, honest. This is the target shape for every module.

## Fixed already (Product Audit execution)
- ✅ **Inventory module completion (2026-07-20, CEO directive)** — Overall 5→7 (Completeness 6→8, Polish 5→8). All browser-tested on local:
  - PO Print built (route `inventory.purchase.print` + `printPO()` + `purchase-print.blade`) — fixed the 404.
  - Received Stock Report (list + print over GRN tables, dentist-language) — the "GRN screen" that was missing.
  - Stock Movement plain-language timeline (Purchased/Used/Adjustment/Correction).
  - Nav simplified to Items · Purchase Orders · Stock Movement · Stock Count (+ Received Stock, Reports, Settings).
  - **Inventory Settings relocated into the module** (route → `InventoryController::settings()`; was redirecting to global) — new architecture rule: each module owns its settings.
  - Reports hub with 5 named reports (Received Stock, Purchase, Stock, Stock Value, Stock Adjustments).
  - **Fake implant KPI killed** (`$implantLow=0` → honest "Implant Catalog" count). Closes backlog P1 #11.
  - PO edits now audit-logged (Auditable trait on PurchaseOrder → `audit_logs`, module=inventory) — verified in admin Activity Log. Reverse-receive already voids the vendor bill atomically via `InventoryService`.
  - Remaining (not blocking Phase-1 prod): collapse web `receivePO` onto canonical `InventoryService` (P2 #14, AI-readiness) keeps Inventory AI-Ready at 4.
- ✅ Namespace-collision landmine: `ContentManagement/TreatmentVisitController.php` class removed (tombstoned; git-delete the empty file from Windows).
- ✅ **P0 #1** — Inventory web GRN-receive (`receivePO()`) wrapped in `DB::transaction()`. **Runtime-verified 2026-07-18** on local: forced failure → HTTP 500 + stock stayed 0 (full rollback, no partial records); success → HTTP 200 + stock 0→5 + PO→Completed + vendor bill created. Inventory Stability 4→6, Overall 4→5.

## Coverage
Audit complete (26 modules). Still needs runtime confirmation: `php artisan app:crawl-routes`,
`security:selftest`, `automation:parity`, browser walkthroughs of *(rt)* items, and confirming the HR
role-backfill + membership-receipt backfill migrations actually ran in prod.
