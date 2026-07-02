# Dentfluence Relationship Engine — V1 Build Plan

> **Last updated:** 2026-07-02
> **Author:** Sumit / Dentfluence
> **Status:** Phases 1–7 complete.

---

## Vision in One Sentence

Build the operating system that quietly runs the relationship between a dental clinic and its patients for the next 20 years.

---

## What This Is Not

- Not a CRM refactor.
- Not a redesign of the application.
- Not a new product.

This is a **major architectural refactor of the existing PRM module** into the Dentfluence Relationship Engine — the intelligence layer that every future Dentfluence product (PMS, Marketing Engine, Patient App, AI, Analytics, Marketplace) will eventually consume.

---

## Core Principle: Relationship over Lead

| Old Thinking | New Thinking |
|---|---|
| Lead is the primary object | Relationship is the primary object |
| Lead converts → Patient | Relationship contains both journeys |
| Lead ends when converted | Relationship never ends |
| Separate histories | One unified timeline, forever |
| CRM pipeline | Clinic operating system |

---

## The 7 Engines

Everything in this module is an engine — reusable, config-driven, consumed by multiple surfaces.

| # | Engine | Purpose |
|---|---|---|
| 1 | **RelationshipEngine** | Central aggregate. One record per person, forever. |
| 2 | **ActivityEngine** | Universal log. Everything writes here. Powers Timeline, Audit, AI context. |
| 3 | **TodayActionsEngine** | Generates today's work automatically. The most important page. |
| 4 | **RulesEngine** | Config-driven business rules. Replaces hardcoded conditions everywhere. |
| 5 | **ReminderEngine** | All reminders: appointments, recall, birthday, membership, lab. |
| 6 | **TaskEngine** | Auto-generates tasks from journey changes, recall triggers, opportunity aging. |
| 7 | **RelationshipScoreEngine** | Configurable health/score calculation. Powers analytics and prioritization. |

---

## Consolidated Architecture Decisions

The following were stated multiple times in various forms in the product brief — consolidated here as single principles:

### 1. Engine-First, Not Feature-First
Do not build "Lead Followup" + "Recall Followup" + "Membership Followup."
Build **Followup Engine** and let everything consume it.

### 2. Configuration Over Hardcoding
All automation timings, recall intervals, reminder windows, communication rules, score weights live in config files or the rules engine. No `if (implant && 6months)` buried in controllers.

### 3. Audit Everything (extending Phase A Security)
Every meaningful action generates an audit event with: who, when, what, old value, new value. Phase A already built `HashChained` audit. We extend it — we do not rebuild it.

### 4. API-First
Every engine exposes clean service methods. UI calls services. Mobile calls the same services via `/api/v1/`. Future products call the same services.

### 5. Relationship Health = Relationship Score
These are the same concept. One engine. Score is the output. Factors: visit frequency, recall compliance, treatment completion, membership, referrals, communication response, payment history. Weights are configurable.

### 6. Permissions Already Exist
Phase A built RBAC, role gates, and `BranchScope`. We respect existing permissions. We add new gate checks inside the Relationship Engine where needed — we do not rebuild the permission system.

---

## What Already Exists (Do Not Rebuild)

| Component | Location | Status |
|---|---|---|
| `RecallEngineService` | `app/Services/RecallEngineService.php` | ✅ Built — 6 triggers, deduplication |
| `Task` model | `app/Models/Task.php` | ✅ Robust — recurring, branch, lab, PO-linked |
| `TreatmentOpportunity` model | `app/Models/TreatmentOpportunity.php` | ✅ Built — stages, priorities, overdue detection |
| `Huddle` module | `app/Modules/Huddle/` | ✅ Full — boards, cards, DTOs, role-based views |
| `Lead` model + Observer | `app/Models/Lead.php` + `LeadObserver` | ✅ Built — AI enrichment, routing, stages |
| `LeadIngestService` | `app/Services/Prm/LeadIngestService.php` | ✅ Built — webhook deduplication, channel tagging |
| `config/prm.php` | Config file | ✅ Config-driven — AI, routing, webhooks, value bands |
| RBAC + Audit + BranchScope | Phase A Security | ✅ Complete |
| `TimelineService` | `app/Services/ContentManagement/TimelineService.php` | ⚠️ Partial — needs unification |
| `communication_queue` | Existing table | ⚠️ Used by RecallEngine — will feed ActivityEngine |

