# Dentfluence — Complete Architecture Lock
**Locked:** 2026-06-18 | **Stack:** Laravel + MySQL + Blade + Alpine.js + Tailwind/Bootstrap

> ⚠️ **SUPERSEDED (2026-07-03):** the current authoritative reference is
> **`docs/architecture/ENGINEER_HANDOVER.md`** (+ `docs/architecture/system-map.mermaid`).
> This file is kept for history; where they conflict, the handover doc wins.

> This document is the authoritative reference for Dentfluence's architecture.
> Update it at the end of every major build session.
> Check `DEVLOG.md` for per-session changelogs.

---

## 1. Tech Stack

| Layer | Choice |
|---|---|
| Backend framework | Laravel (PHP) |
| Database | MySQL via Laragon |
| Templating | Blade + Alpine.js (x-data, x-show, x-on) |
| CSS | Tailwind CSS + Bootstrap utilities |
| JS utilities | Alpine.js, Flatpickr (date/time), Select2 (dropdowns), Chart.js |
| Auth | Laravel session auth + Role-based permissions |
| Scheduler | Laravel Console (routes/console.php) |
| Storage | Local disk (public) + storage:link |
| Dev environment | Laragon @ C:\laragon\www\dentfluence |

---

## 2. High-Level Module Map

```
Dentfluence
├── CLINICAL
│   ├── Patients (full CRUD, 8-tab profile)
│   ├── Consultations (4 typed workflows)
│   ├── Treatment Plans + Stages
│   ├── Treatment Visits (tooth chart, FDI)
│   ├── Prescriptions (CDSS, drug DB)
│   └── Clinical Files / Documents
│
├── OPERATIONS
│   ├── Appointments
│   ├── Lab Cases v2 (enterprise rebuild)
│   ├── Inventory (items, stock, PO, GRN, vendors)
│   └── Procurement (PO→GRN→Invoice→AP chain)
│
├── FINANCE
│   ├── Billing (invoices, receipts, payments, voids)
│   ├── Wallet (patient credit, debit, transaction register)
│   ├── Finance (expenses, cashbook, bank accounts, payroll, GST)
│   ├── Coupons & Memberships
│   ├── EMI (schemes, providers, schedules)
│   └── Vendor Invoices (AP auto-create)
│
├── COMMUNICATION
│   ├── Recall Engine (auto, scheduler)
│   ├── Opportunity / PRM Pipeline
│   ├── Inbound Leads (UTM attribution)
│   ├── B2B (vendor/lab/consultant queue)
│   ├── Unified Inbox + My Queue
│   └── KPI Dashboard + Daily Digest Emails
│
├── MARKETING HUB v2
│   ├── Campaigns (CRUD, goals, team)
│   ├── Brainstorm / Idea Board
│   ├── Content Calendar
│   ├── Publish Engine (Instagram, Facebook, Google Business)
│   ├── Asset Library (brand kit, folders, tags)
│   ├── Platform Integrations (OAuth)
│   └── Intelligence / Analytics Layer
│
├── REPORTS
│   ├── Appointments tab
│   ├── Revenue tab
│   ├── Patients tab
│   ├── Treatments tab
│   ├── Lab tab
│   └── Inventory tab
│
├── SETTINGS & ADMIN
│   ├── Roles & Permissions
│   ├── Masters (treatments, categories, tags)
│   ├── App Personalisation
│   ├── Content Management (CMS)
│   └── Settings (global)
│
└── SYSTEM
    ├── Auth (login, roles)
    ├── Mobile Auth (OTP login, PIN reset)
    ├── Notifications (in-app)
    ├── User Profile (avatar, password)
    └── Dashboard
```

---

## 3. Routes Files

