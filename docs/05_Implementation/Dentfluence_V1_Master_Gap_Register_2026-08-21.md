# DENTFLUENCE V1 — MASTER GAP REGISTER
**Delta audit · 21 August 2026 · FROZEN on completion**

| | |
|---|---|
| **Audit type** | Delta — scoped against prior V1 audits, current implementation, the Dr Systems workflow benchmark, and CEO Implementation Directive (08-07) |
| **Go-live** | **14 September 2026 (Ganesh Chaturthi) — 24 days** |
| **Method** | Source inspection of `E:\Dentfluence\Dentfluence_OS\Dentfluence Web` @ 21-Aug-2026 (433 migrations, 186 models, 1,814 PHP files) |
| **Status** | **FROZEN.** Fix one gap at a time and update this file. Do not re-audit the product. |
| **Supersedes** | Nothing. Complements Audit #4 (08-07), Billing Master Audit (08-15), Void Path Audit (08-19), V1 Position Report (08-20) |

> **Scope honesty.** Three things were *not* re-verified from source and are carried forward from prior audits rather than re-proved: the void/delete path (Audit 08-19, verdict E), the production `.env` on the VPS (not readable remotely), and the Flutter/Android app (not present in the connected folder). Dr Systems is referenced from the workflow description supplied in the audit brief — no screenshots were available in this session.

---

## 1. EXECUTIVE VERDICT

**Dentfluence's money *engine* is sound. Its money *reporting* is not.**

The billing run of 15–18 August did its job. Collections, allocation, wallet, advances, receipts and reversals rest on canonical tables with one writer per fact, and `ReportMetricsService` gives web and mobile a single definition of "collected". That is a real asset and most of it should not be touched.

The failure is one layer up. **Six of the owner-facing numbers Tulip would run the clinic on are wrong, dead, or contradicted by a second number elsewhere in the same product** — and none of them fail loudly. They render a figure. The figure is ₹0, or all-time, or clamped at zero, or computed from a different table than the screen next to it. A clinic that trusted these screens for a month would not discover the problem; it would simply make worse decisions and never know why.

Concretely, today Tulip **cannot** answer three of the questions Dr Systems answers on a spreadsheet:

- **"Which treatments earn us money?"** — the report reads `finance_income_entries`, a table **no code in the application ever writes**.
- **"How did each doctor perform?"** — the report attributes revenue through `invoices.appointment_id`, a column **no code in the application ever sets**. Every rupee shows as *Unassigned*.
- **"Does the cash drawer tie out?"** — there is no opening balance, no physical count, and no reconciliation; the dashboard's "cash in hand" is an all-time running total wrapped in `max(0, …)`, so a shortfall displays as ₹0.

The good news is the size of the repair. Every one of these is a **read-path or wiring defect over data the system already holds correctly** — exactly the pattern the August billing run established. `invoice_items.treatment_id` already exists and is populated. `appointments.in_chair_at` is already written. `treatment_visits.doctor_id` is already the clinical truth. Nothing here requires a new money model, a schema redesign, or a Dr Systems-shaped rebuild. It requires connecting things that were built and never joined up.

**Verdict against 14 September: achievable, but only on a frozen list.** The register below is 23 gaps. **8 are P0, 8 are P1.** The P0 set is roughly 9–11 working days of focused work — which fits 24 days *only* if nothing else is added. The single largest risk to this date is not any gap on this list; it is the audit-begets-audit pattern that consumed the 07–20 August window. This register exists to end that pattern. Freeze it.

**One decision is still owed by the CEO and blocks nothing else until it is made:** U1 — whether GST is computed pre- or post-concession. One sentence, live compliance exposure, carried open since 15 August.

---

## 2. DR SYSTEMS ↔ DENTFLUENCE GAP MATRIX

**A** = solved properly · **B** = partial · **C** = missing · **D** = exists technically, operationally weak · **E** = exists but unreliable / integrity issue

| Dr Systems capability | Dentfluence equivalent | Status | Evidence | V1 action |
|---|---|---|---|---|
| Daily collections | `ReportMetricsService::collected()` → `invoice_payments`; shared by web + mobile | **A** | `app/Services/Analytics/ReportMetricsService.php` | **Do not touch** |
| Collections — daily series | `collectionsSeries()`, zero-filled | **A** | same file | None |
| Collection by patient / invoice | Income + Collection report tabs | **A** | `FinanceReportsController::incomeData`, `collectionData` | None |
| Collection by **doctor** | Provider tab via `invoices.appointment_id` | **E** | `FinanceReportsController::providerData`; no writer sets `appointment_id` | **G-02** |
| Collection by **treatment / category** | Reports → `finance_income_entries` | **E** | `ReportsController.php:110-124`; zero writers | **G-01** |
| Payment modes | Enum on `invoice_payments` + receipts; standardised 08-19 | **A** | `2026_08_19_100001_standardise_payment_modes.php` | None |
| Payment-mode reporting | By-mode breakdown on 3 surfaces | **A** | `revByMode`, `incomeData.byMode` | None |
| Split / partial / advance payments | FIFO allocation, patient credit, advance receipts | **A** | `PatientPaymentAllocationService`, U8 rules | **Do not touch** |
| Refunds / reversals | A1 wallet refund, A2 payment reversal, receipt restore | **A** | 08-16→08-18 run | None |
| Void / delete of invoices | Old 3-dot delete path | **E** | Void Path Audit 08-19, verdict E | **G-08** (carried) |
| Outstanding / receivables | Receivables tab with 30/90 ageing | **A** | `receivablesData()` | None |
| **Cash drawer** — opening/closing | `/finance/cashbook`, derived | **D** | `FinanceController::cashbook`, `$balance = 0` | **G-05** |
| **Cash drawer** — reconciliation / discrepancy | — | **C** | `finance_cashbook` table: 0 readers, 0 writers | **G-05** |
| Cash in hand (headline KPI) | Finance dashboard tile | **E** | `FinanceController.php:61-62,92` — all-time, `max(0,…)` | **G-04** |
| Expenses — entry | Full CRUD + bill-photo scan | **A** | `FinanceController::expenseStore` + `/expenses/scan` | None |
| Expense categories | Master data, seeded, enforced | **A** | `FinanceSeeder`, `exists:` validation | None |
| Expenses — auto-feeds | Lab, procurement, inventory all post automatically | **A** | `LabExpenseService`, `VendorInvoiceService:164`, `InventoryService:737` | **Better than Dr Systems** |
| Expense reporting | By category / vendor / month + export | **A** | `expenseData()`, `AnalyticsController::expenseAnalytics` | None |
| Discounts | Coupon + invoice-header manual discount only | **B** | `discountData()` — no line-item, plan-item or promo credit | **G-13** |
| **P&L** | Two implementations, two definitions | **E** | `FinanceController:72` vs `AnalyticsController:488` | **G-06** |
| P&L — monthly / quarterly / FY | Indian FY quarters, margin % | **A** | `AnalyticsController::businessIntelligence` | None (after G-06) |
| Revenue by treatment category | Dead read path | **E** | as above | **G-01** |
| **Skill / service mix** | Grouped on free-text `procedure` | **D** | `ReportsController::buildTreatmentData` `txVisitsByProc` | **G-09** |
| **Chair time** | Captured, never reported | **D** | written `AppointmentService:369-371`; zero analytic readers | **G-10** |
| Average ticket size | `avg_invoice` on Revenue tab | **A** | `buildRevenueData()` | None |
| Treatment acceptance / conversion | — | **C** | no acceptance or conversion metric in any report | **G-14** |
| Reason for conversion / lead source | `patients.source`, lead module | **B** | `patSource` breakdown only; not tied to revenue | **G-14** |
| Pending / outstanding treatment | — | **C** | outstanding *money* only | **G-15** |
| Patient database | Full module, frozen V1.1 | **A** | patients module | **Do not touch** |
| Patient DB export | Admin CSV export + import | **A** | `PatientImportExportController` | None |
| Treatment history export | — | **C** | no clinical export route | **G-17** |
| Benchmarks / targets | Monthly revenue target only | **B** | `billing.monthly_revenue_target` | P3 |
| Mapping / settings / master data | Treatment + category CRUD, modes, vendors, roles | **A** | `routes/web.php:347-352, 484` | None |
| Recall / follow-up | Recall engine on `last_visit_date` | **E** | advanced by Consultation only, not TreatmentVisit | **G-11** |
| Lab workflow | Cases, vendors, costs, reconciliation, auto-expense | **A** | lab module + `LabExpenseService` | None |
| Inventory | Frozen V1, append-only ledger, auto-expense | **A** | Inventory Hardening 08-05 | **Do not touch** |
| CA / accountant handoff | CA Export (xlsx + csv) | **B** | Treatments column always blank | **G-12** |

