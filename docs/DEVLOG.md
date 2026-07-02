# Dentfluence — Build Progress Tracker
> **Last full audit:** 2026-06-11 (scanned all controllers, models, routes, views from disk)
> Update this file at the start/end of every session.

---

## 📊 APP STATS (as of 2026-06-11)
- **159 migrations** run (or pending)
- **120+ models** across 8 namespaces
- **55 controllers**
- **16 services**
- **27 seeders**
- **Estimate: ~65% of a shippable clinic management system**

---

## ✅ FULLY BUILT — Controller + Model + Routes + Views all wired to real DB

### 🔐 Auth & Core
- [x] **Auth** — Login / Logout
- [x] **Dashboard** — Stat cards, appointment summary, alerts

### 🦷 Patients
- [x] **Patient CRUD** — Create, view, edit, delete, print
- [x] **Patient Notes** — Add / delete, note_type (bug B2 pending fix)
- [x] **Patient Documents** — Upload / delete (category filter bug B3 pending)
- [x] **Patient Tags** — Attach / detach
- [x] **Relationship Notes** — Add / delete (B1 dead code bug pending)
- [x] **Treatment Opportunities** — Add / update / delete per patient
- [x] **Patient Communications Log** — `PatientCommunicationController` — call/WhatsApp/email/SMS log per patient, scheduled + sent entries, Alpine.js tab in patient profile
- [x] **Patient Import / Export** — CSV upload → preview → confirm → DB; 3 template downloads

### 📅 Appointments
- [x] **Appointment Calendar / List** — Full CRUD
- [x] **Today's Queue** — Status view
- [x] **Conflict Check** — AJAX endpoint
- [x] **Status Updates** — arrived / in-chair / done / etc.

### 🩺 Clinical
- [x] **Consultations** — Full 7-type consultation engine (new_patient, followup, same_issue, second_opinion, emergency, specialist_referral, coha)
- [x] **Consult Assist Panel** — AJAX: chief complaint → matched specialties from `treatment_knowledge`; Alpine chip accept/reject
- [x] **Dynamic Specialty Modules** — inline panels for ortho, perio, endo, smile_design, prostho; `packModules()` on submit
- [x] **COHA Mode** — 9-section structured oral health assessment form + printable patient-facing awareness report
- [x] **Follow-up Context Loading** — past consultation selector, read-only context panel, `previous_consultation_id` FK saved
- [x] **Consultation → Treatment Plan Handoff** — "Save & Start Treatment Plan →" button; `createFromConsultation` route pre-fills plan
- [x] **Treatment Plans** — CRUD + AI suggest stub; `treatment_plan_items` table (not JSON blob)
- [x] **Treatment Visits** — CRUD + print; FDI tooth chart picker; stage carry-forward; billing prompt trigger on save
- [x] **Treatment Stages** — defined per-treatment in Treatment module "Stages" tab; drives visit form dynamically

### 💊 Prescriptions
- [x] **Prescription CRUD** — Full CRUD + finalize / repeat / cancel
- [x] **CDSS Alert Engine** — `PrescriptionAlertService`: allergy check, duplicate molecule, drug-drug interactions, dose cap, antibiotic stewardship
- [x] **Live Drug Typeahead** — AJAX `/api/rx/drugs/search`
- [x] **Rx Write-Pad UI** — Alpine.js M/A/N dose grid, auto-qty calculator, CDSS alert panel + override modal
- [x] **Prescription Settings** — Drug master, generics, categories, dose/duration templates, routes, food instructions, warning rules, Rx templates
- [x] **Rx Show** — Audit log + override log

### 🏭 Inventory
- [x] **Product Master** — Full CRUD with sub-types + variants (variant AJAX endpoints)
- [x] **Inventory Sub-Types & Variants** — `InventorySubType` + `InventoryVariant` models; AJAX store/update/delete
- [x] **Stock Dashboard** — Current qty view
- [x] **Stock In / Out** — Movements ledger
- [x] **Purchase Orders** — Create + GRN receive
- [x] **GRN (Goods Receipt Notes)** — Dedicated `goods_receipt_notes` + `grn_items` tables alongside StockMovement ledger; tied to PO
- [x] **Inventory Vendors** — Add / edit; auto-sync to FinanceVendor on save (`syncToFinance()`)
- [x] **Vendor Invoices** — `VendorInvoiceController` CRUD; auto-creates AP `FinanceExpense` on save
- [x] **Implant Registry** — Catalog + placements
- [x] **Expiry Tracker**
- [x] **Reusable Assets**