| File | Lines | Purpose |
|---|---|---|
| `routes/web.php` | 670 | Core app routes (patients, appointments, billing, finance, lab, inventory, settings, reports, notifications, profile) |
| `routes/communication.php` | 135 | All communication module routes (recall, opportunities, leads, B2B, KPI, tasks, timeline, huddle, templates) |
| `routes/marketing.php` | 81 | All marketing hub routes (campaigns, brainstorm, calendar, publish, assets, integrations, analytics) |
| `routes/prescriptions.php` | 122 | Prescription CRUD + drug/template/CDSS endpoints |
| `routes/clinical-library.php` | 45 | Clinical library (protocols, SOPs, treatment knowledge) |
| `routes/cms.php` | 80 | Content Management System routes |
| `routes/prm.php` | 27 | PRM (Patient Relationship Management) routes |
| `routes/tags-routes.php` | 23 | Tag CRUD |
| `routes/timeline.php` | 13 | Patient timeline endpoint |
| `routes/console.php` | 79 | Scheduled commands (comm:morning-briefing 7:05am, comm:sla-alert 2pm, comm:evening-summary 6pm, comm:auto-escalate every 30min) |

---

## 4. Controllers

### Clinical
| Controller | Location | Responsibility |
|---|---|---|
| `PatientController` | `Http/Controllers/` | Patient CRUD, notes, docs, tags, import/export |
| `ConsultationController` | `Http/Controllers/` | 4 typed workflows: New, Same Issue, Minor Visit, Emergency |
| `TreatmentPlanController` | `Http/Controllers/` | Plan CRUD, stage management |
| `TreatmentVisitController` | `Http/Controllers/` | Visit recording, tooth chart, FDI |
| `TreatmentController` | `Http/Controllers/` | Treatment master data |
| `TreatmentCategoryController` | `Http/Controllers/` | Treatment categories |
| `ClinicalFileController` | `Http/Controllers/` | Clinical file store/index/show/update/destroy |
| `ConsultAssistController` | `Http/Controllers/` | COHA / consultation assistant |

### Prescription (namespace: `Http/Controllers/Prescription/`)
Full CDSS suite — drug CRUD, templates, categories, generics, routes of admin, interactions, allergy rules, warnings, food instructions, dose/duration templates.

### Operations
| Controller | Location | Responsibility |
|---|---|---|
| `AppointmentController` | `Http/Controllers/` | Appointment CRUD + calendar |
| `LabController` | `Http/Controllers/` | Lab Case v2 (filters, transitions, duplicate, archive, attachments) |
| `LabVendorController` | `Http/Controllers/` | Lab vendor CRUD + Finance sync |
| `LabReconciliationController` | `Http/Controllers/` | Lab monthly reconciliation |
| `InventoryController` | `Http/Controllers/` | Items, stock, PO, GRN, vendors, receive PO |
| `VendorInvoiceController` | `Http/Controllers/` | Vendor invoice CRUD, auto AP expense |

### Finance (namespace: `Http/Controllers/Finance/`)
| Controller | Responsibility |
|---|---|
| `FinanceController` | Expenses, cashbook, bank accounts, payroll, GST, vendor payments |
| `FinanceReportsController` | Finance-specific reports |
| `AnalyticsController` | Finance analytics |
| `WalletController` | Wallet index (5 KPI cards), register, register export, patient ledger |
| `WalletCampaignController` | Wallet campaigns/promos |
| `CouponController` | Coupon CRUD, validation, usage tracking |
| `MembershipController` | Membership plans + patient memberships |
| `VoucherController` | Finance vouchers |

### Billing
`BillingController` — Full billing CRUD, 8+ payment modes, receipts, final bill, void, refund, coupon apply, wallet debit, EMI, convenience fee, provider EMI settlement.

### Communication (namespace: `Http/Controllers/Communication/`)
| Controller | Responsibility |
|---|---|
| `CommunicationController` | Unified inbox, queue management |
| `RecallController` | Recall engine triggers + list |
| `OpportunityController` | PRM kanban + list, stage AJAX, convert to lead, patient search |
| `FollowUpController` | Follow-up management |
| `HuddleController` | Huddle notes (daily briefing) |
| `TaskController` | Staff task management |
| `TemplateController` | Message templates |
| `TimelineController` | Patient communication timeline |
| `B2BController` | Vendor/lab/consultant communication queue |
| `KpiController` | Communication KPI dashboard |
| `ManagerController` | Manager escalation + oversight |
| `PrmController` | PRM pipeline views |
| `DashboardController` | Communication dashboard |

