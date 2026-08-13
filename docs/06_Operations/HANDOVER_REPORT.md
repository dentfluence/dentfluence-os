# Dentfluence OS — Complete Technical Handover Report

> **Audience:** Another senior AI (ChatGPT) or a developer joining cold.
> **Purpose:** Self-contained, exhaustively detailed project context.
> **Date generated:** 2026-06-30
> **Author:** Lead Software Architect — Dentfluence OS

---

## TABLE OF CONTENTS

1. [Project Overview](#1-project-overview)
2. [Tech Stack](#2-tech-stack)
3. [Folder Structure](#3-folder-structure)
4. [Database Schema](#4-database-schema)
5. [Authentication](#5-authentication)
6. [Modules Built](#6-modules-built)
7. [UI Screens](#7-ui-screens)
8. [APIs](#8-apis)
9. [Reusable Components & Traits](#9-reusable-components--traits)
10. [State Management](#10-state-management)
11. [Current Features Checklist](#11-current-features-checklist)
12. [Known Bugs](#12-known-bugs)
13. [Technical Debt](#13-technical-debt)
14. [Security](#14-security)
15. [Performance](#15-performance)
16. [Mobile App](#16-mobile-app)
17. [Deployment](#17-deployment)
18. [SaaS Readiness](#18-saas-readiness)
19. [Future Roadmap](#19-future-roadmap)
20. [What Another Developer Should Know](#20-what-another-developer-should-know)
21. [Architecture Diagram](#21-architecture-diagram)
22. [Current Limitations](#22-current-limitations)
23. [Refactoring Suggestions](#23-refactoring-suggestions)
24. [Final Project Health Score](#24-final-project-health-score)

---

## 1. Project Overview

### Project Name
**Dentfluence OS** (Operating System for Dental Clinics)

### Purpose
A full-stack dental clinic management system covering the entire lifecycle of a dental practice: patient registration, clinical workflow (consultations, treatment plans, prescriptions, lab, visits), billing & finance, HR, inventory, marketing, PRM (Patient Relationship Management), and a local-AI assistant named "Tulip."

### Vision
Replace the fragmented tools a dental clinic uses (paper registers, WhatsApp, generic billing software) with a single, intelligent, India-first platform. Long-term goal: become the Salesforce of dental in India — deeply embedded, AI-augmented, ABDM-compliant, and impossible to churn from.

### Target Users
- **Primary:** Dental clinic owners and their staff (doctors, front-desk, assistants, accounts)
- **First customer / live pilot:** Tulip Dental, Dombivli (the founder's own clinic)
- **Future:** Multi-location dental chains, DSOs, franchises

### Current Development Stage
- **Feature surface built:** ~90% (110 controllers, 176 models, 60 services, 267 migrations)
- **Verified working end-to-end:** ~70% (based on route-crawler passing 178/180 pages + selective manual tests)
- **Production-deployment ready:** ~5% (infra gap — no live server yet, but Docker kit is built)
- **Launch target:** July 1, 2026 (first live clinic)

### Overall Architecture
Monolithic Laravel web application with:
- Server-side rendered Blade views (no SPA)
- Alpine.js for interactive UI components
- A separate RESTful `/api/v1` layer (JSON) consumed by the Flutter mobile app
- Local AI layer (Ollama + faster-whisper) for Tulip assistant, voice notes, and receipt scanning
- Webhook receivers for inbound WhatsApp (Meta Cloud API) and website leads
- ABDM/FHIR layer (design + skeleton code — not yet live-integrated with NHA)

### Technologies Used
See Section 2 for full detail.

### Deployment Details
- **Current:** Laragon on Windows (`C:\laragon\www\dentfluence` → moved to `E:\Dentfluence\Dentfluence_OS\Dentfluence Web`)
- **Target (purchased):** Hostinger KVM 2 VPS, IP `187.127.152.68`, Ubuntu 24.04, Docker Compose stack
- **Domain purchased:** `dentfluence.in`
- **Docker deployment kit:** Fully built (`Dockerfile`, `docker-compose.yml`, `deploy.sh`, `backup.sh`, nginx config, `DEPLOY.md`) — not yet executed on VPS

### Current URLs
- **Local dev:** `http://dentfluence.test` (Laragon)
- **Production:** Not yet live (DNS pending pointing to Hostinger VPS)
- **Mobile API base:** `http://<server-ip>/api/v1/`

---

## 2. Tech Stack

### Backend
| Technology | Version / Notes |
|---|---|
| PHP | 8.3 |
| Laravel | 13.x (`^13.7`) |
| Laravel Sanctum | 4.3 (API token auth for mobile) |
| Laravel Tinker | 3.0 (REPL) |
| Laravel Dusk | 8.6 (browser tests) |
| PHPUnit | 12.5 |
| PHPSpreadsheet | 5.7 (Excel import/export) |
| pragmarx/google2fa-qrcode | 4.0 (TOTP MFA) |
| bacon/bacon-qr-code | 3.1 (QR generation for HR attendance) |
| Composer | 2.x |

### Frontend (Web)
| Technology | Version / Notes |
|---|---|
| Blade | Laravel's default templating engine |
| Alpine.js | 3.x (CDN — reactive UI without full JS framework) |
| Tailwind CSS | 4.x (via Vite pipeline) |
| Bootstrap | Utilities used alongside Tailwind |
| Vite | 8.x (asset bundling) |
| Chart.js | CDN (charts on dashboard, huddle, reports) |
| Flatpickr | CDN (date/time pickers) |
| Select2 | CDN (searchable dropdowns) |

### Database
| Technology | Notes |
|---|---|
| MySQL | Via Laragon (local), Docker MySQL (production) |
| Eloquent ORM | Laravel's built-in ORM |
| 267 migrations | Covering all tables |

### Authentication (Web)
- Laravel session auth (cookie-based)
- Role-based permission system (dual: legacy string `role` column + new `roles` table with `role_module_permissions`)
- TOTP MFA via `google2fa` (Phase A security sprint)

### Authentication (Mobile/API)
- Laravel Sanctum — `personal_access_tokens` table
- Bearer token in `Authorization` header

### Storage
- Local disk (`storage/app/public`, symlinked to `public/storage`)
- Private clinical files behind `SecureMediaController` (stream through auth-gated route)
- No cloud storage yet (AWS S3 bucket exists in config but empty)

### Hosting
- **Local:** Laragon (Windows)
- **Production target:** Hostinger KVM 2 VPS (Ubuntu 24.04), Docker Compose

### Build Tools
- Vite 8.x (`npm run build` / `npm run dev`)
- Composer (PHP dependencies)
- npm (JS dev dependencies only — no frontend framework bundles)

### Package Managers
- Composer (PHP)
- npm (JS)

### State Management
- **Web:** Alpine.js `x-data` for local component state; no global store
- **Mobile:** Flutter `setState` + provider pattern (no dedicated state management library confirmed)

### UI Libraries
- Tailwind CSS 4
- Bootstrap (partial, legacy)
- Alpine.js (interactivity)
- Chart.js (data visualization)
- Flatpickr (date pickers)
- Select2 (enhanced selects)

### Icons
- Bootstrap Icons (CDN, used throughout Blade views)
- Custom SVG in some views

### Forms & Validation
- Laravel Form Requests (`app/Http/Requests/`, `app/Http/Requests/Api/`)
- Blade `@error` directives for web
- API: JSON validation with `422 Unprocessable Entity` responses

### API Structure
- Versioned: `/api/v1/...`
- Auth: Sanctum Bearer token
- Response envelope: `{ success, data, message, meta }` pattern
- Throttle: 120 req/min globally; 5 req/min on auth endpoints
- Role guards: `api.role` middleware (`EnsureApiRole`)

---

## 3. Folder Structure

```
E:\Dentfluence\Dentfluence_OS\Dentfluence Web\
│
├── app/
│   ├── Abdm/                    # ABDM/FHIR layer (design + skeleton, NOT live)
│   │   ├── Clients/             # HTTP clients for NHA APIs
│   │   ├── Contracts/           # Interfaces
│   │   └── Fhir/                # FHIR builders, bundles, mappers, validators
│   │
│   ├── Actions/                 # Single-purpose action classes
│   ├── Casts/                   # Custom Eloquent casts (Encrypted, EncryptedArray)
│   ├── Console/
│   │   └── Commands/            # 30+ artisan commands (see Section 6)
│   │       └── Phase8/          # CMS/media migration commands
│   │
│   ├── DTOs/                    # Data Transfer Objects
│   ├── Enums/                   # PHP 8.1+ enums
│   ├── Helpers/                 # Global helper functions
│   │
│   ├── Http/
│   │   ├── Controllers/         # 110 controllers (see breakdown below)
│   │   │   ├── Abdm/            # ABHA capture controller
│   │   │   ├── Api/V1/          # Mobile API controllers (18 controllers)
│   │   │   ├── Auth/            # ForgotPasswordPin, MobileOtp
│   │   │   ├── Cms/             # Content Management System
│   │   │   ├── Communication/   # Recall, Leads, B2B, Inbox, Templates
│   │   │   ├── ContentManagement/
│   │   │   ├── Finance/         # Finance, Analytics, Wallet, Coupon, Membership, Voucher
│   │   │   ├── HR/              # HR controllers (attendance, payroll, training)
│   │   │   ├── Marketing/       # Campaigns, Content Calendar, Assets, Publish
│   │   │   ├── Prescription/    # Drug CRUD, templates, CDSS, categories
│   │   │   ├── Settings/        # Roles, modules, app settings
│   │   │   └── Webhooks/        # Website leads, Meta leads, WhatsApp, Chatbot
│   │   │
│   │   ├── Middleware/          # 7 custom middleware (see Section 5)
│   │   ├── Requests/            # Form Request validation classes
│   │   │   └── Api/             # API-specific request classes
│   │   └── Resources/           # Eloquent API Resources (JSON transformers)
│   │
│   ├── Jobs/
│   │   └── Marketing/           # Queued jobs for marketing automation
│   │
│   ├── Mail/                    # Mailable classes (morning briefing, notifications)
│   │
│   ├── Models/                  # 176 Eloquent models
│   │   ├── Finance/             # Finance-specific models (13 models)
│   │   ├── Inventory/           # Inventory-specific models
│   │   ├── Marketing/           # Campaign, Lead, LeadActivity models
│   │   ├── Prescription/        # Drug, Template, Interaction models
│   │   ├── Procurement/         # PO, GRN models
│   │   └── Scopes/              # Global query scopes (BranchScope)
│   │
│   ├── Modules/                 # Modular "mini-MVC" packages
│   │   ├── Appointment/         # Controller, Model, Service, Repo, Routes, Resources
│   │   ├── Huddle/              # Daily Huddle board
│   │   ├── Lab/                 # Lab Cases v2
│   │   ├── Patient/             # Patient module
│   │   ├── PracticeProtocols/   # Job library + SOPs
│   │   └── Treatment/           # Treatment Plans + Visits
│   │
│   ├── Observers/               # Eloquent model observers
│   ├── Providers/               # AppServiceProvider, etc.
│   ├── Repositories/            # Repository pattern classes
│   │
│   ├── Services/                # 60 service classes ("the brain")
│   │   ├── Assistant/           # Tulip AI assistant + tool registry
│   │   │   └── Tools/           # Individual tool handlers for Tulip
│   │   ├── Billing/             # InvoicePaymentService
│   │   ├── ClinicalLibrary/     # Protocol/SOP services
│   │   ├── Cms/                 # CMS services
│   │   ├── Communication/       # FollowUpRulesService
│   │   ├── ContentManagement/   # Content management services
│   │   ├── Huddle/              # Huddle board services
│   │   ├── Inventory/           # InventoryService
│   │   ├── Marketing/           # CampaignService, OAuthService, MarketingScoreService
│   │   ├── Prescription/        # CDSS, drug search, alert checking
│   │   ├── Prm/                 # Patient Relationship Management
│   │   ├── Reviews/             # Review request + reputation service
│   │   ├── Voice/               # Voice note transcription (Whisper)
│   │   └── Whatsapp/            # WhatsAppCloudService, Outbound/InboundMessageService
│   │
│   ├── Traits/                  # Reusable model traits (Auditable, BranchScoped)
│   └── Workflows/               # Multi-step workflow orchestrators
│
├── bootstrap/                   # Laravel bootstrap (app.php — routes, middleware)
├── config/                      # All config files (app, auth, database, sanctum, security, etc.)
│   └── security.php             # Custom security config (Phase A)
│
├── database/
│   ├── factories/               # Model factories for testing
│   ├── migrations/              # 267 migrations (chronological)
│   └── seeders/                 # Demo/master data seeders
│
├── docker/                      # Docker configs (nginx, php, startup)
├── docs/                        # Project documentation
│   ├── abdm/                    # 9 ABDM architecture design docs
│   ├── competitive/             # Competitive analysis (Eka vs Dentfluence)
│   ├── security/                # Security audit docs
│   └── plan-*.md                # Build timeline, feature roadmap, phase plans
│
├── modules/                     # (Legacy/alternate module location — largely superseded by app/Modules/)
├── public/                      # Web root (index.php, storage symlink, assets)
├── resources/
│   ├── css/                     # Tailwind entry point
│   ├── js/                      # app.js (Vite entry)
│   └── views/                   # 40+ Blade view directories (see Section 7)
│
├── routes/
│   ├── web.php                  # Core app routes (~852 lines)
│   ├── api.php                  # Mobile API routes
│   ├── communication.php        # Communication OS routes
│   ├── marketing.php            # Marketing Hub routes
│   ├── prescriptions.php        # Prescription CRUD + CDSS endpoints
│   ├── clinical-library.php     # Clinical library routes
│   ├── cms.php                  # CMS routes
│   ├── prm.php                  # PRM routes
│   ├── tags-routes.php          # Tag CRUD
│   ├── timeline.php             # Patient timeline
│   └── console.php              # Scheduled commands
│
├── storage/                     # Laravel storage (logs, cache, uploads)
├── tests/                       # PHPUnit feature + Dusk browser tests
│   ├── Feature/                 # ~13 feature tests
│   └── Browser/                 # ~20 Dusk browser tests
│
├── Dockerfile                   # Production Docker image
├── docker-compose.yml           # Production stack definition
├── deploy.sh                    # One-command deploy script
├── backup.sh                    # DB + uploads backup script
├── ARCHITECTURE.md              # Architecture reference doc
├── DENTFLUENCE_MASTER.md        # Single source of truth for project decisions
├── PROGRESS_STATUS_2026-06-27.md # Honest completion audit
└── vite.config.js               # Vite build config
```

---

## 4. Database Schema

### Overview
267 migrations spanning from initial setup (2024-01-01) to latest security/comms sprints (2026-06-29). All core tables use `id` (auto-increment PK), `created_at`, `updated_at`. Soft deletes are used selectively.

### Core Tables

#### `users`
Purpose: All staff accounts (doctors, front-desk, admin, assistant, accounts)
Columns: `id`, `name`, `email`, `password`, `role` (legacy string), `role_id` (FK→roles), `branch_id` (FK→branches), `is_active`, `last_login_at`, `phone`, `designation`, `avatar`, `color`, `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted array), `two_factor_confirmed_at`, `remember_token`, `email_verified_at`, timestamps

#### `roles`
Purpose: Role definitions for the RBAC system
Columns: `id`, `name`, `slug`, `category`, `description`, `color`, `is_system`, timestamps

Seeded roles: `admin`, `doctor`, `resident_dentist`, `associate_dentist`, `visiting_consultant`, `front_desk`, `assistant`, `accounts`, `manager`

#### `modules`
Purpose: Feature modules that can be permission-gated
Columns: `id`, `name`, `slug`, `description`, `is_active`, timestamps

#### `role_module_permissions`
Purpose: Pivot — which role can view/edit/delete which module
Columns: `id`, `role_id` (FK→roles), `module_id` (FK→modules), `can_view`, `can_edit`, `can_delete`, timestamps

#### `branches`
Purpose: Multi-location support (clinic branches)
Columns: `id`, `name`, `address`, `phone`, `email`, `is_active`, plus ABDM extension fields (`hfr_id`, `facility_type`, `abdm_config_id`), timestamps

#### `patients`
Purpose: The central entity — every clinical and billing record links here
Columns: `id`, `branch_id`, `uhid` (unique clinic ID), `name`, `phone`, `email`, `dob`, `gender`, `blood_group`, `address`, `city`, `pincode`, `occupation`, `referred_by`, `patient_source_id`, `is_active`, `deactivated_at`, `deactivation_reason`, PHI encrypted fields (name, phone, email, dob — encrypted at rest since 2026-06-29 migration), ABDM extension fields (`abha_address`, `abha_id`, `abha_verified`, `nominee_name`, `nominee_relation`, `nominee_phone`), timestamps

Relationships: has-many consultations, treatment-plans, visits, prescriptions, invoices, wallet-transactions, lab-cases, notes, documents, consents, identifiers, allergies

#### `patient_identifiers`
Purpose: ABDM polymorphic identifiers (ABHA, Aadhaar, PAN, etc.)
Columns: `id`, `patient_id`, `type`, `value`, `verified_at`, `source`, timestamps

#### `consultations`
Purpose: Clinical encounter records (4 types: New, Same Issue, Minor Visit, Emergency)
Columns: `id`, `patient_id`, `branch_id`, `user_id` (doctor), `type` (new/same_issue/minor_visit/emergency), `chief_complaint`, `hopi` (History of Present Illness), `clinical_findings`, `diagnosis`, `diagnosis_risk`, `specialty_findings` (JSON), `treatment_notes`, `follow_up_date`, `is_emergency`, timestamps

#### `treatment_plans`
Purpose: Multi-item treatment proposals with stage management
Columns: `id`, `patient_id`, `branch_id`, `user_id`, `title`, `status` (draft/accepted/in_progress/completed), `total_amount`, `discount`, `notes`, `accepted_at`, timestamps

#### `treatment_plan_items`
Purpose: Line items on a treatment plan (tooth-level)
Columns: `id`, `treatment_plan_id`, `treatment_id`, `tooth_number` (FDI, widened), `surface`, `variant`, `quantity`, `unit_price`, `status`, `notes`, timestamps

#### `treatment_visits`
Purpose: Actual clinical visit records (linked to plan or standalone)
Columns: `id`, `patient_id`, `branch_id`, `user_id`, `treatment_plan_id` (nullable), `visit_type`, `notes`, `teeth_treated` (JSON), vitals: `bp_systolic`, `bp_diastolic`, `pulse`, `spo2`, `temperature`, `blood_sugar`, `weight`, timestamps

#### `treatment_visit_items`
Purpose: Procedures done in a single visit
Columns: `id`, `treatment_visit_id`, `treatment_plan_item_id` (nullable), `treatment_id`, `tooth_number`, `notes`, `is_repeated`, timestamps

#### `prescriptions` (via Prescription models)
Purpose: Drug prescriptions with CDSS checking
Key tables: `prescriptions`, `prescription_items`, `medicines`, `medicine_generics`, `medicine_interactions`, `medicine_allergy_rules`, `prescription_templates`, `medicine_routes`, `dose_duration_templates`

#### `invoices`
Purpose: Patient billing invoices
Columns: `id`, `patient_id`, `branch_id`, `invoice_number`, `status` (draft/issued/paid/partially_paid/void), `subtotal`, `discount`, `tax`, `total`, `notes`, `issued_at`, timestamps

#### `invoice_items`
Columns: `id`, `invoice_id`, `treatment_id` (nullable), `description`, `quantity`, `unit_price`, `discount`, `total`

#### `invoice_payments`
Purpose: Payment records against invoices (cash/card/UPI/EMI/wallet)
Columns: `id`, `invoice_id`, `patient_id`, `amount`, `payment_mode`, `reference_number`, `emi_scheme_id` (nullable), `paid_at`, timestamps

#### `receipts`
Purpose: Official receipt after payment
Columns: `id`, `invoice_payment_id`, `receipt_number`, `amount`, `issued_at`, timestamps

#### `final_bills`
Purpose: Consolidated final bill record (created after full payment)
Columns: `id`, `invoice_id`, `patient_id`, `total`, `paid`, `balance`, timestamps

#### `wallets`
Purpose: Patient credit wallet (advance payment / membership benefit)
Columns: `id`, `patient_id`, `balance`, `currency`, timestamps

#### `wallet_transactions`
Purpose: Credits/debits to patient wallet
Columns: `id`, `wallet_id`, `patient_id`, `type` (credit/debit), `amount`, `description`, `reference_type`, `reference_id`, timestamps

#### `emi_schemes`
Purpose: EMI scheme definitions (Bajaj, CashE, etc.)
Columns: `id`, `name`, `provider`, `tenure_months`, `interest_rate`, `processing_fee`, `is_active`, timestamps

#### `emi_schedules`
Purpose: Per-payment EMI schedule (instalment breakdown)
Columns: `id`, `invoice_payment_id`, `instalment_number`, `due_date`, `amount`, `status`, `paid_at`, timestamps

#### `lab_cases` (v2 enterprise rebuild)
Purpose: Dental lab work orders
Columns: `id`, `patient_id`, `branch_id`, `user_id`, `lab_vendor_id`, `case_number`, `work_type`, `shade`, `status` (draft/sent/in_lab/received/delivered), `due_date`, `amount`, `paid_amount`, `notes`, workflow fields (sent_at, received_at, etc.), timestamps

Related: `lab_case_items`, `lab_case_attachments`, `lab_case_events`, `lab_monthly_reconciliations`, `lab_reconciliation_items`, `lab_reconciliation_events`, `lab_vendors`, `lab_vendor_contacts`, `lab_vendor_services`

#### `appointments`
Purpose: Patient appointment scheduling
Columns: `id`, `patient_id`, `branch_id`, `user_id` (doctor), `operatory_id` (nullable), `appointment_date`, `start_time`, `end_time`, `status` (scheduled/confirmed/in_queue/in_chair/completed/cancelled/no_show), `type`, `chief_complaint`, `notes`, `staff_instruction`, `cancel_reason`, `follow_up_type`, `hidden_from_calendar`, timestamps

#### `inventory_items`
Purpose: Dental supplies and equipment
Columns: `id`, `category_id`, `sub_type_id`, `name`, `sku`, `brand`, `unit`, `reorder_level`, `current_stock`, `cost_price`, `selling_price`, timestamps

Related: `inventory_categories`, `inventory_sub_types`, `inventory_locations`, `inventory_vendors`, `inventory_stocks`, `stock_movements`, `purchase_orders`, `reusable_assets`, `inventory_settings`, `implant_catalog`, `implant_placements`, `product_dealers`

#### HR Tables
- `hr_departments`: Department definitions
- `hr_shifts`: Shift schedules
- `hr_staff_profiles`: Extended staff info (bank, contact, documents)
- `hr_staff_shifts`: Staff↔shift mapping
- `hr_attendance`: Daily attendance records (supports QR scan)
- `hr_entry_exit_logs`: QR scan log
- `hr_salary_components`: Salary structure
- `hr_incentive_rules`: Performance incentive rules
- `hr_staff_advances`: Staff salary advances
- `hr_bonuses`: Bonus records
- `hr_training_sessions`: Training definitions
- `hr_training_enrollments`: Staff enrollment in training
- `hr_periodic_training_requirements`: Compliance training deadlines
- `hr_performance_memos`: HR memos/PIPs
- `finance_payroll`: Monthly payroll records

#### Finance Tables
- `finance_transactions`: General ledger-style transaction register
- `finance_income_entries`: Income records
- `finance_expenses`: Expense records
- `finance_expense_categories`: Expense categorization
- `finance_vendors`: Vendor master
- `finance_vendor_payments`: AP payment records
- `finance_bank_accounts`: Bank account register
- `finance_cashbook`: Cash flow entries
- `finance_gst_records`: GST filing records
- `finance_membership_plans`: Membership plan definitions (AOCP)
- `finance_patient_memberships`: Patient membership enrollments
- `membership_benefit_logs`: Membership benefit usage log
- `finance_vouchers`: Finance vouchers
- `billing_audit_logs`: Billing-specific tamper-evident audit trail
- `billing_prompts`: AI billing suggestions
- `coupon_codes`: Promo coupon definitions
- `coupon_usages`: Coupon redemption log

#### Communication / PRM Tables
- `communication_queues`: Outbound message queue (recall, opportunity, B2B)
- `patient_communications`: Per-patient comm log
- `follow_ups`: Follow-up records
- `follow_up_notes`: Notes on follow-ups
- `leads`: Inbound lead records (UTM, Meta, website)
- `lead_activities`: Lead interaction log
- `treatment_opportunities`: Clinical upsell opportunities (per patient)
- `escalations`: SLA breach escalation records
- `message_templates`: WhatsApp/SMS/email templates
- `wa_threads`: WhatsApp conversation threads
- `wa_messages`: Individual WhatsApp messages

#### DPDP / Consent Tables
- `consent_purposes`: Configurable consent purpose catalogue
- `patient_consents`: Per-patient consent state (granted/withdrawn) + guardian fields
- `consent_logs`: Hash-chained immutable consent audit trail
- `data_requests`: DSAR (Data Subject Access Requests)
- `data_breaches`: Breach incident register
- `retention_policies`: Data retention policy definitions

#### ABDM Tables
- `patient_identifiers`: Polymorphic health identifiers (ABHA, Aadhaar, etc.)
- `practitioner_identifiers`: Doctor HPR IDs
- `practitioner_qualifications`: Doctor qualification records
- `facility_abdm_config`: HFR config per facility
- `branch_settings`: Branch-level config (ABDM + general)
- `fhir_documents`: Generated FHIR document records
- `terminology_maps`: SNOMED/LOINC code mappings
- `patient_allergies`: Structured allergy records (FHIR-ready)

#### AI / Voice Tables
- `ai_conversations`: Tulip assistant conversation sessions
- `ai_messages`: Individual Tulip messages (user + assistant)
- `ai_action_logs`: Confirmed agentic actions (audit trail)
- `voice_notes`: Polymorphic voice recordings + transcripts

#### Reviews / Reputation
- `reviews`: Patient review records (rating, platform, token-gated link)

#### Content / CMS Tables
- `cms_edu_categories`, `cms_edu_items`: Educational content
- `cms_media`, `cms_tags`, `cms_treatment_cases`: CMS content library
- `education_categories`, `education_media`, `education_treatments`: Patient education

#### Other Tables
- `tasks`: Clinic task management (assignee, due date, priority, module link, protocol_id, lab_case_id, po_id)
- `huddle_boards`, `huddle_cards`, `huddle_task_logs`, `huddle_comments`, `huddle_settings`: Daily Huddle Kanban board
- `huddle_notes`: Huddle session notes
- `app_settings`: Global app configuration (key-value store)
- `app_notifications`: In-app notification bell records
- `mobile_otps`: OTP codes for mobile login
- `staff_activity_logs`: Staff action logs
- `personal_access_tokens`: Sanctum API tokens
- `audit_logs`: Tamper-evident, hash-chained audit trail (Phase A)
- `cache`, `jobs`, `failed_jobs`: Laravel system tables
- `tags`, `taggables` (pivot): Polymorphic tagging
- `practice_protocols`, `practice_protocol_materials`: Job Library / SOPs
- `watermark_settings`: Clinical media watermark config
- `clinical_files`: Secure clinical documents
- `clinical_media`: Photos/X-rays attached to consultations
- `patient_documents`: General patient documents
- `patient_notes`: Quick notes per patient
- `patient_alerts`: Clinical alerts (allergy, medical condition flags)
- `patient_relationship_notes`: Family/referral relationship notes
- `complaints`: Patient complaints
- `operatories`: Dental chair/room definitions

### Data Flow Summary
```
Patient → Consultation → TreatmentPlan → TreatmentPlanItems
                                  ↓
                          TreatmentVisit → TreatmentVisitItems
                                  ↓
                             Invoice → InvoiceItems
                                  ↓
                         InvoicePayment → Receipt → FinalBill
                                  ↓                     ↓
                         FinanceTransaction       Wallet (credit)
                                  ↓
                            LabCase → LabCaseItems
```

---

## 5. Authentication

### Web Login Flow
1. GET `/login` → `AuthController@showLogin` → `auth/login.blade.php`
2. POST `/login` → `AuthController@login` (throttled 5/min per IP)
3. Validates credentials via `Auth::attempt()`
4. If user has `two_factor_confirmed_at` set → redirects to `/two-factor/challenge`
5. TOTP code verified via `google2fa` library
6. Session created → redirect to `/dashboard`

### Web Logout
- POST `/logout` → `AuthController@logout` → `Auth::logout()` → session invalidated → redirect to login

### Password Reset (PIN-based)
- POST `/forgot-pin/send` → sends 6-digit PIN to user's phone/email
- POST `/forgot-pin/verify` → validates PIN
- POST `/forgot-pin/reset` → sets new password

### Mobile OTP Login
- POST `/auth/mobile/send-otp` → generates OTP, stores in `mobile_otps` table
- POST `/auth/mobile/verify` → validates OTP → issues Sanctum token

### API Authentication (Mobile)
- POST `/api/v1/auth/login` → email + password → returns `{ token, user }`
- All protected routes: `Authorization: Bearer <token>` header
- Logout: POST `/api/v1/auth/logout` (revokes current token) or `/api/v1/auth/logout-all` (revokes all)
- Token stored in Sanctum's `personal_access_tokens` table

### MFA / Two-Factor
- TOTP (Google Authenticator compatible) via `pragmarx/google2fa-qrcode`
- Setup: GET/POST `/two-factor/setup`, `/two-factor/enable`
- Challenge: GET/POST `/two-factor/challenge` (throttled 5/min)
- Recovery codes: 8 codes, generated and stored encrypted
- Secret and recovery codes stored encrypted (`App\Casts\Encrypted`)

### User Roles (Legacy String System)
Stored as string in `users.role`:
- `admin` — full access
- `doctor` — clinical access
- `resident_dentist` — clinical access (trainee level)
- `associate_dentist` — clinical access
- `visiting_consultant` — clinical access (limited)
- `front_desk` — scheduling, billing, patient registration
- `assistant` — limited clinical support
- `accounts` — finance only

### Role-Module Permission System (New)
- `roles` table: role definitions
- `modules` table: feature module definitions
- `role_module_permissions`: pivot with `can_view`, `can_edit`, `can_delete` booleans
- Used by `CheckModulePermission` middleware (`module:<slug>` or `module:<slug>,edit`)

### Session Handling
- Laravel's default session driver (`SESSION_DRIVER=database` or file)
- `AbsoluteSessionTimeout` middleware: enforces hard session expiry (Phase A) regardless of activity
- Sessions contain user ID, branch context, 2FA verification state

### Middleware Stack
| Middleware | Purpose |
|---|---|
| `AbsoluteSessionTimeout` | Hard session time limit (Phase A security) |
| `CheckModulePermission` | RBAC — can user view/edit/delete this module? |
| `CommunicationModuleAccess` | Extra gate for communication features |
| `EnsureApiRole` | API role check (e.g. `api.role:admin,front_desk`) |
| `EnsureMarketingActive` | Checks marketing module is enabled |
| `SecureApiHeaders` | Sets security headers for API responses |
| `SecureWebHeaders` | Sets HSTS, CSP, X-Frame, etc. for web |

### Guards
- `web` guard: session-based (default)
- `sanctum` guard: token-based (API)

---

## 6. Modules Built

### 6.1 Dashboard
**Status:** ✅ Complete
**Features:** KPI cards (patients today, appointments, revenue, tasks), appointment timeline, pending tasks widget, lab status, daily huddle summary. Chart.js graphs.
**Route:** `/dashboard`
**Controller:** `DashboardController`

### 6.2 Patients
**Status:** ✅ Complete (~95%)
**Features:**
- Full CRUD (create with 5-tab modal: personal, medical, consent, financial, documents)
- AI scan of paper intake forms (Snap-a-Patient) via `qwen2.5vl:7b` Ollama vision model
- Search with filters (name, phone, UHID, branch)
- 12-tab patient profile: Consultations, Treatment Plans, Visits, Prescriptions, Lab Cases, Invoices, Wallet, Membership, Documents, Notes, Communications, Timeline
- Patient deactivate/reactivate
- Tag system (polymorphic)
- Relationship notes
- Treatment opportunity tracking
- ABHA (health ID) capture form (local — no live ABDM API)
- Import/Export (PHPSpreadsheet)
- DPDP consent per patient
**Incomplete:** Full ABDM live integration, document OCR beyond basic scan
**Route prefix:** `/patients`
**Controller:** `PatientController`, `PatientNoteController`, `PatientCommunicationController`, `PatientDocumentController`, `ClinicalFileController`

### 6.3 Appointments & Calendar
**Status:** ✅ Complete
**Features:**
- Appointment CRUD with doctor/operatory assignment
- Calendar view (day/week) with color-coded doctors
- Walk-in flow
- Status transitions: scheduled → confirmed → in_queue → in_chair → completed
- Blocked slots / doctor unavailability
- Cancel with reason
- Follow-up type tracking
- Staff instructions
- Integration into Huddle "Yesterday's Flow"
**Route prefix:** `/appointments`
**Controller:** `AppointmentController` (web), `Api\V1\AppointmentController` (mobile)

### 6.4 Consultations
**Status:** ✅ Complete
**Features:**
- 4 typed workflows:
  - **New:** Full HOPI, clinical findings, specialty module, diagnosis, treatment notes
  - **Same Issue:** Pre-fills from last consultation, tracks same-issue progression
  - **Minor Visit:** Lightweight form for minor/review visits
  - **Emergency:** Emergency flag, rapid entry form
- COHA (Consultation Objective/History Assistant) — AI-assisted clinical notes
- Consultation photos (linked to `clinical_media`)
- Consultation scans
- View/print parity (show + print mirrors create form)
- Specialty findings modules (JSON blob per specialty)
**Route prefix:** `/patients/{patient}/consultations`, `/consultations`
**Controller:** `ConsultationController`

### 6.5 Treatment Plans
**Status:** ✅ Complete
**Features:**
- Multi-item treatment proposal
- FDI tooth number support (per item)
- Treatment variants (e.g. Ceramic vs. Metal crown)
- Status lifecycle: draft → accepted → in_progress → completed
- Accept/revert actions
- Cost summary with discount
- Print PDF
- Link to Treatment Visits
**Route prefix:** `/patients/{patient}/treatment-plans`, `/treatment-plans`
**Controller:** `TreatmentPlanController`

### 6.6 Treatment Visits
**Status:** ✅ Complete
**Features:**
- Record actual clinical work done in a visit
- Link to treatment plan items (marks them done) or standalone
- FDI tooth chart
- Optional vitals section (BP, pulse, SpO2, temp, blood sugar, weight)
- Auto-triggers: billing prompt, draft lab case, 6-month recall task
- Print PDF
- Visit type classification
**Route prefix:** `/patients/{patient}/visits`, `/visits`
**Controller:** `TreatmentVisitController`

### 6.7 Prescriptions
**Status:** ✅ Complete — most complex module
**Features:**
- Full drug database (medicines, generics, routes of administration)
- CDSS (Clinical Decision Support System): drug-drug interaction checks, allergy rule checks
- Dose/duration templates
- Prescription templates (saved for common conditions)
- Drug categories
- Lifecycle: draft → finalized → (version/repeat/cancel)
- Repeat prescription workflow
- Print PDF (clinic letterhead)
- Food instructions, special warnings
**Route prefix:** `/prescriptions` (from `routes/prescriptions.php`)
**Controllers:** Full suite under `Http/Controllers/Prescription/`
**Services:** Prescription services under `Services/Prescription/`

### 6.8 Lab Cases (v2 Enterprise Rebuild)
**Status:** ✅ Complete (~90%)
**Features:**
- Full lab case lifecycle: draft → sent → in_lab → received → delivered
- Lab vendor management (contacts, services, digital email)
- Case items (multiple work types per case)
- Attachments (photos, X-rays)
- Event log (status history)
- Monthly reconciliation (invoice matching with lab vendor)
- Auto-create overdue tasks for stuck cases
- Finance integration (expense auto-create on receive)
- Duplicate/archive
- Lab alerts service
**Route prefix:** `/lab`
**Controllers:** `LabController`, `LabVendorController`, `LabReconciliationController`

### 6.9 Inventory
**Status:** ✅ Complete (~85%)
**Features:**
- Item master (categories, sub-types, brand, SKU, unit, reorder level)
- Stock management (current stock, movements)
- Stock-in / stock-out
- Purchase Orders (PO) → GRN (Goods Received Note) workflow
- Vendor management
- Reusable assets tracking
- Implant catalog + placement records
- Stock count (periodic physical count)
- Product dealer master
**Route prefix:** `/inventory`
**Controller:** `InventoryController`, `StockCountController`

### 6.10 Procurement
**Status:** ✅ Complete
**Features:**
- Full PO → GRN → Vendor Invoice → Accounts Payable chain
- Auto-creates `FinanceExpense` (AP) on GRN receive
- Vendor invoice CRUD with auto-AP sync
- `VendorInvoiceController` for standalone invoice entry
**Controller:** `VendorInvoiceController`

### 6.11 Billing & Finance
**Status:** ✅ Complete (~90%)

**Billing sub-module:**
- Invoice creation, editing, void
- Multiple payment modes: cash, card, UPI, EMI, wallet
- EMI scheme management (Bajaj, CashE, etc.)
- EMI schedule (instalment breakdown)
- Wallet credit/debit
- Receipt generation
- Final bill generation
- Payment mark-provider-paid
**Controller:** `BillingController`

**Finance sub-module:**
- Expense management (categories, vendors)
- Cashbook
- Bank account register
- GST records
- Payroll processing
- Finance transactions (ledger)
- Vendor payments (AP)
- Vouchers
- Finance analytics + reports
**Controllers:** `Finance/FinanceController`, `Finance/AnalyticsController`, `Finance/WalletController`, `Finance/CouponController`, `Finance/MembershipController`, `Finance/VoucherController`, `Finance/WalletCampaignController`

**Memberships (AOCP):**
- Membership plan definitions
- Patient enrollment with finance chain (invoice + payment + receipt + transaction all auto-created)
- Benefit log tracking
- Membership benefit service (`MembershipBenefitService::enrollWithFinance`)
**Status:** ✅ Tested working end-to-end

**Coupons:**
- Coupon CRUD, validation, usage tracking

**Known issue:** 6 feature tests currently failing, specifically for the `finance payment → receipt → ledger → mark-invoice-paid` path. The code exists; the tests may be stale. Must be re-run and made green before launch.

### 6.12 HR (Human Resources)
**Status:** ✅ Complete (~85%)
**Features:**
- Department and shift management
- Staff profiles (extended info, bank details, emergency contacts)
- Attendance tracking (manual + QR scan via `/hr/scan` — public no-auth endpoint)
- Entry/exit log
- Salary components and payroll
- Incentive rules
- Staff advances and bonuses
- Training sessions, enrollment, periodic training requirements
- Performance memos
- Auto-absent marking (`HrMarkAbsent` artisan command)
**Route prefix:** `/hr`
**Controllers:** Under `Http/Controllers/HR/`

### 6.13 Communication OS
**Status:** ✅ Built (~85%), **UNTESTED** in production

Four engines:
1. **Recall Engine** — Auto-queues 6-month recall messages. `RunRecallEngine` command + `RecallEngineService`. Daily cron.
2. **Opportunity Engine** — PRM pipeline for treatment opportunities. Lead stages, SLA tracking.
3. **Inbound Leads** — UTM-attributed website leads, Meta Lead Form webhook, AI enrichment (lead scoring via Ollama).
4. **B2B Queue** — Vendor/lab/consultant outreach queue.

Plus:
- **Unified Inbox** (`/communication/inbox`) — all inbound messages
- **My Queue** — staff's pending tasks from communication
- **KPI Dashboard** — comm module metrics
- **Daily Digest Emails** — morning briefing (7:05am), SLA alert (2pm), evening summary (6pm)
- **WhatsApp Two-Way** — `WaThread`/`WaMessage`, `WhatsAppCloudService`, inbound webhook at `Webhooks/WhatsAppLeadController`
- **Appointment Reminders** — `WhatsAppSendReminders` command, daily 10am, idempotent
- **Templates** — configurable message templates (WhatsApp/SMS/email)
- **Reviews** — patient review request, public `/r/{token}` rating page, admin reviews dashboard, `reviews:request` command (daily 11am)

**Incomplete/TODO:** Meta WhatsApp credentials + approved templates; inbound webhook needs public URL (tunnel in dev); recall auto-wire to confirm-first flow.

### 6.14 Marketing Hub
**Status:** ✅ Built (~80%)
**Features:**
- Campaigns (CRUD, goals, budget, team assignment)
- Brainstorm / Idea Board
- Content Calendar
- Publish Engine (Instagram, Facebook, Google Business — OAuth integration)
- Asset Library (brand kit, folders, tags)
- Platform Integrations (OAuth flow for Meta, Google)
- Marketing Intelligence / Analytics
- Lead scoring service (`MarketingScoreService`)
- Campaign-lead service (`CampaignLeadService`)
**Controllers:** Under `Http/Controllers/Marketing/`
**Services:** Under `Services/Marketing/`
**Incomplete:** Live publish testing; OAuth tokens in production; analytics depth

### 6.15 Daily Huddle
**Status:** ✅ Complete
**Features:**
- Kanban board (huddle_boards, huddle_cards with drag-drop)
- Morning briefing data (yesterday's flow, today's schedule, pending tasks)
- Task layer (create, assign, status update)
- Staff list
- Huddle notes
- Huddle comments
- "Yesterday's Flow" marks "Visit Logged" if consultation OR treatment visit exists
- Period reports (weekly/monthly/quarterly/annual)
**Route prefix:** `/huddle`
**Controllers:** `Modules/Huddle/Controllers/`

### 6.16 Tasks
**Status:** ✅ Complete
**Features:**
- Task CRUD (title, description, assignee, due date, priority, module link)
- Links to: purchase orders, lab cases, practice protocols
- Recurring/maintenance tasks
- Task reminders (artisan commands: `TaskPeriodicReminder`, `TaskShiftReminder`)
- Integration into Huddle board
**Model:** `Task`

### 6.17 PRM (Patient Relationship Management)
**Status:** ✅ Built (~80%)
**Features:**
- Opportunity pipeline (treatment upsells per patient)
- Follow-up management with notes
- SLA tracking and auto-escalation
- Lead management (source attribution)
- AI enrichment for leads (Ollama)
- Follow-up rules service
**Controllers:** `CRMController`
**Services:** `Services/Prm/`, `Services/Communication/FollowUpRulesService`
**Route prefix:** `/prm` (from `routes/prm.php`)

### 6.18 Clinical Library
**Status:** ✅ Built (~80%)
**Features:**
- Treatment knowledge base (clinical protocols + best-practice notes)
- SOPs (Standard Operating Procedures)
- Treatment rules
- Practice Protocols + Protocol Materials (Job Library)
- Practice Protocol seeder (pre-seeded dental protocols)
- Auto-generate tasks from protocol (`GenerateProtocolTasks` command)
**Controllers:** Under `Http/Controllers/`; `Modules/PracticeProtocols/`
**Services:** `Services/ClinicalLibrary/`
**Route prefix:** `/clinical-library` (from `routes/clinical-library.php`), `/practice-protocols`

### 6.19 Content Management (CMS)
**Status:** ✅ Built (~75%)
**Features:**
- Educational content categories and items
- Media management
- Treatment cases (case study library)
- Tags
- Patient education content
**Controllers:** `Cms/`, `ContentManagement/`
**Route prefix:** `/cms`, `/content-management`

### 6.20 Reports
**Status:** ✅ Built (~75%)
**Features:**
- Appointments tab
- Revenue tab
- Patients tab
- Treatments tab
- Lab tab
- Inventory tab
- Finance-specific reports (under Finance module)
**Controller:** `ReportsController`, `Finance/FinanceReportsController`
**Route:** `/reports`

### 6.21 Settings
**Status:** ✅ Complete
**Features:**
- Role and module management (RBAC admin UI)
- Treatment masters (categories, types)
- App personalisation (watermark, logo)
- Notification preferences
- Branch management
- User management
**Controllers:** Under `Http/Controllers/Settings/`
**Route prefix:** `/settings`

### 6.22 AI Assistant "Tulip"
**Status:** ✅ Built, environment-bound
**Features:**
- App-wide chat assistant (floating widget)
- Routes clinical questions to `llama3.1:8b`, admin/data questions to `qwen2.5:7b`
- Agentic mode with confirm-card UI (requires user confirmation before clinical/financial actions)
- Tool registry (`Services/Assistant/Tools/`) for structured actions
- `TulipChat`, `TulipHuddle`, `TulipPull`, `TulipTranscribe` artisan commands
- Voice transcription via `faster-whisper` GPU
- Receipt scan / Snap-a-Bill via `qwen2.5vl:7b` vision model
- Paper intake form scan (Add Patient modal)
**Dependency:** Requires local Ollama + `faster-whisper` on GPU machine. Will NOT work on generic VPS without this setup.
**Controller:** `AiAssistantController`
**Tables:** `ai_conversations`, `ai_messages`, `ai_action_logs`

### 6.23 Voice Notes
**Status:** ✅ Built, environment-bound
**Features:**
- Record audio in-browser → sent to backend → transcribed by `faster-whisper` → formatted by Ollama into clinical notes
- Polymorphic — attachable to any model (consultation, visit, note, etc.)
**Command:** `VoiceNoteTest`
**Model:** `VoiceNote`
**Table:** `voice_notes`

### 6.24 DPDP Compliance Module (Wave 5)
**Status:** ✅ Built (~90%)
**Features:**
- Consent purpose catalogue (admin-configurable)
- Per-patient consent capture, update, withdraw
- Immutable hash-chained consent audit trail (`consent_logs`)
- DSAR (Data Subject Access Request) management with download + erase
- Data breach incident register with board notification workflow
- Retention policies (data retention rules, dry-run mode)
**Routes:** `/consent`, `/data-rights`, `/breaches`, `/retention`
**Controllers:** `ConsentController`, `DataRequestController`, `DataBreachController`, `RetentionController`
**Services:** `ConsentService`, `DataRightsService`, `RetentionService`, `BreachService`
**Legal deadline:** DPDP Act enforcement May 13, 2027 (₹250 Cr penalties)

### 6.25 Security (Phase A — 4 Sprints)
**Status:** ✅ Code complete, pending user-side migration + backfill
**Features:**
- PHI encryption at rest (Patient name, phone, email, dob; finance fields; consultation PHI)
- Tamper-evident hash-chained audit log (`audit_logs` table, `HashChained` trait)
- `audit:verify` artisan command to detect tampering
- Event logging (all CRUD actions)
- Login throttle (5/min per IP)
- Password policy enforcement
- Absolute session timeout middleware
- HTTPS/HSTS/security headers (`SecureWebHeaders`, `SecureApiHeaders`, `config/security.php`)
- BranchScope (query-level branch isolation)
- MFA (TOTP via `google2fa`)
- `security:selftest` artisan command
**Verify:** `php artisan security:selftest`
**Commands:** `AuditVerify`, `EncryptPatientPhi`, `SecureClinicalMedia`

### 6.26 ABDM / FHIR Layer
**Status:** 🟡 Design + skeleton only — NOT live-integrated with NHA
**Features (designed, not live):**
- 9 architecture design docs in `docs/abdm/`
- ABHA (Health ID) local capture form (works offline)
- Patient/Practitioner/Facility identifier models
- FHIR builders, bundles, mappers, validators (code scaffold)
- Terminology maps (SNOMED/LOINC)
- `FhirPreviewPatient`, `FhirShow` artisan commands (preview only)
**Note:** ABDM sandbox registration and live API integration are NOT done. This is design + preparatory code only.

### 6.27 Notifications
**Status:** ✅ Complete
**Features:**
- In-app notification bell
- `app_notifications` table
- `AppNotification` model
- `NotificationsController`
**Route:** `/notifications`

---

## 7. UI Screens

### Web Screens (Blade)

| Screen | Route | Purpose | Status |
|---|---|---|---|
| Login | `/login` | Auth | ✅ |
| Two-Factor Challenge | `/two-factor/challenge` | MFA code entry | ✅ |
| Two-Factor Setup | `/two-factor/setup` | Enable/disable TOTP | ✅ |
| Dashboard | `/dashboard` | KPI overview | ✅ |
| Patients List | `/patients` | Search, filter, list | ✅ |
| Add Patient (modal) | `/patients/create` | 5-tab modal form | ✅ |
| Patient Profile | `/patients/{id}` | 12-tab clinical profile | ✅ |
| Patient Edit | `/patients/{id}/edit` | Edit demographics | ✅ |
| ABHA Capture | `/patients/{id}/abha` | Health ID form | ✅ (local) |
| Consent Trail | `/consent/patient/{id}` | DPDP consent view | ✅ |
| New Consultation | `/patients/{id}/consultations/create` | Full clinical form | ✅ |
| Same Issue Consult | `/patients/{id}/consultations/same-issue` | Pre-filled consult | ✅ |
| Minor Visit | `/patients/{id}/consultations/minor-visit` | Lightweight visit | ✅ |
| Emergency Consult | `/patients/{id}/consultations/emergency` | Emergency form | ✅ |
| Consultation Show | `/consultations/{id}` | View + print | ✅ |
| Treatment Plans List | `/patients/{id}/treatment-plans` | Plan list | ✅ |
| Treatment Plan Create | `/patients/{id}/treatment-plans/create` | Multi-item plan | ✅ |
| Treatment Plan Show | `/treatment-plans/{id}` | View + accept | ✅ |
| Visit Create | `/patients/{id}/visits/create` | Visit recording form | ✅ |
| Visit Show | `/visits/{id}` | View visit details | ✅ |
| Prescriptions | `/prescriptions` | Drug write-pad | ✅ |
| Drug Database | `/prescriptions/drugs` | Drug master | ✅ |
| Appointments | `/appointments` | Calendar + list | ✅ |
| Lab Cases | `/lab` | Case board | ✅ |
| Lab Case Show | `/lab/{id}` | Case detail | ✅ |
| Lab Vendors | `/lab/vendors` | Vendor management | ✅ |
| Inventory | `/inventory` | Items + stock | ✅ |
| Purchase Orders | `/inventory/purchase-orders` | PO list | ✅ |
| Billing | `/billing` | Invoice list | ✅ |
| Invoice Show | `/billing/{id}` | Invoice + payments | ✅ |
| Finance Dashboard | `/finance` | Expense + cashbook | ✅ |
| Wallet | `/finance/wallet` | Patient wallet register | ✅ |
| Memberships | `/finance/memberships` | AOCP plans | ✅ |
| Payroll | `/finance/payroll` | Payroll processing | ✅ |
| HR Dashboard | `/hr` | Staff overview | ✅ |
| HR Attendance | `/hr/attendance` | Daily attendance | ✅ |
| QR Scan (public) | `/hr/scan` | Staff QR check-in (no auth) | ✅ |
| Daily Huddle | `/huddle` | Kanban board | ✅ |
| Huddle Reports | `/huddle/report` | Period reports | ✅ |
| Communication Inbox | `/communication/inbox` | Unified inbox | ✅ |
| Communication KPI | `/communication/kpi` | Metrics dashboard | ✅ |
| WhatsApp Inbox | `/communication/whatsapp` | WA thread list | ✅ |
| Reviews Dashboard | `/communication/reviews` | Reputation management | ✅ |
| PRM Pipeline | `/prm` | Lead/opportunity board | ✅ |
| Marketing Hub | `/marketing` | Campaign overview | ✅ |
| Marketing Calendar | `/marketing/calendar` | Content calendar | ✅ |
| Marketing Assets | `/marketing/assets` | Asset library | ✅ |
| Clinical Library | `/clinical-library` | Protocol/SOP list | ✅ |
| Practice Protocols | `/practice-protocols` | Job Library | ✅ |
| Reports | `/reports` | Multi-tab analytics | ✅ |
| Settings — Roles | `/settings/roles` | RBAC management | ✅ |
| Settings — Masters | `/settings/masters` | Treatment masters | ✅ |
| Data Rights | `/data-rights` | DSAR management | ✅ |
| Breach Register | `/breaches` | Breach incident register | ✅ |
| Consent Purposes | `/consent/purposes` | DPDP consent catalogue | ✅ |
| Retention | `/retention` | Data retention policy | ✅ |
| Profile | `/profile` | User profile + avatar | ✅ |
| Notifications | `/notifications` | Notification bell list | ✅ |
| Help | `/help` | Help & support | ✅ |
| Public Review | `/r/{token}` | Patient rating page | ✅ |

Total: ~50+ distinct screen routes, ~178 pages rendering HTTP 200 (per route crawler, June 2026)

---

## 8. APIs

Base URL: `/api/v1`
Auth: `Authorization: Bearer <sanctum_token>` (except public routes)
Rate limit: 120/min globally, 5/min on auth endpoints

### Public Endpoints
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/ping` | Health check |
| POST | `/api/v1/auth/login` | Exchange email+password for Sanctum token |

### Auth Endpoints (Sanctum-protected)
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/auth/me` | Get current user profile |
| PUT | `/api/v1/auth/me` | Update profile |
| POST | `/api/v1/auth/logout` | Revoke current token |
| POST | `/api/v1/auth/logout-all` | Revoke all tokens |

### Patient Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/patients` | Any | Paginated list |
| GET | `/api/v1/patients/search` | Any | Search by name/phone/UHID |
| GET | `/api/v1/patients/{id}` | Any | Full patient detail |
| POST | `/api/v1/patients` | admin, front_desk | Create patient |
| PUT/PATCH | `/api/v1/patients/{id}` | admin, front_desk | Update patient |
| POST | `/api/v1/patients/{id}/deactivate` | admin | Deactivate |

### Patient Profile Tabs (Read-only)
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/patients/{id}/consultations` | Consultation list |
| GET | `/api/v1/patients/{id}/treatment-plans` | Treatment plans |
| GET | `/api/v1/patients/{id}/visits` | Visit history |
| GET | `/api/v1/patients/{id}/lab-cases` | Lab cases |
| GET | `/api/v1/patients/{id}/prescriptions` | Prescriptions |
| GET | `/api/v1/patients/{id}/invoices` | Invoice list |
| GET | `/api/v1/patients/{id}/wallet` | Wallet info |
| GET | `/api/v1/patients/{id}/documents` | Documents |
| POST | `/api/v1/patients/{id}/documents` | Upload document |
| GET | `/api/v1/patients/{id}/notes` | Notes |
| GET | `/api/v1/patients/{id}/communications` | Communications |
| GET | `/api/v1/patients/{id}/memberships` | Memberships |

### Consultation Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/consultations/{id}` | Any | Full consult record |
| PUT | `/api/v1/consultations/{id}` | doctor roles | Update |
| GET | `/api/v1/patients/{id}/consultations/same-issue-context` | Any | Context for same-issue |
| POST | `/api/v1/patients/{id}/consultations` | doctor roles | New consultation |
| POST | `/api/v1/patients/{id}/consultations/same-issue` | doctor roles | Same-issue |
| POST | `/api/v1/patients/{id}/consultations/minor-visit` | doctor roles | Minor visit |
| POST | `/api/v1/patients/{id}/consultations/emergency` | doctor roles | Emergency |

### Treatment Plan Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/treatments` | Any | Treatment master list |
| GET | `/api/v1/treatment-plans/{id}` | Any | Plan detail |
| POST | `/api/v1/patients/{id}/treatment-plans` | admin, front_desk | Create |
| PUT | `/api/v1/treatment-plans/{id}` | admin, front_desk | Update |
| POST | `/api/v1/treatment-plans/{id}/accept` | admin, front_desk | Accept plan |
| POST | `/api/v1/treatment-plans/{id}/revert` | admin, front_desk | Revert acceptance |

### Treatment Visit Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/patients/{id}/visits/form-options` | Any | Form dropdowns |
| GET | `/api/v1/visits/{id}` | Any | Visit detail |
| POST | `/api/v1/patients/{id}/visits` | doctor roles | Create visit |
| PUT | `/api/v1/visits/{id}` | doctor roles | Update visit |
| DELETE | `/api/v1/visits/{id}` | doctor roles | Delete visit |

### Prescription Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/rx/drugs/search` | Any | Drug search |
| GET | `/api/v1/rx/form-options` | Any | Form data |
| POST | `/api/v1/rx/check-alerts` | Any | CDSS check |
| POST | `/api/v1/rx/check-repeat` | Any | Repeat check |
| GET | `/api/v1/prescriptions/{id}` | Any | Rx detail |
| POST | `/api/v1/patients/{id}/prescriptions` | doctor roles | Create |
| PUT | `/api/v1/prescriptions/{id}` | doctor roles | Update |
| POST | `/api/v1/prescriptions/{id}/finalize` | doctor roles | Finalize |
| POST | `/api/v1/prescriptions/{id}/repeat` | doctor roles | Repeat |
| POST | `/api/v1/prescriptions/{id}/cancel` | doctor roles | Cancel |

### Appointment Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/appointments` | Any | List |
| GET | `/api/v1/appointments/today` | Any | Today's schedule |
| GET | `/api/v1/appointments/form-options` | Any | Dropdowns |
| GET | `/api/v1/appointments/blocked-slots` | Any | Blocked times |
| GET | `/api/v1/appointments/{id}` | Any | Detail |
| POST | `/api/v1/appointments` | admin, front_desk | Create |
| POST | `/api/v1/appointments/walk-in` | admin, front_desk | Walk-in |
| POST | `/api/v1/appointments/block-slot` | admin, front_desk | Block slot |
| PATCH | `/api/v1/appointments/{id}/status` | admin, front_desk | Update status |
| PATCH | `/api/v1/appointments/{id}/cancel` | admin, front_desk | Cancel |

### Billing Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/billing/invoices` | Any | Invoice list |
| GET | `/api/v1/billing/summary` | Any | KPI summary |
| GET | `/api/v1/patients/{id}/open-invoices` | Any | Unpaid invoices |
| POST | `/api/v1/patients/{id}/wallet/credit` | Any | Wallet top-up |
| POST | `/api/v1/invoices` | Any | Create invoice |
| GET | `/api/v1/invoices/{id}/payment-options` | Any | Payment mode options |
| GET | `/api/v1/invoices/{id}/receipts/{rid}` | Any | Receipt detail |
| POST | `/api/v1/invoices/{id}/payments` | admin, front_desk | Record payment |
| POST | `/api/v1/invoices/{id}/payments/{pid}/mark-provider-paid` | admin, front_desk | Mark EMI provider paid |

### Lab Endpoints (Mobile, Read-only)
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/lab/cases` | Case list |
| GET | `/api/v1/lab/summary` | KPI summary |
| GET | `/api/v1/lab/cases/{id}` | Case detail |

### Dashboard & Reports
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/dashboard` | Mobile home screen KPIs |
| GET | `/api/v1/reports/overview` | Analytics overview |

### Huddle Endpoints
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/huddle` | Any | Board data |
| GET | `/api/v1/huddle/tasks` | Any | Task list |
| GET | `/api/v1/huddle/staff` | Any | Staff list |
| POST | `/api/v1/huddle/comms/push` | admin, front_desk, doctor | Push comms |
| POST | `/api/v1/huddle/tasks` | admin, front_desk, doctor | Create task |
| PATCH | `/api/v1/huddle/tasks/{id}/status` | admin, front_desk, doctor | Update status |
| PATCH | `/api/v1/huddle/tasks/{id}/assign` | admin, front_desk | Assign task |

### Inventory Endpoints (Mobile)
| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/inventory/meta` | Any | Categories, units |
| GET | `/api/v1/inventory/items` | Any | Item list |
| GET | `/api/v1/inventory/items/{id}` | Any | Item detail |
| GET | `/api/v1/inventory/products` | Any | Product list |
| POST | `/api/v1/inventory/products` | Any | Create product |
| GET | `/api/v1/inventory/vendors` | Any | Vendor list |
| GET | `/api/v1/inventory/implants/catalog` | Any | Implant catalog |
| GET | `/api/v1/inventory/implants/placements` | Any | Placements |
| GET | `/api/v1/inventory/purchase-orders` | Any | PO list |
| GET | `/api/v1/inventory/purchase-orders/{id}` | Any | PO detail |
| PUT | `/api/v1/inventory/items/{id}` | admin, front_desk | Update item |
| POST | `/api/v1/inventory/items/{id}/adjust` | admin, front_desk | Adjust stock |
| POST | `/api/v1/inventory/stock-in` | admin, front_desk | Stock in |
| POST | `/api/v1/inventory/stock-out` | admin, front_desk | Stock out |
| POST | `/api/v1/inventory/purchase-orders` | admin, front_desk | Create PO |

### Membership Endpoints
| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/membership/plans` | Plan list |
| GET | `/api/v1/membership/active-members` | Active members |
| GET | `/api/v1/patients/{id}/membership-benefits` | Benefit log |
| POST | `/api/v1/patients/{id}/membership/enroll` | Enroll patient |

### Webhook Endpoints (No auth — external services call these)
| Method | Endpoint | Source |
|---|---|---|
| POST | `/webhooks/website-lead` | Website contact forms |
| POST | `/webhooks/meta-lead` | Meta Lead Ads |
| POST | `/webhooks/whatsapp` | Meta WhatsApp Cloud API |
| POST | `/webhooks/chatbot` | Chatbot integration |

---

## 9. Reusable Components & Traits

### Blade Components (Partials)
Located in `resources/views/components/` and `resources/views/partials/`:
- Layout (`layouts/app.blade.php`) — topbar, sidebar, content wrapper
- Topbar with notification bell, user menu
- Sidebar with module-gated nav items
- Patient profile tab component (reused across all 12 tabs)
- Huddle Kanban card component
- Alert/toast notification component
- Modal wrapper
- FDI tooth chart component
- Data table with search/filter
- Timeline preview banner
- KPI card component (stat cards used across multiple modules)

### PHP Traits
- `Auditable` (`app/Traits/Auditable`) — auto-logs CRUD actions to `audit_logs`. Used on nearly every model.
- `BranchScoped` (`app/Models/Scopes/BranchScope`) — global query scope that auto-filters by `auth()->user()->branch_id`. Applied via Eloquent model `booted()`.
- `HashChained` — applied to audit tables for tamper-evidence (each row's hash includes previous row's hash)

### Service Classes (Reused across web + API)
- `PatientService` — shared by web `PatientController` and API `V1\PatientController`. Reduces web controller from 633 → 394 lines.
- `AppointmentService` — shared by web and API appointment controllers
- `TreatmentVisitService` — shared by web and API; auto-triggers billing prompt, lab case draft, recall task
- `MembershipBenefitService::enrollWithFinance` — shared by web and API; runs full finance chain
- `InvoicePaymentService` — shared billing logic
- `InventoryService` — shared inventory logic
- `RecallEngineService` — recall automation

### Eloquent API Resources
Located in `app/Http/Resources/`:
- JSON transformers for all API responses
- Consistent envelope format: `{ success, data, message, meta }`

---

## 10. State Management

### Web (Server-side + Alpine.js)
- **No global client-side store.** All data is server-rendered via Blade.
- **Alpine.js `x-data`** handles local component state: modals open/close, tab switching, form step navigation, AJAX fetch results, dropdown state.
- **API caching:** None explicit (no Redis/Memcached). Relies on MySQL query performance.
- **Loading states:** Alpine.js `x-show` with loading spinner patterns.
- **Error handling:** Laravel `@error` directives for form validation; try-catch in service layer with user-friendly error flashes.
- **Optimistic updates:** Not implemented. All writes are server-round-trip.

### Mobile (Flutter — location unconfirmed in mounted workspace)
- `setState` for local widget state (confirmed pattern from build sessions)
- API calls via `api_client.dart` service layer
- No confirmed global state management library (no Provider/Riverpod/Bloc explicitly confirmed in accessible code)
- Receipts and PDFs fetched on demand, no local DB cache
- **Offline support:** Not implemented. App requires active connection.

---

## 11. Current Features Checklist

### Clinical
- ✅ Patient CRUD (full 5-tab modal)
- ✅ Patient profile 12 tabs
- ✅ Patient import/export (Excel)
- ✅ Paper form scan → patient (AI vision)
- ✅ Consultations — 4 typed workflows
- ✅ Consultation view + print parity
- ✅ Treatment Plans (multi-item, FDI)
- ✅ Treatment Visits (tooth chart, vitals)
- ✅ Prescriptions (full CDSS, drug DB, templates)
- ✅ Clinical files (secure upload)
- ✅ Clinical photos/scans
- ✅ Voice notes (requires local AI)
- ✅ Patient alerts (allergy, medical flags)
- ✅ Patient notes
- ✅ Patient documents
- ✅ Patient tags
- ✅ Patient communications log
- ✅ Patient timeline
- ✅ ABHA capture (local)
- 🟡 ABDM live integration (skeleton only)
- ❌ DICOM imaging viewer

### Operations
- ✅ Appointments + calendar
- ✅ Lab Cases v2 (enterprise)
- ✅ Lab vendor management
- ✅ Lab monthly reconciliation
- ✅ Inventory (items, stock, PO, GRN)
- ✅ Implant catalog + placements
- ✅ Stock count
- ✅ Procurement (PO→GRN→AP chain)
- ✅ Daily Huddle (Kanban + tasks)
- ✅ Huddle period reports
- ✅ Task management

### Finance
- ✅ Invoice creation + payment
- ✅ Multiple payment modes (cash/card/UPI/EMI/wallet)
- ✅ EMI scheme management
- ✅ Wallet (credit/debit)
- ✅ Receipt generation
- ✅ Final bill
- ✅ Memberships (AOCP) + finance chain
- ✅ Coupons
- ✅ Finance expenses + cashbook
- ✅ Bank accounts
- ✅ Payroll
- ✅ GST records
- ✅ Vendor payments (AP)
- ✅ Vendor invoices (auto-AP)
- ✅ Finance analytics
- 🟡 Finance payment automated tests (6 tests currently FAILING — must fix before launch)

### HR
- ✅ Staff profiles
- ✅ Departments + shifts
- ✅ Attendance (manual + QR)
- ✅ Entry/exit log
- ✅ Payroll processing
- ✅ Salary components + incentive rules
- ✅ Staff advances + bonuses
- ✅ Training sessions + enrollment
- ✅ Performance memos
- 🟡 Full payroll automation (manual steps remain)

### Communication & PRM
- ✅ Recall engine (auto-queue, scheduler)
- ✅ Opportunity pipeline
- ✅ Inbound leads (UTM, Meta webhook)
- ✅ B2B queue
- ✅ Unified inbox
- ✅ Daily digest emails (morning/SLA/evening)
- ✅ WhatsApp two-way messaging (built, untested live)
- ✅ Appointment reminders via WhatsApp
- ✅ Message templates
- ✅ Reviews / reputation management
- 🟡 WhatsApp live testing (needs Meta credentials + approved templates)
- ❌ SMS gateway integration

### Marketing
- ✅ Campaigns (CRUD, goals, budget)
- ✅ Brainstorm / idea board
- ✅ Content calendar
- ✅ Asset library
- ✅ Platform OAuth (Meta, Google)
- 🟡 Live publish (needs live OAuth tokens)
- ❌ Analytics API pull (impressions, reach — planned)

### Security & Compliance
- ✅ PHI encryption at rest
- ✅ Hash-chained audit trail
- ✅ MFA (TOTP)
- ✅ Login throttle
- ✅ Absolute session timeout
- ✅ Security headers
- ✅ RBAC (role-module permission system)
- ✅ BranchScope (query-level isolation)
- ✅ DPDP consent management
- ✅ DSAR (data subject access requests)
- ✅ Breach register
- ✅ Retention policies
- 🟡 MFA enforcement by role (optional, not enforced)
- ❌ SOC 2 / ISO 27001 certification (future)

### AI / Automation
- ✅ Tulip AI assistant (local Ollama)
- ✅ Voice notes + transcription (local)
- ✅ Receipt scan / Snap-a-Bill (local vision AI)
- ✅ Patient intake form scan (local vision AI)
- ✅ AI lead enrichment/scoring
- ❌ Cloud AI fallback (no Anthropic/OpenAI API integration yet for production)

### Mobile App
- ✅ Login + auth (Sanctum)
- ✅ Patient list + search
- ✅ Patient profile (12 tabs, read-only)
- ✅ Add Patient
- ✅ Consultations (4 workflows, create)
- ✅ Treatment Plans (create/edit/accept)
- ✅ Treatment Visits (create)
- ✅ Prescriptions (full CDSS parity)
- ✅ Membership enrollment
- ✅ Billing (invoice list, record payment)
- ✅ Appointments (today + schedule)
- ✅ Daily Huddle (board + tasks)
- ✅ Inventory (read + write)
- ✅ Lab module (read-only)
- ✅ Reports overview
- ✅ Settings (server URL config, theme, notifications)
- 🟡 Record Payment Part B (EMI flow) — pending
- 🟡 Home quick-add (Add Patient FAB, Add Appointment FAB)
- ❌ Offline support
- ❌ Push notifications
- ❌ Dark mode (partial)

### Deployment
- ✅ Docker Compose kit (Dockerfile, docker-compose.yml, nginx, php config)
- ✅ deploy.sh + backup.sh scripts
- ✅ DEPLOY.md runbook
- ✅ Hostinger VPS purchased (187.127.152.68)
- ❌ DNS pointed to VPS
- ❌ SSH + Docker installed on VPS
- ❌ App live on VPS
- ❌ SSL/HTTPS configured
- ❌ Backups tested/running
- ❌ Queue worker running in production

---

## 12. Known Bugs

### Bug 1: Finance Payment Tests Failing
- **Cause:** 6 feature tests failing — `finance payment → receipt → ledger → mark-invoice-paid`, inventory item create, inventory stock-in movement, lab-case auto-close, recall-engine-queues-recall, appointment status flow/revert. Likely factory drift or environment mismatch, not necessarily broken logic.
- **Impact:** CRITICAL — the money path must be verified before launch.
- **Priority:** P0
- **Fix:** Re-run `php artisan test` locally; identify exact assertion failures; fix factories/seeds if needed.

### Bug 2: Two Stray Zero-Byte Files in Root
- **Files:** `canAccess('practice_protocols')]` and `toArray()` in project root
- **Cause:** Shell redirect accidents during development
- **Impact:** Low — cosmetic, harmless
- **Fix:** Delete both files

### Bug 3: APP_DEBUG=true in .env
- **Cause:** Local dev config never changed
- **Impact:** CRITICAL in production — will expose stack traces, database credentials, and internal paths to public users
- **Fix:** Set `APP_DEBUG=false` and `APP_ENV=production` before deploying

### Bug 4: PHI Encryption Migration Not Run
- **Cause:** The `encrypt_patient_phi_columns` migration exists but requires backfill of existing records
- **Impact:** HIGH — existing patients' PHI is unencrypted until migration + `php artisan phi:encrypt` is run
- **Fix:** Run migration, then run `EncryptPatientPhi` artisan command against existing data

### Bug 5: Queue Worker Not Running
- **Cause:** `QUEUE_CONNECTION=database` but no queue worker runs automatically
- **Impact:** HIGH — recall jobs, notification jobs, email jobs silently do nothing without worker
- **Fix:** `docker-compose.yml` already includes a `queue` service — confirm it starts in production

### Bug 6: Mail Not Sending
- **Cause:** `MAIL_MAILER=log` in local config
- **Impact:** MEDIUM — password reset, notifications, morning briefing emails all go to log file only
- **Fix:** Configure Brevo SMTP credentials in production `.env`

### Bug 7: AI Features Environment-Bound
- **Cause:** Tulip, voice notes, vision scan all require Ollama + faster-whisper on local GPU
- **Impact:** MEDIUM — these features simply won't work on VPS without GPU/Ollama setup
- **Fix:** Either (a) gate off AI features in production v1 via `config/security.php` flag, or (b) provision a GPU VPS tier

### Bug 8: Mobile App Location Unconfirmed in Workspace
- **Cause:** Flutter app is NOT in the main Laravel workspace folder; it's a separate project (`dentfluence_mobile` directory outside mounted folder)
- **Impact:** Cannot directly edit/read mobile code in current session
- **Fix:** Mount the `dentfluence_mobile` folder separately

---

## 13. Technical Debt

### Code Smells
1. **Dual role system:** `users.role` (legacy string) + `role_id` FK to `roles` table both exist. Logic sometimes checks one, sometimes both. Needs unification — legacy string should be deprecated.
2. **Fat controllers:** Some early controllers (e.g., original `PatientController`) were 600+ lines. Partially refactored via service extraction but not fully consistent across all modules.
3. **Inconsistent module structure:** Some modules use `app/Modules/` (proper internal MVC), others use flat `app/Http/Controllers/` with adjacent services. No single enforced pattern.
4. **Alpine.js scope fragility:** `show.blade.php` for patients (3152 lines) uses deeply nested Alpine `x-data` scopes that are fragile — editing requires careful read-before-touch.

### Duplicated Code
- View partials for patient tabs partially duplicated across web + print layouts
- Some seeder logic duplicates model factory logic

### Hardcoded Values
- Branch IDs hardcoded in some seeders (acceptable for pilot, must parameterize for multi-clinic SaaS)
- Ollama host URL (`localhost:11434`) in AI service — needs env var
- WhatsApp API version pinned in service class

### Missing Validation
- Some API endpoints lack full Form Request validation (rely on controller-level checks)
- ABDM skeleton code has no input sanitization yet (pre-launch, so low risk now)

### Performance Issues
- No query result caching (no Redis/Memcached)
- Patient profile page loads all 12 tabs' data on initial load (no lazy-loading per tab via AJAX)
- Large Blade views (`show.blade.php` at 3152 lines) are slow to parse
- No database query optimization audit (N+1 queries likely in list views)

### Security Concerns
- `.env` file historically committed with placeholder secrets — must audit git history before public repo
- No CSP nonce on inline scripts (CSP header set but with `unsafe-inline` fallback)
- API tokens never expire (no TTL set on Sanctum tokens)

### Architecture Concerns
- Monolith with no service boundary enforcement — all modules can call each other freely
- Queue/job infrastructure exists but untested in production environment
- No feature flags system (can't toggle features per clinic without code deploy)

---

## 14. Security

### Authentication
- Session-based for web (Laravel auth guard)
- Token-based for API (Sanctum)
- MFA: TOTP (Google Authenticator) — Phase A

### Authorization
- RBAC: `CheckModulePermission` middleware on web routes
- API: `EnsureApiRole` middleware on API routes
- Clinical writes restricted to doctor roles; financial writes to admin/front_desk
- BranchScope: query-level branch isolation (prevents cross-clinic data leakage)

### Input Validation
- Laravel Form Requests for web forms
- API validation via Request classes
- PHPSpreadsheet handles import sanitization

### SQL Injection Prevention
- Eloquent ORM (parameterized queries by default)
- No raw SQL unless with `DB::raw()` in controlled analytics queries
- No user-controlled raw SQL input

### XSS Protection
- Blade `{{ }}` auto-escapes output
- `{!! !!}` used selectively (known safe HTML only)
- Security headers (`X-XSS-Protection`, `Content-Security-Policy`) via `SecureWebHeaders`

### CSRF
- Laravel's built-in CSRF protection on all web POST routes (`@csrf` tokens in forms)
- API routes exempt (use token auth instead)

### Rate Limiting
- Web login: 5 attempts/min per IP (Laravel `throttle:5,1`)
- Two-factor challenge: 5 attempts/min
- API global: 120 req/min
- API login: 5 req/min

### File Upload Security
- Clinical files served through `SecureMediaController` (auth-gated streaming — not directly accessible via public URL)
- `SecureClinicalMedia` artisan command migrates files to private storage
- MIME type checking on upload (assumption — not explicitly verified in code review)

### PHI Encryption
- `App\Casts\Encrypted` custom cast: AES-256-CBC encryption via Laravel's `encrypt()`/`decrypt()`
- Applied to: patient name, phone, email, dob; consultation PHI fields; finance PHI; HR sensitive fields
- Migration `2026_06_29_160000_encrypt_patient_phi_columns.php` exists — must be run + backfilled

### Audit Trail
- `Auditable` trait auto-logs all CRUD to `audit_logs` table
- Hash-chained: each row includes hash of previous row (tamper detection)
- `AuditVerify` command detects chain breaks
- Separate `billing_audit_logs` for billing-specific events

### Current Weaknesses (Honest Assessment)
1. APP_DEBUG=true in dev config (CRITICAL — must flip before launch)
2. Sanctum tokens have no expiry TTL
3. No WAF (Web Application Firewall) in front of VPS
4. CSP has `unsafe-inline` fallback
5. Git history may contain early .env commits with secrets
6. MFA not enforced by role (optional, not mandatory even for admin)
7. No penetration test performed

---

## 15. Performance

### Lazy Loading
- Web: No per-tab lazy loading on patient profile (all data fetched on page load)
- Mobile API: Paginated responses (`per_page` default)

### Pagination
- Web: Laravel `paginate()` on list views (patients, lab, appointments, etc.)
- API: Consistent pagination with `meta: { total, per_page, current_page, last_page }`

### Caching
- No application-level cache (no Redis, no Memcached configured)
- Laravel's file-based config cache used (`php artisan config:cache`)
- Query caching: not implemented

### Database Optimization
- Basic indexes on FKs (created by migrations)
- No explicit compound index strategy
- No query profiling done
- N+1 issues likely in list views (not audited)

### Large Dataset Handling
- PHPSpreadsheet for Excel imports (memory-intensive for large files)
- Lab reconciliation processes month-at-a-time (not chunked for large histories)

### Current Bottlenecks (Known)
- `patients/{id}` show view: 3152-line Blade with eager-loaded relationships
- No HTTP-level caching (no Varnish/CDN in front of VPS)
- AI inference (Ollama) is synchronous in the request cycle — blocks thread

### Image/Asset Optimization
- Vite handles CSS/JS minification and fingerprinting
- No image optimization pipeline (clinical photos stored as-is)

---

## 16. Mobile App

### Current Implementation
Flutter application (`dentfluence_mobile` — separate project, NOT in this workspace folder).

All API calls go to `http://<server-url>/api/v1/` (configurable via Settings screen in app).

### Navigation Structure
- Bottom navigation: Dashboard (Home), Patients, Schedule, Profile
- Home module grid: 12 tiles (Clinical, Billing, Lab, Reports, Settings + coming-soon tiles)
- Patient detail: tabbed (12 tabs, same as web)

### Design System
- "Infinity OS" identity: DM Sans font (via `google_fonts`), plum/magenta primary color (`#a01a86`)
- Custom UI design system in `lib/ui/`

### Confirmed Implemented Screens (from build sessions)
- Login screen
- Home (module grid + today's schedule)
- Patient list + search + filters
- Add Patient (`AddPatientScreen`)
- Patient profile (all 12 tabs — read-only)
- Consultation create (4 workflows)
- Treatment Plan create/edit/accept/revert/print
- Treatment Visit form
- Prescription write-pad (full CDSS parity)
- Membership tab (enrollment working, tested)
- Billing screen (invoice list + summary)
- Record Payment screen (non-EMI, Part A)
- Lab screen (read-only)
- Reports overview screen
- Settings module (server URL config, dark theme, notification toggles)
- Daily Huddle (board + tasks)
- Inventory (read + write)
- Profile screen

### Differences from Web
- No Marketing Hub (coming soon tile)
- No HR module (coming soon tile)
- No PRM/CRM (coming soon tile)
- No Clinical Library (coming soon tile)
- No Finance sub-module (expenses, cashbook) — mobile covers billing only
- No DPDP/consent screens

### Offline Support
Not implemented. App requires active network connection.

### Synchronization
No local database / sync strategy. All data fetched fresh from API on each screen load.

### Test Status
As of 2026-06-29:
- All 12 home tiles resolved
- Billing/Lab/Reports/Settings/home wiring confirmed working on emulator
- Tested on real device (Motorola, serial ZD222PHKDW) via `adb reverse + flutter run`
- Record Payment Part B (EMI) still pending
- Dark mode polish optional

---

## 17. Deployment

### Current State
Runs on Laragon (local dev server) at `http://dentfluence.test`. NOT publicly accessible.

### Target Deployment Stack
```
Hostinger KVM 2 VPS
├── Ubuntu 24.04 LTS
├── Docker Engine
├── Docker Compose
│   ├── app (PHP-FPM 8.3 + Laravel)
│   ├── nginx (reverse proxy)
│   ├── mysql (MySQL 8)
│   ├── queue (php artisan queue:work)
│   └── scheduler (php artisan schedule:run loop)
└── Certbot/Caddy (HTTPS — to be configured)
```

### Environment Variables (Key)
```
APP_NAME=Dentfluence
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dentfluence.in
APP_KEY=<generated>
DB_CONNECTION=mysql
DB_HOST=mysql          # Docker service name
DB_PORT=3306
DB_DATABASE=dentfluence
DB_USERNAME=dentfluence_user
DB_PASSWORD=<secret>
QUEUE_CONNECTION=database
MAIL_MAILER=smtp       # Brevo SMTP
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=dentfluence.in
AI_ENABLED=false       # Gate off AI v1
```

### Build Process
```bash
# On VPS:
git clone <repo> /opt/dentfluence
cd /opt/dentfluence
cp .env.production .env
# Fill in secrets in .env
bash deploy.sh
```

`deploy.sh` does:
1. `docker compose build`
2. `docker compose up -d`
3. `docker compose exec app php artisan migrate --force`
4. `docker compose exec app php artisan config:cache`
5. `docker compose exec app php artisan route:cache`
6. `docker compose exec app php artisan storage:link`

### Backup Process
`backup.sh` script:
- Dumps MySQL (`mysqldump` via Docker exec)
- Archives `storage/app/private/` (clinical files)
- Timestamps the archive
- (Offsite transfer not yet configured)

### Deployment Commands (Manual steps remaining)
```bash
# 1. Point DNS: dentfluence.in A record → 187.127.152.68
# 2. SSH into VPS
ssh root@187.127.152.68
# 3. Install Docker
curl -fsSL https://get.docker.com | sh
# 4. Clone repo and deploy
git clone <repo> /opt/dentfluence
cd /opt/dentfluence && bash deploy.sh
# 5. Configure SSL (Caddy or Certbot)
# 6. Run PHI backfill: php artisan phi:encrypt
# 7. Seed masters: php artisan db:seed --class=MasterDataSeeder
```

### Rollback Process
- **Currently:** None formal. Git tag + manual `migrate:rollback` (risky).
- **Needed:** Tagged releases + tested migration rollback scripts or blue-green swap.

### Current Limitations
- VPS not yet configured
- DNS not pointed
- No SSL
- No automated backup offsite transfer
- No CI/CD pipeline (manual git pull + deploy.sh)
- No health check endpoint monitoring

---

## 18. SaaS Readiness

**Honest answer: NOT SaaS-ready. This is a single-tenant application.**

| Capability | Status | Notes |
|---|---|---|
| Multi-tenancy | ❌ Not built | Single clinic. BranchScope adds branch isolation within ONE clinic's data. Not cross-clinic isolation. |
| Subscriptions | ❌ Not built | No subscription model in the app. |
| Billing (SaaS) | ❌ Not built | No Stripe/Razorpay for clinic subscriptions. |
| Super Admin | ❌ Not built | No super-admin panel to manage multiple clinic tenants. |
| Clinic Isolation | 🟡 Partial | BranchScope isolates branches within one clinic. DB-level isolation between clinics = not implemented. |
| Feature Flags | ❌ Not built | No per-clinic feature gating system. |
| Plan Restrictions | ❌ Not built | No tiered feature limits. |
| Payment Gateway | ❌ Not built | No gateway for SaaS subscriptions (Razorpay/Stripe). |
| Tenant Onboarding | ❌ Not built | No self-serve signup flow. |

### What's Missing for SaaS
1. **Tenant model:** Either shared DB with `clinic_id` on every table, or separate DB per tenant. Neither is implemented.
2. **Tenant registration:** Self-serve signup, clinic profile setup, initial data seed.
3. **Subscription management:** Plan selection, payment, upgrades/downgrades.
4. **Feature flags per tenant:** Some clinics on Basic plan shouldn't see Marketing Hub.
5. **Super Admin panel:** See all tenants, suspend/activate clinics, billing overview.
6. **Data isolation audit:** Ensure no query ever returns another clinic's data.

**Current use case:** Single-clinic installation (Tulip Dental). Each new clinic would require a separate deployment today.

---

## 19. Future Roadmap

### Critical (Required for launch or immediate post-launch)
1. **VPS deployment** — DNS, SSH, Docker, SSL, queue worker, backups
2. **Fix 6 failing feature tests** — especially finance payment path
3. **PHI encryption backfill** — run `EncryptPatientPhi` on existing data
4. **APP_DEBUG=false** in production
5. **Mobile app location resolution** — mount `dentfluence_mobile` and verify all features on real device
6. **Record Payment EMI Part B** — complete mobile EMI flow
7. **WhatsApp live credentials** — Meta WhatsApp API keys + approved templates

### Important (Phase B / next 3 months)
1. **Multi-clinic SaaS architecture** — tenant model design + implementation
2. **ABDM live integration** — register on NHA sandbox → production
3. **Automated testing expansion** — bring coverage from ~13 to 50+ feature tests
4. **Cloud AI option** — Claude/GPT API fallback for production Tulip (instead of local Ollama)
5. **ProConsult bridge** — visiting consultant portal linking to Dentfluence patient data
6. **Push notifications** — mobile push (FCM)
7. **Dark mode** — complete mobile dark mode

### Optional (Phase C+)
1. **DICOM imaging viewer** — X-ray/CBCT viewer within the app
2. **SMS gateway** — fallback for WhatsApp failures
3. **Analytics API pulls** — live social media impressions, reach
4. **SOC 2 / ISO 27001** — compliance certification for enterprise sales
5. **PRM AI automation** — Boxly-style AI/automation for patient relationship flows (plan: `docs/plan-prm-ai.md`)
6. **Job Library expansion** — Role-based SOPs (plan: `docs/plan-job-library-sops.md`)

### Future Vision (18-month horizon)
1. **Dentfluence Ecosystem:** OS (clinic management) + ProConsult (visiting consultant bridge) + Lab Portal + Chairside (chair-side tablet UI)
2. **ABDM as core layer** — full FHIR-native medical records, ABHA-linked
3. **MCP (Model Context Protocol) layer** — internal tool registry for AI agents over clinic data
4. **Scale to 50+ clinics** by end of 2026; 500+ by 2027

---

## 20. What Another Developer Should Know

### Before Making Any Changes

1. **Read before editing.** The patient `show.blade.php` is 3152 lines with deeply nested Alpine.js scopes. The wrong edit breaks the whole profile page. Always read the section you're changing first.

2. **Dual role system.** `users.role` (legacy string) AND `role_id` (FK to `roles` table) both exist. The system uses both in different places. Don't assume one is authoritative — check the specific middleware or guard you're working with.

3. **BranchScope is a global scope.** Most models auto-filter by `branch_id`. If you're writing a query that should be clinic-wide (e.g., admin report), you must call `->withoutGlobalScope(BranchScope::class)` explicitly.

4. **Service layer is the brain.** Business logic lives in `app/Services/`. Controllers are thin. If you're writing more than ~10 lines of business logic in a controller, extract it to the service.

5. **The audit trail is hash-chained.** Any model with the `Auditable` trait auto-logs to `audit_logs`. If you delete audit records, you'll break the chain. Don't delete from `audit_logs`.

6. **AI features are local-only.** Tulip, voice notes, and vision AI all call `localhost:11434` (Ollama). They won't work in production without a GPU machine with Ollama installed. Gate them off with `AI_ENABLED=false`.

7. **No destructive migrations without asking.** `migrate:fresh` and `migrate:rollback` are flagged in project rules as needing approval. Always check with the founder before running these.

8. **PHI fields are encrypted.** Patient name, phone, email, dob and some finance/HR fields are encrypted via the `Encrypted` cast. Querying encrypted columns for search requires decryption in PHP, not MySQL — you cannot do `WHERE name LIKE '%Smith%'` on encrypted columns. Use the `PatientService::search()` method which handles this correctly.

9. **Mobile app is a separate project.** The Flutter app is in `dentfluence_mobile/` (not mounted in the main workspace). The web backend (`/api/v1/`) serves it. Changes to API response shapes will break the mobile app — check both sides.

10. **The Docker stack is built but untested on VPS.** `deploy.sh` has never been run against the actual Hostinger server. Expect surprises on first deploy — especially around file permissions, environment variables, and the queue worker.

11. **Queue worker is required.** Recall jobs, notification jobs, and email dispatch all go through the Laravel queue (`QUEUE_CONNECTION=database`). Without a running `queue:work` process, these silently do nothing.

12. **Routes are split across 10 files.** `web.php` covers core routes; `communication.php`, `marketing.php`, `prescriptions.php`, `clinical-library.php`, `cms.php`, `prm.php`, `tags-routes.php`, `timeline.php`, `api.php`, `console.php` cover domains. Check all files when looking for a route.

---

## 21. Architecture Diagram

### Request Flow — Web

```
Browser (Chrome/Safari)
        │
        │ HTTPS (Certbot/Caddy)
        ▼
   nginx (reverse proxy)
        │
        │ FastCGI (php-fpm)
        ▼
  Laravel Application
  ┌─────────────────────────────────────────────┐
  │  bootstrap/app.php                           │
  │    → Middleware stack                        │
  │       → SecureWebHeaders                    │
  │       → AbsoluteSessionTimeout              │
  │       → auth (session)                      │
  │       → CheckModulePermission               │
  │                                             │
  │  routes/web.php + domain route files        │
  │    → Controller (thin layer)                │
  │       → Service ("brain")                   │
  │          → Eloquent Model                   │
  │             → MySQL (via Docker)            │
  │          → Storage (disk)                   │
  │          → Queue (database)                 │
  │    → Blade View → HTML response             │
  └─────────────────────────────────────────────┘
        │
        │ Alpine.js handles UI state client-side
        │ Vite-compiled CSS/JS served from /public
        ▼
    Browser renders
```

### Request Flow — Mobile API

```
Flutter App (Android/iOS)
        │
        │ HTTPS Authorization: Bearer <token>
        ▼
   nginx (reverse proxy)
        │
        ▼
  Laravel Application
  ┌─────────────────────────────────────────────┐
  │  bootstrap/app.php                           │
  │  Middleware:                                 │
  │    → SecureApiHeaders                        │
  │    → throttle:120,1                          │
  │    → auth:sanctum (token validation)        │
  │    → EnsureApiRole (RBAC)                   │
  │                                             │
  │  routes/api.php                             │
  │    → Api\V1\Controller (thin)               │
  │       → Same Service as web!               │
  │          → Eloquent Model                  │
  │             → MySQL                        │
  │       → Eloquent Resource (JSON shape)     │
  │    → JSON response { success, data, meta } │
  └─────────────────────────────────────────────┘
        │
        ▼
Flutter parses JSON → renders UI
```

### Webhook Flow (WhatsApp / Meta Leads)

```
Meta Cloud API / Website Form
        │
        ▼
  POST /webhooks/whatsapp (or /webhooks/meta-lead)
        │ (no auth — verified by Meta signature)
        ▼
  Webhooks\WhatsAppLeadController
        │
        ▼
  InboundMessageService (parse, create WaThread/WaMessage)
  OR
  MetaLeadController → Lead model → LeadActivity
        │
        ▼
  MySQL (wa_threads, wa_messages, leads)
        │
        ▼
  Queue Job → staff notification
```

### AI / Local Stack (Tulip)

```
Browser (Tulip chat widget)
        │
        ▼
  AiAssistantController
        │
        ▼
  Services/Assistant/
        │ HTTP call
        ▼
  Ollama (localhost:11434)
  ├── llama3.1:8b    (clinical queries)
  ├── qwen2.5:7b     (admin/data queries)
  └── qwen2.5vl:7b  (vision — receipt/form scan)
        │
        ▼
  Response → AiMessage stored → Confirm card (if action)
  → Agentic action via Tool registry (with user confirmation)
```

### Storage Architecture

```
Clinical Files (Private)
        │
  SecureMediaController (auth-gated stream)
        │
  storage/app/private/clinical/
  (NOT publicly accessible — requires session auth)

Patient Documents + Uploads (Public)
        │
  storage/app/public/  ←→  public/storage/ (symlink)
  (Accessible via URL — watermarked)

Database Backups
        │
  backup.sh → mysqldump → /opt/dentfluence/backups/
  (Offsite transfer: NOT YET CONFIGURED)
```

---

## 22. Current Limitations

### Architectural Limitations
1. **Single-tenant monolith.** Cannot onboard a second clinic without a separate deployment. Full SaaS refactor needed.
2. **No feature flags.** All features are all-or-nothing per deployment. Cannot enable/disable per clinic.
3. **Local AI dependency.** The most differentiated features (Tulip, voice notes, vision) require local GPU + Ollama. Not viable on standard VPS.
4. **No query cache.** High-traffic list pages will hammer MySQL directly with no cache layer.
5. **Synchronous AI calls.** AI inference blocks the PHP-FPM worker thread for the duration of the Ollama call (~2-10s for 7B models). Under concurrent users, this degrades performance significantly.
6. **No CDN.** Clinical media served directly from VPS storage. No edge caching, no image optimization.
7. **No blue-green deployment.** Updates require `docker compose up -d --build` which causes brief downtime.
8. **Encrypted PHI un-searchable at DB level.** Can't do MySQL full-text search on encrypted name/phone columns. Search must be done by decrypting in PHP (slow at scale).

### Business Limitations
1. **ABDM not live.** Cannot legally claim ABDM compliance for go-live. ABHA capture is local-only.
2. **WhatsApp not live tested.** Two-way messaging built but not connected to real Meta WhatsApp credentials.
3. **No payment gateway for SaaS subscriptions.** Clinic can process patient payments, but Dentfluence cannot yet bill clinics for subscriptions.

---

## 23. Refactoring Suggestions

### Priority 1 — Before First Customer (Launch-critical)
1. **Unify the role system.** Deprecate the legacy `users.role` string column. Migrate all role checks to use `role_id → roles → role_module_permissions`. This eliminates dual-path bugs.
2. **Fix the 6 failing tests.** Re-run `php artisan test`. Fix factory drift. Make the payment path green.
3. **Split the patient show.blade.php.** 3152 lines in one file is unmaintainable. Extract each of the 12 tabs into `views/patients/tabs/tab-name.blade.php` partials and include them.
4. **Set Sanctum token TTL.** Add `'expiration' => 60 * 24 * 30` (30 days) to `config/sanctum.php`. Prevents indefinite token validity.
5. **Add `.env` to `.gitignore` audit.** Ensure no secrets are in git history. Use `git log -- .env` to check.

### Priority 2 — First Month Post-Launch
1. **Add Redis** for session + cache. Move `SESSION_DRIVER=redis` and add `CACHE_DRIVER=redis`. Will dramatically improve performance under real clinic load.
2. **N+1 audit.** Run Laravel Debugbar locally, identify N+1 queries on the 5 most-used pages (patient list, appointment calendar, billing index, lab cases, huddle board). Add `with()` eager loading.
3. **API response caching.** Cache static/slow-changing data (treatment list, drug list, form options) with `Cache::remember()` — 5-minute TTL.
4. **CSP nonce implementation.** Move from `unsafe-inline` to per-request nonces for inline scripts.
5. **Standardize module structure.** Decide: all modules go in `app/Modules/` OR flat `app/Http/Controllers/`. Pick one, migrate the outliers.

### Priority 3 — Before Second Clinic (SaaS Step 0)
1. **Tenant ID on every table.** Add `clinic_id` to all tables (or implement separate DB per tenant). This is the biggest architectural change and must be done before onboarding clinic #2.
2. **Global scope for tenant isolation.** Replace `BranchScope` with `ClinicScope` that filters by `clinic_id` — the same pattern, but at clinic level not branch level.
3. **Feature flag system.** Simple `clinic_features` table: `clinic_id`, `feature_slug`, `enabled`. Gate expensive modules (Marketing Hub, AI) behind plan checks.
4. **Super Admin panel.** Basic Filament or Nova admin for managing clinics, users, and subscriptions.

---

## 24. Final Project Health Score

### Scoring (1–10 scale, 10 = best)

| Dimension | Score | Reason |
|---|---|---|
| **Architecture** | 7/10 | Service layer is clean; module structure is logical. Loses points for dual role system, no tenant model, and some flat-controller inconsistency. |
| **Code Quality** | 6.5/10 | Good use of Eloquent, traits, and service classes. Patient show.blade.php at 3152 lines is a smell. Inconsistent validation rigor across modules. |
| **Maintainability** | 6/10 | 267 migrations, 176 models, 110 controllers — large surface area for one person. Most code is readable. Dual role system and Alpine scope fragility are maintenance risks. |
| **Scalability** | 5/10 | Single-tenant, no cache layer, synchronous AI, no CDN. Fine for one clinic. Will hit limits at 10+ concurrent users or 50K+ patient records without Redis + query optimization. |
| **Security** | 7.5/10 | PHI encryption, hash-chained audit, MFA, RBAC, rate limiting, security headers, DPDP consent — this is genuinely well done for a solo-built product. Main gaps: APP_DEBUG, token TTL, MFA not enforced. |
| **Performance** | 5/10 | No cache, no CDN, synchronous AI, potentially N+1 queries. Adequate for a single small clinic; will need work for scale. |
| **Documentation** | 7.5/10 | `DENTFLUENCE_MASTER.md`, `ARCHITECTURE.md`, `PROGRESS_STATUS_2026-06-27.md`, `DEPLOY.md`, 9 ABDM docs, phase plan docs — unusually well-documented for a solo project. |
| **Overall Production Readiness** | 5/10 | Feature surface is impressive (~90% built). But: 6 failing tests, no live deployment, AI features environment-bound, WhatsApp untested, PHI backfill not run. "Works in dev" ≠ "ready for real patient data on a public server." With 1–2 focused weeks of infra + test work, this reaches 8/10. |

### Summary Assessment

Dentfluence OS is an **exceptionally ambitious solo-built product** that covers a clinical management scope comparable to products built by 10-person teams. The core clinical loop (register patient → consult → plan → prescribe → bill) is solid and live-tested at the founder's own clinic.

The primary gaps before launch are **infrastructure and deployment**, not features. The code is largely there; the server is not. A focused 2-week sprint on:
- VPS deployment + SSL
- Fixing 6 failing tests
- PHI encryption backfill
- WhatsApp credentials
- Queue worker + mail config

...would bring this to a launchable state.

The bigger architectural work (SaaS multi-tenancy, cache layer, ABDM live integration) is correctly deferred to post-launch Phase B/C.

---

*Document generated: 2026-06-30*
*Source: Dentfluence OS workspace, git history, memory logs, ARCHITECTURE.md, DENTFLUENCE_MASTER.md, PROGRESS_STATUS_2026-06-27.md*
*Verification: Route crawler (June 2026) — 178/180 pages HTTP 200*
