# Dentfluence Event Map — Execution Layer Blueprint

**Date:** 2026-07-09
**Scope:** Code-verified audit (not memory-based) of every business event across the app, current wiring state, and a sequenced connection plan. No new engines proposed — this is a wiring exercise over what already exists.

---

## 0. Executive Summary

Dentfluence already has an event bus. It is not obvious because nobody calls it a "bus" — it's `ActivityEngine::log()`. Every call to it writes an `Activity` row, then inside `DB::afterCommit()`:

1. `RulesEngine::evaluate($event, ...)` runs synchronously — matches `config/relationship_rules.php`, creates Tasks/FollowUps.
2. `RecalculateRelationshipScoreJob` dispatches if the event is listed in `config('relationship_score.recalculate_on_events')`.
3. `DomainEventBus::publish(new ActivityRecorded(...))` — the one Laravel-style pub/sub hook in the app.

`ActivityRecorded` has exactly **one subscriber today**: `RecalculateInsightSignalsListener` (Insights Engine), gated behind `insights.signals` (default OFF).

**The real problem is not missing infrastructure — it's missing producers.** Verified by direct grep, these business events that matter to the brief's own example chains **never call `ActivityEngine::log()` at all**: Payment Received, Treatment Plan Accepted, Consultation Completed, Opportunity Created, Membership Enrolled, Membership Expiring, Appointment No-Show. Because the bus never sees them, RulesEngine and Insights never react — not because those engines are broken, but because nothing tells them anything happened.

Compounding it: five `RulesEngine` rules are `enabled => true` today but keyed to event strings (`treatment.completed`, `visit.completed`, `appointment.missed`, `opportunity.created`, `membership.expiring`) that are **never dispatched anywhere in the codebase** — confirmed by grep, these strings exist only in doc-comments. They look live in the admin UI. They are dead.

**Recommendation in one sentence:** add roughly eight missing `ActivityEngine::log()` calls at the right places, fix the dead-rule trigger names, and the existing RulesEngine + Insights Engine machinery lights up for free — no new engine code needed for 90% of the brief's requested chains. `AutomationEngine` and `WorkflowEngine` need their own narrower next-slice, both already have a working shadow-run discipline from Phase 2/5 — reuse it, don't reinvent it.

---

## 1. The Existing Spine (how it works today)

```
Producer code
   └─ ActivityEngine::log($subject, 'event.name', $metadata)
         └─ Activity::create(...)
         └─ DB::afterCommit():
               ├─ RulesEngine::evaluate('event.name', ...)      [sync, config/relationship_rules.php]
               │     └─ create_task / create_reminder actions
               ├─ RecalculateRelationshipScoreJob (conditional)
               └─ DomainEventBus::publish(ActivityRecorded)
                     └─ RecalculateInsightSignalsListener          [gated: insights.signals, default OFF]
                           └─ RecalculateInsightSignalsJob → insight_signals table
```

Two other patterns exist in parallel and are worth preserving as reference implementations rather than replacing:

- **Lab Case** (`LabCase` model `booted()` hooks + `LabCaseObserver`) — genuine Eloquent-event → cross-module side effect, zero flags, fully live. Creation auto-writes a `LabCaseEvent` timeline row; status changes auto-update a B2B `CommunicationQueue` entry and `CommActivityLog`, auto-closing on `final_received/complete/rejected`. This is the cleanest event-driven slice in the app today. Model for everything else.
- **Patient Registration** (`PatientRelationshipLinker`) — publishes `RelationshipLinked` + `PatientRegistered` via the same `DomainEventBus`, gated by `identity.link_patient` (confirmed **default `true`** in `config/features.php` — this is the *only* flag that defaults on). But `PatientRegistered` is a distinct event class from `ActivityRecorded`, and nothing subscribes to it — so today it fires into a void. Structurally live, functionally silent.

---

## 2. Event Map

Status legend: **LIVE** = fully wired end to end · **PARTIAL** = fires but a downstream link is broken/dead/flagged off · **MISSING** = producer never calls the bus at all.

### Clinical Spine