---

## 3. MASTER V1 GAP REGISTER

Format per gap: **Gap → Evidence → Current state → Business impact → Severity → Exact V1 fix → Dependencies → Acceptance criteria.**

### P0 — Trust / data integrity / financial correctness

---

#### **G-01 · Revenue by treatment category reads a table nothing writes**

- **Evidence** — `app/Http/Controllers/ReportsController.php:110-124` queries `finance_income_entries` joined to `treatments` → `treatment_categories`. Repository-wide search for writers of that table returns **zero** in `app/`; the only inserts live in a dummy-data seeder (`database/seeders/.fuse_hidden…:794`) and in delete/merge manifests.
- **Current state** — The Reports → category KPI renders. Every category shows `revenue = 0` and `txn_count = 0`, merged silently into the appointment-count table so the row still looks populated.
- **Business impact** — The single most valuable question in the Dr Systems sheet — *which treatments generate our revenue* — cannot be answered. Worse than missing: it renders a confident ₹0 next to a real appointment count, which reads as "this category earns nothing".
- **Severity** — **P0** (trust). A visibly wrong money number on the owner's main report.
- **Exact V1 fix** — Delete the `finance_income_entries` read. Derive category revenue from canonical data: `invoice_payments` → `invoices` → `invoice_items.treatment_id` → `treatments.treatment_category_id`, allocating each payment across that invoice's items **pro rata on `invoice_items.total`**. Report *collected* (cash basis) to match every other money number on the page; show *billed* as a second column only if the CEO asks. Lines with `treatment_id IS NULL` roll into an explicit **"Unclassified"** row — never hidden, never zero-filled.
- **Dependencies** — None. `invoice_items.treatment_id` exists (`2026_07_02_100004`) and is populated by `TreatmentPlanBillingService:129`. Interacts with **G-18** (retire the dead table) and **G-09** (custom/manual lines are the source of "Unclassified").
- **Acceptance criteria**
  1. Seed one invoice with two items in different categories, pay it half; the report splits the payment pro rata across both categories and the two rows sum to the amount paid.
  2. A manual invoice line with no `treatment_id` appears under **Unclassified** with its rupee value, not omitted.
  3. Sum of all category rows (incl. Unclassified) **equals** `ReportMetricsService::collected()` for the same range — asserted in a test.
  4. `finance_income_entries` is referenced nowhere in `app/`.

---

#### **G-02 · Doctor-wise collection is structurally dead — every rupee is "Unassigned"**

- **Evidence** — `app/Http/Controllers/Finance/FinanceReportsController.php::providerData()` attributes revenue via `invoice_payments → invoices → LEFT JOIN appointments ON invoices.appointment_id → users`. `appointment_id` is `fillable` on `App\Models\Invoice` (line 49) but **no service or controller ever sets it** — searches across `app/Services/Billing`, `app/Services/TreatmentPlan` and `BillingController` return zero assignments. `invoices` has no `doctor_id` or `provider_id` column.
- **Current state** — The Provider tab renders `byDoctor`, `byDoctorMonth`, `doctorRows` and an `unassigned` total. In practice `doctorRows` is empty and `unassigned` equals total collections. The screen does not say so.
- **Business impact** — No doctor productivity, no consultant/associate payouts, no incentive calculation, no answer to "who earned what". Blocks the associate-dentist model entirely and materially weakens the product's sale value.
- **Severity** — **P0** (trust + core reporting). A report whose every row is a fallback label.
- **Exact V1 fix** — Attribute revenue to the clinician who **did the work**, not to a nullable appointment link. Add `invoice_items.performed_by_user_id` (nullable), populated from `treatment_visits.doctor_id` when the line is billed from a visit and from `treatment_plans.doctor_id` when billed from a plan. Rewrite `providerData()` to allocate payments pro rata across items by `performed_by_user_id`. Keep an explicit **Unattributed** row. Leave `invoices.appointment_id` alone — do not retro-wire it.
- **Dependencies** — Do **G-01** first; both need the same pro-rata payment-allocation helper, which should be written once and shared. Touches the same seam as **G-09**.
- **Acceptance criteria**
  1. An invoice billed from a treatment visit attributes its collected amount to that visit's `doctor_id`.
  2. A two-doctor invoice splits pro rata; the two rows sum to the amount collected.
  3. A manual line with no clinician appears under **Unattributed** with its value shown.
  4. Sum of all provider rows equals `collected()` for the range — asserted in a test.
  5. Where `doctorRows` is empty, the view states *"No attributed revenue in this period"* rather than rendering an empty table.

---

#### **G-03 · Two different "collected today" — the Huddle was never migrated to the shared brain**

- **Status** — ✅ **FIXED 2026-08-24.** `HuddleService::yesterdaySection()` and `HuddleBoardApiService::kpis()` now call `app(ReportMetricsService::class)->collected($from, $to, $branchId)`; both `class_exists(FinanceTransaction::class)` guards are gone and no `FinanceTransaction` reference remains anywhere in `app/Services/Huddle/`. Two behaviour changes worth knowing: the briefing line is now **"Total collections"**, not "Total collections (all sources)" — advances and wallet top-ups are liabilities, not collections, and were inflating it; and the mobile board's `collected_today` is now **branch-scoped**, matching the rest of that payload (it was clinic-wide). Covered by `tests/Feature/Finance/HuddleCollectionsParityTest.php`, whose fixture asserts the seeded day is one where the two definitions genuinely disagreed. **Not in scope of this row, appended as G-32:** the *web* Huddle KPI panel and the assistant KPI tool still read `finance_transactions`.

