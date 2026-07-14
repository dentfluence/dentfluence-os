# Web ↔ Mobile Parity Audit — 2026-07-14

**Scope:** Full read-only audit of `Dentfluence Web` (Laravel) vs `dentfluence_mobile` (Flutter) across every module. Layout/UX differences excluded by design; only functional, validation, calculation, permission and report differences are reported. All high-severity findings were independently verified against source before inclusion.

---

## 1. Overall Parity

| Lens | Parity | Notes |
|---|---|---|
| **Overall (all modules, weighted)** | **≈ 50%** | Dragged down by web-only back-office: Finance, HR, Settings, DPDP, Marketing, Presentations, Reviews |
| **Daily clinical operations** (patients, appointments, consults, visits, Rx, billing, payments, huddle) | **≈ 80%** | Strong — most daily flows work on mobile via shared services |
| **Back-office / admin** (Finance, HR, Settings, DPDP, roles, reports) | **≈ 15%** | Almost entirely web-only |

**Answer to the key question — can a dentist or receptionist run the entire clinic mobile-only?**
**No.** The mobile app covers the *chairside and front-desk day* well, but the clinic cannot be *administered* from it. Full list of missing capabilities in §7.

---

## 2. Mobile Parity Matrix

Status legend: ✅ Complete · ◐ Partial · ✗ Missing · ⚠ BL = Business Logic Difference · ⚠ V = Validation Difference