---

## The Core Problem (Why We're Refactoring)

```
Lead (stage=new_enquiry) ──────► converted ──────────────────────────────►  DEAD END
                                     │
                                     ▼
                              Patient created ──────────────────────────────► New record
```

When a lead converts, `stage = 'converted'` is set and a new Patient record is created. There is no persistent link. Pre-conversion history lives in `lead_activities`. Post-conversion lives in `patient_communications` + `communication_queue`. Three separate timeline views. No unified person record.

```
After refactor:

Relationship (permanent, never deleted)
     │
     ├── Lead Journey (temporary, closes when treatment starts)
     ├── Patient record (permanent, created on first visit/conversion)
     ├── Treatment Journey
     ├── Recall Journey (lifetime, runs forever)
     ├── Opportunity Journey (one per opportunity, multiple allowed)
     ├── Membership Journey
     ├── ActivityEngine feed (one unified timeline)
     └── RelationshipScore (continuously recalculated)
```

---

## Phase Build Plan

### ✅ Phase 0 — Analysis (Complete)
- [x] Map existing models and services
- [x] Identify what to keep, extend, and replace
- [x] Define all 7 engines
- [x] Consolidate repeated requirements
- [x] This document

---

### 🔲 Phase 1 — Foundation: Relationship + ActivityEngine

**Goal:** Every person in the system has one `Relationship` record. Nothing breaks.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| `relationships` table | Migration | Master record. `phone`, `email`, `name`, `relationship_since`, `source`, `score`, `status` |
| `relationship_journeys` table | Migration | One row per journey type per relationship. `type` enum: lead/treatment/recall/opportunity/membership/referral |
| `activities` table | Migration | Universal log. Polymorphic `subject` (what). `actor_type/id` (who). `event` (action). `metadata` JSON. |
| FK: `leads.relationship_id` | Migration | Nullable. Added to existing `leads` table. |
| FK: `patients.relationship_id` | Migration | Nullable. Added to existing `patients` table. |
| `Relationship` model | Model | Relationships to Lead, Patient, Journeys, Activities, Score |
| `RelationshipJourney` model | Model | State machine: valid states + valid transitions enforced |
| `Activity` model | Model | Polymorphic. Replaces `LeadActivity` long-term. |
| `RelationshipEngine` service | Service | `findOrCreate()`, `linkLead()`, `linkPatient()`, `getProfile()` |
| `ActivityEngine` service | Service | `log(subject, event, actor, metadata)` — single method, everything calls this |

**What we preserve:**
- `leads` table untouched (just adds one nullable FK column)
- `patients` table untouched (just adds one nullable FK column)
- Existing PRM board continues working on `leads` table
- Existing LeadIngestService continues working

**State machine for RelationshipJourney (Lead Journey):**
```
new_enquiry → contacted → appointment_booked → consultation → treatment_planned → treatment_started [CLOSED]
           ↘ lost (from any stage)
```

**Estimated size:** ~5 migrations, 3 models, 2 services (~300 lines total)

**Trace of old app:** `leads` and `lead_activities` remain. PRM board remains. Nothing removed.

---

### 🔲 Phase 2 — Today's Actions

**Goal:** Reception lands on this page every morning and knows exactly who to call, why, and what to say.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| `TodayActionsEngine` service | Service | Aggregates 12 action categories from all data sources |
| `/relationship/today` route + view | Route + Blade | The new default landing page |
| Call Workflow view | Blade partial | Open patient → summary → reason → checklist → log response → next |
| Dynamic checklists | Config/Blade | Per call type: Appointment Reminder, Recall, Opportunity, Estimate, Birthday, Membership Renewal |
| `YesterdayReviewService` | Service | Runs at midnight: missed appts, unanswered recalls, cancelled appts → feed into today |

