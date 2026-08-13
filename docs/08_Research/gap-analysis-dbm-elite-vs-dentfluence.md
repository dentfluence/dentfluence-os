# Dentfluence vs. DBM Elite Operating Model — Gap Analysis & Roadmap

**Date:** 2026-07-09
**Method:** Every claim below was verified against the live codebase (not memory, not docs) by direct file/grep audit on 2026-07-09. File:line citations are given wherever a capability is confirmed or denied.
**Related docs:** `docs/gap-analysis-current-to-target.md` (2026-07-02 engineering-architecture gap — "how the plumbing should be wired") and `docs/target-architecture-engine-first.md` are the technical-architecture companion to this document. This document is the **business-capability** gap: does the DBM Elite operating model exist as usable behavior, regardless of which engine implements it. Read together, a clear pattern emerges — see Finding 0 below.

---

## Finding 0 — The real gap isn't missing modules, it's disconnected wiring

Before going concept-by-concept, one theme showed up in every single audit area and changes how the roadmap should be sequenced:

Dentfluence has already built **four separate "engine" abstractions** — `AutomationEngine`, `WorkflowEngine`, `InsightsEngine`, and `PracticeProtocols` — that are code-complete, migrated, and in three of four cases sitting behind a feature flag that defaults to **off**, with **zero callers**. Meanwhile the actual automation that runs today is 15+ independent `Schedule::command()` cron jobs in `routes/console.php`, each hand-built, with no shared retry/cooldown/event logic. There is no `app/Events` or `app/Listeners` directory in the entire codebase — nothing fires when a domain thing happens (patient created, plan accepted, invoice paid); everything is either a cron sweep or a manual button click.

This matters commercially: the instinct after reading the DBM list will be "build 10 new modules." The evidence says otherwise — **most of Phase 1's value is free**, sitting in code that's already written and just needs a caller. Flip `insights.signals` on, wire one Eloquent observer on `TreatmentPlan::accept()`, and two of the fifteen DBM concepts move from 🔴/🟡 to materially better, with no migration and no new UI. That is the highest-ROI work available and it should happen before any new module is scoped.

Second theme: **duplication, not absence**. Daily Huddle exists twice (a web controller and a separate AI-tool service with different scope) and BI metrics are split across four different controllers with no single source of truth. Consolidation, not addition, is the right instinct here too.

---

## Section A — Scorecard

| # | DBM Concept | Status | One-line reason |
|---|---|---|---|
| 1 | Daily Huddle Automation | 🟡 | Two parallel, inconsistent huddles; missing review-requests, revenue target, AI summary |
| 2 | Treatment Acceptance System | 🟡 | Opportunity pipeline is real but **never auto-created** from a plan; no objections, no probability |
| 3 | Patient Relationship Management | 🟡 | Unified interaction ledger + relationship identity is genuinely strong; objections, trust score, referral-source structure are weak |
| 4 | Marketing System | 🟡 | Campaign/ROI/GBP-posting is real; GBP performance read-back, reactivation-as-marketing, referral campaigns missing |
| 5 | Community Outreach | 🔴 | Zero code, zero schema — confirmed absent |
| 6 | Internal Marketing | 🟡 | Reviews + recall are automated and live; membership/education nudges are not |
| 7 | AI Practice Manager (intelligence) | 🟡 | Tulip is a real DB-grounded agent, not a chat toy — but it reports numbers, it doesn't diagnose causes |
| 8 | Team Accountability | 🟡 | Action Board + Task assignment is solid; per-staff scorecards live in a *different, disconnected* report; morning/evening checklists already exist (Practice Protocols) but are unused for this purpose |
| 9 | Business Intelligence | 🟡 | Most numbers exist somewhere; no single dashboard; several named metrics (chair utilisation, doctor productivity, cancellation %) are simply not computed |
| 10 | Standard Operating Procedures | 🟡 | Practice Protocols (recurring duty checklists) is built + scheduled + live and **isn't even in tracked project memory**. Event-triggered per-case checklists (implant, new patient) don't exist except for Lab, which is a working proof of concept |
| 11 | AI Coaching | 🔴 | Explicitly documented in code as "no AI" (pure aggregation); no proactive nudge generator anywhere |
| 12 | Marketing Content System | 🔴 | No AI content generation exists; one static seeded field is the closest analog |
| 13 | Referral Network | 🔴 | Patient-to-patient cash reward only; referring doctors/schools/companies are a free-text field, not an entity |
| 14 | Membership System | 🟡 (leaning ✅) | Plans/family/benefit-audit/FY-revenue are solid and live; renewal reminders are pull-only (staff must look), no outbound send; no upsell tracking |
| 15 | Decision Support ("ask the AI") | 🟡 | Tulip answers real DB-grounded questions with a genuine tool-use loop and a confirm-gate for actions; it cannot yet answer "why" questions |

