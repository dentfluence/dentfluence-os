# Dentfluence — Architecture Audit

**Prepared by:** Principal Software Architect (review engagement)
**Date:** 2026-07-02
**Scope:** Full-codebase architectural review, focused on the newly introduced **Relationship Engine** layered on top of the existing **PRM / Communication** stack.
**Nature:** Analysis only. No code was written, changed, or refactored. No recommendation to delete anything is made in this document.

---

## How to read this document

This is a CTO-level architecture review for software expected to serve thousands of clinics over the next decade. It is deliberately blunt about technical debt. A companion file, `architecture-diagram.mermaid`, contains the system diagram referenced in Section 1.

**A note on verification.** Several early machine-generated findings claimed that core Relationship Engine files (`RelationshipJourney.php`, `routes/relationship.php`, `ActivityEngine.php`) were *truncated on disk* and that the rules config and identity-linking were *missing/unwired*. **These were false** — they were artifacts of a stale file-system cache in the analysis sandbox. Every one of those claims was re-checked against the authoritative source and disproven:

- `routes/relationship.php` **does** register Today, Notifications, Analytics, Search, and Profile routes.
- `config/relationship_rules.php` **does** contain `rules`, `communication_guard`, and `failsafe` sections.
- `RelationshipEngine::linkLead()` **is** called from `LeadIngestService`.
- `RelationshipJourney::canTransitionTo()` / `transition()` are complete.

The audit below reflects the *verified* state of the code, which is materially healthier than those first-pass claims suggested. The one genuine gap in that area is that `linkPatient()` is defined but not yet called anywhere.

---

## Product framing & terminology alignment (Revision 2)

This audit describes the system **as it exists today**, and that current-state description is unchanged — the code has not moved. Two things have been aligned so this document stays consistent with the companion **target** architecture, `docs/target-architecture-engine-first.md` (Revision 2):

- **Dentfluence is a Dental Operating Platform, not a CRM.** The lens for every recommendation below is *does this make a real clinic day simpler* — the morning huddle, today's calls, the treatment procedure, the lab case, the recall. Where software elegance and clinic workflow conflict, clinic workflow wins. The audit's findings are unchanged; only the framing is made explicit.
- **Forward-looking engine names now match the target document.** Where this audit points at the future single-source-of-truth owners, it uses the definitive engine names: identity is owned by the **Relationship Engine** (no separate Identity Engine); time-based work (recall, reminders, delays, retries, cooldowns, expirations) is owned by the **Automation Engine**; scoring/insight is owned by the **Insights Engine**; multi-step clinical procedures (RCT, Implant, Membership, Lab, Marketing) are owned by the **Workflow Engine**; all external systems route through the **Integration Engine**; and modules communicate by announcing **domain events** through shared services — never by depending on "an event bus" directly.

The current-state **diagram** (`docs/architecture-diagram.mermaid`) is intentionally left as-is: it depicts today's three-generation reality accurately, and the target-state engine picture lives in `docs/target-architecture-diagram.mermaid`. Keeping the two separate avoids the audit misrepresenting what actually ships today.

---

## 1. Current Module Architecture

### 1.1 The shape of the system

Dentfluence is a **Laravel 11 modular monolith**. It is not a set of microservices; it is one application with strong internal domain boundaries expressed three different (and inconsistent) ways:

1. **`app/Services/*`** — the primary home of domain logic. Each subfolder is effectively a module (`Billing`, `Inventory`, `Lab`-related, `Marketing`, `Prm`, `Relationship`, `Reviews`, `Whatsapp`, `Assistant`, `Huddle`, etc.), plus a set of loose root-level services (`PatientService`, `AppointmentService`, `RecallEngineService`, `WalletService`, DPDP services, and so on).
2. **`app/Http/Controllers/*`** — HTTP grouping by namespace (`Api/V1`, `Communication`, `Finance`, `HR`, `Marketing`, `Relationship`, `Abdm`, `Webhooks`, plus ~40 root controllers).
3. **`app/Modules/*`** — a partial, aspirational DDD-style layout (`Appointment`, `Huddle`, `Lab`, `Patient`, `PracticeProtocols`, `Treatment`). **Only Huddle and PracticeProtocols actually wire their own route files.** The rest are half-built and duplicate domains already implemented in `app/Services` + root controllers.

The existence of three parallel organizing schemes is itself the first architectural smell: there is no single, enforced convention for "where does a module live."

### 1.2 Major modules

