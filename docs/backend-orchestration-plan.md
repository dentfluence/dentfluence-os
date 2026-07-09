# Backend Orchestration & Event Wiring — Action Map

**Date:** 2026-07-09
**Scope:** Task 1 & 2 from Sumit's Phase Next instructions — for every existing user-facing business action, map which already-built backend machinery should run automatically after it. No code in this document. No UI referenced or touched anywhere below.

This supersedes nothing in `docs/event-map-blueprint.md` — it sharpens two of its numbers (see §0) and reframes the findings against the 13 specific actions requested, using the exact reusable services found in the codebase this pass.

---

## 0. Two corrections to the prior audit, found while grounding this table

**Dead-rule count was undercounted.** The Event Map blueprint said "5 dead RulesEngine rules." Reading the full `config/relationship_rules.php` (11 rules total, not the subset checked before) and grepping every trigger string against `app/`: **10 of the 11 configured rules are dead** — keyed to an event string that is never dispatched anywhere in the codebase. Only `appointment_reminder` (trigger `appointment.booked`) has a trigger that's actually live — and that one rule is the one already manually disabled (metadata-key bug, documented in the config file's own comment). In other words: **every single rule that is currently `enabled => true` in the admin Settings screen is inert.** None of them do anything today. This isn't a design problem — the rule definitions and their `action_config` (task titles, priorities, cooldowns) are well-formed and ready — it's purely that nothing calls `ActivityEngine::log()` with the matching event name.

**New bug found: `reviews:request` never matches any appointment.** `App\Console\Commands\ReviewsRequest.php` queries `Appointment::where('status', 'completed')` to find yesterday's finished visits. But `AppointmentController::updateStatus()`'s validation rule is `in:scheduled,checkin,in_chair,checkout,done,cancelled,no_show` — the real "finished" value is `'done'`, not `'completed'`. There is no status value `'completed'` anywhere in the appointment lifecycle. This means the daily `reviews:request` command (scheduled 11:00, gated by `REVIEWS_ENABLED`+`WHATSAPP_ENABLED`) has matched **zero appointments, ever**, independent of those flags. This is a one-line string fix, not a wiring change — flagged separately in §2.13 as a bug fix, not an orchestration slice.

**Confirmed a strong reuse pattern already exists and is production-wired:** `RulesEngine`'s `create_task` action already calls `TaskEngine::autoCreate()` (`app/Services/Relationship/TaskEngine.php`), which has its own dedup guard (won't create a second open task with the same relationship+category+title+due-date). `create_reminder` calls `ReminderEngine::createReminder()`. A `notify`/`create_task_and_notify` action type also exists and would call `NotificationEngine::notify()` (`app/Services/Relationship/NotificationEngine.php`, itself dedup-guarded with a 24h minimal-noise rule) — though no currently-configured rule uses that action type yet. **All of this is exactly the machinery Sumit asked to reuse. It is fully built, tested, and idle — waiting on producers.**

---

## 1. How to read the table

For each action: **Current Behavior** (what happens today, verified) → **Existing Service to Auto-Execute** (what already exists that should run) → **RulesEngine Rule(s) Activated** (which of the 11 configs would now fire, and what they do) → **ActivityEngine Event to Record** (the one new thing each slice adds) → **Tasks Created** → **Notifications** → **Dashboards/Analytics Refreshed** → **Duplicate-Risk Check** (explicit, since "no duplicate tasks" is non-negotiable).

Nothing below adds a new table, a new engine, or a new UI surface. Every "Tasks Created" cell is an existing rule's existing `action_config` already sitting in `config/relationship_rules.php`.

---

## 2. The 13 Actions

### 2.1 Patient Registration
- **Current behavior:** `PatientService::createFromInput()` → `Patient::create()` → `PatientRelationshipLinker::link()` (runs by default, `identity.link_patient` defaults `true`) → publishes `PatientRegistered` via `DomainEventBus`. Nobody subscribes to it.
- **Existing service to reuse:** none apply automatically — there is no RulesEngine rule keyed to a registration event today.
- **RulesEngine rule activated:** none exist. Adding a subscriber here would require a **new** rule config entry, which is a product decision (what should happen on registration?), not a wiring fix. Flagging, not proposing.
- **Recommendation:** lowest priority of the 13. Safe minimal step, if Sumit wants the door open later, is adding `ActivityEngine::log($patient, 'patient.registered', ...)` alongside the existing `PatientRegistered` publish — purely additive, creates zero Tasks/Notifications by itself since no rule matches it yet.
- **Duplicate risk:** none — nothing reacts to this today.