### Marketing (namespace: `Http/Controllers/Marketing/`)
| Controller | Responsibility |
|---|---|
| `MarketingController` | Hub overview |
| `CampaignController` | Campaign CRUD (modal-based, no campaigns.create route) |
| `BrainstormController` | Idea board |
| `IdeaController` | Idea CRUD |
| `CalendarController` | Content calendar |
| `PublishController` | Publish engine (Instagram 2-step, Facebook, Google Business) |
| `AssetController` | Asset library |
| `LibraryController` | Library view |
| `BrandKitController` | Brand kit management |
| `IntegrationController` | OAuth platform connections |
| `OverviewController` | Marketing overview |
| `SettingsController` | Marketing settings |
| `AnalyticsController` | KPI cards, bar chart, platform breakdown, ROI table, intelligence insights, activity feed |
| `CmsMediaController` | CMS media within marketing |

### Reports
`ReportsController` — single `index()` feeding 6 tabs (Appointments, Revenue, Patients, Treatments, Lab, Inventory). Date range: 7/30/90/365/custom.

### Settings (namespace: `Http/Controllers/Settings/`)
Roles, permissions, masters, global settings.

### CMS (namespace: `Http/Controllers/ContentManagement/` + `Http/Controllers/Cms/`)
Education categories, items, media, treatment cases, tags.

### System
| Controller | Responsibility |
|---|---|
| `DashboardController` | Main dashboard |
| `NotificationsController` | In-app notifications (index, recent AJAX, markRead, markAllRead) |
| `ProfileController` | User profile show/update/password/avatar |
| `AuthController` | Login/logout |
| `Auth/MobileOtpController` | OTP-based mobile login |
| `Auth/ForgotPasswordPinController` | PIN-based password reset |
| `TagController` | Global tag management |
| `PatientImportExportController` | Patient data import/export |
| `PatientCommunicationController` | Patient-level communication history |
| `PatientNoteController` | Patient notes CRUD |
| `PatientDocumentController` | Patient documents |

---

## 5. Models

### Root namespace (`App\Models`)
`Patient`, `User`, `Role`, `RoleModulePermission`, `Module`, `Appointment`, `DoctorBlockedSlot`, `Operatory`, `Consultation`, `ConsultationCohaReport`, `ConsultationPhotograph`, `ConsultationScan`, `ConsultationSpecialtyModule`, `TreatmentPlan`, `TreatmentPlanItem`, `TreatmentVisit`, `TreatmentVisitItem`, `Treatment`, `TreatmentCategory`, `TreatmentType`, `TreatmentOpportunity`, `TreatmentKnowledge`, `TreatmentMedia`, `TreatmentRule`, `TreatmentSop`, `ClinicalFile`, `ClinicalFinding`, `ClinicalMedia`, `Prescription`, `LabCase`, `LabCaseItem`, `LabCaseAttachment`, `LabCaseEvent`, `LabVendor`, `LabVendorContact`, `LabVendorService`, `LabMonthlyReconciliation`, `LabReconciliationItem`, `LabReconciliationEvent`, `Invoice`, `InvoiceItem`, `InvoicePayment`, `Receipt`, `FinalBill`, `BillingPrompt`, `BillingAuditLog`, `EmiProvider`, `EmiScheme`, `EmiSchedule`, `CouponCode`, `CouponUsage`, `Wallet`, `WalletTransaction`, `WalletCampaign`, `Tag`, `Task`, `Lead`, `LeadActivity`, `CommunicationQueue`, `CommActivityLog`, `FollowUp`, `FollowUpNote`, `HuddleNote`, `Escalation`, `MessageTemplate`, `PatientNote`, `PatientRelationshipNote`, `PatientCommunication`, `PatientDocument`, `PatientAlert`, `PatientSource`, `Complaint`, `AppNotification`, `AppSetting`, `WatermarkSetting`, `Diagnosis`, `DentalCondition`, `MedicalCondition`, `Investigation`, `Medicine`, `DocumentationProtocol`, `DocumentationProtocolStep`, `EducationCategory`, `EducationMedia`, `EducationTreatment`

### `App\Models\Finance`
`FinanceTransaction`, `FinanceExpense`, `FinanceExpenseCategory`, `FinanceVendor`, `FinanceVendorPayment`, `FinanceBankAccount`, `FinanceCashbook`, `FinanceGstRecord`, `FinanceMembershipPlan`, `FinancePatientMembership`, `FinancePayroll`, `FinanceVoucher`