---

## Section B — Detail per concept

### 1. Daily Huddle Automation — 🟡

**What exists:** Two independent implementations. The web one (`app/Modules/Huddle/Controllers/HuddleController.php`) computes today's appointments/collections target, yesterday's visit-logged tracking, lab cases due/overdue, low-stock alerts, branch tasks, and — via `TodayActionsEngine` — recall calls, missed appointments, lead follow-ups, and membership renewals. A second, AI-facing one (`app/Services/Huddle/HuddleService.php`, run by `php artisan tulip:huddle`, scheduled daily 08:00) computes an overlapping-but-different set and just writes to a log file nobody reads.

**Missing:** Google review requests due today, a daily/potential-revenue-today figure, and any generated summary — `HuddleController::accountability()` and `storeNote()` are literal stub methods returning `'Not yet implemented.'`.

**Evolve, don't rebuild:** Retire the log-file `HuddleService` path or make it the single source of truth that the web controller reads from — right now the AI copilot and the human staff are looking at two different huddles, which will erode trust in the number the moment they disagree.

**DB changes:** none required for the merge. A `clinic_settings.daily_revenue_target` field (or reuse an existing Settings table if one already holds targets) is the only new column needed for "revenue vs target today."

**Workflow / automation:** Make huddle generation itself an event — regenerate on `08:00` cron (already exists) but also make the AI summary a scheduled Tulip call using the tool-use loop already built for other tools (see #15), writing a 3-line owner brief instead of a raw log dump.

**AI capability to add:** One new `HuddleSummaryTool` that takes the already-computed huddle payload and produces a short natural-language brief — this is cheap because the data aggregation is done; only the summarization step is new.

---

### 2. Treatment Acceptance System — 🟡

**What exists:** A real, working manual Kanban (`OpportunityPipelineController.php`, `TreatmentOpportunity` model) with six pipeline stages, a `declined_reason` free-text field, note logging via the shared Activity ledger, and an `is_overdue` scope that surfaces stale opportunities on the Today's Actions board.

**What's missing, and it's the whole point of the DBM concept:** `TreatmentPlanController::accept()` does nothing but flip a status column. No Opportunity is created automatically, no follow-up is scheduled, no notification fires. Every treatment-acceptance workflow today depends on a staff member remembering to manually open the pipeline and create a card. Objection tracking doesn't exist (grep for "objection" returns zero results anywhere in `app/`) — only a single free-text decline reason. There is no conversion-rate metric (accepted vs. lost) and no acceptance-probability field of any kind.

**Evolve, don't rebuild:** This does **not** need the dormant `WorkflowEngine` (that's for multi-visit clinical staging, a different problem — see Finding 0). It needs one Eloquent observer.

**DB changes:** Add a `decline_reason_category` enum (price / time / fear / second_opinion / other) alongside the existing free-text `declined_reason` — cheap, high value, and turns a text field nobody can aggregate into a report DBM explicitly asks for ("record objections").