- **Evidence** — `app/Services/Huddle/HuddleService.php:238-239` and `app/Services/Huddle/HuddleBoardApiService.php:172-173` both compute collections from `Finance\FinanceTransaction`. Every other surface uses `invoice_payments` via `ReportMetricsService`. That service's own docblock names this exact defect as the reason it was created on 2026-07-14: *"three surfaces computed 'collections' from three different tables (web reports: InvoicePayment; **huddle report: FinanceTransaction**; mobile API: Receipt)"*. Two of the three were migrated. The Huddle was not.
- **Current state** — The morning Huddle board — the screen staff look at first, every day — can show a different "collected today" from Finance and Reports for the same day. `finance_transactions` remains effectively write-only (13 writers, 1 reader per the Billing Master Audit); that 1 reader is the Huddle.
- **Business impact** — Staff and owner argue about which number is right on day one of go-live. This is precisely the class of contradiction that destroys trust in a new system, and it is the *first* screen of the day.
- **Severity** — **P0** (trust). Incomplete fix of a defect already declared fixed.
- **Exact V1 fix** — Replace both `FinanceTransaction` queries with `app(ReportMetricsService::class)->collected($from, $to, $branchId)`. Delete the `class_exists()` guards — the model is not optional. No schema change.
- **Dependencies** — None. Smallest P0 on the list; do it first.
- **Acceptance criteria**
  1. On a day with a mix of cash, UPI, wallet-tender and advance receipts, Huddle "collected today" **equals** Finance dashboard "today's collection" to the rupee.
  2. A test asserts the two surfaces agree across a seeded day containing at least one advance and one wallet settlement.
  3. No `FinanceTransaction` reference remains in `app/Services/Huddle/`.

---

#### **G-04 · "Cash in hand" is all-time, mode-blind, and hides deficits**

- **Evidence** — `app/Http/Controllers/Finance/FinanceController.php:61-62`:
  `$cashReceived = InvoicePayment::where('payment_mode','cash')->sum('amount');` and
  `$cashSpent = FinanceExpense::where('payment_mode','cash')->sum('total_amount');`
  — **neither is date-bounded** — then line 92: `'cash_in_hand' => max(0, $cashReceived - $cashSpent)`. Line 93: `'bank_balance' => 0, // wire to FinanceBankAccount when balances tracked`.
- **Current state** — Four separate defects in one tile: (a) all-time since first record, so it can never equal the physical drawer; (b) excludes cash **advances**, which `cashbook()` deliberately includes (its own comment: *"a cash ADVANCE is real cash in the till… It must be included or the drawer can never be tied out"*) — the two screens therefore disagree by construction; (c) excludes cash **refunds** (`FinanceTransaction type='refund'`), so the drawer is overstated by every refund ever paid; (d) `max(0, …)` renders a genuine shortfall as **₹0**.
- **Business impact** — The headline cash figure on the finance dashboard is not the cash in the drawer and cannot be reconciled to it. The `max(0,…)` clamp actively conceals the one condition a clinic owner most needs to see. Hard-coded `bank_balance = 0` sits beside it.
- **Severity** — **P0** (financial correctness). Silently wrong money on a headline tile.
- **Exact V1 fix** — Delete the tile's bespoke arithmetic and source it from **one** cash function shared with `cashbook()` (see **G-05**), scoped to the selected period, including cash advances **and** cash refunds. Remove the `max(0, …)` clamp and render negatives in red. Either wire `bank_balance` to `FinanceBankAccount` opening balance + `finance_bank_transactions`, or remove the tile — do not ship a hard-coded zero.
- **Dependencies** — Pairs with **G-05**; build the shared cash function once, use it in both. Do together.
- **Acceptance criteria**
  1. Dashboard "cash in hand" **equals** `/finance/cashbook` closing balance for the same period, to the rupee — asserted in a test.
  2. A cash advance increases it; a wallet-tender settlement does not.
  3. A cash refund decreases it.
  4. Cash out exceeding cash in renders a negative figure, not ₹0.
  5. `bank_balance` is either real or absent.

---

#### **G-05 · No cash drawer: no opening balance, no count, no reconciliation, no day-close**

- **Evidence** — `FinanceController::cashbook()` initialises `$balance = 0` at the start of the requested range and accumulates forward, so the "balance" column is range-relative, not the drawer. Cash out reads `finance_expenses` only — cash **refunds** are absent. The `finance_cashbook` table exists (model `App\Models\Finance\FinanceCashbook`) with **zero readers and zero writers** in `app/`; the only references are dummy-data seeders.
- **Current state** — The cashbook is a derived cash-in/cash-out listing. It is architecturally right (derived, not a parallel ledger) and correctly includes cash advances. But there is no opening balance, no way to record a **physical count**, no discrepancy figure, and no day-close — a back-dated cash entry can silently rewrite a day already counted and banked.
- **Business impact** — This is the Dr Systems *Cash Drawer* sheet, and it is the single most operationally important thing the spreadsheet does that Dentfluence does not. Without it a receptionist cannot hand over a till, and an owner cannot detect leakage. Cash is where clinics lose money.
- **Severity** — **P0** (financial correctness / fraud detection).
- **Exact V1 fix** — Give `finance_cashbook` a purpose: **one row per clinic day** holding `business_date` (unique), `opening_balance`, `counted_closing_balance`, `counted_by`, `counted_at`, `notes`. System closing = previous day's counted closing + period cash in − period cash out (advances and refunds included). Discrepancy = counted − system, displayed always, never clamped. Once a day has a counted close, refuse writes of cash-dated rows into it — reject with a clear message rather than silently accepting. One screen: **Close Day**. No opening-balance carry-forward magic beyond "yesterday's counted close".
- **Dependencies** — Shares the cash function with **G-04**. Independent of everything else.
- **Acceptance criteria**
  1. Closing a day with counted cash ≠ system cash records and displays the discrepancy with its sign.
  2. The next day's opening balance equals the previous day's **counted** closing.
  3. Attempting to record a cash payment or cash expense dated into a closed day is refused with a clear error.
  4. A cash advance and a cash refund both move the drawer; a wallet-tender settlement does not.
  5. Day-close is permission-gated and stamped with user + timestamp.

---

#### **G-06 · Two different profit definitions on two owner-facing screens**

- **Evidence** — `FinanceController.php:72`: `$periodProfit = $periodCollection - $periodExpense`, where `$periodExpense` sums **all** `finance_expenses` in range regardless of payment status. `AnalyticsController.php:488`: the same profit is computed with `->where('payment_status', 'paid')`. Both surfaces are labelled "profit".
- **Current state** — Finance dashboard and Business Intelligence report different profit and different margin % for identical date ranges. Separately, both mix bases: revenue is cash (collections), expenses are accrual (`expense_date`, regardless of payment) on one screen and cash-ish on the other.
- **Business impact** — Two profit numbers is worse than none — the owner cannot tell which is real, and margin % (which drives every pricing and cost decision) is not comparable between screens or across periods.
- **Severity** — **P0** (financial correctness).
- **Exact V1 fix** — Extend `ReportMetricsService` with `expenses(from, to, basis)` and `profit(from, to)` as the **single definition**, exactly as `collected()` did for revenue. Pick one basis and state it in the UI: recommend **cash basis both sides** (collections vs expenses actually paid) because it matches the cash-drawer reality a clinic runs on and matches `collected()`. Both screens call the service. Label every profit tile with its basis.
- **Dependencies** — None. Do immediately after **G-03**; same pattern, same file.
- **Acceptance criteria**
  1. Finance dashboard and Business Intelligence show **identical** profit and margin for the same range — asserted in a test.
  2. Every profit/margin figure in the UI carries a visible basis label.
  3. No profit arithmetic remains outside `ReportMetricsService`.