**12 action categories:**

```
1. New Enquiries (uncontacted leads, last 24h)
2. Lead Followups (overdue stage followups)
3. Treatment Opportunities (overdue follow_up_date)
4. Recall Calls (from RecallEngine queue)
5. Appointment Reminder Calls (appointments tomorrow/today)
6. Yesterday Missed Calls (recall items not actioned)
7. Yesterday Missed Appointments (no-shows)
8. Pending Estimates (opportunities in 'quoted' stage, overdue)
9. Membership Renewals (expiring in 30 days)
10. Birthday Wishes (today ± 1 day)
11. Lab Ready (lab cases received, no appointment)
12. Payment Reminders (overdue invoices, configurable threshold)
```

**Estimated size:** 1 service, 1 controller, 2 views (~400 lines total)

**Trace of old app:** `/communication/prm/inbox` remains. Old board remains. Today's Actions is an additive new page.

---

### 🔲 Phase 3 — Relationship Profile

**Goal:** Open any person → see everything from first enquiry to last payment on one page.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| `/relationship/{id}` route + controller | Route + Controller | Unified profile. Works whether person is Lead, Patient, or both. |
| Relationship Summary section | Blade partial | Started date, age, lifetime revenue, visits, pending treatment, score, next action |
| Unified Timeline | Blade + JS | Merges: lead_activities + patient_communications + appointments + payments + tasks + notes. Powered by ActivityEngine. |
| Universal Search | Blade + AJAX | Name, phone, patient ID, treatment, appointment ref. Returns Relationship records. |
| `/relationship/{id}/journeys` | Blade partial | All active/closed journeys. Visual state machine. |

**Unified Timeline merges (old traces preserved in ActivityEngine):**

```
Old: lead_activities table          → ActivityEngine reads + displays
Old: patient_communications table   → ActivityEngine reads + displays
Old: communication_queue table      → ActivityEngine reads + displays
Old: /communication/timeline/       → Replaced by unified timeline on Relationship Profile
Old: /communication/prm/lead/{id}/  → Redirects to /relationship/{id}
```

**Estimated size:** 1 controller, 4 blade partials, 1 JS file (~500 lines total)

---

### 🔲 Phase 4 — Journey Engine + Kanban Refactor

**Goal:** The Kanban board visualizes Relationship Journeys, not just lead stages.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| Refactor PRM board | Blade + JS | Rename to "Relationship Journey." Cards pull from `relationships` table via `RelationshipJourney`. |
| State machine enforcement | Service | Invalid transitions rejected. E.g. cannot jump from `new_enquiry` to `treatment_started`. |
| Wire `TreatmentOpportunity` to Relationship | Migration + Model | Add `relationship_id` FK. Opportunities visible inside Relationship card. |
| Wire `RecallEngineService` to Relationship | Service update | Recall queue items linked to `relationship_id`. |
| `AppointmentReminderEngine` | Service | Auto-creates reminder tasks 24h before appointments. No manual creation. |

**Trace of old app:**
- PRM board routes (`/communication/prm/board`) kept — redirect or co-exist during transition
- `lead_activities` still written to (backward compat) alongside ActivityEngine

**Estimated size:** ~2 migrations, 2 service updates, refactored board view (~400 lines)

---

### 🔲 Phase 5 — Automation Engines

**Goal:** System generates tomorrow's work automatically. Staff stop creating manual reminders.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| `RulesEngine` | Service | Config-driven. Reads `config/relationship_rules.php`. Replaces all scattered `if (treatment && days)` logic. |
| `config/relationship_rules.php` | Config | All automation rules: timings, triggers, cooldowns. |
| `ReminderEngine` | Service | Structured. Consumes RulesEngine. Writes to TaskEngine + ActivityEngine. |
| `CommunicationGuard` | Service | Silent. Checks last contact before queuing any outbound. Prevents over-communication. |
| `TaskEngine` | Service | Extends existing `Task` model. Auto-creates tasks from journey events. |
| `FailSafeQueue` | Observer/Job | Logs all automation failures. Places failed items in exception queue. Admin notification on repeated failures. |