**Workflow / automation (event-driven):** A `TreatmentPlanObserver::created()` (or a `PlanCreated` event/listener, matching the target-architecture doc's event-backbone plan) that auto-creates a `TreatmentOpportunity` at stage `prospect` with a default follow-up date (e.g., +2 days), and fires a notification to the assigned treatment coordinator. This single hook closes sub-items (a), (c), and most of (h) from the DBM brief with no new model.

**AI capability to add — deliberately deferred:** Skip ML-based probability prediction. With a single clinic (or even a handful once this becomes multi-tenant), there isn't enough data to train anything meaningful, and a black-box score a receptionist can't explain will be ignored. Use a transparent weighted heuristic instead (days-in-stage, objection count, plan value vs. patient's historical acceptance rate) — this is Phase 3/4 work, not Phase 1.

---

### 3. Patient Relationship Management — 🟡

**What's genuinely strong:** `CommunicationQueue` unifies call/WhatsApp/walk-in/referral/Instagram/Facebook/website/email into one inbox; the Relationship module (Phase 0–8, per prior verified work) gives one identity per patient with family linkage; `InsightSignal`/`InsightsEngine` (built, migrated, just flag-off) already model Health/LTV/Risk scoring — i.e., the "trust score / engagement history" DBM wants is **already built**, just switched off.

**Weak spots:** previous objections (see #2, doesn't exist), referral source is a free-text field not a structured entity (see #13), and because Insights is dormant, nothing today actually surfaces an engagement or risk score anywhere a receptionist would see it.

**Evolve, don't rebuild:** Turning on `insights.signals` (`config/features.php`) and surfacing the existing Health/LTV/Risk scores on the patient profile and Today's Actions is 80% of "trust score" with zero new schema.

**Product-thinking note:** Don't build a bespoke "trust score" algorithm from scratch as DBM frames it — that's exactly the InsightsEngine's job already, and building a second, competing scoring system is the kind of duplication Finding 0 warns against.

---

### 4. Marketing System — 🟡

**What exists:** A genuinely capable Marketing module — content calendar, AI-assisted post ideas, multi-platform publishing including Google Business Profile posts, a real campaign ROI dashboard (budget vs. spend, cost-per-lead, ROI%), and OAuth integrations for Meta/Google/WordPress/WhatsApp.

**Missing:** GBP is write-only today — posts go out but review/rating/search performance never comes back in, so "GBP performance tracking" doesn't exist. Reactivation and birthday campaigns are technically live, but they live entirely inside `RecallEngineService`, disconnected from the Marketing module's ROI reporting — so a campaign a doctor would recognize as "marketing" isn't tracked as one. Referral campaigns, school/corporate outreach, and patient-education content have no representation at all.

**Evolve, don't rebuild:** Extend `Campaign` (which already has goals/team/budget/ROI) with a `campaign_type` covering `reactivation`, `birthday`, `community_outreach`, `school_program`, `corporate_tie_up`, and link `RecallEngineService` triggers to a campaign_id when they fire, so recall/reactivation sends start counting toward the same ROI dashboard instead of living in a silo. This directly also answers Concept 5 (Community Outreach) and Concept 6 (Internal Marketing) without a new module — see Section D.

---

### 5. Community Outreach — 🔴

Confirmed zero code, zero schema, zero UI — grepped for school/camp/society/corporate-outreach/employee-program across the whole app with no hits.

**Recommendation — don't build a new module.** Per Finding on Concept 4, add `campaign_type` values to the existing `Campaign` model and a `referral_source_id` (see #13) that a patient can be attributed to. A school talk or society camp is, structurally, a marketing campaign with a physical/offline channel and a resulting patient list — it does not need its own controller, its own table set, or its own dashboard. Building it as a first-class module would be exactly the kind of "feature nobody logs into daily" the project instructions warn against; a clinic runs 2–4 outreach events a year, not enough volume to justify standalone tooling.

---

### 6. Internal Marketing — 🟡

Review requests (✅ live, `reviews:request` daily cron, DPDP-gated) and recall reminders (✅ live, 6 triggers in `RecallEngineService`) are genuinely automated. Referral requests exist only as a reward ledger, not a proactive ask. Membership promotion, treatment education, and seasonal campaigns either don't push anything outbound or rely on a static seeded field (`FestivalDate.suggested_content_type`).

**Evolve:** Reuse the exact pattern that already powers recall/review sends (queue → template → DPDP-gated WhatsApp) for two more trigger types: membership expiring (data already computed in `TodayActionsEngine::membershipRenewals()`, just needs a send step) and post-treatment education (trigger off `TreatmentVisit` completion). No new sending infrastructure needed — this is template + trigger, not a new engine.

---

### 7. AI Practice Manager (Intelligence, not reports) — 🟡

**What's real:** `AssistantService` (Tulip) is a genuine agentic tool-use loop against a local Ollama model, with 13 registered tools (`ToolRegistry.php`) that query live data — schedule, patient summary, KPIs, huddle, tasks, membership — plus write-capable tools (`CreateTaskTool`, `BookAppointmentTool`) gated behind a confirm-card for anything clinical or financial. This is not a chatbot wrapper; it's closer to what DBM is asking for than most PMS competitors will have.

**What's missing is the "why," not the "what":** every existing tool answers a lookup question. None of them compare periods, cross-reference causes, or flag a trend unprompted. "Why did revenue drop" requires a tool that can pull two periods and diff them across cancellations/no-shows/lead source — that tool doesn't exist yet.

**AI capability to add:** A small set of diagnostic tools (`RevenueTrendTool`, `ChairUtilizationTool`, `StaffPerformanceTool`) that do multi-query comparison, reusing the same ToolRegistry pattern already proven out. This is additive to the existing agent, not a new AI system.

---

### 8. Team Accountability — 🟡

**What's real:** `/relationship/today` (Today's Action Board) is a solid, live accountability surface — 12 category feeds, a Log/Close/Dismiss workflow with mandatory dismiss reasons, and a Task model with assignment, priority, and human/system classification.

**The interesting find:** a genuine "morning/evening checklist" system already exists — `app/Modules/PracticeProtocols/` (time/frequency-triggered recurring duty checklists: daily/weekly/monthly, by category including "decon," admin, reception), fully built, migrated, and scheduled (`protocols:generate` at 00:10 daily). **This module is not in tracked project memory at all** — it appears to have shipped without being logged. Per-staff scorecards (Practice Protocol Compliance: done/missed/rate per person) already exist too, but only inside the separate Huddle *report* page, disconnected from the Action Board where staff actually work day to day.

**Evolve, don't rebuild:** Surface Practice Protocol checklists and compliance scores directly on the Action Board (or a "My Day" staff view), rather than treating them as a report only management sees. Call-quality scoring is the one genuinely missing piece here, and it depends on infrastructure that doesn't exist yet (call recording) — defer it (see Phase 4).

---

### 9. Business Intelligence — 🟡

Revenue-today, outstanding balance, collection rate, lead conversion %, recall success %, LTV, and staff KPIs are all computed correctly today — just scattered across four different places (`DashboardController`, `ReportsController`, `Relationship/AnalyticsController`, and Tulip's `KpiReportTool`, which is chat-only and invisible to anyone who doesn't ask). Revenue-vs-target, marketing ROI in one place, chair utilisation, doctor/front-desk productivity as named metrics, average treatment value, and cancellation rate % are not computed anywhere.

**Evolve, don't rebuild:** One consolidated "Practice Performance" page that pulls from the existing controllers rather than a new analytics engine — the raw numbers already exist in three of four cases; the fourth (chair utilisation, productivity) needs new queries but not new tables (appointment duration × chair count is derivable from existing `appointments` data).

---

### 10. Standard Operating Procedures — 🟡

Two very different things share the "SOP" label in DBM's brief, and they're at different maturity here. Recurring staff duties (open/close checklists, decontamination, admin) are **built and live** via Practice Protocols (see #8) — this half of the concept is closer to done than tracked. Event-triggered *per-case* workflows (new patient arrival → reception checklist; implant case → surgery checklist; lab case → lab tracking) are **not generalized** — the one place this pattern genuinely works today is the Lab module, where `LabCase::transition()` auto-creates the next task and closes the previous one on every status change. That's a real, working proof of concept for exactly the pattern DBM describes; it just hasn't been extended past Lab. Sterilization tracking and implant surgery checklists don't exist (the "implant" model found is inventory/lot tracking only, no procedure checklist).

**Evolve, don't rebuild:** Generalize the Lab status-machine pattern (auto-create-next-task-on-event) into a small reusable trait/service any module can attach to (new-patient intake, implant case start), rather than building the previously-scoped `JobLibrary` concept from scratch (`docs/plan-job-library-sops.md` is still a pure design doc, zero code) or turning on the dormant `WorkflowEngine`, which solves a related but heavier problem (multi-visit clinical staging with branching/looping) than a linear checklist needs.

---

### 11. AI Coaching — 🔴

Confirmed absent. `HuddleService` has an explicit code comment stating it does no AI reasoning, only aggregation. The dashboard's alert strip is rule-based threshold checking (overdue lab, no-shows today), which is useful but not "coaching" — it doesn't generate an insight like "recall conversion is dropping this month vs. last."

**Recommendation — sequence this after #7 and #9, not before.** Coaching output is only as good as the diagnostic tools underneath it (Concept 7) and the metrics it's coaching against (Concept 9). Building a coaching layer before those exist would produce generic, non-actionable nudges — exactly the kind of "technically interesting but not something a clinic would pay for" feature the project's product-thinking mandate warns against. Once #7's diagnostic tools exist, a weekly scheduled command that runs them and pushes a 3-bullet WhatsApp/dashboard digest is genuinely cheap — reuses the existing scheduled-command pattern and WhatsApp send pipeline.

---

### 12. Marketing Content System — 🔴

No AI content generation exists anywhere; `FestivalDate.suggested_content_type` is a static seeded label, not generated. `docs/plan-prm-ai.md` confirms this was designed but explicitly marked unstarted.

**Product-thinking verdict: low priority, possibly skip entirely.** AI blog/Instagram/GBP post drafting is now a commodity — every marketing tool and half of ChatGPT wrappers already do this, so it's not a differentiator that makes a dentist pay Dentfluence specifically. It also doesn't touch clinical workflow, revenue, or patient experience — the four questions the project instructions say every feature must answer. If it's built at all, it belongs in Phase 4, gated on evidence that clinics actually want it (ask 3–5 pilot clinics before writing a line of code).

---

### 13. Referral Network — 🔴

`patients` has `referrer_type` (Doctor/Friend/Family/Staff/Corporate/Other), `referrer_name`, `referrer_mobile` as free text, plus a `ReferralReward` model that's a wallet-credit ledger for patient-refers-patient only. There is no entity representing a referring doctor, specialist, school, or company that persists across patients or tracks volume/value over time — every referral is re-typed from scratch per patient with no aggregation possible.

**Evolve, don't rebuild:** Promote the existing free-text fields into a lightweight `ReferralSource` model (name, type: doctor/specialist/school/company/society, contact info) with a foreign key from `patients.referral_source_id`, and a simple rollup view (patients referred, revenue generated, last referral date) per source. This is a small, high-leverage change — it turns an untrackable text field into the referral-value reporting DBM explicitly asks for, and it's the same FK-instead-of-free-text pattern that would also power Community Outreach attribution (#5).

---

### 14. Membership System — 🟡 (closest to done of all 15)

Plans, enrollments, family/dependent linkage, a real per-use benefit audit trail (`MembershipBenefitLog`), and FY revenue rollups are all built and live. The only real gaps: renewal reminders are pull-only (they appear on the Action Board but nothing sends a WhatsApp/SMS/email), no cron job confirmed for `FinancePatientMembership::expireStale()`, and there's no upsell-opportunity tracking (e.g., flagging a family plan candidate).

**Evolve:** Wire the already-computed `membershipRenewals()` feed into the same outbound-send pattern used for recall/reviews (see #6) — this is the single highest-ROI, lowest-effort item in the whole list, because every piece except the "press send" step already exists.

---

### 15. Decision Support ("Ask the AI") — 🟡

Tulip already does real decision support for lookup-style questions — "what's my no-show rate this week," "what's pending for today," "who owes money" — via genuine DB queries, not hallucination, with a safety confirm-gate on any write action. It cannot yet answer causal or prioritization questions ("why are implants down," "what should reception do next") because no tool exists that reasons across multiple data sources — this is the same gap as Concept 7, and the fix is the same: add diagnostic tools to the existing agent loop, don't build a second AI system.

---

## Section C — What NOT to build (explicit scope cuts)

Per the project's product-thinking mandate, these DBM ideas should be deliberately deprioritized or reshaped rather than built as described:

A **standalone Community Outreach module** — fold into Campaign (#5). A dedicated **outreach/events table set with its own dashboard** would be used a handful of times a year; not worth the surface area.

**ML-based acceptance-probability prediction** — with current single-clinic data volume there isn't enough signal to train anything trustworthy, and an unexplainable score erodes staff trust faster than no score at all. Use a transparent weighted heuristic instead, and only revisit real ML once Dentfluence has multi-clinic SaaS-scale data.

**AI Marketing Content System** — commodity capability, doesn't touch the four value questions (time saved / mistakes reduced / patient experience / revenue), and is already available generically outside Dentfluence. Validate demand with pilot clinics before building.

**Call-quality scoring** — genuinely blocked on infrastructure that doesn't exist (call recording/telephony integration). Don't scope this until that infra decision is made; it may be better served by the existing local Whisper voice-note pipeline once/if call recording is added, rather than a new system.

**A second scoring/"trust score" system for PRM** — the InsightsEngine already models this (Health/LTV/Risk); turning it on is strictly cheaper and more consistent than a bespoke DBM-flavored score.

**Generic "Automation Engine" activation as a blanket flag flip** — `config/features.php`'s `automation.engine` flag has zero callers today; before flipping it on, decide which specific triggers it should own (start with the one concrete win in Phase 1 — the Treatment Plan → Opportunity hook) rather than turning on a generic scheduler with nothing wired to it, which is how it ended up dormant the first time.

---

## Section D — Roadmap

### Phase 1 — Quick Wins (2–4 weeks, mostly wiring + small schema, no new modules)

1. Wire `TreatmentPlan::accept()` → auto-create `TreatmentOpportunity` at stage `prospect` with default follow-up + coordinator notification (one observer, closes most of Concept 2).
2. Add `decline_reason_category` enum next to existing `declined_reason` (objection tracking, Concept 2/3).
3. Turn on `insights.signals` flag and surface Health/LTV/Risk on patient profile + Today's Actions (Concept 3, zero new schema).
4. Wire membership renewal WhatsApp send using the existing recall/review send pattern (Concept 6/14 — highest ROI-per-effort item on this list).
5. Merge the two Huddle implementations into one; add Google-review-requests-due and a revenue-target/actual line (Concept 1).
6. Document and surface Practice Protocols on the Action Board / staff view instead of only in the disconnected Huddle report (Concept 8/10 — also: log this module into project memory, it's currently untracked).
7. Confirm/add the missing `expireStale()` cron for memberships.

### Phase 2 — High Impact Automation (1–3 months)

1. Generalize the Lab module's auto-next-task-on-status-change pattern into a small reusable service; apply it to new-patient intake and implant-case-start (Concept 10).
2. Promote `patients.referrer_*` free text into a `ReferralSource` model with FK + ROI rollup; reuse for Community Outreach attribution (Concepts 5, 13).
3. Extend `Campaign` model with `campaign_type` covering reactivation/birthday/outreach/school/corporate; link `RecallEngineService` sends to a campaign_id so they count in one ROI dashboard (Concepts 4, 6).
4. Build one consolidated Practice Performance dashboard pulling from the four existing metric sources, plus net-new: revenue-vs-target, chair utilisation, doctor/front-desk productivity, cancellation rate % (Concept 9).
5. Pull GBP review/rating performance read-back through the existing `GoogleConnector` (Concept 4).

### Phase 3 — AI Practice Manager (3–6 months)

1. Add diagnostic tools to Tulip's existing `ToolRegistry`: `RevenueTrendTool`, `ChairUtilizationTool`, `StaffPerformanceTool` (Concepts 7, 15).
2. Build the weekly AI-coaching digest as a scheduled command reusing the diagnostic tools + existing WhatsApp/dashboard send pipeline (Concept 11).
3. Surface cancellation/no-show risk from the now-live InsightsEngine signals directly on Today's Actions ("patients likely to cancel") (Concepts 3, 7).
4. Aggregate the Phase 1 objection-category data into a "why patients decline" report (Concept 2).

### Phase 4 — Autonomous Dental Practice (6–12+ months, gate each item on evidence, not assumption)

1. Weighted-heuristic acceptance-probability scoring (not ML) on Opportunities, calibrated against Phase 3's objection/conversion data.
2. AI Marketing Content drafting — only after validating demand with pilot clinics.
3. Call-quality scoring — only after a telephony/call-recording decision is made; reuse the existing local Whisper pipeline if so.
4. Expand autonomous (no-confirm-card) execution only for genuinely low-risk actions (this already exists for recall/review sends) — keep the confirm-gate permanently for anything clinical or financial, both for DPDP compliance and staff trust.

---

## Section E — What to tell the DBM framework, honestly

Dentfluence is closer to the "AI Practice Operating System" vision than the raw ✅/🟡/🔴 count suggests, because the pattern across nearly every 🟡 is "the hard part (data model, aggregation, agent infrastructure) is done; the easy part (a caller, a send step, a UI surface) is missing." That is a much better position than it would be if the gaps were architectural. The two genuinely 🔴 concepts that matter commercially — Community Outreach and Referral Network — are both solvable as small extensions of models that already exist (Campaign, patients.referrer_*), not new modules. The two that are 🔴 and arguably *shouldn't* be built soon — AI Coaching and AI Content — both depend on diagnostic capability that doesn't exist yet, so building them now would produce something generic and unconvincing rather than genuinely useful.