---

#### **G-07 · The four Audit #4 P0s — re-verified 21 Aug 2026, all still open**

- **Evidence** — verified in source this session:
  - **P0-1** `backup.sh` present at repo root; `grep backup routes/console.php` finds only a comment (line 413). No scheduled backup, no `app/Console/Commands/*Backup*`.
  - **P0-2** `config/assistant.php:19` → `filter_var(env('ASSISTANT_ENABLED', true), …)` — defaults **true**; local `.env:125` `ASSISTANT_ENABLED=true`.
  - **P0-3** `config/filesystems.php:62-67` — `consultation-photos` disk still `'visibility' => 'public'`.
  - **P0-4** `.env`: `LOG_STACK=single`, `MAIL_MAILER=log`, `APP_ENV=local`, `APP_DEBUG=true`. No error tracker, no alerting, no dead-man ping.
- **Current state** — Unchanged since 07 Aug. Not a regression; simply never executed.
- **Business impact** — No backups at a live clinic is unrecoverable data loss. Public patient photographs is a DPDP exposure. `MAIL_MAILER=log` means no email ever leaves the system. `APP_DEBUG=true` in production leaks stack traces.
- **Severity** — **P0**. These gate go-live regardless of anything else in this register.
- **Exact V1 fix** — As specified in Audit #4 (07-08-2026), tasks 1–51. Not restated here; **do not re-plan them**.
- **Dependencies** — None. Independent of every gap in this register.
- **Acceptance criteria** — Per Audit #4. Plus: production `.env` verified **on the VPS by a human**, since it is not readable from a remote session and is the one unverified surface.

---

#### **G-08 · Old void / delete path — carried, verdict E, not re-audited**

- **Evidence** — Void Path Audit, 19 Aug 2026 (`project_void_path_audit_0819`). Five entry points; the 3-dot **Delete** on an invoice issues refunds; patient cash is credited back as clinic-funded (non-refundable) credit. Standing CTO ruling 08-20: **🔧 FIX BEFORE SHIP**.
- **Current state** — Open. Deliberately not re-audited — the existing audit is complete and re-proving it would be exactly the drift this register exists to stop.
- **Business impact** — Can lose real money, and convert a patient's refundable cash into non-refundable clinic credit, in week one at Tulip.
- **Severity** — **P0**.
- **Exact V1 fix** — Per the 08-19 audit. Also fold in: `restoreInvoice()` and `restoreBill()` remain bare one-liners of the same bug class that `ReceiptRestoreService` fixed for receipts (recorded 08-18, deliberately untouched at the time).
- **Dependencies** — None.
- **Acceptance criteria** — Per the 08-19 audit, plus: restoring an invoice or bill undoes its full cascade in one transaction, creates no new financial record, and refuses rather than fabricating.

---

### P1 — Core clinic workflow missing or broken

---

#### **G-09 · The clinical record is free text — no procedure is linked to the catalogue**

- **Evidence** — `treatment_visit_items.treatment_name` is `string(150)` with **no `treatment_id`** (`2026_06_05_100001`, confirmed against all four later alters). `treatment_visits.procedure` and `.treatment_name` are likewise plain strings. By contrast `treatment_plan_items.treatment_id` **was** added (`2026_07_02_100002`) and `invoice_items.treatment_id` (`2026_07_02_100004`) is populated by `TreatmentPlanBillingService:129`. Per the 08-19 unification, a visit procedure arrives through exactly two doors — Treatment Plan (has a plan item, therefore reachable) or **+ Add Custom Treatment** (has none, therefore unreachable).
- **Current state** — `ReportsController::buildTreatmentData()` groups `txVisitsByProc` on `COALESCE(NULLIF(procedure,''), 'Not specified')`. "RCT", "R.C.T", "Root Canal" and "root canal" are four different procedures. Custom-treatment visits have no path to the catalogue at all.
- **Business impact** — Skill mix — the Dr Systems capability that tells a practice what it actually does all day — is unreliable by construction. It also caps **G-01** and **G-02**: any revenue not billed from a plan lands in Unclassified/Unattributed.
- **Severity** — **P1** (core workflow + reporting integrity). Not P0 only because it degrades gracefully rather than showing a wrong number.
- **Exact V1 fix** — Add nullable `treatment_id` to `treatment_visit_items`, populated from `treatment_plan_items.treatment_id` when billed from a plan. For custom treatments, require the user to pick a **category** (not a catalogue item — do not force catalogue writes, which the CEO deferred to V1.1 on 08-19) and store `treatment_category_id`. Report skill mix from `treatment_id` → catalogue name, falling back to category, falling back to free text — each tier labelled. **No backfill** of historical rows.
- **Dependencies** — Should follow **G-01** so the "Unclassified" bucket exists to receive the residue. Must respect the 08-19 CUSTOM PROCEDURE freeze — two doors, no third.
- **Acceptance criteria**
  1. A plan-billed visit item carries the plan item's `treatment_id`.
  2. A custom treatment requires a category before save.
  3. Skill mix groups on catalogue identity, not string equality; "RCT" typed twice is one row.
  4. Historical free-text rows still appear, under a visibly labelled tier.
  5. No change to `TreatmentVisitService` write semantics; the 08-05 UX freeze holds.

---

#### **G-10 · Chair time is captured and never reported**

- **Evidence** — `AppointmentService.php:369-371` writes `checked_in_at`, `in_chair_at`, `completed_at` on status transition; `chair_number` and `operatory_id` are written (`:344-345`, `:521`) and validated (`AppointmentController:256-257`). Repository-wide, the only reader is a quick-view card (`resources/views/appointments/index.blade.php:1245,1912`). No `TIMESTAMPDIFF` over these columns exists anywhere; `duration_minutes` is the *scheduled* value, never the actual.
- **Current state** — The data required for chair-time analytics has been accumulating correctly for months and nothing reads it.
- **Business impact** — No chair utilisation, no actual-vs-scheduled duration, no revenue-per-chair-hour, no answer to "which procedures are worth the chair time" — the productivity half of the Dr Systems benchmark.
- **Severity** — **P1**.
- **Exact V1 fix** — Add a **Productivity** section to the existing Reports page (do not build a new module). From `appointments`: chair minutes = `TIMESTAMPDIFF(MINUTE, in_chair_at, completed_at)` where both are non-null. Report utilisation per doctor and per chair/operatory, actual vs scheduled variance, and — once **G-02** lands — revenue per chair hour. Exclude rows with null timestamps from averages and **state the excluded count** rather than treating null as zero.
- **Dependencies** — Richer with **G-02** and **G-09**, but independently shippable and independently useful.
- **Acceptance criteria**
  1. An appointment moved through checkin → in_chair → done yields correct chair minutes.
  2. Appointments missing either timestamp are excluded from averages and their count is displayed.
  3. Utilisation reports by doctor and by chair for any date range.
  4. Actual-vs-scheduled variance is shown per doctor.