| Module | Web | Mobile | Status | Gap | Recommendation |
|---|---|---|---|---|---|
| Dashboard | Full KPIs: revenue, outstanding, lab pending/overdue, alerts strip, status breakdown | Module grid + today's schedule only | ◐ Partial | API `DashboardController` returns only patient/appointment counts | Extend API dashboard payload; render KPI strip on home |
| Patients | Full CRUD + import/export, print, scan-form, reactivate, delete | Create/edit/deactivate/list/search (shared `PatientService`) | ◐ Partial + ⚠ V | Import/export, print, reactivate, destroy, OCR scan-form missing; API deactivate skips web's password gate | Add reactivate endpoint; align deactivate security; leave import web-only (acceptable) |
| Appointments | Book, walk-in, block-slot, status, cancel, **reschedule, edit, delete**, operatory, hide, revert | Book/walk-in/block/status/cancel | ◐ Partial + ⚠ BL | No reschedule/edit/delete API; **mobile can never send `allow_overlap` → deliberate double-booking impossible**; type enum lacks `follow-up`; doctor filter not wired | P1: add `allow_overlap` to Flutter + `WalkInRequest`; P2: reschedule/edit endpoints |
| Consultations (4 workflows) | All 4 + delete, server print, plan-prefill, inline Rx/instructions | All 4 workflows + COHA | ◐ Partial + ⚠ V | Delete missing; inline prescriptions/instructions not accepted by API; `doctor_id` nullable on API vs required on web | Accept inline Rx data in API; add delete |
| Treatment Plans | Full incl. consent print, AI suggest, item delete | Create/edit/view/accept/revert/bill/print(client PDF) | ⚠ BL | **API revert has no billing guard + no audit log** (web blocks revert if invoiced); API drops `treatment_id`/`consent_required` fields; no `treatment_plan.created` activity | P1: port billing guard + audit into API revert (share via `TreatmentPlanAcceptanceService`) |
| Treatment Visits | Full | Full — same `TreatmentVisitService::rules()` both sides | ✅ Complete | Server print only | Gold standard; replicate this pattern everywhere |
| Clinical Notes | store/destroy | store/update/delete | ✅ Complete | API is richer than web | None |
| Clinical Library / Documents | Upload, edit metadata, delete, filtered browse | Upload doc + clinical photo capture only | ◐ Partial | No metadata edit, no delete, no per-file show on API | Add PUT/DELETE clinical-file endpoints |
| Tooth Charting (incl. pediatric) | Adult/child toggle, chart_data | Full FDI chart with adult/child toggle | ✅ Complete | — | None |
| Presentations (Smart Treatment) | Full module + public patient links | Nothing | ✗ Missing | Zero API routes, zero mobile screens | Product decision: is chairside presentation-on-tablet a selling point? If yes, high-value build |
| Billing / Invoices | Full incl. edit, delete (password-gated), payment-date edit, Final Bill view | Create (full recompute chain), detail, print/share PDF, cancel, void | ◐ Partial | Invoice edit/delete, payment-date edit, Final Bill view/delete missing | Add invoice edit; expose `updatePaymentDate` (service already has it) |
| Payments / EMI / Receipts | Full | Record payment, direct+provider EMI, schedules, mark-paid, receipt PDF | ⚠ BL | **`wallet_used` stripped by API — cannot pay from wallet credit on mobile** (service already supports it; controller omits the rule) | One-line-class fix: add `wallet_used` to API rules + Flutter field. Highest ROI fix in the audit |
| Wallet | Advance, refund, adjust, promo credit, campaigns, register, ledger, credit-note PDF | Balance view, refund, advance credit | ◐ Partial + ⚠ BL | **API `addWalletCredit` posts NO FinanceTransaction, no audit, no permission gate** (invisible to cashbook); adjust/promo/register/ledger missing | P1: route mobile advance through the web's `receiveAdvance` chain |
| Membership (AOCP) | Plan CRUD, enroll, cancel, toggle | Enroll (shared `MembershipBenefitService`, full finance chain) | ◐ Partial | Plan CRUD + cancel enrollment web-only | Acceptable: plan admin is a settings task |
| Coupons | CRUD + validate/apply | Validate/apply (hardened, server recompute) | ◐ Partial | CRUD web-only | Acceptable |
| Lab v2 | Cases CRUD, transitions, templates CRUD, **reconciliation**, lab-vendors CRUD, price lists | Read-only: summary/list/detail | ◐ Partial + ⚠ BL | Write endpoints exist but **no Flutter UI**; **API transition never posts lab expense to AP** (web does); reconciliation + lab vendors entirely missing | P1: add `LabExpenseService` call to API transition; P2: wire existing write endpoints into Flutter |
| Inventory | Full | Products, stock in/out, adjust, reversal, PO→GRN, implants, reusable assets, stock counts | ◐ Partial + ⚠ BL | No archive/delete, no GRN reversal, no CSV import (intentional); **API create skips `cost_per_usage` calc**; **quick-adjust drops `unit_price`** (never updates item cost); GRN finance `source_type` differs between paths | Consolidate onto `InventoryService` (its docblocks already ask for this) |
| Vendors | CRUD + toggle | Read + update only | ◐ Partial | No create, no activate/deactivate | Add 2 endpoints |
| Vendor Invoices / AP | Create with **double-AP guard** (added 2026-07-14) | Create/delete | ⚠ BL **HIGH** | **API path lacks the double-AP guard → mobile vendor invoice against a received PO double-books the payable.** Also: web GRN books expense as `GoodsReceiptNote` class, service as `'PurchaseOrder'` string — guard is fragile on both channels | P0: extract shared `VendorInvoiceService` with the guard; normalize `source_type` |
| Finance (expenses, Snap-a-Bill, payroll, cashbook, GST, vouchers, CA export, analytics/BI) | Full | Nothing (except vendor-invoice AP + outstanding list) | ✗ Missing | Zero API routes for the entire `Finance/*` namespace | Product decision: expense entry + cashbook view are plausible mobile wants; payroll/GST/CA-export are desk work |
| Reports | 6-tab reports w/ 7/30/90/365/custom ranges; Huddle Weekly/Monthly/Quarterly/Annual w/ trends + protocol compliance; Finance reports | Fixed overview (today/week/month) + outstanding list | ✗ Missing + ⚠ BL | No date-range params at all; period reports absent; **web outstanding = draft+partial but API = all non-cancelled → different totals**; **collections from 3 different tables across 3 surfaces** (`InvoicePayment` vs `FinanceTransaction` vs `Receipt`) | P1: shared `ReportMetricsService`; P2: date-range param on `/reports/overview` |
| Communication / WhatsApp | Two-way inbox, reply, template send, consent-gated server sends | Log-a-communication only; "WhatsApp" = device deep-link | ✗ Missing + ⚠ BL | **Mobile deep-link bypasses the DPDP consent gate entirely** (no `PatientConsent` check, no `wa_messages` record, no audit) — web sends are consent-gated in production | Decide: either consent-gate a proper API send path, or accept deep-link as "personal phone" behavior and document it |
| Tasks | Huddle task log w/ proof upload, carry-forward; Comm tasks w/ evidence, escalate | Create/toggle/assign(API-only) huddle tasks | ◐ Partial + ⚠ BL | Proof/evidence/escalate/carry-forward missing; web `POST /huddle/tasks` logs an existing task while API creates a new one — same route name, different semantics | Unify task-create semantics |
| Daily Huddle / Action Board | Full | Board, Yesterday's Flow, Today's Flow, Action Board (outcomes/dismiss/close/notes/add-call), comms push | ✅ Complete (best-in-app) | Quick-note is a stub on **both** platforms | None — reference implementation |
| HR | Staff, roles (admin-only), attendance, payroll, training, memos | Nothing | ✗ Missing | Zero API routes | Acceptable for v1; QR attendance already has a token path |
| Marketing | Full module | "Coming soon" tile | ✗ Missing | Intentional — parked 07-10 | Leave parked |
| Reviews | Request/send/reply + public rating pages | Nothing | ✗ Missing | No API | Consider: review-request button on mobile checkout is a high-frequency use |
| Tags | CRUD + patient attach/detach | Nothing | ✗ Missing | No API | Low priority |
| Notifications | Global + PRE in-app lists | Device push prefs only | ✗ Missing | No API | Medium: mobile is where notifications matter most |
| Settings | ~15 admin pages incl. EMI providers, staff/roles, masters, operatories, feature flags, action-options/`closes_task` | Recall/birthday settings + templates only | ✗ Missing | — | Acceptable except action-options (companion to Action Board which IS on mobile) |
| PRE / Relationship | List, pipelines, profile w/ comm tab + inline WhatsApp chat, missed calls, recalls | List, pipelines, profile (score/stage/timeline), missed calls (dismiss/ignore), Today's Actions | ◐ Partial | Profile Communication tab + inline chat missing; `select_all` bulk-dismiss missing (mobile can't clear the 1,810-item backlog); one-click birthday WhatsApp missing; orphaned `bulk-whatsapp` API route (no caller anywhere) | Add `select_all` to API bulk-dismiss; delete orphan route |
| User Profile | Name/email/phone/designation, avatar, password (current-password + complexity) | Name/email/password | ◐ Partial + ⚠ V | Phone/designation/avatar missing; **API password change requires NO current password, only `min:8`** | P0 security: require current password + `Password::defaults()` on API |
| Authentication | Login+2FA/TOTP+recovery, forgot-PIN, mobile-OTP, session timeout | Email/password login, logout | ◐ Partial | **2FA-enabled accounts cannot log in on mobile at all** (API enforces the code; Flutter never sends one); no password reset; no 401/token-expiry handling (30-day token just dies with a raw error) | P0: 2FA code step in Flutter login + 401 interceptor |
| Global Search | Indexed cross-entity search | Patient search + per-list filters | ⚠ BL | Scope difference | Low priority |