| Event | Status | Trigger | Source Module | Listeners (today) | DB Updates | Tasks Created | Notifications | KPIs Updated | Timeline Entries | Future AI Consumption |
|---|---|---|---|---|---|---|---|---|---|---|
| **Patient Registered** | PARTIAL | `PatientService::createFromInput()/quickCreate()` | Patient | `PatientRelationshipLinker` publishes `PatientRegistered`/`RelationshipLinked` (default ON) — but **zero subscribers** | `patients`, relationship link row | none | none | none | generic audit_logs row only, not event-specific | onboarding-task auto-assign, welcome-message trigger, seed initial risk/LTV signal |
| **Consultation Completed** | MISSING | `ConsultationController::store()` (+3 sibling workflows) | Consultation | none | `consultations`, specialty module row, clinical media | none | none | none | none | diagnosis-pattern detection, treatment-plan suggestion prompts |
| **Treatment Plan Created** | MISSING | `TreatmentPlanController::store()` | Treatment Plan | none | `treatment_plans` + items | none | none | none | none | acceptance-probability scoring (feeds the 🔴 gap flagged in prior gap analysis) |
| **Treatment Plan Accepted** | MISSING (highest-value clinical gap) | `TreatmentPlanController::accept()` | Treatment Plan | none | `status`, `accepted_at` only | none | none | none | none | — |
| **Treatment Visit Completed** | PARTIAL | `TreatmentVisitService::create()/update()`, `mark_treatment_complete` | Treatment Visit | `WorkflowShadowRunner` (shadow-only, `workflow.engine` OFF) | `treatment_visits`, `visit_items`, `BillingPrompt`, conditional implant stock, conditional `LabCase` | **yes — already live**: auto-creates "6-Month Recall" Task if none exists (`completePlanAndQueueRecall`) | none | none | none | stage-divergence signal (once Workflow Engine cuts over) |
| **Lab Case Created / Status Changed** | LIVE | `LabController` / `TreatmentVisitService::createLabCase()` | Lab | `LabCaseObserver` (real, unconditional) | `lab_cases`, `lab_case_events` | none directly | B2B `CommunicationQueue` entry created/closed automatically | none | `LabCaseEvent` append-only log — **reference pattern** | vendor-turnaround prediction, case-delay risk |
| **Practice Protocol Task Generated** | PARTIAL | `protocols:generate` scheduled 00:10 daily | Practice Protocols | none | `tasks` (idempotency-guarded) | yes, generation only | none | none | none | — |
| **Practice Protocol Completed/Missed** | MISSING | n/a — no protocol-aware completion hook exists | Practice Protocols | none | generic `Task` status update only | n/a | none | none | none | staff-compliance/streak scoring |

### Revenue & Retention

| Event | Status | Trigger | Source Module | Listeners (today) | DB Updates | Tasks Created | Notifications | KPIs Updated | Timeline Entries | Future AI Consumption |
|---|---|---|---|---|---|---|---|---|---|---|
| **Appointment Booked** | PARTIAL | `AppointmentController::store()` (3 paths) | Appointment | `RulesEngine` — but the one matching rule (`appointment_reminder`) is **disabled** (metadata key bug: reads `event_date`, actual key is `appointment_date`) | `appointments` (+ `patients` on walk-in) | none live | none | none | `Activity` row via `ActivityEngine::log('appointment.booked')` | — |
| **Appointment Completed** | PARTIAL / dead rules | `AppointmentController::updateStatus()` → `done` | Appointment | logged as `appointment.completed`, but the 3 rules that should react (`implant_followup`, `post_treatment_followup`, `recall_6months`) are keyed to `treatment.completed`/`visit.completed` — **never dispatched anywhere** | `status`, `completed_at` | none (dead rules) | none | none | `Activity` row | — |
| **Appointment No-Show** | MISSING | `updateStatus()`, `status==='no_show'` branch | Appointment | none — branch has no `ActivityEngine::log` call at all | `status` | none | none | none | none | — |
| **Payment Received** | MISSING, and duplicated producer | `BillingController::recordPayment()` **and** separately `InvoicePaymentService::recordPayment()` (mobile) — 350-line duplicate | Billing | none — `'payment.received'` exists only as a doc-comment example, never actually fired | `InvoicePayment`, `EmiSchedule`, `Receipt`, `FinalBill`, `FinanceTransaction` | none | none | none | none | revenue trend, doctor productivity, campaign-ROI attribution (none of these are computed anywhere today) |
| **Treatment Plan → Invoice** | MISSING by design | `TreatmentPlanController::accept()` | Treatment Plan / Billing | none | none — `invoices()` relation exists but is populated only via a later, separate manual billing step | none | none | none | none | — |
| **Recall Due / Triggered** | LIVE (partial cutover) | `recall:run` daily 07:00 → `RecallEngineService::runAll()` | Relationship/Recall | `no_visit_6months` cut over to `AutomationEngine`; other 5 triggers legacy | `CommunicationQueue`, `patients.recall_no_visit_queued_at` | n/a | queued, **no auto-send** — CommunicationQueue has no send pipeline by design | `Activity` (`recall.queued`) | — | — |
| **Lead Created** | LIVE | `Lead::create()` | PRE/Leads | `LeadObserver` fires `lead.created` | `leads` | via RulesEngine | — | — | `Activity` row | — |
| **Opportunity Created** | MISSING | 4 separate controllers call `TreatmentOpportunity::create()` | PRE/Opportunity | none in any of the 4 sites | `treatment_opportunities` | none (the enabled `opportunity_nudge_7d` rule is dead) | none | none | none | — |
| **Membership Enrolled** | MISSING | `MembershipBenefitService::enroll()/enrollWithFinance()` | Membership | none | `finance_patient_memberships`, `invoices`, `membership_benefit_logs` | none | none | none | none | — |
| **Membership Expiring** | MISSING — no producer exists at all | n/a | Membership | none — `membership.expiring` never dispatched anywhere, no scheduled scan exists | n/a | n/a (rule ready, never fires) | n/a | n/a | n/a | renewal-risk scoring |
| **Campaign Sent/Response** | DOES NOT EXIST as a concept | n/a | Marketing | n/a | `mkt_campaigns` tracks spend/platform only, no patient/revenue linkage | n/a | n/a | n/a | n/a | out of scope this phase — see §6 |