---

#### **G-11 · `last_visit_date` is advanced by consultations only — recalls fire for patients under active treatment**

- **Evidence** — `app/Observers/ConsultationClinicalWiringObserver.php:50-65` advances `patients.last_visit_date`, keyed on `Consultation` (registered `AppServiceProvider:70`). Registered observers are `LabCase`, `Consultation` ×2, `User` — **no `TreatmentVisit` observer**, and `TreatmentVisitService` never touches the column. The recall engine keys **entirely** on it (`RecallShadowRunner:59-60`, and the observer's own docblock says so).
- **Current state** — This is a **partial fix** of the previously recorded "`last_visit_date` has NO WRITER" defect (PRE Engine Audit, root cause of the 1,810-item backlog). A writer now exists; it covers the wrong half of the clinical workflow. A patient with one consultation followed by six treatment visits keeps `last_visit_date` = the consultation date.
- **Business impact** — Recall messages go to patients who were in the chair last week. Under the standing 1,810-item backlog this is not theoretical — it will misfire at volume in week one, in front of patients, over WhatsApp.
- **Severity** — **P1**. Patient-facing and reputational.
- **Exact V1 fix** — Add `TreatmentVisitObserver` mirroring the consultation one: on create/update, advance `patients.last_visit_date` to `visit_date` **only when later** (the existing never-rewind rule). Reuse the same private helper — do not write a second implementation. Register in `AppServiceProvider`. Update via `newQuery()->update()` so no model events fire, exactly as the consultation observer does.
- **Dependencies** — None. Must land **before** the recall backlog throttle is released, or it will fire the whole backlog on stale dates.
- **Acceptance criteria**
  1. Logging a treatment visit advances `last_visit_date` to the visit date.
  2. Back-dating a visit does **not** rewind it.
  3. A patient with a recent treatment visit is not a recall candidate.
  4. No new activity/timeline noise is generated by the update.

---

#### **G-12 · CA Export "Treatments" column is always blank**

- **Status** — ✅ **FIXED 2026-08-24.** Both call sites now go through one private helper, `FinanceController::invoiceTreatmentLabels()`, which resolves `treatment_id → treatments.name` and falls back to the line's own `description` (retail products, legacy rows) — never blank. `items.treatment` is eager-loaded on both export queries, so no N+1. The CSV income section had **no Treatments column at all**, so one was added to satisfy acceptance criterion 1. Covered by `tests/Feature/Finance/CaExportTreatmentsColumnTest.php` (xlsx + csv + the income export), which asserts a linked line and an unlinked line both render.

- **Evidence** — `FinanceController.php:476` (CSV) and `:1449` (Excel): `$p->invoice?->items?->pluck('treatment_name')->filter()->implode(', ')`. `invoice_items` has **no `treatment_name` column** — the create migration defines `description`, and `App\Models\InvoiceItem::$fillable` confirms `description`, `treatment_id`, `treatment_plan_item_id`, `inventory_item_id`. `pluck()` on a missing attribute yields nulls, `filter()` drops them, `implode()` returns `''`.
- **Current state** — Both export formats emit an empty Treatments column, silently, with no error.
- **Business impact** — The accountant handoff — the deliverable that makes the clinic's books auditable — loses what every rupee was **for**. The CA gets amounts with no descriptions.
- **Severity** — **P1**.
- **Exact V1 fix** — One-line change in both places: pluck `description`, or better, resolve `treatment_id → treatments.name` with `description` as fallback (eager-load `items.treatment` to avoid N+1). Add a test that asserts the column is non-empty for a seeded invoice — the absence of such a test is why this shipped.
- **Dependencies** — None. Smallest fix on the register; ~15 minutes.
- **Acceptance criteria**
  1. CA Export xlsx and csv both list treatment names for an invoice with items.
  2. A line with no `treatment_id` falls back to its description, never blank.
  3. A test asserts non-empty Treatments for a seeded invoice, in both formats.

---

#### **G-13 · Discount reporting misses line-item, plan-item and clinic-funded discounts**

- **Evidence** — `FinanceReportsController::discountData()` returns exactly two sources: `CouponUsage` and `Invoice` rows with `manual_discount_amount > 0`. Not counted: `invoice_items.disc_pct` / `disc_amount` (per-line discounts), `invoices.discount_pct` / `discount_amount` (the original invoice-level fields, distinct from the manual-discount block), `treatment_plan_items.disc_amount`, and **promotional wallet credit** — which the 08-15 architecture decision explicitly classifies as a clinic-funded *discount*, not a payment.
- **Current state** — The Discount report understates real discounting, potentially by a large margin, with no indication that it is partial.
- **Business impact** — Discount leakage is one of the two ways a dental practice quietly loses margin (the other is cash). An owner who trusts this tab will conclude discounting is under control when it may not be.
- **Severity** — **P1**.
- **Exact V1 fix** — Extend `discountData()` to a single **Total Concession** figure broken down by source: coupon · manual (header) · invoice-level · line-item · promotional wallet credit. Compute promotional credit consumption from the wallet ledger by debit date, using the existing `Wallet::availablePromotionalCredit()` replay — **a one-line `WHERE` is wrong here**, per the 08-16 finding. Show concession as a % of gross billed.
- **Dependencies** — None, but shares the pro-rata helper from **G-01** if reported per category.
- **Acceptance criteria**
  1. An invoice with a line-item discount appears in the Discount report.
  2. Promotional credit applied to an invoice appears as concession, **not** as collection.
  3. Total concession % of gross billed is displayed for the range.
  4. Each source is separately visible.

---

#### **G-14 · No treatment acceptance or conversion reporting**

- **Evidence** — No acceptance rate, conversion rate or conversion-reason metric exists in `ReportsController` or `FinanceReportsController`. `treatment_plans` is reported only as a raw status count (`txPlansByStatus`). `patients.source` is reported as a bare patient count (`patSource`), never joined to revenue. The Case Acceptance engine exists but is frozen and unreported; `plan_decisions` / `plan_decision_items` capture decision truth (Slice 2.3, "accepted → Committed") and feed no report.
- **Current state** — Missing. The data is largely present; nothing reads it.
- **Business impact** — Case acceptance is the primary growth lever in a dental practice — it is cheaper to convert an existing plan than to acquire a patient. Dr Systems tracks conversion *and its reason*. Dentfluence cannot tell the owner what proportion of presented treatment is accepted, or why plans are lost.
- **Severity** — **P1** for V1 sellability; the owner's core management question.
- **Exact V1 fix** — Add an **Acceptance** section to Reports, sourced from `plan_decisions` / `plan_decision_items` (the canonical decision record per Slice 2.3) — never from plan status, which is a lifecycle field with a single owner. Report: plans presented, plans accepted, acceptance rate by value and by count, and rate by lead source (`patients.source`). Reason-for-loss only if `plan_decisions` already carries a reason field; **do not add new capture UI** — that is V1.1.
- **Dependencies** — Read-only over Slice 2.3 (complete, 439 tests green). Must not write to `treatment_plans.status` — `PlanLifecycleService` is the sole status writer (TP Freeze F1–F5).
- **Acceptance criteria**
  1. Acceptance rate by count and by value for any date range.
  2. Acceptance rate broken down by lead source.
  3. Figures derive from `plan_decisions`, not from plan status.
  4. Zero writes to any plan lifecycle field.

---

#### **G-15 · No pending-treatment view — outstanding *money* exists, outstanding *work* does not**

- **Evidence** — Receivables, ageing, outstanding-after-wallet and collection tabs all exist. There is no counterpart for accepted-but-undelivered clinical work: no report over `treatment_plan_items.status = 'pending'` / `billing_progress`, and no aggregate of `treatment_plan_item_teeth.status`.
- **Current state** — Missing at the reporting level. `treatment_plan_items.billing_progress` (added `2026_07_02_100002`) and per-tooth status exist and are maintained.
- **Business impact** — Accepted-but-undelivered treatment is simultaneously the clinic's revenue backlog and its clinical obligation. Without it, work is remembered by staff rather than by the system — which is the explicit V1 disqualifier in the brief. It is also the highest-yield recall list a practice has.
- **Severity** — **P1**.
- **Exact V1 fix** — A **Pending Treatment** report: accepted plan items not yet delivered, with patient, tooth, treatment, value, days since acceptance, and last contact. Derive delivery from `treatment_plan_item_teeth.status` and `treatment_plan_items.status` — read-only, no new lifecycle writes. Surface the same list on the patient profile as "Pending Treatment" so it answers *what is pending* in the one-patient view.
- **Dependencies** — Follows the canonical lifecycle contract: **plan = promise, visit = fact**. Read-only.
- **Acceptance criteria**
  1. An accepted plan item with no completed visit appears, with value and age.
  2. Delivering the item removes it from the list.
  3. Total pending value reconciles to the sum of listed rows.
  4. The list appears on the patient profile and is exportable.

---

#### **G-16 · Appointment → Consultation glue still absent**

- **Evidence** — `grep -rl "Start Consultation" resources/views` returns **0 files**. Carried from Audit #4: no "Start Consultation" entry point anywhere in the views, and `appointment_id` is never passed through.
- **Current state** — Unchanged since 07 Aug. Staff must navigate away from the appointment and locate the patient to begin a consultation, and the resulting consultation is not linked to the appointment that produced it.
- **Business impact** — Duplicate navigation on the highest-frequency action of the clinic day, and a broken link in the canonical loop (appointment → consultation). The missing link also weakens **G-02**'s fallback attribution path.
- **Severity** — **P1** (workflow completeness + receptionist operability).
- **Exact V1 fix** — Per Audit #4's three glue links. Not re-specified here.
- **Dependencies** — None.
- **Acceptance criteria** — Per Audit #4, plus: the created consultation carries `appointment_id`.

---

### P2 — Major usability / product gap

---

#### **G-17 · Clinic data export is partial — no clinical or appointment export**

- **Evidence** — Export routes: `settings.data.export` (patients CSV, `admin.only`), `finance.income.export`, `finance.expenses.export`, `finance.ca-export`, `finance.vouchers.export`, `finance.wallet.register.export`, `marketing.calendar.export`. There is no treatment-history, consultation, prescription, appointment or whole-clinic export.
- **Current state** — Patient demographics and finance are portable. The clinical record — the part the clinic is legally and ethically obliged to retain and hand over — is not.
- **Business impact** — For Tulip: no independent copy of clinical history outside the database. For Dentfluence as a **sellable SaaS**: prospects and DPDP both require that a clinic can get its own data out. "Can I export my data?" is asked in most SaaS evaluations, and DPDP's data-portability duty lands before the May 2027 deadline.
- **Severity** — **P2** for the 14 Sep Tulip go-live; **P1 for sellability**.
- **Exact V1 fix** — One admin-only **Clinic Data Export**: patients, appointments, consultations, treatment plans + items, treatment visits + items, invoices + items + payments, prescriptions — as a dated multi-sheet workbook or a zip of CSVs. Reuse `PatientImportExportController`'s permission pattern and the existing PhpSpreadsheet writers. Queue it; do not build it synchronously.
- **Dependencies** — None. Do **after** the P0/P1 set unless a sales conversation forces it earlier.
- **Acceptance criteria**
  1. An admin can export the full clinical + financial record for a date range.
  2. Row counts in the export reconcile to the database for that range.
  3. Non-admins cannot reach the route.
  4. The export runs on the queue and does not time out at Tulip's data volume.

---

#### **G-18 · Dead finance schema — three tables with models and no live path**

- **Evidence** — `finance_income_entries` (read by one dead report, written by nothing), `finance_cashbook` (model `FinanceCashbook`, zero readers and writers), `finance_gst_records` (model `FinanceGstRecord`, referenced only by cleanup seeders). All three appear in `ClearAllDummyDataSeeder` / `ClearDummyFinanceSeeder`, so they look maintained.
- **Current state** — Live schema with no live code. Directly caused **G-01**: a report was pointed at one of them and nobody noticed it was dry.
- **Business impact** — Every future developer must re-derive which finance tables are real. The next G-01 is already latent.
- **Severity** — **P2** (maintainability, but it produced a P0).
- **Exact V1 fix** — Per gap: `finance_cashbook` is **repurposed** by **G-05** (do not drop it). `finance_income_entries` — remove the last read (**G-01**), then mark the model `@deprecated` with a comment naming its replacement. `finance_gst_records` — leave in place pending the **U1 GST decision**; add a docblock stating it is unwired. **Migrations never move** (Repo Cleanup Audit 08-03). No table is dropped before go-live.
- **Dependencies** — **G-01**, **G-05**, and the U1 CEO decision.
- **Acceptance criteria**
  1. Every finance model with no live read/write path carries a docblock saying so and naming its status.
  2. No report reads a table with zero writers — enforced by a one-off check, documented in the register.

---

#### **G-19 · Services instantiated with `new` instead of `app()` in the payment path**

- **Evidence** — `app/Services/Billing/InvoicePaymentService.php:161` and `:276` — `(new WalletService())->receiveAdvance(...)`; also `BillingController.php:1002,1117` and `Api/V1/BillingController.php:1139`.
- **Current state** — Contradicts the rule recorded on 2026-08-18: *always resolve services via `app()`, never `new` — otherwise rollback paths are unmockable and therefore untested.*
- **Business impact** — The surplus-to-advance rollback path in the payment flow cannot be mocked, so it is not covered. `PatientPaymentAllocationService:258` already does this correctly via `app(WalletService::class)`.
- **Severity** — **P2** (test coverage of a P0-adjacent path).
- **Exact V1 fix** — Replace the five `new WalletService()` call sites with `app(WalletService::class)`. Add one test asserting advance creation rolls back when the enclosing transaction fails.
- **Dependencies** — None. Trivial; bundle with **G-12**.
- **Acceptance criteria**
  1. No `new WalletService()` remains in `app/`.
  2. A test proves the advance rollback path.

---

#### **G-20 · `module:finance` is a view gate applied to write routes**

- **Evidence** — `routes/web.php:940` — `Route::middleware('module:finance')->prefix('finance')` wraps the entire group, including ~26 write routes (`expenses.store/update/destroy`, `vendors.*`, `payroll.*`, `banking.*`, `income.trash.*.restore`). Carried from the Billing Master Audit (08-15).
- **Current state** — Open. Anyone who can *view* finance can *write* finance, including restoring voided financial documents.
- **Business impact** — At a clinic where reception may legitimately need to view collections, view permission implies the ability to delete expenses and restore voided receipts. This is the authorisation shape that makes internal fraud possible.
- **Severity** — **P2** for single-owner Tulip (small trusted staff); **P0 for any clinic with separated roles** — i.e. for sale.
- **Exact V1 fix** — Apply `module:finance,edit` to write verbs, matching the pattern already used at `routes/web.php:383-384` for patient import. Do not invent a new gate.
- **Dependencies** — Owner-configured roles are canonical — **never hardcode job titles** (Slice 1.1, constitutional).
- **Acceptance criteria**
  1. A finance-view-only user gets 403 on every finance write route.
  2. A finance-edit user is unaffected.
  3. Restore routes require edit.

---

### P3 — Polish / future differentiation

---

#### **G-21 · `clinic_id` tenant isolation**
`clinic_id` appears in 75 migration references but in essentially no model or query path (`grep` finds it in one model, `KbTopic`); `users` has `branch_id` only. **Not a Tulip go-live blocker — Tulip is one clinic.** It is an absolute blocker for clinic #2 and therefore for the SaaS thesis. Carried from Production Readiness Review and the Encryption/Access parked note. **P3 for 14 Sep; P0 for the second customer.** Do not start before go-live.

#### **G-22 · `bank_balance` hard-coded to 0**
`FinanceController.php:93`. Folded into **G-04** — either wire it to `FinanceBankAccount` + `finance_bank_transactions`, or remove the tile.

#### **G-23 · Web ↔ Android parity — UNVERIFIED this session**
The Flutter application is not present in the connected folder (`E:\Dentfluence\Dentfluence_OS\` contains only `Dentfluence Web`), so parity was **not** re-verified and no claim is made here. Last recorded position: ~50% parity (Web↔Mobile Parity Audit 07-14); mobile completion sprint code-complete but untested. Per the CEO Implementation Directive the locked sequence is Fix → Freeze → Implement → Stabilize → **Android** → V1.1, placing Android *after* stabilisation. **Recommendation: Tulip goes live on web on 14 Sep.** If any Tulip workflow is expected to run on Android on day one, say so now — that changes the plan materially and needs its own scoped pass. Note that **G-03** (Huddle) and **G-06** (profit) both have mobile API surfaces that must call the same shared service, or the divergence simply reappears on the phone.

---

## 4. P0 / P1 V1 BLOCKERS — THE FROZEN LIST

| ID | Gap | P | Est. | Blocks go-live? |
|---|---|---|---|---|
| G-07 | Four Audit #4 P0s (backup, assistant, photo disk, logging) | P0 | 3–4 d | **YES — absolutely** |
| G-08 | Old void/delete path (verdict E) | P0 | 2 d | **YES** |
| G-03 ✅ | Huddle collections ≠ Reports collections — **FIXED 24-Aug** | P0 | 2 h | **YES** |
| G-06 | Two profit definitions | P0 | 4 h | **YES** |
| G-04 | Cash in hand all-time / clamped | P0 | 1 d | **YES** |
| G-05 | Cash drawer: opening, count, reconciliation, day-close | P0 | 2 d | **YES** |
| G-01 | Revenue by category reads a dead table | P0 | 1.5 d | **YES** |
| G-02 | Doctor-wise collection structurally dead | P0 | 1.5 d | **YES** |
| G-12 ✅ | CA Export Treatments blank — **FIXED 24-Aug** | P1 | 15 m | Yes — trivial |
| G-11 | `last_visit_date` not advanced by visits | P1 | 3 h | **YES** — patient-facing |
| G-16 | Appointment → Consultation glue | P1 | 4 h | Yes |
| G-15 | Pending Treatment report | P1 | 1 d | Yes |
| G-09 | Procedure not linked to catalogue | P1 | 1.5 d | Yes |
| G-13 | Discount reporting incomplete | P1 | 1 d | Yes |
| G-10 | Chair time captured, never reported | P1 | 1 d | Borderline |
| G-14 | Acceptance / conversion reporting | P1 | 1 d | Borderline |

**P0 total ≈ 9–11 working days. P0 + P1 ≈ 16–18 working days.** Against 24 calendar days (~17 working days) this fits **only** if the list is frozen and nothing is added. G-10 and G-14 are the designated drop candidates if the schedule tightens — they are the two P1s a clinic can survive one month without.

---

## 5. ALREADY SOLVED — DO NOT TOUCH

Verified working this session. Re-auditing these is drift.

- **`ReportMetricsService`** — one definition of collected / outstanding / appointments-done, shared web ↔ mobile. The model for fixing G-06.
- **Payment allocation** — FIFO oldest-first across open invoices, surplus → patient credit, `PAY-` receipt series.
- **Advance = liability, not income** — `receiveAdvance()`, `funding` (patient|clinic) as the discriminator; advances correctly excluded from collections and correctly included in the cashbook.
- **Patient credit as tender** — `settleInvoiceFromCredit()`: real `InvoicePayment` with `payment_mode='wallet'`, receipt, revenue recognised, no cash moved. Deliberate and correct.
- **Wallet refunds** — all-or-nothing, patient-funded only, partial requests rejected (A1).
- **Payment reversal** — void ≠ refund; `invoice_payments.receipt_id`, `receipts.voided_at`, corrections stay visible (A2). Voided payments are soft-deleted and therefore correctly excluded from `collected()`.
- **Receipt restore** — `ReceiptRestoreService` undoes the full cascade in one transaction; refuses rather than fabricating.
- **Expense auto-feeds** — lab (`LabExpenseService`, idempotent via `lab_cases.expense_id`), procurement (`VendorInvoiceService:164`), inventory (`InventoryService:737`). **Stronger than Dr Systems**, which requires manual expense entry.
- **Cashbook architecture** — derived from canonical tables, no parallel ledger, advances deliberately included and wallet tenders deliberately excluded. G-05 extends this; it does not replace it.
- **Expense CRUD + bill-photo scan**, expense categories as enforced master data.
- **Inventory** — frozen V1, append-only `stock_movements` ledger.
- **Lab module** — cases, vendors, price lists, costs, monthly reconciliation, auto-expense.
- **Patients module** — frozen V1.1; `PatientService::register()` is the sole mint. Import **and** export both exist.
- **Treatment plan lifecycle** — `PlanLifecycleService` sole status writer; F1–F5 frozen; 16 canonical tests.
- **Treatment catalogue + categories** — full CRUD master data; `treatments.unit_basis`; `treatment_options` pricing.
- **Consultations** — S1–S11 complete, 44 tests green.
- **Treatment Visit UX** — **frozen 08-05.** Refuse UX changes without a Tulip-validated issue.
- **Finance reports breadth** — 12 tabs (income, expense, receivables, payables, membership, wallet, coupon, discount, advance, liability, collection, provider) with xlsx/csv export on each. Breadth is not the problem; three specific read paths are.
- **Appointment status model** — `scheduled → checkin → in_chair → checkout → done`, plus cancelled / no_show, with timestamps and cancel-party capture. The data is right; only the reporting is missing (G-10).

---

## 6. POST-V1 / OUT OF SCOPE — object if raised before 14 Sep

- `clinic_id` tenant isolation (**G-21**) — required for clinic #2, not for Tulip.
- U2–U7, U9, G2–G6 from the billing model; unified rules engine — **📦 V1.1**, ruled 08-20.
- Internal Comm Engine — **📦 V1.1**, deferred 08-19.
- "+ Add New Procedure" catalogue-write from the visit screen — deferred to V1.1 by the CEO on 08-19. **G-09 must not reopen this**; it adds a category, not a catalogue write.
- Reason-for-loss *capture UI* — V1.1. G-14 reports only what `plan_decisions` already holds.
- AI Assistant / Tulip copilot, Voice Notes, Receipt Scan (OCR) — halted by CEO #004.
- Marketing: GBP + Meta connect — parked to V1.5.
- Benchmarks / peer comparison — Dr Systems has it; V1 does not need it. Monthly revenue target already exists.
- Lab price-list OCR — reverted; **do not re-propose**.
- Anniversary tracking — **do not re-propose**.
- ABDM, DPDP consent module — design only, flags off. DPDP deadline 13 May 2027.
- Android parity work (**G-23**) — after Stabilize per the locked sequence.

---

## 7. RECOMMENDED BUILD ORDER

Sequenced so each slice ships independently and the shared pieces get built once, before their dependants.

**Week 1 (22–28 Aug) — Stop the bleeding, then earn trust back**
1. **G-03** Huddle → `ReportMetricsService` *(2 h — smallest P0, do it first)*
2. **G-12** CA Export treatment names *(15 m)* + **G-19** `app()` resolution *(15 m)*
3. **G-06** One profit definition in `ReportMetricsService` *(4 h)*
4. **G-11** `TreatmentVisitObserver` → `last_visit_date` *(3 h — before any recall release)*
5. **G-07** Audit #4 P0s: backup, assistant flag, photo disk, logging/alerting *(3–4 d — run in parallel; largely ops, not code)*

**Week 2 (29 Aug – 4 Sep) — Make the money screens true**
6. **Shared pro-rata payment-allocation helper** *(0.5 d — the single dependency under G-01, G-02, G-13)*
7. **G-01** Category revenue from `invoice_items.treatment_id` *(1 d on top of the helper)*
8. **G-02** Doctor attribution via `performed_by_user_id` *(1.5 d)*
9. **G-04 + G-05** Shared cash function, then opening balance / count / discrepancy / day-close *(3 d — build together, they are one problem)*

**Week 3 (5–11 Sep) — Close the operational loop**
10. **G-08** Old void/delete path *(2 d)*
11. **G-15** Pending Treatment report + patient-profile section *(1 d)*
12. **G-16** Appointment → Consultation glue *(4 h)*
13. **G-13** Complete concession reporting *(1 d)*
14. **G-09** `treatment_id` / category on visit items *(1.5 d)*

**Week 4 (12–14 Sep) — Cutover, not code**
15. **G-20** `module:finance,edit` on write routes *(1 h)*
16. Data migration, staff accounts, training, backup restore drill, cutover rehearsal — **no new features**
17. **G-10** chair-time and **G-14** acceptance reporting **only if** weeks 1–3 finished early. Otherwise they are the first V1.1 items.

**Deferred to V1.1, already decided:** G-17 (clinic data export — unless a sale demands it sooner), G-18 (dead-schema documentation, after G-01 and G-05 land), G-21 (`clinic_id`), G-23 (Android).

---

## 7A. APPENDED FINDINGS

*Per standing rule 2: new findings get the next ID and a dated note. They do not trigger a new audit.*

#### **G-32 · Two more surfaces still carry their own "collections" definition** *(appended 2026-08-24, during G-03)*

- **Evidence** — `app/Modules/Huddle/Controllers/HuddleController.php:867-897` (the **web** Huddle KPI panel — collections, refunds, by-mode breakdown and both previous-window trend figures) and `app/Services/Assistant/Tools/KpiReportTool.php:86-91` (the AI assistant's `collections` metric) both still sum `finance_transactions`. Found while fixing G-03; the register's G-03 row names only the two files under `app/Services/Huddle/`.
- **Current state** — G-03 made the briefing and the mobile board agree with Reports. These two did not move, so the web Huddle KPI panel is now the sole surface that disagrees — a cleaner statement of the same defect, but still live on a screen the owner reads daily.
- **Why it was not folded into G-03** — it is not a like-for-like swap. These surfaces need **net** collections (income − refunds), a **by-payment-mode** breakdown and a **previous-window** comparison; `ReportMetricsService` exposes none of the three. Doing it properly means extending the service — the same shape of work as G-06 — not a two-line edit, and it would have blown the 2 h estimate this row was approved against.
- **Severity** — **P1** (trust, same class as G-03). Not a blocker on its own.
- **Proposed V1 fix** — Extend `ReportMetricsService` with `refunds()`, `collectedNet()` and `collectionsByMode()`, then repoint both surfaces. Natural bundle with **G-06**, which is already scheduled to extend the same service.
- **Acceptance criteria**
  1. Web Huddle KPI panel, mobile board, briefing and Reports show the same collections figure for the same range.
  2. The assistant's `collections` metric matches Reports for the same range.
  3. No collections arithmetic remains outside `ReportMetricsService`.
- **Status** — ⛔ **AWAITING CEO APPROVAL.** Not started. Do not begin without a decision on whether it rides with G-06.

---

## 8. STANDING RULES FOR THIS REGISTER

1. **This register is frozen.** Fix one gap, update its row, move on. Do not re-audit the product.
2. **New findings are appended, never merged into a new audit.** A new gap gets the next ID and a dated note. If the impulse is "let's re-run the audit", the answer is no.
3. **Every fix carries its acceptance criteria into a test.** G-12 shipped blank for months because no test asserted the column was populated.
4. **No fix may write to a lifecycle field owned by another service.** `PlanLifecycleService` owns plan status; `PatientService::register()` mints patients; `stock_movements` is append-only.
5. **The Treatment Visit UX is frozen** (08-05). No change without a Tulip-validated issue.
6. **One CEO decision is outstanding and owed: U1 — GST pre- or post-concession.** One sentence. Live compliance exposure since 15 Aug. It gates `finance_gst_records` (G-18) and nothing else.
7. **The named risk remains audit-begets-audit drift.** Between 07 and 20 August, four defensible audits consumed the entire go-live window. **24 days remain.** The correct response to any new idea before 14 September is the 5-question Implementation Check, and — by standing instruction — my objection.

---

*Delta audit conducted 21 August 2026 against `E:\Dentfluence\Dentfluence_OS\Dentfluence Web`. Reconciled against Audit #4 (07-08), Billing Master Audit (15-08), Void Path Audit (19-08) and V1 Position Report (20-08). Carried-forward items are marked as such and were deliberately not re-proved.*