**Example rules in `relationship_rules.php`:**
```php
'implant_followup'        => ['trigger' => 'treatment_completed', 'treatment' => 'implant', 'days_after' => 7],
'recall_6months'          => ['trigger' => 'last_visit', 'days_after' => 180, 'cooldown_days' => 30],
'appointment_reminder'    => ['trigger' => 'appointment_booked', 'hours_before' => 24],
'membership_renewal'      => ['trigger' => 'membership_expiry', 'days_before' => 30],
'birthday'                => ['trigger' => 'birthday', 'days_before' => 3],
'opportunity_nudge'       => ['trigger' => 'opportunity_created', 'days_after' => 7, 'status' => 'prospect'],
```

**Trace of old app:**
- `FollowUpRulesService` (`app/Services/Communication/FollowUpRulesService.php`) — migrated into RulesEngine, old file kept with deprecation comment
- `RunRecallEngine` command — updated to call RulesEngine, old triggers preserved

**Estimated size:** 3 services, 1 config file, 1 observer (~600 lines total)

---

### 🔲 Phase 6 — Notifications + Analytics

**Goal:** Right people notified at right time. Managers see pipeline health without building reports.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| In-app notification system | Model + Service | `relationship_notifications` table. Assigned staff, doctor, manager. Key events only — minimal noise. |
| `RelationshipScoreEngine` | Service | Configurable weights. Recalculates on ActivityEngine events. Stores score on `relationships.score`. |
| Analytics views | Blade | Relationship Growth, Lead Conversion, Recall Success, Lifetime Value, Staff KPIs, Communication KPIs |
| `config/relationship_score.php` | Config | Score weights. Default weights provided. Clinic can tune. |

**Score factors (configurable weights):**
```
Visit frequency          (default 25%)
Recall compliance        (default 20%)
Treatment completion     (default 20%)
Communication response   (default 15%)
Membership active        (default 10%)
Referral activity        (default 10%)
```

**Estimated size:** 2 migrations, 2 services, 3 views, 1 config (~400 lines total)

---

### ✅ Phase 7 — Integrations + Extension Points

**Goal:** Huddle wired. Mobile ready. Marketing Engine has a clean door to knock on.

**What we build:**

| Item | Type | Notes |
|---|---|---|
| Wire Huddle → TodayActionsEngine | Service update | Huddle morning view pulls from TodayActionsEngine instead of building its own query |
| `/api/v1/relationship/` endpoints | Controller | Profile, timeline, journeys, score for Mobile |
| Extension point documentation | `docs/relationship-engine-extensions.md` | Documented hooks for Marketing Engine, Patient App, third-party PMS |

**Trace of old app:**
- Huddle module preserved. `HuddleAggregationService` updated to consume TodayActionsEngine data, old internal queries kept as fallback.

**Estimated size:** 1 API controller, 1 service update, 1 doc (~200 lines total)

---

## Progress Tracker