### 🔬 Lab
- [x] **Lab Cases v2** — Full rebuild: statuses (draft→sent→in_progress→ready→received→delivered→closed), auto case numbers, append-only event timeline, soft deletes
- [x] **Lab Case Items** — Line items per case
- [x] **Lab Case Attachments** — File attachments per case
- [x] **Lab Case Events** — Immutable timeline log
- [x] **Lab Vendors** — Full CRUD; auto-sync to FinanceVendor; `LabVendorController` rewrite
- [x] **Lab Vendor Contacts** — Multiple contacts per lab vendor (CRUD)
- [x] **Lab Vendor Services** — Service catalog + default rates per lab (CRUD)
- [x] **Lab Alert Service** — `LabAlertService`: due today/tomorrow, overdue, urgent, awaiting delivery, stale 15+ days
- [x] **Lab Expense Auto-Create** — `LabExpenseService`: auto expense on received/paid; `expense_id` duplicate guard; auto-creates "lab-charges" category + FinanceVendor link
- [x] **Lab Monthly Reconciliation** — `LabReconciliationController`: full workflow draft→pending_review→approved/disputed; per-line item matching; creates Finance AP on approve; views: index, create, show

### 💰 Billing
- [x] **Invoice Builder** — `BillingController`: createFromPrompt, dismissPrompt, store with wallet/coupon/membership layers
- [x] **Billing Prompts** — Auto-created on visit save; front desk Build Invoice / Dismiss flow
- [x] **Treatment Visit Items** — Doctor selects plan items + custom items on visit; `TreatmentVisitItem` records created
- [x] **Payments + Receipts** — `recordPayment()` auto-creates `Receipt` per payment (before/after balance snapshot)
- [x] **Final Bill** — Auto-generated when invoice fully paid; printable A5 receipt + full settlement doc
- [x] **AOCP Membership** — `FinanceMembershipPlan` + `FinancePatientMembership`; `MembershipBenefitService` (free item matching + % discount); patient billing tab enrollment modal
- [x] **Wallet Engine** — `WalletService`: credit/debit FIFO (promo first → permanent), refund, summary; debited on invoice store
- [x] **Coupon Engine** — `CouponService`: validate, apply, resolveFromRequest; AJAX validator on billing form

### 🏦 Finance
- [x] **Finance Dashboard** — `finance/dashboard.blade.php`
- [x] **Income** — Real `invoice_payments` data; PDF export
- [x] **Expenses** — Full CRUD + form; EMI support; `FinanceExpenseCategory` CRUD; PDF export
- [x] **Finance Vendors** — `FinanceVendor` CRUD + form; type enum (dental_supplier, lab, rent, salary, electricity, water, internet, amc, office_supplies, lawyer, miscellaneous)
- [x] **Payroll** — Inline CRUD; `FinancePayroll` model
- [x] **Cashbook** — Daily cash in/out aggregates
- [x] **Banking** — Bank accounts list; `FinanceBankAccount` model
- [x] **GST** — Invoice items with `gst_pct > 0`
- [x] **CA Export** — CSV download of income/expenses
- [x] **Membership Plans** — `MembershipController` admin CRUD; `finance/membership/index + form`
- [x] **Coupons** — `Finance/CouponController` CRUD; `finance/coupons/index + form`
- [x] **Wallets** — `Finance/WalletController`; per-patient ledger; add-credit form; credit note print
- [x] **Wallet Campaigns** — `WalletCampaignController` CRUD; bulk wallet credit to patient segment (by tag / area / treatment / source); views: index, create, show
- [x] **Vouchers** — `VoucherController`: index, show, print, export (XLSX); auto-created on expense mark-paid
- [x] **Finance Mirror** — Every invoice payment creates `FinanceTransaction`; bidirectional with billing
- [x] **EMI System** — `EmiProvider` + `EmiScheme` + `EmiSchedule` models; Settings routes for provider/scheme CRUD; EMI fields on expenses; receipt tracking