---

## 3. Critical Findings (verified against source)

These were each independently re-verified in this audit session, not just reported by sub-audits.

### Money integrity
1. **Double-booked payables from mobile** — Web `VendorInvoiceController::store` gained a double-AP guard on 2026-07-14 (`VendorInvoiceController.php:183-240`); `Api/V1/VendorInvoiceController.php:220-252` has none. A vendor invoice raised from mobile against an already-received PO creates a second unpaid `FinanceExpense` and double-increments vendor outstanding. *Compounding bug:* web GRN books the expense with `source_type = GoodsReceiptNote::class` while `InventoryService::receivePurchaseOrder` uses `'PurchaseOrder'` — so even the web guard misses web-created GRNs.
2. **Wallet credit silently lost on mobile invoice cancel** — Web `cancelInvoice`/`destroy` call `reverseInvoiceWalletDebit` (`BillingController.php:721,769,1585`); `Api/V1/BillingController::cancelInvoice` never re-credits the wallet. Cancel a wallet-part-paid invoice from mobile → patient's credit vanishes.
3. **Mobile advance payments invisible to the cashbook** — Web `receiveAdvance` posts a `FinanceTransaction` income mirror + `BillingAuditLog` + permission gate; API `addWalletCredit` (`Api/V1/BillingController.php:1091-1122`) does `WalletService::credit` only — no ledger entry, no audit, no gate.
4. **Cannot pay from wallet on mobile** — `InvoicePaymentService` supports `wallet_used` (`:126-142`), but `Api/V1/BillingController::recordPayment` omits it from validation so it's stripped; Flutter never sends it (0 hits in `lib/`). Note: the comment at web `BillingController.php:1130-1133` claiming the service "lacks" this logic is now stale — the logic exists, it's just not wired.
5. **Lab expense never posted from API** — Web lab `transition` calls `LabExpenseService::createForCase` on final receipt (`LabController.php:580-585`); `Api/V1/LabController` contains zero expense references. API-driven lab completion skips AP entirely. (Mitigated today only because mobile lab is read-only — the endpoints are live and unguarded, see #7.)

### Security
6. **API password change needs no current password** — Web requires `current_password` + `Password::defaults()` (`ProfileController.php:49-55`); API `updateMe` accepts a new password with only `min:8` and no current-password check. A stolen/idle token can silently take over the account.
7. **Ungated API writes** — Lab case create/transition/prescription/attachments (`api.php:219-222`) and patient notes/communications writes (`api.php:119-122`) have **no role middleware** — any authenticated token. Reports endpoints ungated too (web requires `module:reports`).
8. **2FA lockout on mobile** — API login correctly enforces 2FA, but the Flutter client has no code field → any staff member you protect with 2FA loses mobile access entirely.
9. **DPDP consent bypass** — All mobile "WhatsApp" actions are device deep-links (`relationship_screens.dart:110-191`) that skip `OutboundMessageService::consentGate` — the gate that `guard.consent_required` enforces in production on web. No consent check, no `wa_messages` record, no audit trail.
10. **Permission-model drift** — Web gates by permission table (`module:*` middleware); API gates by hard-coded role names (`api.role:admin,front_desk`). `EnsureApiRole` gained a `module:` mode on 2026-07-14 but `routes/api.php` uses it **zero times**. Consequences: custom roles with finance permission work on web, blocked on API; doctors can create/accept treatment plans on web, blocked on API; conversely clinical writes are doctor-only on API but open to any patients-module role on web.

### Silent data divergence
11. **Reports disagree between platforms** — Outstanding: web sums `draft+partial` invoices; API sums all non-cancelled. Collections: web reports use `InvoicePayment`, huddle report uses `FinanceTransaction`, API uses `Receipt` — three source tables for one number.
12. **Prescription edit semantics differ** — API implements version control (locked Rx → new revision, original marked REVISED); web `update` force-overwrites in place, destroying history. Same edit, different records, depending on platform. (Here the API is *better* — web should adopt it.)
13. **Mobile cancel-via-status leaves no trail** — `AppointmentService::updateStatus` accepts `cancelled` but doesn't log it (web does); mobile cancellations through the status route are invisible to the timeline.
14. **Mobile-created invoices invisible to Insights** — Web invoice create fires `ActivityEngine 'invoice.created'`; API path doesn't.
15. **Client-side PDF pattern** — Mobile prints/shares consultation/plan/Rx/invoice PDFs locally, so server counters (`print_count`, `printed_at`, `whatsapp_sent_at`, status transitions) never fire for mobile actions.

---

## 4. Duplicated Logic That Must Be Consolidated

The root architectural cause of nearly every drift above: parallel implementations instead of shared services.

| Operation | Web copy | API/service copy | State |
|---|---|---|---|
| Record payment (7-step chain, ~380 lines) | `BillingController.php:794-1181` | `InvoicePaymentService.php:49-371` (self-described "verbatim replica" — web never calls it) | Already drifting (activity actor null vs user) |
| Invoice creation | `BillingController.php:307-508` | `Api/V1/BillingController.php:555-753` | Drifted (ActivityEngine, validation) |
| Void/cancel/refund finance chain | `BillingController.php:1294-1594` (inlined twice) | `Api/V1/BillingController.php:1173-1238` | Drifted (wallet reversal) |
| Vendor invoice + AP | `VendorInvoiceController.php:102-264` | `Api/V1/VendorInvoiceController.php:134-263` | **Drifted (double-AP guard)** |
| GRN receive | `InventoryController.php:1957-2103` | `InventoryService.php:503-642` | Drifted (source_type, transaction, task auto-close) |
| Quick stock adjust | `InventoryController.php:2452-2503` | `InventoryService.php:274-300` | Drifted (unit_price/cost) |
| Appointment create/status | `AppointmentController.php:116-358` | `AppointmentService.php:200-266` | Drifted (cancelled logging) |
| Prescription lifecycle | `Prescription/PrescriptionController.php:112-220` | `Api/V1/PrescriptionController.php:220-400` | **Drifted (version control)** |
| Consultation create ×4 | `ConsultationController.php:49-150,377-512` | `Api/V1/ConsultationController.php:29-139` | Drifted (inline Rx, JSON fields) |
| COHA write | `ConsultationController.php:553-637` | `Api/V1/CohaController.php:40-117` | Copies in sync (for now) |
| Stock-count adjustment | `StockCountController.php:155-229` | `Api/V1/StockCountController.php:156-235` | Copies in sync |
| Report aggregates | `ReportsController`, huddle `HuddleController::report`, `Api/V1/ReportController` | 3-4 independent query sets | **Drifted (outstanding, collections)** |
| Lab case store/transition | `LabController.php:197-585` | `Api/V1/LabController.php:197-331` | **Drifted (expense posting)** |
| Reverse adjustment + stock history | `InventoryController.php:2511-2547+` | `Api/V1/InventoryController.php:618-704` | Verbatim copies |
| Retail stock movements | `BillingController.php:651-714` | `Api/V1/BillingController.php:917-977` | Verbatim copies |

**Working examples to copy:** `TreatmentVisitService` (shared rules + logic), `TreatmentPlanAcceptanceService`, `PatientService`, `MembershipBenefitService`, `TreatmentPlanBillingService`, `ClinicalFileUploadService`, `InventoryService` (API side). The pattern exists — it just needs to be finished.

---

## 5. Recommended Implementation Order

Ordered by (risk × frequency of use) ÷ effort. Slices, one at a time.

**P0 — Money & security correctness (days, not weeks)**
1. Vendor-invoice double-AP guard → shared `VendorInvoiceService`; normalize GRN `source_type`.
2. Wallet reversal on API `cancelInvoice`; FinanceTransaction + audit + gate on API `addWalletCredit`.
3. API password change: require current password + `Password::defaults()`.
4. Role-gate the ungated API groups (lab writes, notes/comms, reports) — the `module:` mode of `EnsureApiRole` already exists, wire it.
5. 2FA code step in Flutter login + 401 interceptor (staff with 2FA are currently locked out of mobile).

**P1 — High-frequency workflow gaps**
6. `wallet_used` in API recordPayment rules + Flutter field (service already supports it).
7. `allow_overlap` in Flutter booking + `WalkInRequest` (front desk cannot double-book from mobile).
8. Appointment reschedule/edit endpoints + Flutter UI.
9. API treatment-plan revert: billing guard + audit (extend `TreatmentPlanAcceptanceService`).
10. Lab expense posting in API transition; then wire the existing lab write endpoints into Flutter.

**P2 — Consolidation engine work (prevents all future drift)**
11. Web `recordPayment` → `InvoicePaymentService`; extract `InvoiceCreationService`, `RefundService`.
12. Fold web `InventoryController` writes onto `InventoryService`.
13. Shared `ReportMetricsService` — one definition of outstanding/collections/completed.
14. Prescription: adopt API's version-control on web via a shared `PrescriptionService`.

**P3 — Capability additions (product-decision gated)**
15. Reports date ranges + period reports on mobile.
16. Notifications API + mobile list.
17. Consent-gated WhatsApp send path on mobile (or documented deep-link policy).
18. Clinical-file edit/delete; vendor create/toggle; missed-calls `select_all`.
19. Presentations on mobile/tablet — only if chairside presentation is a sales priority.

**Explicitly NOT recommended for mobile:** HR/payroll, GST/CA export, plan/coupon/master CRUD, DPDP admin, Marketing (parked), CSV imports. These are desk tasks; porting them adds surface without revenue. The goal is not "everything on mobile" — it is "no *daily* task requires walking to the desktop."

---

## 6. Cleanups Found (no code changed)

- Orphaned API route: `POST /relationship/missed-calls/bulk-whatsapp` — no web method, no mobile caller (mobile removed it 07-06). Delete it.
- Stale comment at `BillingController.php:1130-1133` claiming the shared service lacks wallet logic (it doesn't anymore).
- Stale mobile copy: missed-calls tile still says "bulk WhatsApp or dismiss" (`relationship_screens.dart:272`).
- Huddle `storeNote` stub on both platforms ("Not yet implemented" / local-only).
- Web huddle report counts done as `['done','checkout','completed']` while main reports and API count `'done'` only — web-internal inconsistency.

---

## 7. Can the clinic run mobile-only? — Full missing-capability list

**No.** A receptionist/dentist on mobile-only cannot:

*Front desk:* reschedule or edit an appointment; deliberately double-book; import/export patients; print patient profile; reactivate a patient.
*Clinical:* delete a consultation; edit/delete clinical files; send a treatment presentation; print consent forms; use AI suggest.
*Money:* edit or delete an invoice; edit a payment date; pay any part of an invoice from wallet; adjust a wallet; give promotional credit; view wallet register/patient ledger; record an expense; scan a bill; see the cashbook, GST, payroll, vouchers, CA export, or any finance analytics; manage membership plans or cancel an enrollment; manage coupons.
*Lab:* create a lab case or move its status (endpoints exist, no UI); reconcile a lab month; manage lab vendors or price lists.
*Inventory:* archive an item; reverse a GRN; create or deactivate a vendor; import products.
*Engagement:* see or reply to the WhatsApp inbox; send a consent-gated WhatsApp; send review requests; manage tags; see in-app notifications; bulk-clear the missed-call backlog.
*Reports:* choose any date range; see weekly/monthly/quarterly/annual reports; see treatment/inventory/demographic analytics; see protocol compliance.
*Admin:* everything — staff, roles, permissions, all settings pages, EMI providers, masters, operatories, feature flags, DPDP consent, data requests, breach register, activity log viewer.
*Account:* log in with 2FA enabled; reset a forgotten password; change avatar/phone/designation.

---

*Audit performed read-only. No code modified. Companion documents: `docs/production-readiness-review.md`, `docs/production-hardening-2026-07-14.md`.*