### `App\Models\Inventory`
`InventoryItem`, `InventoryCategory`, `InventorySubType`, `InventoryVariant`, `InventoryStock`, `InventoryLocation`, `InventoryVendor`, `PurchaseOrder`, `PurchaseOrderItem`, `StockMovement`, `ReusableAsset`, `ImplantCatalog`, `ImplantPlacement`

### `App\Models\Procurement`
`GoodsReceiptNote`, `GrnItem`, `VendorInvoice`, `VendorInvoiceItem`

### `App\Models\Prescription`
`Prescription`, `PrescriptionItem`, `PrescriptionAuditLog`, `PrescriptionOverride`, `RxDrug`, `RxDrugCategory`, `RxGeneric`, `RxTemplate`, `RxTemplateItem`, `RxRouteOfAdmin`, `RxDoseTemplate`, `RxDurationTemplate`, `RxFoodInstruction`, `RxAllergyRule`, `RxDrugInteractionRule`, `RxWarningRule`

### `App\Models\Marketing`
`Campaign`, `CampaignGoal`, `Idea`, `IdeaAsset`, `MarketingPost`, `PostVariant`, `PostMedia`, `PostSchedule`, `MarketingAsset`, `AssetFolder`, `AssetTag`, `BrandKit`, `PlatformConnection`, `MarketingSetting`, `FestivalDate`, `MarketingActivityLog`

### `App\Models\Cms`
`CmsEduCategory`, `CmsEduItem`, `CmsEduModels`, `CmsMedia`, `CmsTag`, `CmsTreatmentCase`

---

## 6. Database — Migration Count & Key Tables

**Total migrations: 216**

### Key schema decisions
- `patients` — uses `phone` + `alternate_phone` (not `mobile`)
- `lab_cases` — rebuilt from scratch (2026_06_11_000002 drops old table); `expense_id` col prevents duplicate AP expense creation; overdue is computed, never stored; soft deletes on all lab tables
- `wallet_transactions` — has `invoice_number varchar(50) nullable` for denormalized audit display
- `treatment_opportunities` — has `assigned_to`, `follow_up_time`, `treatment_plan_id`, STAGES/PRIORITY constants
- `communication_queue` — has `contact_type`, `contact_id`, `b2b_subtype`, `lab_case_id` for B2B module
- `consultations` — has 7 typed nullable columns: `update_notes`, `additional_findings`, `related_to_clinic_treatment`, `procedure_performed`, `advice`, `emergency_treatment_rendered`, `converted_to_consultation_id`
- `patient_relationship_notes` — has `note_type` (internal/call/whatsapp/email/sms)
- `purchase_orders` — has `finance_vendor_id`, `approved_by`, `approved_at`, `invoice_status`, `invoiced_amount`
- `inventory_vendors` — has `finance_vendor_id` (sync bridge to finance_vendors)
- `finance_vendors.vendor_type` enum includes: dental_supplier, lab, rent, electricity, water, internet, salary, lawyer, amc, office_supplies, miscellaneous
- `mkt_*` — 18 marketing migrations (all prefixed `mkt_`)
- `app_notifications` — in-app notification table
- `password_reset_pins` + `mobile_otps` — mobile auth tables

### Vendor Sync Rules
- Inventory vendors → auto-sync to `finance_vendors` as `type=dental_supplier`
- Lab vendors → auto-sync to `finance_vendors` as `type=lab`
- Finance-only vendors (rent, salary, etc.) never appear in Inventory or Lab

---

## 7. Views Directory Map