| Module | Home | Responsibility |
|---|---|---|
| **Patients / Clinical Core** | root services + root controllers | Patient records, consultations, clinical findings, documents, alerts. |
| **Appointments** | `AppointmentService` + `Api/V1` | Scheduling, blocked slots, operatories. |
| **Treatment** | root + `app/Modules/Treatment` | Treatment plans, visits, opportunities, SOPs. |
| **Prescription / CDSS** | `Services/Prescription` | Rx write-pad, drug interaction/allergy/warning rules. |
| **Billing / Finance** | `Services/Billing`, Finance controllers | Invoices, payments, EMI, wallet, full finance suite (GST, payroll, vendors, cashbook). |
| **Lab** | root Lab services + `app/Modules/Lab` | Lab cases, vendors, reconciliation. |
| **Inventory / Procurement** | `Services/Inventory` | Items, stock, PO→GRN→vendor invoice chain. |
| **Huddle** | `Services/Huddle` + `app/Modules/Huddle` | Daily huddle board + tasks (web + mobile). |
| **Marketing** | `Services/Marketing` | Campaigns, posts, assets, lead attribution, engagement scoring. |
| **PRM (leads)** | `Services/Prm` + `Communication/PrmController` | **Leads kanban pipeline** + AI enrichment + routing + follow-ups. |
| **Communication OS** | `Services/Communication`, `RecallEngineService`, Communication controllers | Recall engine, unified `communication_queue`, follow-ups, opportunities, B2B. |
| **Relationship Engine** | `Services/Relationship` + `Relationship` controllers | **Newest**: unifying identity + activity + rules + tasks + reminders + scoring + notifications + "Today's Actions." |
| **Reviews / Reputation** | `Services/Reviews` | Review requests, public rating pages, Google routing. |
| **WhatsApp** | `Services/Whatsapp` | Meta Cloud two-way messaging + inbox. |
| **Voice Notes** | `Services/Voice` | Local whisper→Ollama voice→clinical note. |
| **Assistant (Tulip AI)** | `Services/Assistant` | Local-LLM copilot + `ToolRegistry` + ~17 agentic tools (internal MCP layer). |
| **Consent / DPDP** | `ConsentService`, `DataRightsService`, `BreachService` | Regulatory consent, data-subject rights, breach register. |
| **ABDM / FHIR** | `Abdm` controllers + observers | ABHA/HPR/HFR identity, FHIR document generation. |
| **HR** | HR controllers | Staff, attendance, payroll, training. |
| **Security / Audit** | root | Audit chains, roles/permissions, MFA, notifications, settings, branches. |

### 1.3 How modules communicate today

The dominant integration pattern is **synchronous service-layer calls behind thin controllers**, with a *thin* sprinkling of observers and queued jobs. Specifically:

- **Service → service calls are sparse and mostly direct.** Cross-module coupling is realized by controllers orchestrating services, not by services calling each other. Where services do call services, it is via container resolution (`app(SomeService::class)`) or constructor injection — e.g. `ActivityEngine` is injected into several Relationship engines; `WhatsAppCloudService` and `OutboundMessageService` are used by Reviews/WhatsApp; `FollowUpRulesService` is used by the PRM follow-up service.
- **Observers are the only real lifecycle-event mechanism.** There is **no `app/Events` or `app/Listeners` bus.** The observers that exist are: `LeadObserver` (`#[ObservedBy]` on `Lead` — fires enrichment/routing/activity logging on lead creation), `LabCaseObserver` (finance/expense sync), and the ABDM consultation/prescription observers (FHIR generation).
- **A small job queue** handles async work: `EnrichLeadJob`, `RecalculateRelationshipScoreJob`, `RelationshipAutomationFailedJob`, `GenerateWatermark`, and marketing post-publishing.
- **An internal `/api/v1` layer** (Sanctum-authed, `throttle:120,1`, custom `api.role:` middleware) is the mobile/Tulip surface. Critically, **these controllers reuse the same shared services as the web side** (e.g. `InvoicePaymentService`, `PatientService`) — this is the single best-architected part of the system and the one place SSOT is genuinely enforced.
- **Route composition is fragmented.** `bootstrap/app.php` registers `web.php` + `api.php`, then bolts on ~7 additional route files (`cms`, `clinical-library`, `communication`, `prm`, `tags`, `timeline`, `reviews`, `relationship`), each enforcing auth *inside the file*. `web.php` further `require`s `marketing.php`, `prescriptions.php`, and two `app/Modules` route files. There is **no `RouteServiceProvider` grouping** and no central auth/prefix policy.

### 1.4 Architecture diagram

See `docs/architecture-diagram.mermaid`. In summary, the important structural truth is this: **three generations of "patient engagement" logic are stacked and all still wired at once** — legacy PRM (leads), Communication OS (recall/queue), and the new Relationship Engine (unifying layer). The rest of the app (clinical, billing, lab, inventory, HR, marketing, ABDM, DPDP, AI) sits around that contested core and mostly does not care which engagement generation wins — *except* that the Relationship Engine is now reaching into all of them for its score and its "Today" feed.

---

## 2. Relationship Engine