### 📢 Communication
- [x] **Follow-Up Engine** — `FollowUp` + `FollowUpNote`; `FollowUpRulesService`; queue/overdue/calendar views; complete/reschedule/schedule/note/status/convert actions
- [x] **PRM / Leads Board** — `Lead` + `LeadActivity`; Kanban drag-drop persists to DB; lead detail, add/edit, log activity, convert to patient
- [x] **Huddle** — Real Eloquent queries: `buildCounts()`, `buildOverdueItems()`, `buildAlerts()`; boards, cards, task logs, comments, settings
- [x] **Manager Queue** — `CommunicationQueue` model; execution queue, overdue view, log communication POST
- [x] **Tasks** — `TaskController` + `Task` model
- [x] **Message Templates** — `TemplateController` + `MessageTemplate` model
- [x] **Timeline** — `TimelineController` + `TimelineService`; patient communication timeline

### 📚 Content Management
- [x] **Clinical Library** — CRUD + media upload + watermark
- [x] **Education Content** — Categories + items + media
- [x] **CMS / Marketing Content** — `CmsController`; tagging, consent, marketing flags; `CmsMediaController`
- [x] **Treatment Visit Content** — Content per visit type
- [x] **CMS Search** — `CmsSearchService` + `CmsSearchController`

### ⚙️ Settings
- [x] **General / App Settings** — `AppSetting` model
- [x] **Masters** — Treatment categories, treatments (Stages tab, SOP tab, Rules tab, Intelligence tab, Media tab)
- [x] **Treatment Intelligence** — `treatment_knowledge` table; keyword → specialty suggestion seeder (`TreatmentKnowledgeSeeder`)
- [x] **Roles & Permissions** — `Role` + `RoleModulePermission`; `RolePermissionController`
- [x] **Tags Management** — `Tag` model; CRUD
- [x] **EMI Providers & Schemes** — Settings routes wired; `EmiProvider` + `EmiScheme` CRUD

---

## ⚠️ BUILT BUT NOT WIRED TO ROUTES (controller + views exist, routes missing or stub)

| Module | Controller | Views | What's missing |
|---|---|---|---|
| **Finance Analytics** | `Finance/AnalyticsController` (8 methods) | `finance/analytics/` (8 views) | Route is `fn() => 'Coming soon'` — add real routes |
| **Opportunity Engine** | `Communication/OpportunityController` (stub — placeholders only) | `communication/opportunities/` | Routes defined but controller methods are empty placeholders |
| **CRM** | `CRMController` (stub) | `crm/` | Route `Coming soon`; no real logic |

---

## 🔧 PARTIALLY BUILT (exists but incomplete)

### Reports
- [x] Basic index + tab switcher
- [x] Appointment reports — KPIs, daily trend, status breakdown, by category, by doctor, heatmap
- [x] Revenue reports — Collections KPIs, daily chart, payment mode doughnut, top 10 patients, outstanding table
- [ ] Patient reports — not built
- [ ] Treatment reports — not built
- [ ] Lab reports — not built
- [ ] Inventory reports — not built

### Consultation Rebuild (P2C) — Partially complete
- [x] P2C3 — Consult Assist Panel (rules engine AJAX suggest)
- [x] P2C4 — Dynamic Specialty Modules (5 inline panels)
- [x] P2C7 — COHA Mode (form + print view)
- [x] P2C9 — Follow-up context loading
- [x] P2C10 — Consultation → Treatment Plan handoff
- [x] P2C11 — Legacy cleanup (fillable updated; drop-columns migration created but NOT RUN yet)
- [ ] P2C1 — Consultation Type System UI (7-type selector, type-aware form shell) — **pending**
- [ ] P2C2 — DB Schema migrations (5 new migrations) — **`php artisan migrate` required after this**
- [ ] P2C5 — HOPI & Findings Summary auto-draft services — **pending**
- [ ] P2C6 — Diagnosis section rebuild (3-stage) — **pending**
- [ ] P2C8 — Treatment Intelligence tab (Treatment module) — **pending**

### Patient Profile Tab Audit (P1–P8) — Not started
All patient profile tabs need manual browser testing. See Known Bugs below.

### Prescription — Missing pieces
- [x] Full CRUD, CDSS, write-pad UI, all settings
- [ ] Print / PDF view — not built
- [ ] WhatsApp send integration — not built

---

## ❌ NOT BUILT

| Module | Notes |
|---|---|
| **Analytics (Finance)** | `AnalyticsController` + 8 views built — routes not wired |
| **Notifications** | No system. Route is `Coming soon`. |
| **User Profile** | No edit profile page. Route is `Coming soon`. |
| **Help / Docs** | Route is `Coming soon`. |
| **Prescription PDF** | No print view |
| **WhatsApp Integration** | Referenced in several modules, nothing built |
| **Opportunity Engine (real DB)** | Controller is a stub; views are empty placeholders |