### Operating Layer

| Item | Status | Notes |
|---|---|---|
| **ActivityRecorded (the bus itself)** | LIVE, 1 dormant subscriber | Fires on every `ActivityEngine::log()` call. Only `RecalculateInsightSignalsListener` subscribes, gated by `insights.signals` (OFF). Every fix in §2 above that adds a `log()` call automatically feeds this for free. |
| **AutomationEngine** | PARTIAL cutover | `automation.engine` flag OFF by default, DB-override ON in production for `no_visit_6months` recall + appointment reminders only. Other 5 recall triggers still legacy. |
| **WorkflowEngine** | Shadow-only | `workflow.engine` OFF. Sole caller is `WorkflowShadowRunner`, log-only, never mutates a real visit. Needs real shadow data to accumulate before any cutover decision — this is a waiting problem, not a code problem. |
| **InsightsEngine** | Dormant | `insights.signals` OFF. Zero UI/controller reads `insight_signals` outside the Insights service files themselves. Write path exists (the listener above); read path doesn't exist anywhere yet. |
| **Huddle** | Duplicated, unconsolidated | Two live implementations: `app/Modules/Huddle` (web UI, daily-used) vs `app/Services/Huddle/HuddleService.php` (AI-tool/log-file, `tulip:huddle` 08:00 daily). Not a wiring gap — a product decision Sumit needs to make (see §4). |
| **Action Board (`TodayActionsEngine`)** | Working as designed | 13 categories, 12 are read-time synthesis over live tables (not event-pushed) — this is correct for a "board" surface, not a gap. Only `recall_calls` is backed by an actual queued row. Do not convert to push-based; would add complexity for no benefit. |

---

## 3. Duplicate Logic to Remove