```
resources/views/
├── layouts/          # Master layouts
├── partials/         # Shared partials (topbar, sidebar, etc.)
├── components/       # Blade components
├── dashboard/        # Main dashboard
├── auth/             # Login
├── patients/         # Patient CRUD + 8-tab show.blade.php (3152 lines, STABLE)
│   └── partials/     # edit-patient-drawer, treatment-visits-tab, etc.
├── consultations/    # create, same-issue, minor-visit, emergency (4 workflows)
├── appointments/     # Appointment views
├── treatment-plans/  # Treatment plan views
├── treatments/       # Treatment master views
├── visits/           # Treatment visit views
├── prescriptions/    # Prescription views
├── billing/          # Billing views
├── finance/          # Finance views
├── lab/              # Lab Case v2 views
├── labs/             # Legacy lab views (transition)
├── inventory/        # Inventory views + vendor-invoices
├── communication/    # Unified inbox, recall, b2b/, kpi/
├── crm/              # CRM redirects to opportunities
├── marketing/        # Marketing hub (campaigns, brainstorm, calendar, assets, analytics)
├── reports/          # 6-tab reports view
├── notifications/    # In-app notification views
├── profile/          # User profile view
├── settings/         # Settings views
├── content-management/ # CMS views
├── clinical-library/ # Clinical library views
├── cms/              # CMS module views
├── emails/           # Email templates
│   └── digest/       # Morning briefing, SLA alert, evening summary
├── huddle/           # Huddle notes
├── tasks/            # Staff tasks
├── analytics/        # Analytics views
├── errors/           # Error pages
└── welcome.blade.php
```

---

## 8. Services & Jobs

### Services (App\Services)
- `WalletService` — debit, credit, refund; stores `invoice_number`; called from BillingController
- `LabExpenseService` — auto-creates finance expense on lab case received/paid; `expense_id` guard prevents duplicates; auto-creates "lab-charges" category + FinanceVendor link
- `LabAlertService` — due today/tomorrow, overdue, urgent, awaiting delivery, stale 15+ days
- `MarketingScoreService` — marketing engagement scoring
- `CampaignService` — campaign lifecycle management
- `OAuthService` — platform OAuth (Instagram, Facebook, Google)
- `CampaignLeadService` — lead attribution from campaigns

### Jobs
- `ProcessScheduledPost` — real API calls: Instagram (2-step Graph API container→publish), Facebook (page feed), Google Business (localPosts)

### Observers
- `LabCaseObserver` — auto-syncs lab case events; registered in `AppServiceProvider`

### Console Commands
| Command | Schedule | Purpose |
|---|---|---|
| `comm:morning-briefing` | 7:05 AM daily | Per-staff call list for the day |
| `comm:sla-alert` | 2:00 PM daily | SLA breach alert to manager |
| `comm:evening-summary` | 6:00 PM daily | Done/overdue/won summary |
| `comm:auto-escalate` | Every 30 min | ₹30k+ lead, 0 attempts, 2h+ old → escalate |

---

## 9. Module Build Status

### ✅ Complete & Stable

| Module | Notes |
|---|---|
| Auth | Session auth, roles, permissions |
| Mobile Auth | OTP login, PIN password reset |
| Dashboard | Main KPI dashboard |
| Patients | Full CRUD, 8-tab profile, all 7 UI fixes verified |
| Consultations | 4 typed workflows (New, Same Issue, Minor Visit, Emergency) |
| Treatment Plans | CRUD + stages + carry-forward |
| Treatment Visits | Tooth chart, FDI picker, stage-based items |
| Prescriptions | Full CDSS — drug DB, M/A/N grid, typeahead, templates, audit log, finalize/repeat/cancel |
| Appointments | Full CRUD + calendar |
| Lab Cases v2 | Phase 1 (DB + models) ✅, Phase 2 (controller + services) ✅ — UI phases pending |
| Inventory | Items, stock, PO, GRN, vendors, receive PO |
| Procurement | Full PO→GRN→Invoice→AP chain |
| Billing | 8+ payment modes, receipts, void, refund, coupon, wallet, EMI, convenience fee |
| Finance Wallet | Dashboard cards, Transaction Register, Patient Ledger w/ running balance |
| Finance | Expenses, cashbook, bank accounts, payroll, GST, vendor payments |
| Vendor Invoices | CRUD + auto AP expense creation |
| Communication — Recall Engine | Laravel scheduler, 6 trigger types |
| Communication — Opportunity/PRM | Kanban + list, stage AJAX, convert, patient search |
| Communication — Inbound Leads | UTM attribution, lead source tracking |
| Communication — B2B | Vendor/lab/consultant queue |
| Communication — KPI Dashboard | Full dashboard + daily digest emails |
| Marketing Hub v2 | All 6 phases complete — campaigns, brainstorm, calendar, publish, assets, intelligence |
| Reports | 6 tabs with real DB queries, date range filter |
| Notifications | In-app (index, AJAX dropdown, mark read) |
| User Profile | Show/update/password/avatar |
| Settings | Roles, masters, tags, app personalisation |
| Content Management | CMS — education, cases, media |
| Clinical Library | Protocols, SOPs, treatment knowledge |
| Coupons & Memberships | Full CRUD, validation, usage tracking |