---

## 🐛 KNOWN BUGS (confirmed from code audit)

| # | Bug | Location | Severity |
|---|-----|----------|----------|
| B1 | `addRelationshipNote()` is dead code — inside unclosed `/*` comment block | `PatientProfileService.php` | 🔴 High |
| B2 | `noteType` never sent to API — all notes save as generic type regardless of UI selection | Notes tab Alpine.js | 🔴 High |
| B3 | Document category filter pills have no filtering logic (no `x-show` wired to them) | Documents tab | 🟡 Medium |
| B4 | Header stat cards use different formula than Billing tab (model cached fields vs live invoice records) | Patient profile header | 🟡 Medium |
| B5 | No delete button on document cards | Documents tab | 🟡 Medium |
| B6 | Consultation tab sidebar quick-action buttons are unwired static HTML | Consultation show | 🟠 Low |
| B7 | Finance Analytics routes not wired — `AnalyticsController` built but `analytics.index` is `Coming soon` | `routes/web.php:314` | 🟡 Medium |
| B8 | `drop_legacy_tx_columns_from_consultations` migration created but NOT RUN — run only after verifying historical consultation data | `database/migrations/` | ⚠️ Hold |

---

## 🧭 SUGGESTED BUILD ORDER (next sessions)

### Quick wins (already built, just needs wiring)
1. **Wire Finance Analytics routes** — `AnalyticsController` + 8 views done, just needs routes (~30 min)

### Bug fixes
2. **Fix B1** — Restore `addRelationshipNote()` from inside dead comment block
3. **Fix B2** — Send `noteType` in Alpine `saveNote()` call
4. **Fix B3 + B5** — Document tab: add `x-show` filter logic + delete button with confirm

### Consultation completion
5. **P2C1 + P2C2** — Type selector UI + 5 DB migrations
6. **P2C5 + P2C6** — HOPI auto-draft + Diagnosis 3-stage rebuild

### New features
7. **Prescription PDF print view**
8. **Patient profile tabs manual test** (P3 → P4 → P5 → P6 → P7 → P8 in order)
9. **Reports** — Patient + Treatment + Lab + Inventory tabs
10. **Notifications system**
11. **User Profile page**

---

## 📋 SESSION LOG

### 🗂 Pre-DEVLOG Build History (~May 2026)

| Approx. Date | What was built |
|---|---|
| ~May 2026 | Project setup — vision doc, design system (purple/lavender, DM Sans + Cormorant Garamond, Lucide icons), 22-module map, role model. Tech: Laravel + MySQL + Blade + Alpine.js + Tailwind |
| ~May 2026 | Auth + Dashboard — login/logout, dashboard stat cards |
| ~May 2026 | Patients — full CRUD, notes, docs, tags, relationship notes, opportunities, `PatientProfileService` |
| ~May 2026 | Appointments — full CRUD, calendar, queue, conflict check, status updates |
| ~May 2026 | Consultations (v1) — 12-section form (chief complaint, findings, tooth chart, diagnosis, treatment plan, rx, lab, referral, instructions, follow-up, attachments, summary) + print view |
| ~May 2026 | Treatment Plans — CRUD, per-row `treatment_plan_items`, AI suggest stub |
| ~May 2026 | Treatment Visits — CRUD + print, smart clinical fields per type, stage tracker sidebar |
| ~May 2026 | Lab Cases (v1) — basic CRUD, `/lab` index with filter tabs + search |
| ~May 2026 | Inventory — product master, stock, movements, POs + GRN, vendors, implants, expiry, reusable assets |
| ~May 2026 | Settings — general/billing config, masters (categories/treatments), roles & permissions, tags |
| ~May 2026 | Content Management — clinical library, education, CMS/marketing, visit content |
| ~May 2026 | Codebase cleanup — removed dupes, fixed route mismatches, wired missing route files |
| ~May 2026 | Huddle — daily huddle: role views, checklist, boards, cards, task logs, comments, settings |

### 📋 Active Session Log

