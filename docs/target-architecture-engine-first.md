# Dentfluence — Target Architecture (Engine-First, 10-Year Backbone)

**Author:** Chief Software Architect
**Date:** 2026-07-02 · **Revision:** 3 (stabilization pass — architecture now frozen)
**Status:** Definitive, **stable** long-term reference architecture. Analysis and architecture only — **no code, no implementation detail, no migration steps.**
**Companion diagram:** `docs/target-architecture-diagram.mermaid`
**Scope of authority:** This is the reference architecture for every current and future Dentfluence capability — Dentfluence OS, Chairside, Patient App, Marketing Engine, Marketplace, the AI Assistant (as an *interface*, not a surface — see §12A), and the Enterprise/DSO tiers.

> **What changed in Revision 2.** Identity folded into the Relationship Engine (no standalone Identity Engine). The Event Bus is now treated as internal plumbing — products think in *Events*, published through shared services, never "the bus." The Temporal Engine became the **Automation Engine**. The Intelligence Engine became the **Insights Engine** (AI-agnostic). Communication Guard was expanded into a full "never annoy the patient" policy. Two new engines earned their place: the **Workflow Engine** (reusable clinical/operational journeys like RCT and Implant) and the **Integration Engine** (all external systems). A new **Clinic Operating Cycle** section anchors the whole design in how a dental practice actually runs its day. Language throughout was moved from software-speak to clinic-speak. Everything excellent in Revision 1 was kept.

> **What changed in Revision 3 (stabilization — no new core engines).** Ten refinements that *strengthen* the existing engines rather than add to them: (1) the **AI Assistant is reclassified as an intelligent interface, not a product surface** — it consumes engines like everyone else and owns no business logic (§12A); (2) the **Workflow Engine now explicitly supports real, non-linear dentistry** — branching, optional steps, retries, loops, skips, parallel branches, manual overrides, pause/resume, and versioning (§4·B3); (3) a **Decision Log** makes every Rules-Engine decision auditable and explainable (§4·B1a); (4) the **Task Engine formally separates Human Tasks from System Tasks** with distinct queues, SLAs, dashboards, and permissions (§4·B4); (5) an **Organization Engine is reserved for the future** — explicitly *not* built now, but nothing blocks it (§12B); (6)–(10) governance is codified — the **Clinic Operating Cycle** is the intake test, **engine ownership is absolute**, **new features extend engines rather than spawn services**, **complexity stays inside engines**, and the **architecture is now frozen** (§17). No new core engine is introduced. The engine count remains **13**.

---

## 0. First principles (the contract this design must honor)

1. **Dentfluence is a Dental Operating Platform, not a CRM.** Every architectural choice must ultimately make a real clinic day simpler — a smoother morning huddle, fewer dropped recalls, a lab case that never gets forgotten, a patient who feels remembered. When elegance and clinic workflow conflict, **clinic workflow wins.**
2. **We build engines, not modules.** A module is a feature ("Recall"). An engine is a reusable capability ("reach a patient intelligently", "record what happened", "run a treatment procedure to completion"). Features are thin; engines are deep. Every future feature is *assembled from engines*, never a bespoke stack.
3. **One person = one Master Relationship.** Lead, patient, referrer, guardian, corporate contact — all resolve to a single node. Every journey, activity, task, message, insight, and summary hangs off that one person.
4. **The same platform serves solo → enterprise.** A solo dentist and a 400-clinic DSO run the *same engines*. Scale is a deployment and data-partitioning concern, never a rewrite.
5. **No engine may become a God Service.** *Today's Actions* and the *Insights Engine* especially must never reach into a dozen domains at read time. They consume pre-computed views, nothing else.
6. **Truth is a stream of facts.** What happened is recorded once, immutably. Everything a dentist sees — the timeline, today's work, the analytics — is a *view* of that stream.

Everything below follows from these six.

---

## 1. Architectural style — and why (in clinic terms)

### 1.1 The style: an Engine-First platform that thinks in Events, inside one deployable

Three decisions, each chosen for how a clinic actually runs:

**(a) One platform (a modular monolith), not a fleet of services.** The engines are separate *capabilities inside one product*, not separate servers. A solo dentist cannot run — and should not pay for — a rack of microservices. One platform keeps the solo tier trivially simple and the enterprise tier fast. The engine boundaries are logical, so any engine can later run on its own hardware for a large DSO **without changing how it behaves.** We get enterprise-readiness without charging the solo dentist for it.

**(b) The platform thinks in Events — the "bus" is invisible plumbing.** When something happens in the clinic — an appointment is completed, a payment lands, a lab case is ready — the responsible part of the product **announces a fact** ("AppointmentCompleted"). Other parts of the product **listen for the facts they care about** and react. Crucially, **no module ever talks to "an event bus" directly.** Modules publish and subscribe to *events* through shared services with stable, product-meaningful contracts. Whether those events travel in-memory or over a queue is an internal detail the product never sees. Dentists — and the engineers building for them — reason about *"when a payment is received, send the receipt,"* never about message brokers.

Why this matters for a clinic: it is the difference between "the recall never happened because two systems each assumed the other did it" and "the moment treatment completes, the recall arms itself, the follow-up is scheduled, and the patient's timeline updates — automatically, because everyone heard the same fact."

**(c) Separate what's true from what's shown.** The **write side** is small and strict: who the person is, what state their journeys are in, and an unchangeable ledger of everything that happened. The **read side** is a set of ready-made **views** — the patient timeline, today's work, insights, analytics, search — each kept fresh by listening to events. A dentist opening a patient sees a view assembled in advance, not a live scramble across a dozen tables. This is why the reception dashboard loads instantly and can't be broken by a change three domains away.

### 1.2 Why this specifically fixes Dentfluence's problems

- **No more god readers.** Today's Actions and Insights stop querying the whole app live. Each reads one prepared view, updated as facts arrive.
- **No more duplication.** One place records each fact; one engine owns each capability. Two rules engines, two reminder pipelines, three activity logs become *structurally impossible* — there is one publisher and one owner per concern.
- **No more silent drift.** State is never copied by hand between tables. A view is *derived*, so it cannot drift; if it's ever wrong, it is rebuilt from the fact ledger.
- **Scales by tier, not by rewrite.** The same event flow runs in-memory for a solo clinic and over a queue for a DSO. The engines never know the difference.

### 1.3 Consistency, stated honestly