1. **`BillingController::recordPayment()` vs `InvoicePaymentService::recordPayment()`** — ~350 lines of independently-maintained duplicate logic (web vs mobile paths for the same operation). This is also *why* Payment Received has no event: whichever one gets fixed first will drift from the other. Collapse the web controller onto the shared service before wiring the event, not after — otherwise you'd wire two places instead of one.
2. **Two Huddle implementations** — not code duplication in the copy-paste sense, but duplicated *product surface*. Needs Sumit's call on intent (staff board vs AI-context feed) before any further wiring touches either.
3. **`recall_6months` rule vs `TreatmentVisitService`'s built-in recall task** — `completePlanAndQueueRecall()` already auto-creates a "6-Month Recall" Task live today. The dead `recall_6months` RulesEngine rule, if ever fixed to a real trigger, would create a *second* recall task for the same event. Recommendation: delete the `recall_6months` config entry rather than fix its trigger — the inline call already does this job and is tested.
4. **`appointment.completed`-keyed rules vs a future `treatment_visit.completed` event** — once Treatment Visit Completed gets wired to the bus (§2), pick ONE canonical trigger for `implant_followup`/`post_treatment_followup` (recommend `treatment_visit.completed`, since it carries `treatment_type` metadata that a raw appointment status doesn't) and retire the `appointment.completed` version rather than running both.

---

## 4. Scheduled Jobs: Which Should Become Event-Driven

Full inventory of the 17 scheduled commands (`routes/console.php`) was checked against this question. Conclusion: **almost none of them should become purely event-driven, and that's correct, not a gap.** `AutomationEngine` itself models the right distinction — `dueNow()`/`inCooldown()`/`isExpired()` are temporal primitives for "has enough time passed," which is inherently a periodic-scan question, not a reactive one. Converting `recall:run` or `relationship:appointment-reminders` to fire only in response to a single event would miss every patient whose recall window opens on a day nothing else happens to them.

The actual fix is narrower: **scheduled jobs should emit through `ActivityEngine::log()` when they act**, so their output joins the same stream real-time events use, rather than being a second, invisible channel. Today only recall (`recall.queued`) does this. Recommend the same treatment for:

- `lab:create-overdue-tasks` (09:00 daily) — currently silent, should log `lab_case.overdue` so Insights/Action Board can see it.
- A new membership-expiry scan (doesn't exist yet, see §2) — should log `membership.expiring` per patient found, which activates the already-enabled `membership_renewal_30d` rule.

No scheduled job in the current inventory is a good candidate for full removal in favor of real-time triggering.

---

## 5. Recommended Wiring Plan (sequenced, each slice independently shippable)

Per project discipline (shadow → parity → cutover, small verifiable slices, hold for confirmation) — none of this should be built as one large change.

**Slice 1 — Additive producer wiring (near-zero risk, no behavior change to existing flows):**
Add `ActivityEngine::log()` calls at: `consultation.completed`, `treatment_plan.created`, `treatment_plan.accepted`, `treatment_visit.completed`, `appointment.missed` (1-line fix in the `no_show` branch), `opportunity.created` (via a new `TreatmentOpportunity` observer mirroring `LeadObserver` — one choke point instead of patching 4 controllers), `membership.enrolled`. Each is purely additive — nothing currently reads these tables' absence.

**Slice 2 — Fix dead rule configs:**
Fix the `appointment_reminder` metadata key bug; retarget/dedupe `implant_followup`/`post_treatment_followup` onto the new `treatment_visit.completed` event; delete the redundant `recall_6months` rule (superseded by the existing inline Task creation, see §3).

**Slice 3 — Treatment Plan Accepted → Opportunity (highest-value single fix):**
In `accept()`, auto-create a `TreatmentOpportunity` linked via the existing `treatment_plan_id` FK if none exists yet, then call the Slice-1 `treatment_plan.accepted` log. This is the brief's own top example chain and the schema is already built for it — only the wiring is missing.

**Slice 4 — Payment Received (requires Slice 0: dedupe first):**
Collapse `BillingController::recordPayment()` onto `InvoicePaymentService::recordPayment()`, then add the `payment.received` log call inside that single service.

**Slice 5 — Membership Expiring scan:**
Small new scheduled command (or extend `recall:run`) scanning `FinancePatientMembership.end_date`, logging `membership.expiring` per match — activates the already-enabled rule.

**Slice 6 — Flip `insights.signals` (after Slices 1–5 have soaked):**
Once real events are flowing through `ActivityRecorded`, run `insights:rebuild-signals`, eyeball output, then flip per-branch — same discipline already used for `automation.engine`.

**Slice 7 — Huddle consolidation:** not a code slice — needs Sumit's decision on which implementation is canonical before any merge work starts.

**Explicitly NOT recommended this phase:** Campaign ROI / doctor productivity dashboards (no underlying data model exists — would be new feature work, not wiring, and its commercial case should wait until Payment Received data exists to prove demand); Lab Case → auto-generate-next-protocol (brief's own example, but nothing today generates protocol instances on demand — would need new logic, not just a listener; confirm real clinic need before building); Practice Protocol completion/miss tracking (blocked on `tasks.human_system_split` landing first).

---

## 6. Future AI Consumption

Once Slices 1–6 land, `ActivityRecorded` becomes a near-complete stream of everything that happens in the practice — registration, clinical progression, revenue, retention. That single stream is what a future AI layer (Tulip, per the existing roadmap) should subscribe to instead of polling tables:

- **Near-real-time signals** instead of batch: Insights Health/LTV/Risk recompute per-event instead of on a schedule.
- **Proactive suggestions**: a listener on `treatment_plan.accepted` could eventually draft the follow-up communication instead of just creating a Task.
- **Doctor productivity / campaign ROI**: only becomes buildable once `payment.received` exists with the right metadata (doctor_id, invoice source) — sequence AFTER Slice 4, not before.
- **Protocol compliance scoring**: needs the Practice Protocol completion event (currently missing) before any AI coaching layer could reason about staff consistency.

---

## 7. Product Take — What Not to Chase Right Now

The brief's example chains are directionally right, but two of them describe capabilities the codebase doesn't have yet and shouldn't be built as a side effect of "wiring":

- **"Campaign ROI" and "doctor productivity" updates on Payment Received** — real, sellable ideas (they tie marketing spend and clinical output directly to revenue, which is a genuine MRR-defensible feature), but there's no data model for either today. Building them now, before Payment Received even fires an event, is solving a problem you can't yet measure. Wire the event first, let data accumulate, then decide if the metric is worth the schema.
- **"Generate next protocol" on Lab Case Status Changed** — Lab Case is the *best-wired* event in the app already (observer pattern, live). The gap isn't wiring, it's that "next protocol" isn't a defined concept yet (Practice Protocols are date-scheduled staff checklists, not case-triggered clinical steps). Don't force-fit this chain until there's a concrete protocol definition to trigger.

Both are flagged, not rejected — they're V3/V4 material (per the project's MVP-before-perfection layering), not part of this connect-the-wiring phase.