| Date | What was done |
|---|---|
| 2026-06-04 | Created DEVLOG.md — deep audit revealed Communication module was all dummy data |
| 2026-06-04 | Phase 1.1 — Follow-Up Engine fully wired to DB |
| 2026-06-04 | Phase 1.2 — PRM/Leads wired to DB (`leads` + `lead_activities`) |
| 2026-06-04 | Phase 1.3 — Huddle wired to real Eloquent queries |
| 2026-06-04 | Phase 1.4 — Manager Queue wired to DB. Phase 1 complete. |
| 2026-06-05 | Billing & Finance full concept + architecture finalized (`financedevlog.md`) |
| 2026-06-05 | F1 — DB Foundation (9 migrations, 8 models) |
| 2026-06-05 | F2 — Doctor side: visit items + billing prompt trigger |
| 2026-06-05 | F3a — Invoice builder (front desk flow) |
| 2026-06-05 | F3b — Payments + Receipts + Final Bill |
| 2026-06-05 | F4a — AOCP Membership module |
| 2026-06-05 | F4b — Wallet + Coupon engine |
| 2026-06-05 | F5 — Finance mirror + accounts module + revenue reports. Phase 2 complete. |
| 2026-06-05 | Visit tab UX fixes — status enum fix, FDI tooth chart picker, stage carry-forward, implant stages updated |
| 2026-06-05 | Treatment Stages — `stages` JSON column on treatments; stages driven from Treatment module "Stages" tab |
| 2026-06-05 | Patient Module full audit — P1–P8 phases defined; 6 confirmed bugs (B1–B6) |
| 2026-06-05 | Patient Import/Export wired — CSV upload + preview + confirm + 3 templates |
| 2026-06-06 | P2C7 — COHA Mode (9-section form + printable patient awareness report) |
| 2026-06-06 | P2C9 — Follow-Up context loading (past consultation selector, read-only context panel, `previous_consultation_id` FK) |
| 2026-06-06 | P2C10 + P2C11 — Consultation → Treatment Plan handoff; `create.blade.php` was truncated at line 771, restored to 1018 lines; drop-legacy-columns migration created (NOT run) |
| 2026-06-06 | P2C3 — Consult Assist Panel (rules engine AJAX suggest endpoint + Alpine chip UI) |
| 2026-06-06 | P2C4 — Dynamic Specialty Modules (5 inline panels, Alpine toggles, packModules) |
| 2026-06-06 | Prescription module — full CDSS engine (`PrescriptionAlertService`), write-pad UI, all settings pages, drug master |
| 2026-06-10 | Patient Communications Log — `PatientCommunicationController` + `patient_communications` table; call/WA/email/SMS log per patient with scheduled + sent entries |
| 2026-06-11 | Lab v2 Phase 1 — DB rebuild: `lab_vendors`, `lab_cases` rebuild, `lab_case_items`, `lab_case_attachments`, `lab_case_events`; migrations run |
| 2026-06-11 | Lab v2 Phase 2 — `LabController` rewrite (filters, one-click transitions, duplicate, archive/restore, attachments, subtypes AJAX); `LabVendorController`; `LabExpenseService`; `LabAlertService` |
| 2026-06-11 | Inventory Variants — `InventoryVariant` model + migrations + AJAX routes for sub-type variant management |
| 2026-06-12 | Procurement/Finance/Lab Foundation — PO→GRN→Invoice→AP chain; `extend_vendor_architecture`, dedicated GRN tables, `VendorInvoice` auto-AP, Lab Vendor contacts/services CRUD, `syncToFinance()` on both vendor types |
| 2026-06-13 | Finance Vouchers — `FinanceVoucher` model + `VoucherController` (auto-created on expense mark-paid; index/show/print/XLSX export) |
| 2026-06-13 | Wallet Campaigns — `WalletCampaign` model + `WalletCampaignController` (bulk wallet credit to patient segment by tag/area/treatment/source) |
| 2026-06-13 | Lab Monthly Reconciliation — `LabMonthlyReconciliation` model + `LabReconciliationController` (draft→pending_review→approved/disputed; Finance AP on approve) |
| 2026-06-13 | Finance Analytics — `AnalyticsController` (8 analytics views: vendor, procurement, lab, expense, cashflow, business, outstanding, audit). **Routes not wired yet — routes/web.php:314 still `Coming soon`** |
| 2026-06-11 | **DEVLOG full rebuild from disk scan** — 159 migrations, 120+ models, 55 controllers confirmed. All unlogged sessions above reconstructed from codebase. |