The write spine (who the person is, journey state, the fact ledger) is **immediately consistent** inside a transaction. The read views (timeline, today's work, analytics) are **fresh within moments** of the event. That is the right trade for a clinic: a receptionist seeing a new note appear a fraction of a second later is fine; a patient being double-charged is not. Money, clinical records, and consent are never eventual — they stay transactional in their own domains. Only the *derived views* are near-real-time.

---

## 2. The core: the Master Relationship (now including Identity)

The **Master Relationship** is the heart of the platform — the one place everything about a person lives. Per Revision 2, it also **owns identity**, so there is no separate Identity Engine to reason about.

The Relationship Engine owns:

- **Master Relationship** — the single canonical person node.
- **Identity** — recognizing that "the WhatsApp lead," "the walk-in patient," and "the referrer" are the *same human*.
- **Deduplication** — never creating two records for one person (phone/email/name/DOB/ABHA matching).
- **Merge history** — when two records are found to be one person, merging them and keeping an auditable trail.
- **Relationship Journeys** — the parallel, typed lifecycle states a person can be in at once: *Lead*, *Treatment*, *Opportunity*, *Recall*, *Membership*, *Referral*. Journeys are the single source of truth for **pipeline state**.

The node stores **identity + journey state** and *references* everything else. It does **not** store activities, messages, or insights inside itself — those live in their owning engines and are *composed onto* the person when a dentist opens the profile. This is the discipline that keeps the Relationship Engine the **hub, not the warehouse**, and prevents it from becoming the new god object.

> **Design note (identity as a seam, not an engine).** Identity resolution is kept as a clearly-bounded responsibility *inside* Relationship, with its own internal contract (`resolve → relationshipId`, `merge`, `link`). It does not warrant a separate engine today — that was abstraction ahead of need. The seam means that *if* ABDM, patient-app self-registration, and marketplace referrals ever make matching a heavyweight problem, it can be lifted out later without disturbing anything that depends on it. Simplicity now; an exit later. That is the correct call at this stage.

---

## 3. The Clinic Operating Cycle — the product's true north

Before the engines, the workflow they exist to serve. Dentfluence optimizes the **entire daily operating cycle of a clinic**, and every product and engine must fall naturally into it:

```
Morning
  ↓
Daily Huddle        — the team aligns on the day
  ↓
Today's Actions     — "my work for today," per role
  ↓
Reception           — calls, confirmations, check-ins, collections
  ↓
Doctor              — consultation, diagnosis, treatment planning
  ↓
Treatment           — the procedure runs (often a multi-visit Workflow)
  ↓
Billing             — estimate, payment, receipt, membership, EMI
  ↓
Lab                 — impressions out, cases tracked, deliveries in
  ↓
Relationship Follow-up  — recall armed, reviews requested, opportunities nurtured
  ↓
Analytics           — the day is measured, patterns surface
  ↓
Tomorrow            — insights feed the next morning's huddle
```

This is not a diagram for engineers; it is the promise to the dentist. **Every engine below earns its keep by making one or more steps of this cycle smoother, and every product surface plugs into the cycle rather than sitting beside it.** When we evaluate any future feature, the first question is: *"Where does this fit in the operating cycle, and which step does it make better?"* If it doesn't fit the cycle, it doesn't belong in the platform.

The mapping, at a glance:

| Cycle step | Primary engines serving it |
|---|---|
| Daily Huddle | Analytics, Task, Insights |
| Today's Actions | Today's Actions view (fed by Automation, Workflow, Insights, Task) |
| Reception | Communication, Task, Relationship, Automation |
| Doctor | Relationship, Workflow, Activity |
| Treatment | **Workflow**, Task, Activity, Automation |
| Billing | (Billing domain) + Activity, Insights (LTV) |
| Lab | (Lab domain) + Workflow, Automation, Communication |
| Relationship Follow-up | Automation, Communication, Rules, Insights |
| Analytics | Analytics, Insights |
| Tomorrow | Insights → Analytics → Huddle |

---

## 4. The Engine Catalog

Thirteen engines, grouped into five layers. I **challenged the engine list** and kept it lean:

- **Identity folded into Relationship** (−1 engine).
- **Temporal Engine → Automation Engine** (renamed; broader, more product-recognizable responsibility).
- **Intelligence Engine → Insights Engine** (renamed; AI-agnostic).
- **Workflow Engine added** — it owns reusable clinical/operational procedures, a genuinely reusable long-term problem that nothing else covers.
- **Integration Engine added** — the single anti-corruption boundary for every external system.

Net change vs. Revision 1: **12 → 13 engines** (−1 Identity, +2 Workflow/Integration). Each engine owns exactly one capability from the Single-Source-of-Truth table (Section 5). If an engine's job can't be said in one sentence without "and," it must be split or merged — that is the standing test against bloat.

> For each engine: **Purpose · Responsibilities · Inputs · Outputs · Public interface (conceptual) · Consumed by · Publishes / Subscribes · Data ownership · What must NEVER live inside it.**

---

### LAYER A — The Write Spine (what is true)

#### A1. Relationship Engine — *the central operating layer* (now includes Identity)

- **Purpose:** The heart of the platform: the one place a person exists, and the single authority on what stage each of their journeys is in.
- **Responsibilities:** Identity resolution, deduplication, merge + merge-history, cross-facet linking (lead↔patient↔referrer↔guardian↔ABHA); create/hold the Master Relationship; own every **Journey** state machine and its legal transitions; be the façade through which every surface reads a person and requests a journey change.
- **Inputs:** Person signals from anywhere (webhook lead, front-desk registration, patient-app signup, referral, marketplace); transition requests; events that imply a state change (e.g. `AppointmentCompleted` may advance a Recall Journey).
- **Outputs:** `PersonCreated/Matched/Merged`, `JourneyEntered/Transitioned/Closed`; a composed relationship read-façade.
- **Public interface:** `resolve(signal)`, `merge(a,b)`, `link(person, facet)`, `get(relationshipId)`, `transition(journey, toState, reason, actor)`, `openJourney/closeJourney`.
- **Consumed by:** Every surface and every engine that needs the canonical person or journey state.
- **Events:** *Publishes* identity + journey events. *Subscribes* to domain events that drive journey state.
- **Data ownership:** The person node, identity graph + merge history, journeys, transition log. Nothing else.
- **NEVER inside it:** Activities, message bodies, tasks, insights, analytics, timeline rendering. The Relationship Engine is the **hub, not the warehouse.**

#### A2. Activity Engine (the Fact Ledger)

- **Purpose:** The single, unchangeable record of *everything that ever happened* to a person. The clinic's memory.
- **Responsibilities:** Record immutable facts `{relationship, subject, actor, event, metadata, occurred_at}`; guarantee append-only integrity; be the substrate every read view is built from.
- **Inputs:** Domain events from every engine and module.
- **Outputs:** The fact stream; `ActivityRecorded`.
- **Public interface:** `record(fact)`, `stream(relationshipId, filters)`.
- **Consumed by:** Timeline, Insights, Analytics, Search, Today's Actions (all built *from* it).
- **Events:** *Subscribes* to essentially all domain events. *Publishes* `ActivityRecorded`.
- **Data ownership:** The one activity/fact ledger for the whole platform.
- **NEVER inside it:** Decisions or reactions. The ledger records; it never decides. Dumb, fast, never throws back at its caller.

---

### LAYER B — Orchestration & Automation (decide, run, schedule, do)

#### B1. Rules Engine — *the decision layer*

- **Purpose:** The single home of automation policy: "**when** this happens under these conditions, request that." Reactive and event-triggered.
- **Responsibilities:** Listen for facts, evaluate business conditions, and **request** actions from the doing-engines (create a task, send a message, alert staff, propose a journey transition, advance a workflow). Owns the *policy* of whether an automation applies.
- **Inputs:** Domain events; rule definitions (clinic-configurable); relationship state + insights as conditions.
- **Outputs:** Action requests: `TaskRequested`, `CommunicationRequested`, `NotificationRequested`, `TransitionProposed`, `WorkflowStepRequested`. Audit of every firing.
- **Public interface:** `evaluate(event)`, rule registry.
- **Consumed by:** Indirectly by every workflow — the automation brain.
- **Events:** *Subscribes* broadly. *Publishes* action-request events (never performs the action).
- **Data ownership:** Rule definitions + the **Decision Log** (see B1a).
- **NEVER inside it:** Sending, task creation, timing mechanics, or multi-step procedure state. Rules **decide and request**; they never **execute** and never **schedule**. (Scheduling belongs to Automation; procedures belong to Workflow.)

##### B1a. The Decision Log — the brain's audit trail

Every time the Rules Engine evaluates something, it records the decision. This is not a nice-to-have; it is what makes an automated clinic *trustworthy and explainable*. Each entry captures:

- **Rule name** — which rule ran.
- **Inputs** — the event and data it saw.
- **Conditions evaluated** — each condition and how it resolved.
- **Result** — matched / did not match.
- **Decision** — what was requested (or why nothing was).
- **Requesting engine** — who asked (Rules on its own, or on behalf of Workflow, Marketing, Automation).
- **Timestamp**, **Relationship**, and **User** (if a human was involved).

The Decision Log exists for exactly four purposes — **debugging, audit, AI explanation, and future analytics** — and for nothing operational. Its defining use case: a receptionist or dentist asks *"Why wasn't this patient contacted?"* and the platform answers from the log — "the recall rule matched, but the Communication Guard's frequency cap suppressed it at 09:14, because the patient was messaged yesterday." The AI Assistant reads this log to explain the system's behaviour in plain language; it never invents an explanation of its own.

The Decision Log is **owned by the Rules Engine** (it is the firing log, formalized). It is append-only, like the Activity Ledger, but distinct from it: the Activity Ledger records *what happened to the patient*; the Decision Log records *what the brain decided and why*. Keeping them separate keeps the patient timeline clean of internal machinery.

#### B2. Automation Engine — *the execution layer for time-based & deferred work* (was "Temporal Engine")

- **Purpose:** Run the clinic's time-based and deferred workflows reliably. If Rules decides *whether*, Automation makes it *actually happen at the right moment, and keeps happening until it's done.*
- **Responsibilities:** Time-based triggers; **recall scheduling**; **appointment reminders**; delayed actions ("nudge in 3 days if no reply"); **retry logic** (a failed send re-attempts); **cooldowns** (never repeat too soon); **expiration rules** (an estimate offer lapses); scheduled/recurring evaluations (the nightly "who is due?" sweeps). It emits the resulting facts — `RecallDue`, `ReminderDue`, `MembershipExpiring`, `EstimateExpired` — and drives deferred steps to completion.
- **Inputs:** Relationship/journey state; schedules; timing requests from Rules and Workflow; delivery outcomes (to drive retries).
- **Outputs:** Temporal domain events and completed deferred actions.
- **Public interface:** `schedule(action, when)`, `recur(evaluator, cadence)`, `defer(action, delay)`, `cancel(scheduled)`, `emitDue()`.
- **Consumed by:** Rules (reacts to temporal events), Workflow (schedules its steps), Today's Actions view.
- **Events:** *Publishes* temporal + completion events. *Subscribes* to state that arms/disarms timers and to delivery outcomes.
- **Data ownership:** Schedules, timers, "last fired" stamps, retry/cooldown state, expiration windows.
- **NEVER inside it:** Deciding business policy (Rules) or knowing *how* to reach a person (Communication). Automation answers "it is time, and I will keep at it until it's done or expired."

> **Rules vs. Automation — the boundary, stated once and enforced everywhere.** *Rules* is **reactive** ("this fact just occurred → should we act?"). *Automation* is **temporal** ("run this at time T, retry on failure, don't repeat within the cooldown, expire it if unanswered"). A recall is the clean example: Automation notices it is *due* and emits `RecallDue`; Rules decides the *policy* (which patients, which message, respecting insights); Communication *delivers*; Task creates the *call* for reception if needed. Four engines, one job each, zero overlap.

#### B3. Workflow Engine — *the clinical & operational procedure layer* (NEW)

- **Purpose:** Run Dentfluence's real-world **operational journeys** — the multi-step procedures that define dentistry — as reusable, clinic-configurable templates. This is the engine that makes the platform feel like it *understands dentistry*, not just contacts.
- **What it is (and is NOT):** A Workflow is a **templated, multi-step procedure instantiated per case** (an RCT on tooth #26, an implant on #36). It is *not* the same as a Relationship Journey. **Journeys = coarse, one-per-type lifecycle state on the person** ("this person has an active Treatment Journey"). **Workflows = the concrete, ordered steps of a specific procedure**, and a person can run **several at once** (two RCTs on different teeth). A Workflow *instance* drives its Journey's state; the Journey never tries to hold the steps.
- **Real dentistry is not linear — the engine must not be either.** Treatment rarely runs in a straight line: healing can fail, a step can be skipped, a case can pause for months, two branches can run at once. The Workflow Engine must therefore support, as first-class primitives:
  - **Conditional branching** — the next step depends on an outcome (healing successful → impression; else → healing review).
  - **Optional steps** — steps that may be skipped for a given case.
  - **Retries** — a step that didn't take can be repeated (re-impression, re-cement).
  - **Loops** — repeat a step or sub-sequence until a condition is met (healing reviews until healed).
  - **Skipped steps** — a clinician may deem a step unnecessary and move on.
  - **Parallel branches** — independent tracks running at once (lab fabrication proceeding while the patient heals).
  - **Manual overrides** — the dentist is always in charge; they can force a step, jump, or close a case, and the override is recorded.
  - **Pause / resume** — a case can be suspended (patient travels, defers treatment) and picked up later without losing state.
  - **Workflow versioning** — templates evolve; in-flight cases keep running the version they started on, while new cases use the latest. No case is ever silently rewritten mid-treatment.
- **Example — a conditional Implant workflow:**

  ```
  Consultation → CBCT → Planning → Placement → Healing
                                                  │
                        ┌─────────── healing successful? ───────────┐
                        │ YES                                        │ NO
                        ▼                                            ▼
                   Impression → Lab → Delivery → Recall        Healing Review → Retreatment → (loop back to Healing)
  ```

  The same engine expresses the linear RCT, the branching Implant, a looping Recall, a parallel Lab track, and a paused Membership renewal — because branching, loops, parallelism, and pause/resume are engine primitives, not per-workflow code.
- **Generic enough for the whole platform.** One engine serves **RCT, Implant, Orthodontics, Membership, Recall, Marketing, Lab, HR onboarding, and any future module.** Orthodontics (a multi-year loop of adjustment visits) and HR onboarding (a checklist with optional and parallel steps) are the *same engine*, different templates. If a future module has a multi-step process, it defines a template — it never writes its own sequencer.
- **Responsibilities:** Hold workflow *definitions* (steps, branch/loop/parallel structure, expected durations, entry/exit conditions, optionality, version, who does each step) and *instances* (which step(s) this case is on, what's overdue, paused/active, which version). Evaluate branch conditions from the facts it receives and advance accordingly. At each step, **request** the work from the other engines — a Task for the clinician, a scheduled reminder from Automation, a message from Communication, a Journey transition from Relationship. It **orchestrates; it never executes, and it never contains the business logic of a step** — it only knows the *shape* of the procedure and *which step comes next*.
- **Inputs:** Workflow templates; domain events (a completed step's fact); manual step actions.
- **Outputs:** `WorkflowStarted/StepEntered/StepCompleted/WorkflowCompleted`; requests to Task/Automation/Communication/Relationship.
- **Public interface:** `start(template, relationship, context)`, `advance(instance, step)`, `status(instance)`, template registry.
- **Consumed by:** Chairside, Dentfluence OS (treatment surface), Lab, Membership, Marketing, Daily Huddle (open procedures).
- **Events:** *Subscribes* to the facts that complete steps. *Publishes* workflow events + step-action requests.
- **Data ownership:** Workflow definitions + running instances + step history.
- **NEVER inside it:** Creating tasks directly, sending messages, scheduling timers, or deciding policy. It *asks* Task, Communication, Automation, and Rules to do those — through the same shared contracts everyone uses. This is the guardrail that stops Workflow from becoming a god-orchestrator: it coordinates *what step comes next*, and delegates *everything else*.

#### B4. Task Engine — one store, two classes of work

- **Purpose:** The single store of *work to be done* — whether a human does it or the system does it.
- **Two internal task classes (same engine, deliberately separated):**
  - **Human Tasks** — work a staff member performs: *call patient, explain estimate, collect payment, lab follow-up, wellness call.* These are the clinic's to-do list.
  - **System Tasks** — work the platform performs on itself: *retry a webhook, retry a WhatsApp send, refresh a projection, sync Google Calendar, retry an integration.* These are internal machinery.
- **Why one engine, but separated:** both are "work with a state and a retry/SLA," so they share the same engine — but they must never share a screen. Human and System tasks differ in **priority**, **queue**, **SLA**, **dashboard**, and **permissions**. **Reception must never see system jobs** — a receptionist's "my work for today" shows *call the patient*, never *retry webhook #4821*. System tasks surface only in operational/admin views. This separation keeps the clinic experience calm and human while the platform's plumbing hums invisibly underneath.
- **Responsibilities:** Create/assign/dedup/close tasks of both classes; per-class SLA + overdue tracking; per-class routing, queues, and visibility.
- **Inputs:** `TaskRequested` (from Rules/Workflow — human work) and system-task requests (from Automation/Integration — machinery); manual creation.
- **Outputs:** `TaskCreated/Assigned/Completed/Overdue` (tagged by class).
- **Public interface:** `create(class, …)`, `assign`, `complete`, `queryOpen(class, relationship|assignee|branch)`.
- **Consumed by:** Human tasks → Daily Huddle, Today's Actions, Reception, Chairside. System tasks → operational/admin dashboards only.
- **Events:** *Subscribes* to task-request events. *Publishes* task lifecycle events.
- **Data ownership:** Tasks (both classes) + assignments + SLA state.
- **NEVER inside it:** Messaging, rules, timing, or workflow orchestration. A task is a unit of work — not a message, a decision, or a procedure. And a system task must **never leak into a clinical/reception surface.**

---

### LAYER C — Delivery (reach patients & staff)

#### C1. Communication Engine — one intelligent gateway to the patient

- **Purpose:** The **single gateway** through which the clinic reaches or hears from a person on any channel — phone log, WhatsApp, SMS, email, push, patient app, and any future channel. Its governing goal: **never annoy patients, never spam, always communicate intelligently.**
- **Responsibilities:** Channel-agnostic send/receive; one unified conversation per person across all channels; templates; inbound capture; delivery/receipt status. The **Communication Guard** (expanded — see Section 10) evaluates *every* outbound message before it leaves. Physical delivery to external providers is performed **through the Integration Engine**, never by embedding vendor SDKs here.
- **Inputs:** `CommunicationRequested` (from Rules, Workflow, Marketing, staff, Reviews, AI Assistant); inbound from Integration.
- **Outputs:** `CommunicationSent/Delivered/Failed/Received`; thread updates.
- **Public interface:** `send(relationship, intent, template, payload)` (always through Guard), `receive(inbound)`, `thread(relationshipId)`.
- **Consumed by:** Everyone who talks to a person — Rules, Workflow, Marketing, Reviews, AI Assistant, Reception, Patient App.
- **Events:** *Subscribes* to communication requests. *Publishes* communication events (→ ledger, timeline, insights).
- **Data ownership:** Threads, messages, templates, delivery status, the Guard's decision + contact log.
- **NEVER inside it:** Deciding *whether* to reach out (Rules/Workflow/Marketing), *who* the person is (Relationship), or *how to physically connect* to a vendor's API (Integration). It executes contact *under policy*; it does not originate intent or own the wire. **No module may ever message a patient except through this engine.**

#### C2. Notification Engine — internal, staff-facing

- **Purpose:** Deliver *internal* alerts to staff/roles (the bell, staff push, escalations). Distinct from Communication (which is patient-facing).
- **Responsibilities:** Role/recipient resolution, in-app + staff-push delivery, read/dismiss state, noise suppression, escalation.
- **Inputs:** `NotificationRequested` (from Rules, SLA breaches, escalations).
- **Outputs:** Delivered staff notifications; read/dismiss events.
- **Public interface:** `notify(recipients|role, type, payload)`, `markRead`, `feed(user)`.
- **Consumed by:** Every engine that must alert a human internally.
- **Data ownership:** The one internal staff-alert store.
- **NEVER inside it:** Patient-facing messaging (Communication) or business rules.

---

### LAYER D — Read & Insights (views; never authoritative writers)

Every engine here is a **listener that maintains a ready-made view**. None is a source of truth; each can be dropped and rebuilt from the Fact Ledger. This is what structurally prevents god services.

#### D1. Timeline Engine

- **Purpose:** The single, unified story of a person's history — what a dentist sees when they open a patient.
- **Responsibilities:** Maintain a per-relationship timeline view from the ledger; blend activities, communications, journey changes, workflow steps, tasks, clinical + finance events into one chronological story; filter/paginate.
- **Consumed by:** Relationship Profile (OS + Patient App), AI Assistant.
- **Data ownership:** The timeline view only (derived, rebuildable).
- **NEVER inside it:** Writes to source data or its own "truth." A mirror, never a master.

#### D2. Insights Engine (see Section 9) — was "Intelligence Engine"

- **Purpose:** Turn a person's history into *useful operational insight* — a panel of practical signals a clinic can act on, not one opaque score. **AI-agnostic:** it produces the signals; future AI models *consume* them.
- **Responsibilities:** Maintain independent views for Relationship Health, Lifetime Value, Risk, Referral Potential, Treatment Opportunity, Engagement, Patient Behaviour, Communication Preference, and an AI Summary — each updated from the events relevant to it.
- **Consumed by:** Today's Actions, Analytics, Marketing (audiences), AI Assistant, Rules (as conditions), Communication Guard (preference).
- **Data ownership:** Insight views + model config.
- **NEVER inside it:** Live cross-domain reads, actions, or a hard dependency on any specific AI vendor. It *summarizes*; it never *acts*, never *queries other domains' tables directly*, and never *is* the AI.

#### D3. Analytics Engine

- **Purpose:** Aggregate, cohort, and trend the clinic's performance, per branch and across an organization.
- **Responsibilities:** Maintain aggregate views (conversion, recall success, LTV distribution, staff KPIs, channel ROI, procedure throughput) incrementally from events; serve dashboards and DSO roll-ups.
- **Consumed by:** OS dashboards, Daily Huddle, DSO/Enterprise roll-ups, Marketing.
- **Data ownership:** Aggregate views.
- **NEVER inside it:** Per-record truth or operational decisions. Analytics observes; it never drives a workflow.

#### D4. Search Engine

- **Purpose:** One fast, unified search across people and their attached records.
- **Responsibilities:** Maintain a search index from events; typeahead + structured query; power universal search and audience selection.
- **Consumed by:** Every surface with a search box; Marketing audience builder.
- **Data ownership:** The index (derived, rebuildable).
- **NEVER inside it:** Source truth. The index is disposable and reconstructable.

---

### LAYER E — Integration (the edge of the platform)

#### E1. Integration Engine (NEW)

- **Purpose:** The **single boundary** between Dentfluence and the outside world. Every external system connects here — and nowhere else.
- **Why it exists:** Without it, vendor APIs leak into business engines (a WhatsApp SDK inside Recall, a payment SDK inside Billing), and every provider change ripples through the product. The Integration Engine is the **anti-corruption layer**: it absorbs the messiness of external systems so the engines stay clean and provider-agnostic.
- **External systems it fronts:** WhatsApp, Google Calendar, Google Reviews, Meta, the practice website, ABDM, payment gateways, third-party PMS, the future Marketplace, and any future API.
- **Responsibilities:** Own external authentication/credentials, API clients, webhooks, rate limits, retries at the transport level, and **protocol/shape translation** — turning a vendor's payload into a clean internal fact, and an internal request into a vendor call. Inbound from any external system becomes a normalized domain event; outbound requests from engines are translated to the provider.
- **Inputs:** Internal requests (send-this, sync-that); external webhooks/callbacks.
- **Outputs:** Normalized inbound domain events; provider responses.
- **Public interface:** `connector(system).call(request)`, `connector(system).onInbound(payload) → event`.
- **Consumed by:** Communication (to physically deliver on a channel), Billing (payment gateways), Relationship/ABDM (identity), Marketing (Meta/website/Google), Scheduling (Google Calendar), Reviews (Google).
- **Events:** *Publishes* normalized inbound facts. *Subscribes* to outbound requests.
- **Data ownership:** Connector configs, credentials, sync state, external-id mappings.
- **NEVER inside it:** Business logic, decisions, or patient policy. It is a *translator and a wire*, never a decision-maker. It does not decide *whether* to send a WhatsApp — it only knows *how* to reach WhatsApp when Communication asks.

> **Integration vs. Communication — the boundary.** Communication owns *messaging semantics* (threads, templates, Guard, "should we and what do we say"). Integration owns *the wire* (how to physically authenticate and call WhatsApp/Meta/etc.). Communication sends **through** Integration. One decides and composes; the other connects and translates.

---

## 5. Single Source of Truth — capability → owning engine

| Capability | **Single owner** |
|---|---|
| **Identity** (who is this person) | Relationship Engine |
| **Master Relationship + Journeys / pipeline state** | Relationship Engine |
| **Activities / history / facts** | Activity Engine (the one ledger) |
| **Timeline** (rendered history) | Timeline Engine (view of the ledger) |
| **Automation policy — when should something happen** | Rules Engine |
| **Decision Log — why the brain decided what it did** | Rules Engine |
| **Time-based execution — recall, reminders, delays, retries, cooldowns, expirations** | Automation Engine |
| **Operational & clinical procedures (RCT, Implant, Ortho, Membership, Lab, Marketing, HR onboarding workflows)** | Workflow Engine |
| **Tasks — human work *and* system jobs (separated internally)** | Task Engine |
| **Patient communication (all channels) + Guard** | Communication Engine |
| **Internal staff notifications** | Notification Engine |
| **Insights & scoring (health, LTV, risk, opportunity, preference, AI summary)** | Insights Engine |
| **Analytics / metrics / roll-ups** | Analytics Engine |
| **Search / indexing** | Search Engine |
| **External systems (WhatsApp, ABDM, payments, Google, Meta, website, marketplace…)** | Integration Engine |
| **AI reasoning / explanation / assistance** | *No engine* — the AI Assistant is an **interface** that consumes the engines above (§12A) |
| **Multi-clinic / region / enterprise / franchise** | *Reserved* — future **Organization Engine** (§12B); not built today |
| **Consent / DPDP policy** | DPDP domain (owns policy) → *consumed* by Communication Guard |
| **Clinical, billing, lab, inventory records** | Their own domains (own their truth; **emit events**) |

One capability, one owner. No capability appears twice. **AI owns nothing** (it consumes), and the **Organization Engine is reserved, not implemented.**

---

## 6. Domain Events — the language of the platform

Events are how the platform's parts stay in sync without knowing about each other. A module does one thing when something happens: **announce a fact.** It never calls another engine to "also do" something, and — per Revision 2 — **it never reaches for "an event bus."** It publishes and subscribes to *events* through shared services with product-meaningful names. The transport is invisible.

Naming: past-tense facts (`AppointmentCompleted`), never commands. A *request to act* is its own event (`CommunicationRequested`) so intent is auditable and Guard-gated.

| Domain Event | Who announces it | Who acts | Who just updates a view | Who ignores it |
|---|---|---|---|---|
| **LeadCreated** | Relationship (identity) + lead intake | Rules (assign, first contact), Relationship (open Lead Journey) | Activity, Timeline, Analytics, Search, Insights | Billing, Lab, Inventory |
| **LeadConverted** | Relationship (Lead Journey) | Rules, Workflow (may start a treatment workflow), Automation (arm recall) | Activity, Analytics, Insights | Inventory, Lab |
| **AppointmentBooked** | Scheduling | Rules (confirm), Automation (arm "tomorrow") | Activity, Timeline, Insights, Analytics | Inventory |
| **AppointmentCompleted** | Scheduling/Clinical | Relationship (advance journeys), Workflow (advance step), Rules (post-op, review) | Activity, Timeline, Insights, Analytics | Inventory |
| **AppointmentNoShow / Cancelled** | Scheduling | Rules (re-engage), Automation (re-arm) | Activity, Timeline, Insights, Today's Actions | Lab |
| **TreatmentPlanned** | Clinical | Relationship (open Opportunity Journey), **Workflow (instantiate procedure)**, Rules (estimate follow-up) | Activity, Insights (opportunity), Analytics | Inventory |
| **TreatmentCompleted** | Clinical | Relationship (close Opportunity, advance Recall), Workflow (advance/close), Rules (post-op, review) | Activity, Timeline, Insights (LTV, health) | — |
| **PaymentReceived** | Billing | Rules (receipt, thank-you) | Activity, Insights (LTV), Analytics | Lab, Inventory |
| **LabCaseReady** | Lab | Rules (schedule fitting), Workflow (advance lab step), Automation (arm "overdue") | Activity, Timeline, Today's Actions | Marketing |
| **MembershipRenewed / Expiring** | Membership / Automation | Rules (renewal outreach), Workflow (membership flow) | Activity, Insights, Analytics | Lab |
| **RecallDue** | Automation | Rules (recall outreach → Communication + Task) | Today's Actions, Insights | Billing, Inventory |
| **OpportunityCreated** | Clinical / Relationship | Rules (nurture), Automation (arm follow-up) | Insights, Analytics, Today's Actions | Inventory |
| **CommunicationRequested** | Rules / Workflow / Marketing / staff / AI | **Communication (Guard → Integration → send)** | Activity (after send), Analytics | Lab, Inventory |
| **CommunicationSent / Received** | Communication | Rules (route inbound, escalate) | Activity, Timeline, Insights (engagement/preference) | Inventory |
| **WorkflowStepCompleted** | Workflow | Rules (chain), Automation (schedule next), Task | Activity, Analytics, Huddle | Inventory |
| **TaskCompleted** | Task | Rules (chain next step), Workflow (advance) | Activity, Analytics, Huddle | Lab |
| **ReferralCreated** | Relationship (Referral Journey) | Rules (thank + credit referrer) | Insights (referral potential), Analytics | Inventory |

Reading this table top to bottom *is* the platform's behavior — with **no engine calling another engine directly.** Add a channel, a rule, a workflow, or a report by adding a listener; touch no announcer.

---

## 7. How the current duplicate systems disappear

**Current → Intermediate → Final**, as *architectural states* (not code steps). Each duplicate collapses because the target gives its concept exactly one owner.

| Duplicate today | Current | Intermediate (coexistence) | Final |
|---|---|---|---|
| **3 activity logs** (`lead_activities`, `comm_activity_logs`, `activities`) | Three write targets; timeline merged at read time | All producers also announce facts into the **Activity Ledger**; legacy logs become read-only mirrors; Timeline reads only the ledger | **One Activity Ledger**; legacy logs are archives, written by nothing |
| **2 rules engines** (`FollowUpRulesService` + `RulesEngine`) | Two automation brains, one frozen | All triggers become events into the **one Rules Engine**; legacy rules ported to config | **One Rules Engine**; automation is configurable, not coded |
| **2 reminder pipelines** (RecallEngineService + Relationship reminders) | Two schedulers acting independently | Recall/reminders become **Automation Engine** work; old emitters silenced; Rules decides, Communication/Task execute | **Automation → Rules → Communication/Task**; one path, no overlap |
| **2 notification stores** (`app_notifications` + `relationship_notifications`) | Dual-write, bridged | New notifications flow only through **Notification Engine** | **One Notification Engine store** |
| **Pipeline in 3 places** (`leads.stage`, `opportunities.status`, journeys) | Independent states, hand-synced | Journeys become authoritative; stage/status become **views** of journey state | **Relationship Journeys** are the only pipeline truth |
| **Mock + real timeline** | Dead mock + real merge | Mock retired; timeline served by **Timeline Engine** | **One Timeline Engine**, event-derived |
| **Today's Actions reads 12 domains** | Live god-reader | Reads shift to a **Today's Actions view** fed by events | **Presentation-only**; zero live domain reads |
| **Scattered vendor integrations** | SDKs/API calls inside business services | External calls rerouted through the **Integration Engine** | **One Integration boundary**; engines are provider-agnostic |
| **Ad-hoc treatment tracking** | Procedure steps tracked informally / per-module | Treatment procedures modeled as **Workflow** templates; steps request Task/Automation/Communication | **Workflow Engine** owns every multi-step procedure |

The pattern is identical every time: **introduce the single owner, turn old writers into fact-announcers, turn old stores into rebuildable views or archives, then silence the old writers.** No concept is deleted while it is still the truth; it stops *being* the truth first.

---

## 8. Today's Actions — "My work for today," not a task queue

**Today's Actions owns no data and reads no domain directly.** But more importantly, it should never *feel* like software. To reception staff it must feel like a colleague handing them their day:

- **Today's Calls** — who to phone, and why.
- **Today's Appointments** — who's coming in.
- **Today's Lab Deliveries** — what's arriving or overdue at the lab.
- **Today's Collections** — payments to follow up.
- **Today's Membership Renewals** — memberships lapsing.
- **Today's Recall Patients** — who's due back.
- **Today's Birthdays** — a warm touch.
- **Today's Missed Patients** — yesterday's no-shows to win back.
- **Today's Pending Estimates** — plans awaiting a yes.
- **Today's High-Value Opportunities** — where attention pays off most.

The experience is **"my work for today"** — a clear, human, per-role list — not "a queue of tickets."

How the work reaches it, without any god-reading: every part of the clinic already announces facts (`RecallDue`, `LabCaseReady`, `AppointmentNoShow`, `OpportunityCreated`, `PaymentOverdue`, `MembershipExpiring`, `BirthdayToday`, `WorkflowStepCompleted`…). A dedicated **Today's Actions view** listens for exactly the facts that mean "a human should act on this today," and keeps one ready-made, branch-scoped, per-role list. Items disappear when a closing fact arrives (`AppointmentBooked`, `TaskCompleted`). The Today's Actions surface then does one thing: **read that one view, group and prioritize it for who's logged in, and present it warmly.** No aggregation, no live joins, no scoring at read time — those already happened upstream. The reception dashboard reads one indexed view, loads in a single query, and cannot be broken by a change elsewhere in the platform.

---

## 9. The Insights Engine — practical intelligence a clinic can act on

A single 0–100 score is opaque and becomes a god-service the moment everyone wants to tune it. Instead, Insights is a **panel of independent, event-fed signals** — each simple, each actionable, composed only at the surface. And it is deliberately **AI-agnostic**: it produces clean signals that *future AI models consume*, rather than being an AI itself.

- **Relationship Health** — is this bond warming or cooling? (engagement + visit regularity + sentiment)
- **Lifetime Value** — realized + projected, from payments and treatment history.
- **Risk** — dormancy, missed recalls, no-shows, unanswered outreach.
- **Referral Potential** — past referrals, reviews, satisfaction, tenure.
- **Treatment Opportunity** — planned-but-unaccepted work, clinical flags, affordability (membership, EMI history).
- **Engagement** — responsiveness across channels.
- **Patient Behaviour** — booking patterns, seasonality, price sensitivity.
- **Communication Preference** — best channel + best time, learned from inbound patterns. **Feeds the Communication Guard directly.**
- **AI Summary** — a plain-language rollup of the above + timeline, cached, regenerated on material change.

Each signal is a small independent view updated by the events relevant to it — no signal reads another domain's tables, and no single "score" tries to encode all of them. Surfaces compose the handful they need (Today's Actions, Marketing audiences, the profile header, the AI Assistant). Sophistication lives in the *composition*, not in any one piece — and adding a new signal (say, "insurance eligibility") is a new listener, never a rewrite. Because Insights is AI-agnostic, we can swap or upgrade the AI models that *consume* it without touching the engine.

---

## 10. Communication architecture — intelligent, never annoying

One engine, many channels, one intelligent gate. The governing goal is explicit: **never annoy patients, never spam, always communicate intelligently.**

- **Channel adapters** (phone-log, WhatsApp, SMS, email, push, patient-app, future) sit behind one channel-agnostic contract; the physical connection to each provider runs through the **Integration Engine**. Adding a future channel is a new adapter + connector — nothing upstream changes.
- **Everything outbound is a `CommunicationRequested` event** from Rules, Workflow, Marketing, Reviews, staff, or the AI Assistant. No module holds a channel credential or calls a provider directly.
- **The expanded Communication Guard evaluates every outbound message against eight considerations before it leaves:**
  1. **Patient Preference** — has this patient told us how they want to be reached?
  2. **Consent** — DPDP consent for this purpose and channel (pulled from the DPDP domain).
  3. **Communication Frequency** — have we already contacted them enough recently?
  4. **Quiet Hours** — is it an unreasonable time to reach this person?
  5. **Urgency** — is this clinically or operationally urgent? *Urgency can override frequency and quiet-hours* — a post-op complication check or an appointment-in-one-hour reminder must not be suppressed by a marketing-grade frequency cap.
  6. **Relationship Context** — where is this person in their journeys? (a grieving lapsed patient and an eager new lead are not treated alike)
  7. **Preferred Channel** — reach them where they actually respond (from Insights).
  8. **Communication History** — what have we already said, and did they reply?

  The Guard is **fail-closed**: if it cannot confirm a send is appropriate, it holds it and records the decision as a fact. For a regulated, patient-facing platform, "don't send when unsure" is the only safe default — *except* where verified urgency explicitly overrides, which is itself audited.
- **Inbound is unified:** every channel's inbound (via Integration) becomes `CommunicationReceived`, threaded onto the person, and available to Rules (routing/escalation) and Insights (preference/engagement).
- **One conversation per person, across every channel** — the clinic sees a single thread, not per-channel silos.

This is why Marketing, Reviews, Recall, a treatment Workflow's reminders, and a future patient-app chat all behave consistently and considerately: they all speak `CommunicationRequested`, and the Guard protects the patient identically regardless of who's asking.

---

## 11. Marketing Engine — plugged in, never bypassing the patient relationship

Marketing is a **first-class citizen of the platform, never a side door.** The rules:

- Marketing **never writes to people or sends messages directly.** It builds **audiences** by querying the **Search** and **Insights** views (e.g. "high referral-potential, engaged, membership expiring this month"), then announces `CommunicationRequested` per person — which passes through the **Guard** like everything else. Consent and frequency caps apply to campaigns automatically; a campaign *physically cannot* over-contact or reach a non-consenting patient.
- Marketing's outreach can be run as a **Marketing Workflow** (multi-step nurture), reusing the Workflow Engine rather than reinventing sequencing.
- Inbound responses and new leads flow through **Relationship (identity) → journeys** like any other person, so attribution is just facts (`LeadCreated {source: campaign X}`) that **Analytics** already understands — channel ROI needs no bespoke pipeline.
- External marketing systems (Meta, website, Google) connect only through the **Integration Engine.**

**Non-negotiable:** there is no path from Marketing to a patient that skips the Relationship Engine and the Communication Guard.

---

## 12. How every product surface plugs into the engines

Every surface is a **thin consumer** — it announces facts for what happens in it and reads views for what it shows. None owns engagement logic.

- **Dentfluence OS (the practice management surface).** The primary command centre for the whole operating cycle: books, records clinical/financial facts → **announces events**; renders profile/timeline/today/analytics → **reads views**; requests actions → through Rules/Workflow/Communication/Task. Owns clinical/finance truth in its domains; owns *no* engagement truth.
- **Chairside.** The doctor's in-treatment surface. It runs **Workflows** (RCT, Implant) step by step, announces clinical facts as it goes, and reads the patient's timeline + insights at the chair. Chairside is where the Workflow Engine is most visible to the dentist.
- **Patient App.** A patient-facing window onto the *same* Master Relationship. Self-registration → Relationship (identity/dedup). Booking, messaging, reviews → events + the Communication Engine (as an inbound/outbound channel). Not a separate system — another window on the same person.
- **Daily Huddle.** A read surface over **Task**, the **Today's Actions** view, open **Workflows**, and **Analytics**, scoped to branch/day. It announces task completions; it invents no new store.
- **Inventory & Lab.** Operational domains that own their truth and **announce facts** (`LabCaseReady`, stock events). Lab participates in **Lab Workflows**; a lab delivery becoming tomorrow's fitting is handled by listeners, not by Lab knowing about Today's Actions.
- **Marketplace (future).** Referrals/partners resolve people through **Relationship (identity)**, attach to Master Relationships, and interact only via events + Communication + Integration. Because the platform is person-centric and event-driven, a marketplace is purely additive.

The test every new surface must pass: *"Does it announce facts, read views, and route all intent through engines?"* If yes, it plugs in. If it wants its own activity log, reminder logic, or message sender, it is rejected by design.

---

## 12A. The AI Assistant (Tulip) is an *interface*, not a product surface

This is a deliberate reclassification. Earlier revisions listed Tulip alongside OS, Chairside, and the Patient App as if it were a product. It is not. **Tulip is an intelligent interface layered over the same engines every other surface uses — it owns no business logic and no data of its own.**

Concretely, the AI Assistant may only do four things, and nothing else:

1. **Read projections** — Timeline, Insights, Analytics, Search — to answer questions.
2. **Request actions** — `CommunicationRequested`, `TaskRequested`, a journey transition — through the exact same Guarded, audited, Decision-Logged paths a human uses.
3. **Invoke workflows** — start or advance a Workflow instance via the Workflow Engine.
4. **Ask engines to perform operations** — never performing them itself.

What Tulip must **never** do: hold its own copy of a rule, decide whether a patient may be contacted (the Guard decides), compute its own score (Insights decides), keep its own task list, or reach an external system directly (Integration decides). If the AI were allowed to "just do it," we would have re-created a god service with a friendly voice — the precise anti-pattern this architecture exists to prevent.

Why this matters practically: because Tulip acts *only* through engines, everything it does is automatically consented, frequency-capped, audited, and explainable. When a dentist asks *"why did the assistant not message this patient?"*, the answer comes from the **Decision Log**, not from the AI's imagination. And because Insights is **AI-agnostic**, the underlying model can be swapped or upgraded — or several models can run — without touching a single engine. **The engines remain the only source of truth; the AI is simply the most natural way to talk to them.**

---

## 12B. Reserved for the future: the Organization Engine (not built today)

We are **not** building this now, and adding it now would violate the "no new core engines" discipline. But the architecture must never *block* it — so we name the slot and keep the door open.

A future **Organization Engine** would own the concerns that only appear above a single clinic: **multi-clinic and franchise structure, regions and zones, enterprise hierarchy, cross-clinic referral, cross-clinic patient movement, central/benchmarking analytics, and organization-level permissions.** These are genuinely distinct from what any current engine owns — they are about *relationships between clinics*, not events within one.

Why nothing today blocks it:

- Every event and every view already carries a **branch/organization dimension** (§13), so cross-clinic roll-ups are additive, not a reshape.
- The **Relationship Engine** owns one Master Relationship per person; cross-clinic patient movement becomes a *linking* concern the Organization Engine can layer on top, without the clinic-level engines changing.
- **Analytics** already produces per-branch views that an Organization Engine would aggregate; benchmarking is a consumer of existing views.
- Permissions are already role- and branch-scoped, so organization-level permissions extend the model rather than replace it.

When the DSO/enterprise need is real, the Organization Engine slots in **above** the existing thirteen as a consumer and coordinator — it never reaches inside them. Reserving it now is how we honour "design for the decade" without over-building for today.

---

## 13. One platform, five tiers (solo → DSO/Enterprise)

The same engines serve every scale because **tier is a data-partitioning and transport concern, not an architectural one:**

- **Solo dentist / multi-chair:** all engines in one process, events delivered **in-memory** (simplest possible — no queues, no brokers), one database. The engine boundaries are invisible to the operator.
- **Multi-branch:** branch/organization becomes a mandatory dimension on every event and view; roll-up analytics aggregate across branches. Same product.
- **DSO / Enterprise:** event delivery switches to **asynchronous (queue/broker)** for throughput, isolation, and replay; heavy views (Analytics, Search, Insights) scale out or run on their own hardware **behind the same contracts**; the org hierarchy (Group → Region → Branch → Chair) is tenancy metadata, not new code.

Because engines never call each other directly and never share private tables, moving one to its own service later is a transport change, not a redesign. **The solo dentist and the DSO run the same platform; only the dials differ.** That is the entire payoff of engine-first + events.

---

## 14. Final architecture diagram

See `docs/target-architecture-diagram.mermaid`. It shows the **Clinic Operating Cycle** at the top, the product surfaces, the **AI Assistant as a cross-cutting interface** (not a surface), the events they announce, the five engine layers (Write Spine · Orchestration & Automation · Delivery · Read & Insights · Integration), the **Decision Log** on the Rules Engine, the **Human/System split** in the Task Engine, the expanded Communication Guard, how work reaches **Today's Actions** and **Analytics**, and the **reserved Organization Engine** (future, not built).

---

## 15. Current vs Proposed

| Dimension | Current (three-generation, service-coupled) | Proposed (engine-first, event-driven, clinic-shaped) |
|---|---|---|
| **Integration** | Direct service calls + hidden coupling; god readers | Facts announced as events; no engine calls another for side effects |
| **Identity** | Split across leads/patients | Owned by Relationship Engine (one person, one node) |
| **Activity history** | 3 logs, merged at read time | 1 ledger; everything derives from it |
| **Automation** | 2 rules engines (1 frozen) | 1 Rules Engine (decides) + Automation Engine (executes time-based) |
| **Reminders/recall** | 2 overlapping pipelines | Automation → Rules → Communication/Task, single path |
| **Procedures (RCT/Implant/…)** | Tracked informally / per-module | **Workflow Engine** — reusable, configurable clinical journeys |
| **Pipeline state** | 3 places, hand-synced | Journeys own it; module columns are views |
| **Notifications** | 2 stores, dual-write | 1 Notification Engine |
| **Today's Actions** | Live-reads 12 domains (god service) | Reads 1 view; "my work for today," presentation only |
| **Scoring / insight** | Single opaque score, cross-domain reads | Panel of event-fed, AI-agnostic signals composed at the edge |
| **Communication** | Multiple senders, fail-open guard | One engine; 8-factor, fail-closed, urgency-aware Guard |
| **External systems** | SDKs scattered in business services | One Integration Engine boundary; engines provider-agnostic |
| **Marketing** | Can touch persons/leads directly | Through spine + Guard; attribution via events |
| **Adding a feature** | Wire into N services, risk breaking others | Add a listener; touch no announcer |
| **Scale story** | Same code strains at multi-branch | One platform solo→DSO; transport is the only dial |
| **Feel to the dentist** | A collection of modules | A platform that understands the clinic's day |

**What becomes simpler:** every surface shrinks to "announce facts, read views." New features stop re-implementing activity/reminder/notification/procedure logic.

**What disappears:** the second rules engine, the duplicate reminder pipeline, two of three activity logs (as writers), the duplicate notification store, hand-synced pipeline state, the mock timeline, scattered vendor SDKs, and every god reader.

**What becomes reusable:** all thirteen engines — by definition. A new feature (say, orthodontic aligner tracking) consumes Relationship, Activity, **Workflow**, Rules, Automation, Communication, Task, Insights unchanged, and adds only its own facts and a workflow template.

**What becomes easier to maintain:** one owner per capability; views are rebuildable, so drift is impossible; the event catalog *is* the behavior spec, readable in one table; external change is absorbed at one boundary.

**What becomes easier to scale:** near-real-time views scale horizontally; event delivery upgrades from in-memory to broker without touching engines; heavy views and the Integration boundary extract cleanly.

---

## 16. Challenging this design (devil's advocate)

A design is only trustworthy after surviving its own strongest objections — including the objections to Revision 2's own changes.

1. **"Events are over-engineering for a solo dentist."** *Mitigation:* at the solo tier events are delivered **in-memory** — effectively well-structured method dispatch. Zero infrastructure cost; async only switches on at scale. And because the product thinks in *events, not a bus*, a solo-tier engineer never confronts broker concepts at all.

2. **"Two new engines (Workflow, Integration) contradicts 'don't over-engineer.'"** The sharpest objection, and I take it seriously. *Defense:* each passes the reusability test decisively. **Workflow** solves a problem that appears in *every* treatment type, membership, and campaign — modeling multi-step procedures — and without it that logic would scatter across modules (the exact disease we're curing). **Integration** solves a problem that appears with *every* external vendor — and without it, vendor SDKs metastasize into business engines. Meanwhile I *removed* Identity as a standalone engine, so the net is +1, not +2. Restraint is real: I refused a Journey Engine (journeys live in Relationship), a Preference Engine (a signal in Insights), and a Consent Engine (policy in DPDP).

3. **"Workflow will duplicate Relationship Journeys."** The risk that made me hesitate most. *Mitigation:* the boundary is explicit and enforced — Journeys are *coarse one-per-type lifecycle state*; Workflows are *fine-grained, multi-instance procedure templates* that *drive* journeys but never store lifecycle state themselves. And Workflow *orchestrates by requesting* from Task/Automation/Communication — it never executes — so it cannot swell into a god-orchestrator. The day a Workflow starts sending its own messages or holding its own reminders is the day this boundary has been violated.

4. **"Rules vs. Automation will blur — both sound like 'when.'"** *Mitigation:* the line is fixed in Section 4: Rules is **reactive** (a fact occurred → decide), Automation is **temporal** (run at time T, retry, cooldown, expire). Recall demonstrates all four engines with one job each. If a change can't say whether it belongs in Rules or Automation, that's the signal it's conflating decision with timing.

5. **"Eventual consistency will confuse staff."** *Mitigation:* anything the acting user must see immediately is served from the write side or optimistically updated; only cross-person aggregates are near-real-time, where sub-second lag is invisible. Money, clinical records, and consent are never eventual.

6. **"Views can drift or corrupt."** *Mitigation:* that is the strength — views are disposable and **rebuildable from the ledger**. A bad view is fixed by replay, not data surgery. Contrast today's hand-synced tables, which have no source to rebuild from.

7. **"The expanded 8-factor Guard could suppress messages patients actually need."** A genuine patient-safety concern with the very change requested. *Mitigation:* **Urgency is an explicit override** — verified clinical/operational urgency bypasses frequency and quiet-hours (and is itself audited). The Guard protects against *annoyance*, never against *care*.

8. **"Debugging across events is harder than a call stack."** *Mitigation:* the Activity Ledger *is* a complete, ordered, per-person audit trail — one place to look, easier than today's scattered logs. And the **Decision Log** now answers "why did the brain do that?" directly.

9. **"Reclassifying AI as an interface will limit what the assistant can do."** *Mitigation:* the opposite — it makes the assistant *more* capable and *safer*. Because Tulip acts only through engines, every capability the platform gains is automatically available to it, already consented, audited, and explainable. Constraining AI to "read projections, request actions, invoke workflows" is exactly what lets us trust it with a clinic.

10. **"Non-linear workflows (branches, loops, parallel, versioning) reintroduce complexity."** A fair worry with refinement #2. *Mitigation:* the complexity lives **inside** the Workflow Engine as a small set of reusable primitives, never on the dentist's screen and never copied into modules. A clinic configures a template visually; it never writes branching logic. This is the "complexity inside engines, simplicity on screen" principle (§17) in action — and it is *less* complex than the alternative, which is every treatment type hand-rolling its own step tracking.

**Where I'll guard hardest going forward:** the **Insights Engine**, the **Workflow Engine**, and now the **AI Assistant** are the three most likely future god-services. Insights stays honest only while every signal is an independent view with a one-sentence purpose and zero live cross-domain reads. Workflow stays honest only while it *requests* and never *executes*. AI stays safe only while it *asks engines* and never *acts on its own*. All three lines must be defended in every review — the day someone adds "just one live query" to Insights, "just one direct send" to Workflow, or "just let the AI do it directly," the architecture begins regressing toward the one this document replaces.

**Net:** the design optimizes for the three things a decade-long, multi-tier dental platform needs most — **one owner per truth**, **add-by-listening not add-by-coupling**, and **a shape that mirrors how a clinic actually runs its day.** It is simple at the solo tier, uncompromised at the DSO tier, the same platform throughout, and — above all — built so a dentist feels *"this software understands how my clinic works."*

---

## 17. Architecture Governance & Stability (the standing rules)

Revision 3 declares the foundation **stable**. From here, Dentfluence evolves by *adding capabilities through the existing engines*, not by changing the foundation. Six standing rules govern every future decision.

### 17.1 The Clinic Operating Cycle is the intake test

Every future feature must answer one question first: **"Which step of the clinic operating cycle does this improve?"**

```
Morning → Daily Huddle → Today's Actions → Reception → Doctor → Treatment
   → Billing → Lab → Relationship Follow-up → Analytics → Tomorrow
```

If a proposed feature does not clearly make one of these steps smoother for a real clinic, it is reconsidered or rejected. Engines exist **only** because they simplify one or more steps of this cycle. This is the single most important design principle in the document — it is what keeps Dentfluence a *dental operating platform* and not a generic enterprise toolkit that happens to be sold to dentists.

### 17.2 Engine ownership is absolute

Each capability has exactly one owning engine (§5), and **no engine may slowly absorb another's responsibility.** The recurring failure mode of large platforms is drift: a convenient shortcut puts a rule inside Communication, a score inside Workflow, a direct API call inside Billing — and five years later there are three overlapping systems again (exactly what the audit found today). The boundaries are therefore non-negotiable:

- Relationship — identity + journeys. Activity — facts only. Rules — decisions only (+ Decision Log). Automation — time, schedules, retries, cooldowns. Workflow — procedure orchestration only. Communication — patient contact only. Notification — internal alerts only. Insights — projections/AI inputs only. Analytics — metrics only. Search — indexing only. Integration — third-party systems only.
- Every code review asks: *"Is this capability landing in its owning engine, or leaking into a neighbour?"* Leakage is a defect, regardless of how convenient it is.

### 17.3 New features extend engines; they do not spawn services

Before any new functionality is built, the mandatory question is: **"Can this be achieved by extending an existing engine?"** Almost always the answer is yes — a new event, a new rule, a new workflow template, a new insight signal, a new channel adapter. Creating a new isolated service or a parallel implementation of something an engine already owns is prohibited. Duplicate logic is how the current codebase acquired three activity logs and two rules engines; the platform will not repeat that.

### 17.4 Complexity lives inside engines; screens stay simple

Dentfluence must always **feel like software built by dentists.** Internally the engines are enterprise-grade — event-driven, versioned workflows, projections, a decision log. Externally, a solo dentist, a receptionist, or a first-time staff member must understand the workflow within minutes. Therefore: **complexity belongs inside the engines, never on the screen.** A branching implant workflow is sophisticated underneath and a simple visual sequence on top. If a feature makes the screen more complicated to make the code simpler, it is the wrong trade.

### 17.5 No new core engines without an extraordinary reason

The engine set is **13, and considered complete.** The reserved **Organization Engine** (§12B) is the one anticipated future addition, and only when the DSO/enterprise need is real. Any other proposed engine must clear an extraordinary bar: it must own a genuinely new, reusable capability that no existing engine can absorb without violating §17.2. The default answer to "should we add an engine?" is **no — extend an existing one.**

### 17.6 The foundation is frozen; evolve on top of it

From this point the platform is developed by:

- **strengthening** existing engines,
- **improving** projections,
- **improving** workflows,
- **improving** UI/UX,
- **improving** performance,
- **improving** scalability,

but **not** by redesigning the foundation. The architecture described here is the stable backbone for the next decade. Dentfluence evolves by *adding capabilities through this architecture, not by replacing it.* Revisions to this document should henceforth be additive clarifications — not redesigns.

---

*End of design. Architecture only — no code, no implementation, no migration steps.*