### 2.2 Appointment Booking
- **Current behavior:** `AppointmentController::store()` → `Appointment::create()` → `AppointmentActivityLogger::booked()` → `ActivityEngine::log('appointment.booked')` — **already live**, this is the one rule trigger in the whole config that actually fires.
- **Existing service to reuse:** `appointment_reminder` rule already matches this trigger.
- **RulesEngine rule activated:** `appointment_reminder` — but it is deliberately disabled (own comment in config explains why: wrong metadata key would compute "1 day before" as yesterday, and it risks duplicating the existing `relationship:appointment-reminders` bulk job / `whatsapp:send-reminders`).
- **Recommendation:** do **not** enable this rule as part of orchestration wiring — a bulk reminder job already covers this ground daily. Enabling it would violate the "no duplicate tasks" constraint. Safe action here is none, or (cleanup-only, zero behavior change) removing the dead config entry to stop it looking "on" in Settings when it silently isn't.
- **Duplicate risk:** HIGH if enabled — this is the one action where doing nothing is correct.

### 2.3 Consultation
- **Current behavior:** `ConsultationController::store()` family → `Consultation::create()`. No `ActivityEngine::log()` call anywhere in the controller.
- **Existing service to reuse:** none — no rule in the config targets a consultation event.
- **Recommendation:** additive-only `ActivityEngine::log($consultation, 'consultation.completed', ...)`. Creates zero Tasks (no matching rule) — its only effect is that the event now exists in the `Activity`/`ActivityRecorded` stream for Insights (once that flag is on) and for a future rule if Sumit ever wants one.
- **Duplicate risk:** none.

### 2.4 Treatment Plan Creation
- **Current behavior:** `TreatmentPlanController::store()` → `TreatmentPlan::create()` + `syncItems()`. No log call.
- **Recommendation:** same as 2.3 — additive `treatment_plan.created` log, no rule currently matches, zero Task/Notification side effects, feeds Insights only.
- **Duplicate risk:** none.

### 2.5 Treatment Plan Acceptance — highest-value slice
- **Current behavior:** `TreatmentPlanController::accept()` → `$plan->update(['accepted_at'=>now(),'status'=>'ongoing'])`. Nothing else. `TreatmentOpportunity` model has a `treatment_plan_id` FK built for exactly this link, never populated.
- **Existing service to reuse:** `TreatmentOpportunity::create()` (already used identically in 4 other places, e.g. `OpportunityPipelineController`) + the **already-enabled** `opportunity_nudge_7d` rule (trigger `opportunity.created`, condition `stage=prospect`) → `TaskEngine::autoCreate()`.
- **RulesEngine rule activated:** `opportunity_nudge_7d` → creates "Opportunity follow-up — no appointment yet" call task, medium priority, 7 days out, 14-day cooldown, dedup-guarded by `TaskEngine`.
- **ActivityEngine events to record:** `treatment_plan.accepted` on the plan, `opportunity.created` on the newly-created opportunity (same pattern `OpportunityPipelineController::sendToLead()` already uses for `opportunity.sent_to_lead` — literally copy that call shape).
- **Dashboards/Analytics refreshed:** Action Board's existing `opportunities` category (`TodayActionsEngine`) already reads `TreatmentOpportunity` directly — it will show this new row automatically, no Action Board code changes needed. Insights LTV signal (once flag on) gets a real acceptance-probability input for the first time.
- **Duplicate risk:** none — this Opportunity does not exist today in this flow, nothing to collide with.
- **Why this is the standout candidate:** matches the brief's own top example chain, closes the single highest-value gap from the earlier DBM gap analysis, and every piece of machinery it needs is already live elsewhere in the same controller family.