### ⚠️ Partially Built

| Module | What's Missing |
|---|---|
| Lab Cases v2 | Phases 3–6: UI rebuild (index, create/edit form, case view + timeline), integrations |
| Prescriptions | WhatsApp send integration (PDF print view is done) |
| Finance views | Some POST routes may need verification |

### ❌ Not Built

| Module | Notes |
|---|---|
| WhatsApp Integration | Referenced in multiple places; nothing built |
| Help / Documentation page | Not started |

---

## 10. Key Design Rules

### UI Complexity Rule (enforced globally)
- **Data entry screens** (call logging, visit recording, booking) → dead simple, designed for 12th-pass staff. Max 4–5 visible fields at once. Dropdowns over free text. One obvious primary CTA.
- **Admin / KPI views** (dashboards, reports, analytics) → full complexity allowed. Dense tables, multi-column layouts, filters, exports are fine.
- **Never mix the two** in one screen.

### Alpine.js Scope Rule
- `patients/show.blade.php` (3152 lines) is fragile. The `patientProfile()` Alpine scope wraps the entire page. The `edit-patient-drawer` partial MUST remain inside `<div x-data="patientProfile()">`. An extra `</div>` will break the drawer.
- Always use `Read` tool to check line counts — bash sandbox shows stale cache.

### Vendor Sync Pattern
When an inventory or lab vendor is created/updated, `syncToFinance()` is called automatically to keep `finance_vendors` in sync. Never duplicate vendor data manually.

### Billing → Finance Mirroring
Every billing transaction is mirrored to `finance_transactions`. Audit log entries are written on every state change (void, refund, edit, delete).

### Lab Expense Guard
`lab_cases.expense_id` — once set, `LabExpenseService` refuses to create a duplicate expense for the same case. Safe to call receivePO multiple times.

### Communication Status Flow (no skipping allowed)
`New → Assigned → Attempted(n) → Waiting/Rescheduled → [Appointment Booked ✓ | Lost + reason | Unreachable | Escalated]`

---

## 11. Pending Terminal Commands

> Run these at the end of any session where migrations were written.

```bash
php artisan migrate
php artisan db:seed --class=MarketingModuleSeeder
php artisan db:seed --class=FestivalDateSeeder
php artisan storage:link
```

---

## 12. File Locations Quick Reference

| What | Path |
|---|---|
| Main routes | `routes/web.php` |
| Communication routes | `routes/communication.php` |
| Marketing routes | `routes/marketing.php` |
| Prescription routes | `routes/prescriptions.php` |
| Scheduled commands | `routes/console.php` |
| Patient profile view | `resources/views/patients/show.blade.php` (3152 lines) |
| Edit patient drawer | `resources/views/patients/partials/edit-patient-drawer.blade.php` |
| Treatment visits tab | `resources/views/patients/partials/treatment-visits-tab.blade.php` |
| Lab Case model | `app/Models/LabCase.php` |
| Wallet service | `app/Services/WalletService.php` |
| Lab expense service | `app/Services/LabExpenseService.php` |
| Lab observer | `app/Observers/LabCaseObserver.php` |
| Marketing publish job | `app/Jobs/ProcessScheduledPost.php` |
| Finance migrations | `database/migrations/2026_06_11_*` + `2026_06_12_*` |
| Marketing migrations | `database/migrations/2026_06_17_300*` (18 files, `mkt_` prefix) |
| Mobile auth migrations | `database/migrations/2026_06_18_000001_create_password_reset_pins_table.php` |
| Notifications migration | `database/migrations/2026_06_18_100001_create_app_notifications_table.php` |
| Dev log | `DEVLOG.md` |
| This document | `ARCHITECTURE.md` |

---

*Last updated: 2026-06-18 — Architecture locked after 6 major build sessions.*