| Phase | Status | Started | Completed | Notes |
|---|---|---|---|---|
| 0 — Analysis | ✅ Complete | 2026-07-01 | 2026-07-01 | This document |
| 1 — Foundation | ✅ Complete | 2026-07-01 | 2026-07-01 | Relationship + Activity models, RelationshipEngine, ActivityEngine |
| 2 — Today's Actions | ✅ Complete | 2026-07-01 | 2026-07-01 | TodayActionsEngine (12 categories), /relationship/today, YesterdayReviewService |
| 3 — Relationship Profile | ✅ Complete | 2026-07-01 | 2026-07-01 | /relationship/{id} profile, unified timeline, universal search |
| 4 — Journey Engine | ✅ Complete | 2026-07-01 | 2026-07-01 | RelationshipJourney state machine, AppointmentReminderEngine |
| 5 — Automation Engines | ✅ Complete | 2026-07-01 | 2026-07-01 | RulesEngine, ReminderEngine, CommunicationGuard, TaskEngine, config/relationship_rules.php |
| 6 — Notifications + Analytics | ✅ Complete | 2026-07-01 | 2026-07-01 | NotificationEngine, RelationshipScoreEngine, analytics views, config/relationship_score.php |
| 7 — Integrations | ✅ Complete | 2026-07-02 | 2026-07-02 | Huddle wired to TodayActionsEngine, /api/v1/relationships/* (6 endpoints), extensions doc |

---

## Old App Trace Map

This table tracks what old files/tables are preserved, redirected, deprecated, or replaced at each phase. Update as phases complete.

| Old Component | Old Location | Fate | Replaced By | Phase |
|---|---|---|---|---|
| `leads` table | DB | **Preserved** — adds `relationship_id` FK only | `relationships` table (new) | 1 |
| `lead_activities` table | DB | **Preserved** — ActivityEngine reads it | `activities` table (new, unified) | 1 |
| `patients` table | DB | **Preserved** — adds `relationship_id` FK only | — | 1 |
| `LeadActivity` model | `app/Models/LeadActivity.php` | **Preserved** — backward compat | `Activity` model (new) | 1 |
| PRM board | `/communication/prm/board` | **Preserved** → Phase 4 refactor | Relationship Journey board | 4 |
| Lead Detail page | `/communication/prm/lead/{id}` | **Preserved** → Phase 3 add redirect | `/relationship/{id}` | 3 |
| PRM Inbox | `/communication/prm/inbox` | **Preserved** — Today's Actions is additive new page | `/relationship/today` | 2 |
| `TimelineService` | `app/Services/ContentManagement/TimelineService.php` | **Preserved** → Phase 3 unified | ActivityEngine unified timeline | 3 |
| `/communication/timeline/` | Route | **Preserved** → Phase 3 deprecate | Unified timeline on Relationship Profile | 3 |
| `FollowUpRulesService` | `app/Services/Communication/FollowUpRulesService.php` | **Preserved** → Phase 5 migrate | `RulesEngine` | 5 |
| `LeadIngestService` | `app/Services/Prm/LeadIngestService.php` | **Preserved** → calls `RelationshipEngine::findOrCreate()` after Phase 1 | Extended, not replaced | 1 |
| `RecallEngineService` | `app/Services/RecallEngineService.php` | **Preserved** → wired to relationship_id | Extended, not replaced | 4 |
| `HuddleAggregationService` | `app/Modules/Huddle/Services/HuddleAggregationService.php` | **Preserved** → Phase 7 consumes TodayActionsEngine | Extended, not replaced | 7 |
| `config/prm.php` | Config | **Preserved** — AI + routing config stays | Augmented by `config/relationship_rules.php` (new) | 5 |

---

## Guiding Filter (Applied Before Every Decision)

Before implementing any feature, ask:

1. Does this reduce work for the dentist?
2. Does this reduce work for reception staff?
3. Does this improve the patient's experience?
4. Will this still make sense when Dentfluence has 5,000 clinics?
5. Can this become a reusable engine for another Dentfluence product?

If any answer is "No" — reconsider before writing code.

---

## Commands to Run After Each Phase

```bash
# After any migration phase
php artisan migrate
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Verify application still works
php artisan app:crawl-routes  # existing route crawler
```

---

## Notes on Architecture

- **No breaking changes.** Every phase is additive. Old routes, models, and tables persist until intentionally deprecated.
- **State machines enforce journey integrity.** Invalid transitions rejected at service layer, never at controller level.
- **All automation is observable.** Every automated action logs its trigger reason to ActivityEngine. Developers can always answer: "Why was this task created?"
- **Fail-safe.** If an automation fails, it is logged and queued for retry — never silently lost.
- **AI stays hidden.** No visible AI dashboards. AI powers: relationship summary, call brief, suggested questions, next action, urgency score. All displayed as natural language, never labeled "AI."

---

*This document is the source of truth for the Relationship Engine V1 build. Update the Progress Tracker and Old App Trace Map as each phase completes.*
