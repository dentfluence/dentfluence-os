# Dentfluence — Gap Analysis & Consolidation Roadmap (Current → Target)

**Author:** Principal Software Engineer
**Date:** 2026-07-02
**Inputs:** `docs/architecture-audit-2026-07-02.md` (current state) · `docs/target-architecture-engine-first.md` Rev 3 (frozen target) · verified codebase inventory.
**Nature:** Engineering gap analysis only. **No code. No file changes. No migrations. No refactoring. No architecture redesign** — the architecture is frozen. This document is the roadmap that will *guide* the eventual refactor, nothing more.

> **Scale reference:** ~203 models, ~308 migrations (~172 tables), 15 root services + 13 service namespaces, 13 controller groups + ~40 root controllers, 13 route files, 5 jobs, 4 observers, and **no `app/Events` / `app/Listeners`** (there is no event bus yet). This is a large, feature-rich modular monolith mid-way through a three-generation engagement consolidation.

> **Phase legend used throughout** (aligned to the audit's strangler-fig strategy):
> **P0** Event backbone + shared-service contracts + Decision Log + Guard-fail-closed ·
> **P1** Identity whole (patient linking + backfill) ·
> **P2** Converge reads (single Activity ledger, Timeline & Today's-Actions projections) ·
> **P3** Converge automation (Automation Engine; port legacy rules) ·
> **P4** Pipeline SSOT (journeys authoritative) + Insights multi-signal + read contracts ·
> **P5** Net-new engines (Workflow, Integration) ·
> **P6** Task human/system split, single Notification store, Search index, Analytics projections ·
> **Later** Chairside, Marketplace, Organization Engine.

---

## Section 1 — Current vs Target (component matrix)

| Component | Current state | Target state | Gap | Priority |
|---|---|---|---|---|
| **Relationship Engine** | `Relationship/RelationshipEngine` exists; `linkLead()` wired via `LeadIngestService`; journeys modeled; **`linkPatient()` never called**; no dedup/merge; most `relationship_id` unbackfilled | Owns identity + dedup + merge history + journeys; one node per person | Wire patient linking; add dedup/merge; backfill; make journeys authoritative | **Critical** (P1) |
| **Activity Engine** | `Relationship/ActivityEngine` + `activities` table live; but `lead_activities` & `comm_activity_logs` still written | The one append-only fact ledger; all history derives from it | Route all producers into `activities`; make legacy logs read-only mirrors | **High** (P2) |
| **Rules Engine** | `Relationship/RulesEngine` (config-driven, `relationship_rules`) live; legacy `Communication/FollowUpRulesService` still used (frozen by comment) | Single decision engine + **Decision Log** | Port legacy rules; formalize `relationship_rule_logs` into a full Decision Log | **High** (P0/P3) |
| **Automation Engine** | Does **not** exist by name; time-based logic split across `RecallEngineService`, `Relationship/AppointmentReminderEngine`, `Relationship/ReminderEngine`, scheduled commands | One engine for scheduling, recall, reminders, delays, retries, cooldowns, expirations | Consolidate the scattered temporal logic under one owner; single reminder path | **High** (P3) |
| **Workflow Engine** | Does **not** exist. Treatment tracked via `treatment_plans`/`treatment_visits`/`treatment_opportunities` ad hoc; no branching/loop/parallel/versioning | Reusable non-linear procedure engine (RCT, Implant, Ortho, Membership, Lab, Marketing, HR) | **Net-new capability** — largest greenfield build | **Medium** (P5) |
| **Task Engine** | `Relationship/TaskEngine` + `tasks` (relationship-linked); also `TaskController`; **no human/system split** | One store, two classes (Human vs System) with separate queue/SLA/dashboard/permissions | Add task classification; hide system jobs from reception | **Medium** (P6) |
| **Communication Engine** | Fragmented: `Whatsapp/*`, `Reviews` sends, `communication_queue`; `Relationship/CommunicationGuard` **fails open**, not 8-factor | One gateway, all channels, 8-factor **fail-closed** Guard, delivery via Integration | Unify senders; expand + flip Guard; route external through Integration | **High** (P0/P3) |
| **Notification Engine** | `Relationship/NotificationEngine` dual-writes `relationship_notifications` + `app_notifications` | One internal staff-alert store | Collapse to a single store; new writes through the engine | **Medium** (P6) |
| **Insights Engine** | `Relationship/RelationshipScoreEngine` (single 0–100 score, raw cross-domain reads); separate `Marketing/MarketingScoreService` | AI-agnostic panel of independent event-fed signals | De-god-reader; split into multi-signal projections; keep marketing score distinct | **Medium/High** (P4) |
| **Timeline Engine** | `Relationship/ProfileController` merges history at read time; **mock `Communication/TimelineController` (dead)** | Per-relationship projection of the ledger | Build projection; retire the mock | **Medium** (P2) |
| **Analytics Engine** | `Relationship/AnalyticsController` cached queries; scattered per-module reports | Event-fed aggregate views + DSO roll-ups | Introduce incremental projections | **Medium** (P6) |
| **Search Engine** | `ProfileController::search` live query; `Cms/CmsSearchService` | One unified index fed by events | Build index projection | **Low/Medium** (P6) |
| **Integration Engine** | Does **not** exist as a boundary; vendor calls embedded in `Whatsapp/*`, `Marketing/OAuthService`, payment code, `Abdm/*` | Single anti-corruption boundary for all external systems | **Net-new boundary**; wrap existing integrations | **Medium** (P5) |
| **Daily Huddle** | `Huddle/HuddleService` + `HuddleBoardApiService` + own tables | Read surface over Task / Today's Actions / Analytics | Light: wire to shared engines; keep its board | **Low** |
| **Today's Actions** | `Relationship/TodayActionsEngine` **live-reads ~12 domains** (god reader) | Presentation over a single event-fed view | Introduce Today's Actions projection; strip live reads | **High** (P2) |
| **Marketing** | `Marketing/*` services can touch `leads` directly; own `mkt_*` tables | Audiences via Search/Insights; sends via Communication+Guard; external via Integration | Route through spine + Guard; attribution via events | **Medium** (P4/P5) |
| **Chairside** | Not a distinct surface (mobile treatment-visit screens today) | Workflow-driven doctor surface | Depends on Workflow Engine | **Low / Later** |
| **Inventory** | `Inventory/InventoryService` + domain tables — healthy | Owns truth; emits events | Add event emission | **Low** |
| **Lab** | Lab domain + `LabCaseObserver` finance sync — healthy | Owns truth; participates in Lab Workflows; emits events | Add events; later Lab Workflow template | **Low** |
| **Patient App** | Flutter app on `/api/v1` (Sanctum) — healthy | Window on the same Master Relationship | Minor; benefits from spine consolidation | **Low** |
| **AI Assistant (Tulip)** | `Assistant/*` + `ToolRegistry` + `Tools/*`, agentic with confirm-cards | **Interface only** — reads projections, requests actions through engines/Guard/Decision Log | Ensure every tool routes through engines, not direct writes | **Medium** (P4) |

---

## Section 2 — Database gap analysis

Classification: **KEEP** (authoritative, unchanged) · **MERGE** (fold concept into the SSOT owner) · **MOVE** (ownership shifts) · **DEPRECATE** (stop writing; keep as archive) · **DELETE (future only)** (never now) · **UNKNOWN** (verify before acting). **No live data is recommended for deletion.**

### Engagement layer (the consolidation hotspot)

| Table | Class | Current owner | Future owner | Migration required | Dependencies | Risk |
|---|---|---|---|---|---|---|
| `activities` | **KEEP** | ActivityEngine | Activity Engine | Become the sole write target | ProfileController timeline, API timeline | Low |
| `lead_activities` | **DEPRECATE** | PRM/`LeadActivity` | Activity Engine (mirror) | Backfill → archive; stop writes | PrmController, ProfileController merge | Med (timeline reads) |
| `comm_activity_logs` | **DEPRECATE** | `CommActivityLog` | Activity Engine (mirror) | Backfill → archive; stop writes | Communication controllers | Med |
| `relationships` | **KEEP** | RelationshipEngine | Relationship Engine | Backfill patient links | leads/patients/opportunities/tasks FKs | High (identity) |
| `relationship_journeys` | **KEEP** | RelationshipEngine | Relationship Engine (pipeline SSOT) | Become authoritative for stage | PRM moveStage, opportunities | High |
| `leads.stage` (column) | **MOVE** | PRM | Journey (view) | Stage becomes a projection of journey | PRM board UI | High (UI-facing) |
| `treatment_opportunities.status` | **MOVE** | Opportunity | Journey (view) | Status becomes a projection | Opportunity board | Med |
| `relationship_rule_logs` | **KEEP → EXTEND** | RulesEngine | Rules Engine **Decision Log** | Extend schema to full decision record | RulesEngine | Low |
| `relationship_contact_log` | **KEEP** | CommunicationGuard | Communication Guard | Extend for 8-factor decisions | Guard | Low |
| `relationship_notifications` | **KEEP (primary)** | NotificationEngine | Notification Engine | Become the single store | bell UI | Med |
| `app_notifications` | **MERGE/DEPRECATE** | AppNotification | Notification Engine | Fold into single store or make delivery mirror | many callers | Med (widely used) |
| `follow_ups`, `follow_up_notes` | **MOVE** | Communication | Task Engine / Automation | Recreate as tasks + scheduled actions | FollowUpController, PRM | High (live feature) |
| `communication_queue` | **KEEP** | Communication OS | Communication Engine (unified inbox) | Feed Activity ledger; keep as queue | dashboards, recall | Med |
| `patient_relationship_notes` | **KEEP** | patient notes | Relationship/Activity | Possibly fold into activities later | notes UI | Low |
| `tasks` | **KEEP → EXTEND** | TaskEngine/TaskController | Task Engine | Add human/system class | huddle, today | Med |
| `reviews` | **KEEP** | ReviewService | Communication/Reviews | Sends via Communication Engine | review flow | Low |
| `wa_threads`, `wa_messages` | **KEEP → MOVE wire** | Whatsapp | Communication (semantics) + Integration (wire) | Split transport out to Integration | inbox UI | Med |

### Stable domains (leave alone) — **KEEP**

Patients/clinical (`patients`, `consultations`, `clinical_*`, `diagnoses`…), Treatment (`treatment_*`), Appointments (`appointments`, `operatories`…), Prescription/CDSS (`prescriptions`, `rx_*`), Billing/Finance (`invoices`, `invoice_*`, full `finance_*` suite, `wallets*`, `emi_*`, `coupon_*`), Lab (`lab_*`), Inventory/procurement (`inventory_*`, `purchase_orders`, `grn_*`, `vendor_invoices`…), Huddle (`huddle_*`), Marketing (`mkt_*`), HR (`hr_*`), DPDP (`consent_*`, `patient_consents`, `data_requests`, `data_breaches`), ABDM/FHIR (`patient_identifiers`, `fhir_documents`…), CMS/education, security/audit (`audit_logs`, `roles`, `role_module_permissions`, `branches`…), framework tables.
*Migration required:* none, except **emit domain events** from these domains (additive). *Risk:* low.

### UNKNOWN (verify before touching)

`retention_policies` (overlaps DPDP `RetentionService` semantics), any half-built `app/Modules/*` domain tables, `escalations`, `staff_activity_logs` vs `activities` overlap. *Action:* confirm live usage before classifying.

---

## Section 3 — Model gap analysis

| Model | Class | Why |
|---|---|---|
| `Relationship` | **KEEP** | The core aggregate; extend for identity/dedup/merge. |
| `RelationshipJourney` | **KEEP** | Becomes the pipeline SSOT; enrich transition maps. |
| `Activity` | **KEEP** | The one fact-ledger model; universal + polymorphic. |
| `LeadActivity` | **DEPRECATE** | Superseded by `Activity`; keep as archive/read model. |
| `CommActivityLog` | **DEPRECATE** | Third activity log; folds into `Activity`. |
| `Lead` | **KEEP (slim)** | Still the acquisition card + AI columns; but stage moves to journeys and history moves to `Activity`. Retains enrichment/routing fields. |
| `Patient` | **KEEP** | Domain truth; must gain a reliable `relationship_id` link. |
| `Appointment` | **KEEP** | Domain truth; publishes appointment events. |
| `TreatmentOpportunity` | **KEEP (slim)** | Retains estimate/value; `status` becomes a journey projection. |
| `FollowUp` / `FollowUpNote` | **MOVE** | Concept splits: human follow-up → Task Engine; scheduling → Automation Engine. Model deprecated once migrated. |
| `Task` | **KEEP (extend)** | Add human/system class; keep relationship link. |
| `RelationshipNotification` | **KEEP** | Primary notification model. |
| `AppNotification` | **MERGE** | Fold into single Notification store (or keep as delivery mirror). |
| `CommunicationQueue` | **KEEP** | Unified inbox row; feeds Activity ledger. |
| `WaThread` / `WaMessage` | **KEEP** | Messaging semantics stay; transport moves to Integration. |
| `Review` | **KEEP** | Sends via Communication Engine. |
| `MarketingScore`-related | **KEEP (distinct)** | Marketing engagement score is a *separate legitimate* concept from relationship health. |
| Clinical/Finance/Lab/Inventory/HR/DPDP/ABDM models | **KEEP** | Domain truth; only additive event emission. |
| Half-built `app/Modules/*` models | **UNKNOWN → likely DEPRECATE** | Duplicate domains already implemented elsewhere; verify then retire. |

*Principle:* no model is deleted while it is still the truth. Deprecation means "stop writing new records; keep for history/rebuild."

---

## Section 4 — Service gap analysis

| Service | Current responsibility | Future responsibility | Target engine | Verdict |
|---|---|---|---|---|
| `Prm/LeadIngestService` | Ingest + dedupe + create Lead + `linkLead` | Same, but publishes `LeadCreated`; resolves via Relationship identity | Relationship + PRM | **Remain** (becomes an intake adapter) |
| `Prm/LeadEnrichmentService` | AI summary/label/urgency/value | Unchanged — unique capability | PRM (acquisition) | **Remain** |
| `Prm/LeadRoutingService` | Auto-assign to staff | Unchanged; may emit assignment events | PRM (acquisition) | **Remain** |
| `Prm/LeadReplyService` | AI draft reply | Unchanged (draft only) | PRM (acquisition) | **Remain** |
| `Prm/LeadFollowUpService` | Create follow-ups on stage | Requests tasks/schedules via engines | Task + Automation | **Split/Move** (logic → engines; thin adapter remains) |
| `RecallEngineService` (root) | 6 recall triggers → `communication_queue` | Recall becomes **Automation** temporal events → Rules decides | Automation | **Move/Merge** into Automation Engine |
| `Relationship/RulesEngine` | Config rules → actions | The single decision engine + Decision Log | Rules | **Remain (canonical)** |
| `Communication/FollowUpRulesService` | Legacy follow-up rules (frozen) | Rules ported into RulesEngine | Rules | **Disappear** (after port) |
| `Relationship/ActivityEngine` | Log facts + afterCommit chain | The fact ledger writer | Activity | **Remain (canonical)** |
| `Relationship/ReminderEngine` | Create reminder tasks | Folds into Automation (scheduling) + Task | Automation/Task | **Merge** into Automation Engine |
| `Relationship/AppointmentReminderEngine` | Daily appointment reminders | Automation scheduled evaluation | Automation | **Merge** into Automation Engine |
| `Relationship/TodayActionsEngine` | Live-reads ~12 domains | Reads one projection; presentation feed | Today's Actions view | **Split** (retire live reads; keep composer) |
| `Relationship/YesterdayReviewService` | Yesterday misses | Fed by events into Today's Actions view | Today's Actions view | **Merge/Move** |
| `Relationship/RelationshipEngine` | Identity(partial)+journeys+profile | Identity+dedup+merge+journeys | Relationship | **Remain (extend)** |
| `Relationship/RelationshipScoreEngine` | Single score, raw reads | Multi-signal AI-agnostic projections | Insights | **Split/Rename** into Insights Engine |
| `Relationship/NotificationEngine` | Dual-write notifications | Single internal store | Notification | **Remain (consolidate store)** |
| `Relationship/CommunicationGuard` | Fail-open, 3 rules | 8-factor fail-closed gate | Communication | **Remain (extend + flip)** |
| `Whatsapp/OutboundMessageService` | Send WhatsApp | Communication requests → Integration wire | Communication + Integration | **Split** (semantics stay; wire → Integration) |
| `Whatsapp/InboundMessageService` | Inbound WhatsApp | Integration normalizes → `CommunicationReceived` | Communication + Integration | **Split** |
| `Whatsapp/WhatsAppCloudService` | Vendor API client | Becomes an Integration connector | Integration | **Move** |
| `Marketing/CampaignService`, `CampaignLeadService` | Campaigns + attribution | Audiences via Search/Insights; sends via Communication | Marketing + engines | **Remain (rewire)** |
| `Marketing/MarketingScoreService` | Marketing engagement score | Distinct signal; may live under Insights as separate signal | Insights (separate) | **Remain (distinct)** |
| `Marketing/OAuthService` | Meta/Google OAuth | Integration connector concern | Integration | **Move** |
| `Reviews/ReviewService` | Review requests | Sends via Communication Engine | Reviews + Communication | **Remain (rewire send)** |
| `Billing/InvoicePaymentService` | Shared web+API payment engine | Unchanged; emits `PaymentReceived` | Billing domain | **Remain (excellent)** |
| `AppointmentService`, `PatientService`, `PatientProfileService`, `TreatmentVisitService`, `MembershipBenefitService`, `WalletService`, `CouponService` | Domain services | Unchanged; add event emission | Their domains | **Remain** |
| `Assistant/*` (`AssistantService`, `ToolRegistry`, `Tools/*`, `OllamaClient`, `VisionService`, scan services) | Agentic AI + tools | AI as **interface**: tools call engines, not direct writes | AI interface | **Remain (constrain to engines)** |
| `Huddle/*`, `Inventory/*`, `Prescription/PrescriptionAlertService`, `ClinicalLibrary/*`, `Cms/*`, `ContentManagement/*`, `Voice/*` | Domain services | Unchanged; emit events where relevant | Their domains | **Remain** |
| `ConsentService`, `DataRightsService`, `BreachService`, `RetentionService` | DPDP | Consent consumed by Guard; retention distinct | DPDP domain | **Remain** |
| `Lab*` services (`LabAlertService`, `LabExpenseService`, `LabNotificationService`) | Lab ops | Emit lab events; notifications via Notification Engine | Lab + Notification | **Remain (rewire notify)** |

---

## Section 5 — Controller gap analysis

Guiding rule: **controllers become thin; business logic lives in engines.**

| Controller group | Verdict | Notes |
|---|---|---|
| `Communication/PrmController` | **Simplify + Delegate** | Move stage side-effects into Rules/Journey; keep acquisition UI. Already redirects to `relationship.profile`. |
| `Communication/*` (queue, follow-up, opportunity, B2B, dashboard) | **Simplify/Delegate** | Delegate to Communication/Task/Automation engines; keep as thin HTTP. |
| `Communication/TimelineController` | **Deprecate** | Mock/dead; replace with Timeline projection surface. |
| `Communication/RecallController` | **Delegate/Deprecate** | Recall becomes Automation; controller becomes a thin admin view or retires. |
| `Relationship/TodayController` | **Simplify** | Read the Today's Actions projection only. |
| `Relationship/ProfileController` | **Simplify** | Read Timeline/Insights projections; stop merging at read time. |
| `Relationship/AnalyticsController` | **Delegate** | Read Analytics projections. |
| `Relationship/NotificationController` | **Remain (thin)** | Already thin over Notification Engine. |
| `Api/V1/*` (17 controllers) | **Remain (thin)** | Best-architected layer; reuse shared services. Keep contracts stable for the mobile app. |
| `Api/V1/RelationshipController` | **Remain (thin)** | Already delegates to engines. |
| Root clinical/billing/lab/inventory/treatment controllers (~40) | **Remain / light Simplify** | Domain truth; add event emission, otherwise stable. |
| `Finance/*`, `HR/*`, `Prescription/*`, `Cms/*`, `ContentManagement/*`, `Settings/*` | **Remain** | Stable domains. |
| `Marketing/*` (14) | **Simplify/Delegate** | Route sends through Communication+Guard; audiences via Search/Insights. |
| `Abdm/*` | **Remain** | External identity flows; wire external calls via Integration later. |
| `Webhooks/*` (Meta/Website/WhatsApp/chatbot) | **Delegate** | Become Integration inbound → normalized events. |
| `Auth/*` | **Remain** | Stable. |

---

## Section 6 — Route gap analysis

13 route files, bolted on in `bootstrap/app.php` + `web.php` requires (fragmented — no `RouteServiceProvider` grouping).

| Route file / group | Verdict | Notes |
|---|---|---|
| `api.php` (`/api/v1/*`) | **Remain (stable contract)** | Mobile/Tulip surface — **backward compatibility mandatory**. |
| `relationship.php` | **Remain** | Core engine routes (today/profile/search/analytics/notifications). |
| `prm.php` | **Remain + Redirect** | Keep acquisition UI; `leadDetail` already redirects to profile. |
| `communication.php` | **Merge/Simplify** | Consolidate engagement routes; delegate to engines. |
| `timeline.php` | **Deprecate** | Backs the mock timeline; redirect to relationship profile. |
| `reviews.php` | **Remain** | Public rating pages + admin; keep `/r/{token}` stable. |
| `marketing.php` | **Remain (rewire)** | Keep URLs; rewire controllers to engines. |
| `prescriptions.php`, `clinical-library.php`, `cms.php`, `tags-routes.php` | **Remain** | Stable domains. |
| `web.php` | **Simplify (structure)** | Long-term: centralize route composition; low priority, non-breaking. |
| `console.php` (scheduler) | **Consolidate** | `recall:run`, `relationship:appointment-reminders`, `whatsapp:send-reminders`, `reviews:request` converge under the Automation Engine's scheduled evaluations. |

*Principle:* **maintain backward compatibility wherever practical** — prefer redirects and internal delegation over URL changes, especially for `/api/v1` and public review links.

---

## Section 7 — Event gap analysis

**Reality today:** there is *no event bus*. Cross-module reactions happen via 4 observers (`LeadObserver`, `LabCaseObserver`, 2 ABDM) and an in-process `ActivityEngine::log → afterCommit → RulesEngine` chain. So for almost every workflow, the gap is **"publisher and/or subscribers missing."** The target names each fact and its listeners.

| Workflow | Current flow | Target flow | Missing publishers | Missing subscribers | Duplicate emitters |
|---|---|---|---|---|---|
| **Patient Registered** | Direct patient create; **no relationship link** | `PersonRegistered` → Relationship links/dedupes | Publisher (patient create) | Relationship, Activity, Insights | — |
| **Lead Created** | `LeadObserver` fires enrich/route/activity; `linkLead` called | `LeadCreated` → Rules, Relationship(open journey), Activity, Analytics, Search, Insights | Formal event | Analytics/Search/Insights listeners | Activity logged directly (OK) |
| **Lead Converted** | Stage change in PRM | `LeadConverted` → Rules, Workflow(start), Automation(arm recall) | Publisher | Workflow, Automation | Journey dual-write today |
| **Appointment Booked** | Scheduling write | `AppointmentBooked` → Rules(confirm), Automation(arm tomorrow) | Publisher | Automation, Insights | — |
| **Appointment Completed** | Scheduling/clinical write | `AppointmentCompleted` → Relationship(advance), Workflow(step), Rules(post-op/review) | Publisher | Workflow, Relationship advance | Recall stamp columns (ad hoc) |
| **Treatment Planned** | Clinical write | `TreatmentPlanned` → Relationship(open opp), **Workflow(instantiate)**, Rules | Publisher | **Workflow (none exists)** | — |
| **Treatment Completed** | Clinical write | `TreatmentCompleted` → Relationship, Workflow, Rules, Insights(LTV) | Publisher | Workflow, Insights | — |
| **Payment Received** | Billing write | `PaymentReceived` → Rules(receipt), Insights(LTV), Analytics | Publisher | Insights, Analytics | — |
| **Lab Ready** | `LabCaseObserver` (finance) | `LabCaseReady` → Rules, Workflow(lab step), Automation(overdue) | Formal event | Workflow, Automation | — |
| **Membership Renewed/Expiring** | Membership + stamps | `MembershipRenewed/Expiring` → Rules, Workflow | Publisher/Temporal | Workflow | recall stamp overlap |
| **Recall Due** | `RecallEngineService` sweep → queue | Automation emits `RecallDue` → Rules → Comm/Task | Move to Automation | Rules/Today's Actions | **Two reminder pipelines** |
| **Task Completed** | Task update | `TaskCompleted` → Rules(chain), Workflow(advance), Analytics | Publisher | Workflow, Analytics | — |
| **Communication Logged** | Whatsapp/queue writes | `CommunicationSent/Received` → Activity, Timeline, Insights(pref) | Formal event | Insights preference | 3 activity logs |
| **Review Requested** | `ReviewService` direct send | `CommunicationRequested` → Guard → Integration | Route via Comm | Guard/Integration | direct send bypasses Guard |

**Net:** the single biggest event gap is the **absence of a formal publish/subscribe contract** plus **no Workflow subscribers** (the engine doesn't exist). P0 introduces the event backbone; P5 adds Workflow subscribers.

---

## Section 8 — Single Source of Truth (current → target)

| Capability | Current owner | Target owner | Migration difficulty | Risk |
|---|---|---|---|---|
| **Identity** | split `leads`/`patients` (lead-linked only) | Relationship Engine | **High** (backfill + dedup) | High |
| **Timeline** | ProfileController merge + mock | Timeline Engine (projection) | Medium | Med |
| **Relationship / journeys** | RelationshipEngine + PRM stage + opp status | Relationship Engine (journeys) | High | High (UI-facing) |
| **Activities** | 3 logs | Activity Engine | Medium | Med |
| **Reminder / recall** | RecallEngineService + Reminder engines | Automation Engine | High | High (duplicate sends) |
| **Task** | TaskEngine + TaskController + follow_ups | Task Engine (human/system) | Medium | Med |
| **Notification** | 2 stores | Notification Engine | Medium | Med |
| **Communication** | Whatsapp + queue + Reviews sends | Communication Engine + Guard | High | High (consent/DPDP) |
| **Analytics** | AnalyticsController + scattered | Analytics Engine (projections) | Medium | Low |
| **Insights** | RelationshipScoreEngine (raw reads) | Insights Engine (signals) | Medium/High | Med |
| **Workflow** | none (ad hoc treatment) | Workflow Engine | **High (greenfield)** | Med |
| **Rules** | RulesEngine + FollowUpRulesService | Rules Engine + Decision Log | Medium | Med |
| **Search** | live query + CmsSearchService | Search Engine (index) | Medium | Low |
| **External systems** | scattered vendor SDKs | Integration Engine | Medium/High | Med (regressions) |

---

## Section 9 — Duplication report

| Duplicate | Current duplication | Future owner | Migration strategy |
|---|---|---|---|
| **Activity logging** | `activities` + `lead_activities` + `comm_activity_logs` (+ `staff_activity_logs`, `mkt_activity_log`) | Activity Engine (`activities`) | Producers emit facts → ledger; legacy logs backfilled → archive → stop writes |
| **Reminder scheduling** | `RecallEngineService` **and** `AppointmentReminderEngine`/`ReminderEngine` | Automation Engine | Route all temporal triggers through Automation; silence duplicate emitter |
| **Rules** | `RulesEngine` **and** `FollowUpRulesService` (frozen) | Rules Engine | Port legacy config; delete legacy invocation after parity |
| **Notifications** | `relationship_notifications` **and** `app_notifications` | Notification Engine | New writes through engine; collapse to one store (or delivery mirror) |
| **Pipeline state** | `leads.stage` + `treatment_opportunities.status` + `relationship_journeys` | Relationship Journeys | Journeys authoritative; stage/status become read-through projections |
| **Timeline** | mock `TimelineController` + ProfileController merge | Timeline Engine | Retire mock; build projection; profile reads projection |
| **Analytics** | AnalyticsController + per-module reports | Analytics Engine | Incremental projections fed by events |
| **Tasks** | TaskEngine + TaskController + `follow_ups` | Task Engine | Follow-ups recreated as tasks/scheduled actions |
| **Search** | ProfileController::search + CmsSearchService | Search Engine | One index fed by events |
| **Scoring** | RelationshipScoreEngine + MarketingScoreService | Insights Engine (marketing stays a distinct signal) | Split relationship signals into projections; keep marketing score separate |
| **Relationship identity** | leads/patients unlinked | Relationship Engine | Wire `linkPatient` + backfill + dedup |

---

## Section 10 — Technical debt register

| # | Debt | Category | Severity |
|---|---|---|---|
| 1 | Three-generation engagement stack (PRM + Communication OS + Relationship) running at once | Architecture / Coupling | **Critical** |
| 2 | `linkPatient()` unwired → `relationships` inconsistent with `patients` | Data integrity | **Critical** |
| 3 | Two reminder pipelines can double-contact patients | Architecture / Data integrity | **High** |
| 4 | `CommunicationGuard` **fails open** + legacy recall bypasses it (DPDP exposure) | Security / Compliance | **High** |
| 5 | "God readers" (`TodayActionsEngine`, `RelationshipScoreEngine`) raw-query ~12 domains | Coupling / Performance / Scalability | **High** |
| 6 | Three activity logs; no unified history | Architecture / Database | **High** |
| 7 | Sync-by-side-effect (lead stage⇄journey, notification dual-write, vendor tables) | Data integrity | **High** |
| 8 | No event bus; reactions via 4 observers + afterCommit chain | Architecture | **High** |
| 9 | No/thin automated test coverage around engagement engines | Testing | **High** |
| 10 | Fragmented routing (13 files, no `RouteServiceProvider`, per-file auth) | Architecture / Maintainability | **Medium** |
| 11 | Three module conventions (`Services` vs `Http/Controllers` vs half-built `Modules`) | Naming / Maintainability | **Medium** |
| 12 | Vendor SDKs embedded in business services (no Integration boundary) | Coupling / Scalability | **Medium** |
| 13 | Mock `TimelineController` routed but dead | Maintainership / Documentation | **Medium** |
| 14 | `ARCHITECTURE.md` stale (2026-06-18) — now superseded by the target docs | Documentation | **Medium** |
| 15 | Live multi-table Today/score queries; event-driven unbounded recompute | Performance | **Medium** |
| 16 | Vendor tables kept consistent by app-code, not FKs | Database / Data integrity | **Medium** |
| 17 | Workflow/Integration capabilities absent (net-new) | Architecture | **Medium** |
| 18 | System retries/webhooks share `tasks` with human work (no class split) | Maintainability / DX | **Low/Medium** |
| 19 | No Decision Log → automation not fully explainable | Observability | **Low/Medium** |
| 20 | Developer onboarding cost (can't infer where things live) | Developer Experience | **Low/Medium** |

---

## Section 11 — Migration risk analysis

| Risk | Where it bites | Mitigation |
|---|---|---|
| **Broken workflows** | Consolidating reminders/rules could drop live automations | Strangler-fig: run new path in shadow, compare outputs, cut over per rule behind flags |
| **Lost events** | Introducing a bus without every publisher wired | P0 first: define event contracts + backfill publishers before any consumer relies on them |
| **Duplicate reminders** | Both pipelines active during transition | Single-owner cutover per trigger; disable legacy emitter as each trigger moves; idempotency/cooldown in Automation |
| **Relationship mismatch** | `linkPatient` backfill mis-merges people | Dry-run dedup with review queue; conservative match rules; reversible merges (merge history) |
| **Performance regression** | Projections rebuild cost; broker latency | Build projections incrementally; keep in-memory events at solo tier; index the Today's-Actions view |
| **Backward compatibility** | `/api/v1` and public review links | Freeze API contracts; prefer redirects/internal delegation; contract tests before cutover |
| **Mobile API drift** | Flutter app depends on `/api/v1` shapes | Version endpoints; snapshot tests; never change response shape silently |
| **Data integrity** | Dual-write windows | Make one side authoritative first; derive the other; never hand-sync long-term |
| **Testing gaps** | Engagement engines under-tested | Add characterization tests around current behavior *before* refactoring (safety net) |
| **Rollback** | A cutover goes wrong | Feature flags per capability; keep legacy path warm until new path verified; projections rebuildable from ledger |
| **DPDP/compliance** | Guard changes mid-flight | Flip Guard fail-closed early (P0); route every sender through it before removing legacy sends |

---

## Section 12 — What should NOT change (leave excellent things alone)

- **The `/api/v1` layer** reusing shared services (`InvoicePaymentService`, `PatientService`, etc.) — this is the model the rest should follow. Freeze its contracts.
- **The clinical/billing/lab/inventory/HR/finance domains** — deep, correct, vertical logic. Only *additively* emit events.
- **Prescription CDSS** (`rx_*`, `PrescriptionAlertService`) — mature and safety-critical; leave alone.
- **DPDP consent stack** and **ABDM/FHIR** scaffolding — regulatory, working; consume (don't rebuild) from the Guard/Integration.
- **The Activity ledger design** (`Activity` polymorphic, ActivityEngine never throws, afterCommit chain) — already the right shape; extend, don't replace.
- **The Assistant `ToolRegistry`/confirm-card pattern** — the correct way to make AI safe; keep it, just ensure tools call engines.
- **Finance suite, wallet/EMI/coupon chains, Huddle board, Marketing `mkt_*` internals** — functional; only rewire *sends* and *attribution* to events.
- **The strangler-fig instinct already in the code** (RecallEngine dual-writing to ActivityEngine, PRM redirecting to profile) — continue the pattern.

*Avoid unnecessary refactoring:* do not "tidy" stable domains for consistency's sake. Effort belongs in the engagement layer.

---

## Section 13 — What should change first (top 20, ranked)

Each: **BV** business value · **TV** technical value · **Cx** complexity · **Rk** risk · **Deps** · **Phase**.

1. **Add characterization tests around engagement behavior** — BV: safety · TV: enables all refactor · Cx: Med · Rk: Low · Deps: none · **P0**.
2. **Define the domain-event catalog + shared publish/subscribe contracts** — BV: unblocks everything · TV: Critical · Cx: Med · Rk: Med · Deps: 1 · **P0**.
3. **Flip Communication Guard to fail-closed + route all sends through it** — BV: DPDP compliance · TV: High · Cx: Med · Rk: Med · Deps: 2 · **P0**.
4. **Formalize the Decision Log (extend `relationship_rule_logs`)** — BV: explainability/audit · TV: High · Cx: Low · Rk: Low · Deps: 2 · **P0**.
5. **Wire `linkPatient()` into patient creation** — BV: identity integrity · TV: Critical · Cx: Med · Rk: Med · Deps: 2 · **P1**.
6. **Backfill `relationship_id` across leads + patients (with dedup review queue)** — BV: one-person truth · TV: High · Cx: High · Rk: High · Deps: 5 · **P1**.
7. **Make `activities` the sole write target; mirror legacy logs read-only** — BV: unified history · TV: High · Cx: Med · Rk: Med · Deps: 2 · **P2**.
8. **Build the Timeline projection; retire mock `TimelineController`** — BV: real patient story · TV: Med · Cx: Med · Rk: Low · Deps: 7 · **P2**.
9. **Introduce the Today's Actions projection; strip live 12-domain reads** — BV: fast, reliable reception dashboard · TV: High · Cx: Med · Rk: Med · Deps: 2,7 · **P2**.
10. **Stand up the Automation Engine; move recall triggers into it** — BV: no double-contact · TV: High · Cx: High · Rk: High · Deps: 2,3 · **P3**.
11. **Consolidate reminder task creation on Task Engine; silence duplicate emitter** — BV: reliability · TV: High · Cx: Med · Rk: High · Deps: 10 · **P3**.
12. **Port `FollowUpRulesService` config into Rules Engine; retire legacy invocation** — BV: one automation brain · TV: High · Cx: Med · Rk: Med · Deps: 4,10 · **P3**.
13. **Make Journeys the pipeline SSOT; `leads.stage`/`opportunity.status` become projections** — BV: consistent pipeline · TV: High · Cx: High · Rk: High · Deps: 6 · **P4**.
14. **Split RelationshipScore into Insights multi-signal projections (AI-agnostic)** — BV: actionable intelligence · TV: Med · Cx: Med · Rk: Med · Deps: 7 · **P4**.
15. **Introduce read contracts insulating Insights/Today from raw domain tables** — BV: change safety · TV: High · Cx: Med · Rk: Low · Deps: 9,14 · **P4**.
16. **Collapse notifications to a single Notification store** — BV: consistency · TV: Med · Cx: Med · Rk: Med · Deps: 2 · **P6**.
17. **Stand up the Integration Engine; wrap WhatsApp/OAuth/payments/ABDM** — BV: provider-agnostic, fewer outages · TV: Med · Cx: Med/High · Rk: Med · Deps: 3 · **P5**.
18. **Build the Workflow Engine (non-linear) + first template (RCT or Implant)** — BV: "understands dentistry" · TV: High · Cx: High · Rk: Med · Deps: 2,10 · **P5**.
19. **Add Human/System task classification (hide system jobs from reception)** — BV: calm UX · TV: Med · Cx: Low/Med · Rk: Low · Deps: 11 · **P6**.
20. **Build the Search index projection + Analytics projections** — BV: fast search/reporting · TV: Med · Cx: Med · Rk: Low · Deps: 2,7 · **P6**.

*(Chairside, Marketplace, and the reserved Organization Engine come after this list — they depend on Workflow/Integration and the DSO need being real.)*

---

## Section 14 — Migration readiness score

Scored out of 10 — *readiness of the current codebase to reach the target*, with rationale.

| Dimension | Score | Rationale |
|---|---|---|
| **Architecture** | 6/10 | Modular monolith with the *right* target already designed and partially built (Relationship/Activity/Rules exist). Held back by three-generation overlap and no event bus. |
| **Database** | 6/10 | Rich, mostly well-modeled; core tables exist. Debt = duplicate logs, unbackfilled links, sync-by-side-effect, app-enforced vendor consistency. |
| **Services** | 6/10 | Strong shared-service pattern in places (Billing/Patient reused web+API). Debt = duplicated engagement services and god-readers. |
| **Events** | 3/10 | No event bus, no `Events`/`Listeners`; only 4 observers + an afterCommit chain. This is the biggest structural gap and gates most work. |
| **Engines** | 5/10 | 8 of 13 target engines exist in some form (Relationship, Activity, Rules, Task, Notification, Guard, Score→Insights, TodayActions). Missing/immature: Automation (scattered), Workflow (absent), Integration (absent), Search/Analytics/Timeline (as projections). |
| **Testing** | 3/10 | Route crawler exists, but little automated coverage around engagement logic — the exact area being refactored. Must build a safety net first. |
| **Scalability** | 5/10 | Data model scales; read patterns (live multi-domain queries, unbounded recompute) and vendor coupling need work before thousands of clinics. |
| **Maintainability** | 5/10 | Clean domains coexist with a confusing three-convention layout and fragmented routing; stale top-level docs (now superseded). |
| **Developer Experience** | 5/10 | Powerful but hard to navigate; "where does this live?" is ambiguous. The frozen target + this roadmap materially improve DX. |
| **Overall Readiness** | **5.2/10** | A capable, ambitious platform **mid-migration with a clear, frozen destination.** The path is well-understood and de-riskable via strangler-fig; the gating investments are the event backbone (P0), identity integrity (P1), and a test safety net. Nothing here requires a rewrite — only disciplined, phased consolidation. |

---

## Closing note

The current codebase is **not far** from the target in *intent* — the Relationship spine, Activity ledger, and Rules engine already exist and the code shows the migration instinct (dual-writes, redirects, frozen-legacy comments). The distance is in **consolidation, an event backbone, identity integrity, and three net-new/immature engines (Automation, Workflow, Integration)**. Executed in the phase order above — safety net → events → identity → reads → automation → pipeline/insights → net-new engines — this is a controlled, reversible, backward-compatible migration, not a rewrite.

**Per the instruction: this is analysis only. Do not begin implementation from this document — it is the map, not the journey.**

*End of gap analysis.*