### 2.6 Appointment Completion
- **Current behavior:** `updateStatus()`, `status==='done'` → `ActivityEngine::log('appointment.completed')` — **already live**.
- **RulesEngine rule activated:** none — no rule targets `appointment.completed`. The rules that look like they should (`implant_followup`, `post_treatment_followup`) are keyed to `treatment.completed`, a different, more specific clinical concept (a multi-visit RCT appointment can "complete" without the treatment course being done).
- **Recommendation:** leave as-is. Don't attach a rule here — see 2.7 for where these two rules actually belong.
- **Duplicate risk:** n/a, no action taken.

### 2.7 Treatment Completion
- **Current behavior:** `TreatmentVisitService`'s `mark_treatment_complete` branch → `completePlanAndQueueRecall()` → sets plan `status='completed'` and **already auto-creates** a "6-Month Recall..." Task inline if none exists. No `ActivityEngine::log()` call.
- **Existing service to reuse:** the **already-enabled** `implant_followup` and `post_treatment_followup` rules, both keyed to `treatment.completed` and never fired because nothing dispatches that string. `TaskEngine::autoCreate()` handles both, already dedup-guarded.
- **RulesEngine rules activated:** `implant_followup` (condition `treatment_type=implant`) → "Implant follow-up call," high priority, 7 days out, 90-day cooldown. `post_treatment_followup` (all types) → "Post-treatment follow-up call," medium priority, 3 days out, 30-day cooldown.
- **ActivityEngine event to record:** `treatment.completed`, metadata must include `treatment_type` (implant vs other) for the condition check to evaluate correctly.
- **Duplicate risk — must handle explicitly:** do **not** also dispatch `visit.completed` here even though `recall_6months` is configured for it — that rule would create a second 6-month recall Task duplicating the inline one that already runs in the same method. Recommend the `visit.completed`/`recall_6months` rule config be removed rather than wired, since its job is already done by existing code.
- **Duplicate risk for the two rules being activated:** none — different titles/categories from the existing inline recall task, so all three can coexist safely.

### 2.8 Billing (invoice generated from `BillingPrompt`)
- **Current behavior:** `BillingController::store()` → `Invoice::create()`, fulfilling pending `BillingPrompt` rows. No `ActivityEngine::log()` anywhere in this method.
- **Existing service to reuse:** none — no rule in the config targets an invoice-creation event.
- **Recommendation:** additive `invoice.created` log only. Action Board's `pending_estimates`/`payment_reminders` categories already read `Invoice` directly at request time — no Action Board change needed regardless.
- **Duplicate risk:** none.

### 2.9 Payment
- **Current behavior:** `BillingController::recordPayment()` (does **not** call the shared `InvoicePaymentService` — confirmed by grep, that service is only used by `updatePayment()` in the same controller and by the mobile API controller) → `InvoicePayment::create()` + related finance rows. `'payment.received'` exists only as a doc-comment example anywhere in `app/` — never actually dispatched.
- **Existing service to reuse:** none directly for "received" — no rule targets it. The config does have `payment_overdue_3d` (trigger `payment.overdue`), a related-but-different, purely temporal concept (invoice unpaid 3 days past due) that also has zero dispatch sites anywhere and would need a new small scheduled scan (same shape as the recall/reminder scans that already exist) rather than a real-time hook off Payment Received.
- **Recommendation:** (a) collapse `BillingController::recordPayment()` onto `InvoicePaymentService::recordPayment()` first — it's currently a ~350-line duplicate of logic the mobile API already reuses correctly; (b) add one `payment.received` log call inside that single, now-shared service. No rule fires from it yet, but it's the prerequisite for Insights LTV/Risk signals and any future overdue-scan work.
- **Duplicate risk:** the dedup step (a) is the fix, not a risk to introduce.

### 2.10 Lab Status Update — second high-value, low-risk slice
- **Current behavior:** `LabCaseObserver::updated()` already reacts to every status change — updates a B2B `CommunicationQueue` row and `CommActivityLog`, auto-closes on `final_received/complete/rejected`. This is vendor-facing and works well; leave untouched. It does **not** call `ActivityEngine::log()`, so the patient-facing side is silent.
- **Existing service to reuse:** `lab_ready_call` rule — **already enabled**, trigger `lab.received`, condition `appointment_booked=false` — never fires because nothing dispatches `lab.received`.
- **RulesEngine rule activated:** `lab_ready_call` → "Lab ready — book delivery appointment" call task, high priority, immediate, 7-day cooldown.
- **ActivityEngine event to record:** `lab.received`, fired from inside the same `LabCaseObserver::updated()` hook where the status transition is already detected — reuse the existing observer, don't add a second one. Metadata must include whether the patient currently has an appointment booked, to satisfy the rule's condition.
- **Duplicate risk:** none — this is a genuinely new, currently-missing patient-facing action distinct from the existing vendor-facing comm update.