The Relationship Engine is a deliberately-built **unifying layer**: one `Relationship` per real-world person (whether they arrive as a Lead or a Patient), a single universal `Activity` log, a config-driven automation layer, and a set of read-models (Today's Actions, Profile, Analytics). It is organized in phases (Phase 1–7 in docblocks) and is **substantially wired**, with two genuine gaps noted below.

### 2.1 Services (`app/Services/Relationship/`)

| Service | Responsibility |
|---|---|
| **RelationshipEngine** | Identity hub. `findOrCreate()` (dedupe by phone → email), `linkLead(Lead)`, `linkPatient(Patient)`, `getProfile(id)`. Creates one `Relationship` per person and the initial `RelationshipJourney`. |
| **ActivityEngine** | The real hub of the whole engine. `log($subject, $event, $actor, $metadata, ...)` writes an `activities` row, then on `DB::afterCommit()` fires `RulesEngine::evaluate()` and dispatches `RecalculateRelationshipScoreJob` (for score-relevant events). Never throws — logging must not break the caller. |
| **RulesEngine** | Config-driven automation. Reads `config('relationship_rules.rules')`, matches an event → conditions → actions (`create_task`, `create_reminder`, `send_notification`, `create_task_and_notify`). Enforces per-rule cooldowns via `relationship_rule_logs`. On failure dispatches `RelationshipAutomationFailedJob`. |
| **TaskEngine** | `autoCreate()` a relationship-linked `Task` with dedupe (relationship + category + title + due date + open status); logs `task.auto_created`. |
| **ReminderEngine** | `createReminder(type, subject, ...)` → creates a `follow_up`-category `Task` tagged `[reminder:{type}]`, deduped; logs `reminder.created`. Type→title/priority maps for recall, membership renewal, appointment confirm, birthday, lab follow-up. |
| **NotificationEngine** | In-app notifications with **dual-write**: writes a `relationship_notifications` row *and* mirrors into the existing `app_notifications` bell. Role-based recipient resolution, 24h noise guard. |
| **RelationshipScoreEngine** | 0–100 patient-health score from six weighted factors defined in `config('relationship_score.factors')`: visit frequency, recall compliance, communication response, treatment completion, membership active, referral activity. Reads across `appointments`, `communication_queue`, `treatment_plans`, `patient_memberships`, `leads`. |
| **CommunicationGuard** | Outbound gatekeeper. `canContact(relationshipId, channel, type)` enforces same-channel 24h cooldown, ≤3 contacts / 7 days, and birthday promotional block. Logs to `relationship_contact_log`. Fails **open** on error. |
| **AppointmentReminderEngine** | Daily job. Finds tomorrow's non-cancelled appointments, dedupes, creates a `call`-category reminder `Task` for today, logs `reminder.task_created`. |
| **TodayActionsEngine** | The reception "Today" brain. `generate()` returns ~12 categories of prioritized actions from **live data** (no persistence): new enquiries, lead follow-ups, opportunities, pending estimates, recall calls, appointment reminders, membership renewals, birthdays, lab-ready, payment reminders, plus yesterday's misses. Fully fault-tolerant per category. |
| **YesterdayReviewService** | Yesterday's `no_show`/`cancelled` appointments + missed calls; consumed only by TodayActionsEngine. |

### 2.2 Controllers

**Web (`app/Http/Controllers/Relationship/`)**

- **TodayController** — `index()` renders the Today page (sorts categories by priority, injects checklists/response-options config); `logAction()` AJAX logs `call.logged` via ActivityEngine.
- **ProfileController** — `show(id)` builds the unified relationship profile and a merged **timeline** (blends `Activity`, legacy `LeadActivity`, appointments, patient communications, tasks, notes, WhatsApp messages) plus lifetime revenue/visits/score/next-action; `search()` is a universal AJAX typeahead.
- **AnalyticsController** — `index()` renders 6 cached KPIs (relationship growth, lead conversion, recall success, avg lifetime value, score distribution, staff KPIs).
- **NotificationController** — JSON API: `index()`, `markRead(id)`, `markAllRead()` over `relationship_notifications` scoped to the current user.

**API (`app/Http/Controllers/Api/V1/RelationshipController.php`)** — thin Sanctum face for mobile: `today()`, `search()`, `show()`, `timeline()` (paginated), `journeys()`, `logActivity()`. Reuses the same services as web (good SSOT).

### 2.3 Models

| Model | Table | Notes |
|---|---|---|
| **Relationship** | `relationships` | SoftDeletes. `hasOne` lead, `hasOne` patient, `hasMany` journeys, `hasMany` activities. Scopes: active, dormant, byPhone, byEmail. |
| **RelationshipJourney** | `relationship_journeys` | Per-person state machine (`type` ∈ lead/treatment/recall/opportunity/membership/referral). `canTransitionTo()` + `transition()` enforce allowed transitions and log to ActivityEngine. |
| **RelationshipNotification** | `relationship_notifications` | `belongsTo` relationship + recipient (User). Scopes forUser/unread/notDismissed. Mirrors an `app_notifications` row via `app_notification_id`. |
| **Activity** | `activities` | The universal, polymorphic log (`subject`/`actor` morphs, `event`, `metadata`, `occurred_at`). Scopes forRelationship/ofEvent/recent. **This is the declared successor to `lead_activities`.** |
| **PatientRelationshipNote** | `patient_relationship_notes` | **Legacy** (2024) patient-notes feature — named "relationship" but predates the engine and is not part of it. |

There are intentionally **no** `RelationshipActivity` / `RelationshipTask` / `RelationshipScore` / `RelationshipRule` models — those concepts reuse the shared `activities` and `tasks` tables, plus `relationship_rule_logs` (raw DB, no model). This is a reasonable design choice (fewer tables, shared task pool), but it is also the source of the duplication analysis in Section 4.

### 2.4 Routes

- **`routes/relationship.php`** (web, `auth`): `relationship.today`, `relationship.today.action`, `relationship.notifications.*`, `relationship.analytics`, `relationship.search`, `relationship.profile`. *(All registered — verified.)*
- **`routes/api.php`** (`/api/v1/relationships/*`, Sanctum): today, search, show, timeline, journeys, activity.log.
- **`routes/console.php`**: schedules `relationship:appointment-reminders` daily at 08:00.

### 2.5 Events / Observers / Jobs / Config / Tables

- **Events / Listeners:** none dedicated. Automation flows through the in-process `ActivityEngine::log → afterCommit → RulesEngine` chain, **not** the Laravel event bus. The only observer touching the engine is `LeadObserver`, which calls `ActivityEngine::log`.
- **Jobs:** `RecalculateRelationshipScoreJob` (recompute score on relevant events), `RelationshipAutomationFailedJob` (fail-safe: logs `automation.failed` and, past an escalation threshold, creates an urgent admin task).
- **Config:** `config/relationship_score.php` (factors/weights, recalculate-on-events, score bands, cache TTL) and `config/relationship_rules.php` (rules, today-actions thresholds, call checklists, response options, next actions, `communication_guard`, `failsafe`). *(All sections present — verified.)*
- **Database tables:** `relationships`, `relationship_journeys`, `relationship_rule_logs`, `relationship_contact_log`, `relationship_notifications`, plus retro-added nullable `relationship_id` FK columns on `leads`, `patients`, `treatment_opportunities`, and `tasks`.

### 2.6 Genuine gaps (verified)

1. **`linkPatient()` is never called.** `linkLead()` is wired (via `LeadIngestService`), so leads become relationships. But patients created outside the lead flow do **not** get auto-linked. Their `relationships.relationship_id` FK stays null unless they came through a lead. This is the single most important correctness gap in the engine: the "one relationship per person" promise currently holds for lead-origin people, not patient-origin people.
2. **No scheduled recompute for scores or opportunity aging.** Scores recompute only *reactively* on qualifying events. A patient who simply goes quiet will not have their score decay on a schedule. Only appointment reminders are on a timer (08:00).
3. **Backfill not done.** The `relationship_id` columns were added nullable and retro-fitted; the bulk of existing legacy rows are not yet linked. The engine can't be authoritative until a backfill runs.

---

## 3. Legacy PRM

### 3.1 What PRM actually is

PRM is a **leads-only kanban pipeline** — *not* a generic board or a full CRM. There are **no** `prm_boards / prm_cards / prm_stages / prm_columns` tables. The "board" is virtual: `Lead` rows grouped by a `stage` string column, with stages hardcoded in `PrmController::getStages()` (`new_lead, contacted, appointment, consultation, plan_given, converted, lost`).

### 3.2 Models

- **Lead** (`leads`) — the pipeline card. Notable columns: `stage`, `source`/`lead_source`, `urgency`, `lead_value`, `treatment`, `assigned_to`/`assigned_to_id`, `followup_date`, the AI columns (`ai_summary`, `ai_treatment_label`, `ai_urgency`, `ai_estimated_value`, `ai_enriched_at`), and the retro-added `relationship_id`. `hasMany` activities. Uses `#[ObservedBy(LeadObserver::class)]`.
- **LeadActivity** (`lead_activities`) — PRM's **own** timeline (`type`, `label`, `outcome`, `note`, `activity_date`, `activity_time`, `by`).

### 3.3 Services (`app/Services/Prm/`)

| Service | Responsibility |
|---|---|
| **LeadIngestService** | Single ingress for website/Meta/WhatsApp leads. Dedupe by phone within a window, create `Lead`, log activity, then call `RelationshipEngine::linkLead()`. |
| **LeadEnrichmentService** | AI classification (5-word summary, treatment label, urgency) + deterministic ₹ value from `config('prm.value_bands')`. Writes `ai_*` columns. Uses `OllamaClient`. |
| **LeadRoutingService** | Auto-assign to least-loaded/random staff, with treatment→role override and branch restriction; logs the assignment. |
| **LeadFollowUpService** | On stage entry, create follow-up reminders into the shared `follow_ups` table via `FollowUpRulesService::resolve('prm_stage_changed', …)`. |
| **LeadReplyService** | AI draft reply (channel- and stage-aware). **Never sends** — draft only. |

### 3.4 Controller

**`Communication/PrmController`** (~630 lines) owns: kanban `index`/`board` + pipeline stats; `leadDetail` (**redirects to `relationship.profile` when the lead already has a `relationship_id`** — the migration is already visible here); lead CRUD; `moveStage` (which has three side-effects: logs to `lead_activities`, calls `LeadFollowUpService`, and **syncs the `RelationshipJourney` via `transition()`**); `logActivity`/`logReply`; a partial `convertToPatient` stub; AI `reEnrich`/`draftReply`; `inbox`; and analytics (`sourceAnalytics`, `teamPerformance`, `channelRoi`).

### 3.5 Routes & database

- **Routes:** `routes/prm.php` under a `module:prm` gate.
- **Tables PRM owns/uses:** `leads`, `lead_activities`, and it writes into the shared `follow_ups` engine.

### 3.6 What PRM still legitimately owns

Even in a post-Relationship-Engine world, PRM owns genuinely unique capabilities that the Relationship Engine has **no equivalent for**:

- **AI lead enrichment** (summary/label/urgency/value) — `LeadEnrichmentService`.
- **AI draft replies** — `LeadReplyService`.
- **Auto-routing / assignment** — `LeadRoutingService`.
- **Multi-channel lead ingestion + dedupe** — `LeadIngestService`.
- **Lead-source analytics / channel ROI / team first-response performance**.

What is *superseded / being migrated* (and the code says so explicitly): the `lead_activities` timeline → `activities`; lead `stage` as pipeline state → `RelationshipJourney`; the `leadDetail` drawer → `relationship.profile`; and PRM's follow-up creation, which flows through `FollowUpRulesService` — a class explicitly commented as *"legacy — logic being migrated to RulesEngine (Phase 5)… Do NOT add new rules here."*

---

## 4. Duplicate Systems

This is the core finding of the audit. There are **three generations of engagement logic** (PRM leads → Communication OS → Relationship Engine) and they overlap heavily. Each row below is a real, verified duplication.

### 4.1 Activity logging — **THREE+ parallel logs**

- **Old:** `lead_activities` (`LeadActivity`, PRM) and `comm_activity_logs` (`CommActivityLog`, Communication queue). Plus module-specific logs: `mkt_activity_log`, `huddle_task_logs`, `staff_activity_logs`.
- **New:** `activities` (`Activity`) — universal, polymorphic, written by `ActivityEngine`.
- **Current usage:** All coexist. `RecallEngineService` already *dual-writes* into `ActivityEngine`, so migration has started. `ProfileController` merges old and new logs into one timeline at read time.
- **Recommendation (direction only):** `activities` is the declared single log. Legacy logs should become read-only/compat sources during transition, not new write targets.

### 4.2 Rules / automation — **TWO rules engines**

- **Old:** `FollowUpRulesService` + `config/followup_rules.php` (+ `followup_settings.php`).
- **New:** `RulesEngine` + `config/relationship_rules.php`.
- **Current usage:** Both live. The legacy one is *frozen by comment* ("do NOT add new rules here"), but PRM still routes through it.
- **Recommendation:** `RulesEngine` is the intended SSOT for automation; the follow-up rules should be ported, not extended.

### 4.3 Recall / reminders — **TWO pipelines**

- **Old:** `RecallEngineService` (6 triggers → `communication_queue`, `source_engine='recall'`) + `RecallController` + `recall:run` (07:00).
- **New:** `AppointmentReminderEngine` + `ReminderEngine` + `RulesEngine` `create_reminder` action + `relationship:appointment-reminders` (08:00).
- **Current usage:** Both run daily, independently. There is real risk of *two systems generating overlapping reminders* for the same patient.
- **Recommendation:** Converge on one time-based authority (the **Automation Engine** in the target design); keep the richer recall trigger set but funnel output through that single engine.

### 4.4 Follow-up / next-step tracking — **overlapping across three modules**

`follow_ups` + `follow_up_notes` (Communication) vs `relationship_contact_log` + `relationship_journeys` (Relationship) vs `patient_relationship_notes` (patient-level). All encode "we contacted the patient / here's the next step."

### 4.5 Pipeline / stages — **duplicated**

`leads.stage` (PRM kanban) and `treatment_opportunities.status` (Opportunity board) vs `RelationshipJourney` (`TYPE_LEAD` / `TYPE_OPPORTUNITY` state machines). `moveStage()` already dual-writes lead stage into the journey — so the same state lives in two places, kept in sync by application code.

### 4.6 Task generation — **overlapping**

Manual `TaskController` / recall-into-queue vs `TaskEngine::autoCreate` (relationship-linked). New tasks carry `relationship_id`; old ones don't. Same `tasks` table, two creation paths with different metadata guarantees.

### 4.7 Notifications — **TWO tables (bridged)**

`app_notifications` (`AppNotification`, the generic bell) vs `relationship_notifications` (`RelationshipNotification`). This one is **less bad**: `NotificationEngine` dual-writes and links the two via `app_notification_id`, so they are bridged rather than divergent. Still two stores for one concept.

### 4.8 Scoring — **TWO scorers**

`MarketingScoreService` (marketing engagement score) vs `RelationshipScoreEngine` (0–100 relationship health). Different domains, but both "score a person," with no shared scoring primitive.

### 4.9 Timeline UI — **one real, one dead**

`Communication/TimelineController` renders **100% mock/hardcoded data** (`getDummyPatients()`, a fake "Riya Sharma") and touches no tables — it is effectively dead code that is nonetheless routed. The *real* timeline is `ProfileController::show` / the API `timeline()` endpoint over `activities`.

### 4.10 Person identity — **the reason the engine exists**

`leads` and `patients` are separate tables with no unifying key historically. `relationships` (via `findOrCreate`) is the new unifying identity. This is *net-new*, not a duplication — but until backfill + `linkPatient` wiring is complete, identity is effectively still split.

---

## 5. Single Source of Truth Analysis

For each major capability, the SSOT the architecture is *clearly aiming for* (and which the code comments endorse):

| Capability | Should be owned by | Currently split across |
|---|---|---|
| **Person identity** (lead ⇄ patient) | **Relationship Engine** (`relationships`) | `leads`, `patients` (unlinked) |
| **Activity / interaction log** | **Activity Engine** (`activities`) | `lead_activities`, `comm_activity_logs`, module logs |
| **Automation policy (when should something happen)** | **Rules Engine** (`relationship_rules`) | `FollowUpRulesService` (frozen), scattered config |
| **Time-based execution (recall, reminders, delays, retries, cooldowns, expirations)** | **Automation Engine** (one time-based authority) | `RecallEngineService`, `follow_ups`, `AppointmentReminderEngine` |
| **Multi-step clinical procedures (RCT, Implant, Membership, Lab, Marketing)** | **Workflow Engine** (reusable procedure templates) | tracked informally / per-module today |
| **Pipeline state** | **RelationshipJourney** (state machines) | `leads.stage`, `treatment_opportunities.status` |
| **Auto-generated tasks** | **Task Engine** (relationship-linked) | `TaskController`, recall→queue |
| **Relationship insight & score** | **Insights Engine** (AI-agnostic; health, LTV, risk, opportunity, preference, AI summary) | `RelationshipScoreEngine` today; marketing score stays a *separate, legitimate* concept |
| **External systems (WhatsApp, ABDM, payments, Google, Meta, website)** | **Integration Engine** (single anti-corruption boundary) | vendor calls scattered across services today |
| **In-app notifications** | **`app_notifications`** as the delivery bus, with `relationship_notifications` as a typed source that mirrors into it | already bridged; formalize direction |
| **Unified inbox / queue** | **`communication_queue`** (this is the mature, real one) | fine as-is; should feed the Activity log |
| **Lead enrichment / routing / AI replies** | **PRM (`Services/Prm`)** — no engine equivalent; PRM keeps this | not duplicated |
| **Reputation / reviews** | **`Services/Reviews`** | not duplicated |

The clean mental model to adopt: **PRM becomes the "lead acquisition + AI" front door; Communication OS remains the "unified queue/inbox"; the Relationship Engine becomes the identity + activity spine** that both feed into and read from — with automation split cleanly into a **Rules Engine** (decides), an **Automation Engine** (executes time-based work), and a **Workflow Engine** (runs multi-step clinical procedures), and insight consolidated into an AI-agnostic **Insights Engine**. The engine is the spine, not a replacement for PRM's acquisition intelligence. The full target picture — including the Clinic Operating Cycle this is all meant to serve — is in `docs/target-architecture-engine-first.md`.

---

## 6. Dependency Analysis

### 6.1 Who depends on Legacy PRM

- `Webhooks/*` (Meta / Website / WhatsApp lead controllers) → `LeadIngestService`.
- `LeadObserver` → enrichment + routing services.
- `DashboardController` reads `leads` directly for home stats.
- Communication `inbox` reads `leads`.
- `FollowUpController` and the `follow_ups` table (shared with PRM's follow-up creation).

### 6.2 Who depends on the Relationship Engine

- `PrmController::moveStage` → `RelationshipJourney::transition()` (PRM now depends on the engine).
- `PrmController::leadDetail` → redirects to `relationship.profile` (PRM depends on the engine's UI).
- `LeadIngestService` → `RelationshipEngine::linkLead()`.
- `RecallEngineService` → `ActivityEngine::log()` (Communication OS depends on the engine).
- `RelationshipScoreEngine` reads **broadly**: `appointments`, `communication_queue`, `treatment_plans`, `patient_memberships`, `leads`, `patients`. This makes the engine a **wide downstream consumer** of nearly every clinical/finance domain.
- `TodayActionsEngine` reads leads, opportunities, communication queue, appointments, memberships, patients, lab cases, invoices.

### 6.3 Who depends on both

PRM is now **bidirectionally coupled** to the Relationship Engine (PRM calls the engine on `moveStage`/ingest; the engine reads leads for score/today). Communication OS is **one-directionally** coupled (it feeds ActivityEngine; the engine reads the queue).

### 6.4 Dependency risks

- **Circular/bidirectional coupling between PRM and the engine.** `moveStage` writes a journey; the engine reads leads. A change to lead stages or journey states can ripple both ways. This is the highest-risk coupling.
- **The score engine is a "god reader."** Because `RelationshipScoreEngine` and `TodayActionsEngine` read directly (often via raw `DB::table`) from a dozen domains, *any schema change in appointments, treatments, memberships, billing, or the queue can silently break scoring/Today's Actions.* There is no interface or contract insulating the engine from those tables.
- **Sync-by-side-effect.** State kept consistent by application code (lead stage ⇄ journey, notification dual-write, vendor tables) will drift the moment one write path is missed.
- **Fail-open guard.** `CommunicationGuard` fails open — if it errors, messages send anyway. For a DPDP-regulated system this is a compliance risk, not just a technical one.

---

## 7. Database Review

The schema has **~172 application tables**. Below is a classification by *architectural role*, per the request. **No deletion is recommended anywhere** — this is intent-labelling only.

### Core (authoritative, keep as SSOT)

`relationships`, `activities`, `relationship_journeys`, `relationship_notifications`, `patients`, `appointments`, `consultations`, `treatment_plans`/`treatment_plan_items`/`treatment_visits`, `invoices`/`invoice_items`/`invoice_payments`, the `finance_*` suite, `lab_cases` + children, `inventory_*` + procurement chain, `prescriptions` + `rx_*`, `communication_queue`, `follow_ups`, `tasks`, `users`/`roles`/`role_module_permissions`/`branches`, `audit_logs`, `app_notifications`, `wa_threads`/`wa_messages`, `reviews`, consent/DPDP (`consent_logs`, `patient_consents`, `data_requests`, `data_breaches`), ABDM (`patient_identifiers`, `fhir_documents`, etc.).

*Why:* each is the single home for its domain and is actively read/written by current flows.

### Compatibility (real, still-used, but slated to be fed *by* the new spine)

`leads`, `lead_activities`, `comm_activity_logs`, `treatment_opportunities`, `follow_up_notes`, `relationship_rule_logs`, `relationship_contact_log`.

*Why:* these back live features today, but their *concept* is being absorbed by the Relationship Engine (activity → `activities`, stage → journeys, rules → `relationship_rules`). They must keep working through the transition as compat/source tables.

### Duplicate (same concept stored twice — reconcile, don't delete)

- `lead_activities` **and** `comm_activity_logs` vs `activities` (three activity logs).
- `relationship_notifications` vs `app_notifications` (bridged, but two stores).
- Reminder/recall state spread across `communication_queue` (recall items) and relationship-driven reminder `tasks`.
- Pipeline state in `leads.stage` **and** `relationship_journeys`.

*Why:* flagged so a future consolidation has an explicit inventory. These are not safe to touch yet — several have live dual-write paths.

### Deprecated (built but effectively dead / stub)

- Whatever tables the mock `TimelineController` *would* have used — it uses none; the controller itself is the dead artifact.
- `retention_policies` overlaps conceptually with DPDP retention handled in `RetentionService` — verify before trusting.
- Half-built `app/Modules/*` domains (Appointment/Lab/Patient/Treatment) whose route files aren't wired — code, not tables, but same "built-and-abandoned" smell.

*Why:* labelled deprecated for *attention*, explicitly **not** for deletion in this document.

### Future (scaffolding ahead of full use)

`patient_identifiers`, `practitioner_identifiers`, `facility_abdm_config`, `fhir_documents`, `terminology_maps` (ABDM/FHIR — design-forward, lightly used); `relationship_rule_logs` and `relationship_contact_log` (support automation that is config-present but lightly exercised until backfill).

*Why:* these exist for capabilities that are architecturally committed but not yet at full production load.

---

## 8. Risk Analysis

If development continues *today* without addressing structure, these are the material risks, in priority order.

1. **Two sources of truth for engagement state (highest).** Activity, pipeline state, reminders, and follow-ups each live in ≥2 systems. Every new feature must choose which to write, and every report must decide which to read. This compounds: the longer both run, the more code hard-codes each, and the harder consolidation becomes.
2. **Duplicate business logic drifting.** Two rules engines and two reminder pipelines mean a policy change (e.g. "don't call patients twice in a week") must be made in two places or it's wrong in one. `CommunicationGuard` enforces this only on the new path; the old recall path can still over-contact.
3. **Data-integrity risk from sync-by-side-effect.** Lead stage ⇄ journey, notification dual-write, and the three-way vendor tables are kept consistent by application code, not FKs/transactions. Any missed write path silently corrupts state. `linkPatient` not being wired means the `relationships` table is *already* partially inconsistent with `patients`.
4. **Hidden coupling via "god readers."** `RelationshipScoreEngine` / `TodayActionsEngine` reach into ~12 domains with raw queries. A schema migration elsewhere can break scoring and the reception dashboard with no compile-time signal and no test coverage catching it.
5. **Testing.** There is no visible test suite around the engagement engines; the overlap makes them especially fragile, and the raw cross-domain reads are exactly the code most likely to break silently.
6. **Performance / scaling.** `TodayActionsEngine` runs ~12 live multi-table queries per page load with per-category try/catch; `RelationshipScoreEngine` runs 6 aggregate queries per recompute. At thousands of clinics these are N+1 and full-scan risks unless the reads are indexed and cached deliberately. Score recompute is event-driven with no batching ceiling.
7. **Compliance risk (DPDP).** Fail-open `CommunicationGuard` plus a second (older) recall path that doesn't consult the guard means outbound messages can bypass consent/frequency rules — a regulatory exposure, not just a bug.
8. **Onboarding / maintenance.** `ARCHITECTURE.md` is stale (dated 2026-06-18, predates Relationship, ABDM, DPDP, WhatsApp, Reviews, Voice, Assistant). Three module conventions + fragmented routing mean a new engineer cannot infer where anything lives. For a decade-long, multi-tenant SaaS this is a serious long-term cost.

---

## 9. Migration Strategy (no code — direction only)

The safest path is **strangler-fig consolidation**: make the Relationship Engine the spine incrementally, behind the data it already reads, without a big-bang rewrite. Four phases.

**Phase 1 — Make identity whole and stop the bleeding.**
Wire `linkPatient()` into the patient-creation path so every new person becomes a `Relationship`. Backfill `relationship_id` across existing `leads` and `patients`. Freeze *new* writes to legacy rules/activity paths (they are already comment-frozen; make it real). Fix `CommunicationGuard` to fail *closed* and route the legacy recall path through it. *Why first:* nothing else is trustworthy until identity is complete and no new divergence is being created.

**Phase 2 — Converge the read model.**
Make `activities` the single timeline everywhere (retire the merge-at-read-time in `ProfileController` once legacy logs are backfilled/mirrored). Retire the mock `TimelineController`. Point all "what happened with this person" reads at the Activity log. *Why:* reads are lower-risk than writes; unifying them proves the model before you touch write paths.

**Phase 3 — Converge automation and reminders.**
Port `FollowUpRulesService` rules into the **Rules Engine** (which *decides*). Route `RecallEngineService`'s trigger set through the **Automation Engine** (which *executes* time-based work: scheduling, retries, cooldowns, expirations), keeping the richer triggers and dropping the duplicate emitter. Consolidate reminder task creation on the **Task Engine**. *Why:* time-based automation is the highest-drift area; do it after identity + reads are solid so you can verify behavior against a single timeline.

**Phase 4 — Converge pipeline state and formalize contracts.**
Make `RelationshipJourney` the pipeline SSOT; have `leads.stage` / `treatment_opportunities.status` become projections/read-throughs rather than independent state. Introduce read *contracts/interfaces* (or read-models/views) between the score/Today engines and the dozen domains they currently query raw, so schema changes elsewhere can't silently break them. Then, and only then, evaluate compatibility tables for archival. *Why:* pipeline state and the god-reader coupling are the deepest changes; they should be last, on top of a stable spine, with tests in place.

Throughout: every phase behind feature flags, with the old path kept live until the new path is verified, and a written test around each converged capability before the old one is retired.

---

## 10. Final Verdict

**Score: 6 / 10** as an enterprise SaaS codebase today.

This is the score of an **ambitious, feature-rich, well-intentioned system caught mid-migration** — not a bad codebase, but one carrying real architectural debt that will compound if new features are piled on before consolidation.

**Strengths.**
- Genuinely deep domain coverage (clinical, billing, lab, inventory, HR, marketing, DPDP, ABDM, AI) — this is a lot of correctly-modeled dental-vertical logic.
- The `/api/v1` layer reusing shared services is *exactly* how you avoid web/mobile drift — the best-architected part of the system.
- The Relationship Engine is a *correct* strategic bet: a unifying identity + activity spine is what this system needs, and it's more built and more wired than a superficial pass suggests (rules config, routes, linkLead, journeys all present).
- Fault-tolerant read engines (per-category try/catch in TodayActionsEngine; ActivityEngine never throwing) show mature defensive instincts.

**Weaknesses.**
- Three overlapping engagement generations running simultaneously.
- Three module-organization conventions and fragmented, provider-less routing.
- "God reader" services coupled by raw queries to a dozen domains.
- Sync-by-side-effect instead of transactional/FK-enforced consistency.
- Stale top-level architecture documentation.

**Technical debt:** concentrated almost entirely in the engagement layer (PRM ⇄ Communication ⇄ Relationship) and in the organizational inconsistency (`app/Modules` vs `app/Services`, route composition). The clinical/billing/lab core is comparatively clean.

**Long-term maintainability:** currently **at risk**. A new engineer cannot safely add an engagement feature without understanding all three systems. That is the definition of a maintainability tax, and it grows with every feature.

**Scalability:** the *data model* scales; the *read patterns* (live multi-table Today/score queries, event-driven unbounded recompute) need deliberate indexing, caching, and batching before thousands of clinics hit them.

**What must be fixed before adding more features (non-negotiable):**
1. **Wire `linkPatient()` + backfill** so `relationships` is actually one-per-person. The engine cannot be authoritative otherwise.
2. **Declare and enforce the SSOT per capability** (Section 5) and stop new writes to legacy activity/rules paths.
3. **Collapse the two reminder/recall pipelines** onto one authority, routed through a **fail-closed** `CommunicationGuard` (DPDP).
4. **Insulate the score/Today engines** from raw cross-domain reads with a contract/read-model, and add tests around them.
5. **Refresh `ARCHITECTURE.md`** and pick one module convention — cheap, high-leverage, unblocks everyone.

Fix those five and this becomes an 8/10 platform with a clear, defensible spine. Keep stacking features on the current three-headed engagement layer and the score will fall, because the debt is the compounding kind.

---

*End of audit. Analysis only — no code was modified.*