### 2.11 Membership Enrollment
- **Current behavior:** `MembershipBenefitService::enroll()/enrollWithFinance()` → `FinancePatientMembership::create()` + finance chain. No log call.
- **Existing service to reuse:** none directly — no rule targets `membership.enrolled`.
- **Recommendation:** additive log only, feeds Insights.
- **Duplicate risk:** none.

### 2.12 Recall Generation
- **Current behavior:** already the best-wired action in the Revenue & Retention group. `recall:run` (daily 07:00) → `RecallEngineService::runAll()` → for `no_visit_6months`, delegates to `RecallAutomationRunner` (cut over to `AutomationEngine`), which **already** calls `ActivityEngine::log('recall.queued')`.
- **Recommendation:** no action needed for this half. The other half — `membership.expiring`, which feeds the already-enabled `membership_renewal_30d` rule — has **no producer at all**, not even a scheduled scan. If Sumit wants membership renewal recalls, that needs one new small scheduled command (same shape as `recall:run`, scanning `FinancePatientMembership.end_date`) logging `membership.expiring` per match. This is the one place in the whole map where "new code" is a small scheduled scan rather than a single log-call insertion — flagging for a separate go/no-go rather than bundling it into a "just add a log call" slice.
- **Duplicate risk:** none for either half.

### 2.13 Review Request — bug fix, not orchestration
- **Current behavior:** `reviews:request` (daily 11:00, `REVIEWS_ENABLED`+`WHATSAPP_ENABLED` gated) queries `Appointment::where('status','completed')`. Real value is `'done'`. **Command matches zero appointments today, unconditionally.**
- **Fix:** change the query string from `'completed'` to `'done'`. One line, `app/Console/Commands/ReviewsRequest.php`. Not a wiring change, not a new event — a correctness fix to a feature that already looks fully built (flags, dedup via `Review::where('appointment_id',...)`, DPDP-gated WhatsApp send) but has never actually run.
- **Duplicate risk:** none — fixing this makes a currently-inert feature work for the first time; no other code path sends review requests.

---

## 3. Recommended Slice Order

Ranked by value-to-risk, each independently shippable, none batched:

1. **2.13 Review Request bug fix** — one string, zero architectural risk, unblocks a feature Sumit likely already believes is running.
2. **2.5 Treatment Plan Accepted → Opportunity** — highest business value, fully reuses existing patterns, matches the brief's own top example.
3. **2.7 Treatment Completion → implant/post-treatment follow-up tasks** — two already-enabled rules light up for free; requires care only on the duplicate-risk note (don't also wire `visit.completed`).
4. **2.10 Lab Status Update → book-delivery task** — already-enabled rule, single event call inside an existing observer.
5. **2.9 Payment Received** — requires the dedup step first (collapsing the two `recordPayment` implementations), then one log call. No rule fires yet, but unblocks Insights and any future overdue-payment work.
6. **2.3 / 2.4 / 2.8 / 2.11 Consultation / Treatment Plan Created / Billing / Membership Enrolled** — pure additive logging, zero downstream Task risk, batch these together if Sumit wants (low-stakes, but still one approval).
7. **2.12 Membership Expiring scan** — the one new-scheduled-command item, separate go/no-go from the rest since it's the closest thing to "new code" in this entire plan.
8. **2.2 Appointment Booking** — recommend explicitly closing this one out by removing the dead `appointment_reminder` rule config rather than fixing/enabling it.
9. **2.1 Patient Registration** — lowest priority, no rule exists to attach yet; revisit only if Sumit wants an onboarding-task rule defined first (a product decision, not a wiring task).

Nothing above gets built until you tell me which item to start with. Per your Working Method, the next message on whichever slice you pick will cover: current behavior (already done above), proposed change, why it's safe, and the exact file(s) touched — then I stop and wait.
