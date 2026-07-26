# Patient Journey V1.1 — Phase 2: Clinical Truth (Gate B)

**Date:** 2026-07-26 · **Status:** ANALYSIS ONLY — no implementation, no migration, no production data change.
**Comparison point:** Phase 0 frozen `296eb74`; Phase 1 local (ten commits, unpushed).
**Sources:** code inspection of the tables/services listed below + the Slice 0.2 production census.

---

## 1. Current production / code truth

### What already exists and is trustworthy

| Concept | Where it lives | Reliability |
|---|---|---|
| Consultation happened | `consultations` (status draft/completed, `consultation_date`) | ✅ reliable post-go-live (23 rows) |
| Plan created | `treatment_plans` (status **pending**/ongoing/completed/cancelled) | ✅ reliable |
| Plan accepted | `treatment_plans.accepted_at` + status→`ongoing`, written **only** by `TreatmentPlanAcceptanceService` (transactional, logs `treatment_plan.accepted`, syncs one Opportunity) | ✅ reliable, single door, revert-guarded |
| Plan presented | **No column.** Inferred from the existence of a linked `TreatmentOpportunity` at stage `quoted`, created by `TreatmentPlanOpportunitySync` when `markPresented` is called | ⚠️ indirect — presentation is stored as *sales* state, not clinical state |
| Per-item state | `treatment_plan_items.status` (pending/ongoing/completed/cancelled) + `billing_progress` + `invoiced_units` | ✅ exists, under-used |
| Treatment performed | `treatment_visits` (status scheduled/in_chair/completed/cancelled/no_show, `visit_date`, `treatment_plan_id`) | ✅ strongest post-go-live evidence (45 rows / 30 patients) |
| Plan completion | `TreatmentVisitService::completePlanAndQueueRecall()` sets plan → `completed` | ⚠️ see §7 — completion is triggered by a visit flag, not by item state |
| Sales/opportunity state | `treatment_opportunities.status` enum: prospect / discussed / quoted / accepted / declined / completed | ✅ reliable but **conflates** clinical decision with sales stage |
| History | `activities` ledger (`treatment_plan.created/accepted`, `consultation.completed`, …) surfaced by `UnifiedTimelineService` → `PatientJourneyService` (canonical read model) | ✅ good spine, event set incomplete |

### What is missing

1. **No "presented" clinical fact.** Presentation exists only as an Opportunity stage. A plan can be discussed with a patient without any clinical record saying so.
2. **No patient decision record.** `accepted_at` is the only decision that can be expressed. There is no *pending*, *deferred*, *rejected*, or *partially accepted* — and no reason, no date, no actor, no "defer until".
3. **No "no treatment needed" disposition.** A healthy consulted patient is indistinguishable from one whose plan was never written (census: 4 such patients).
4. **No canonical "treatment started" fact.** Acceptance sets status `ongoing` immediately, which conflates *agreed* with *begun*.
5. **`last_visit_date` is dead** — NULL for 3,538/3,538, no writer (Gate A).

### What is unreliable

- **Appointment status.** 0 `done` ever; 143 past appointments still `scheduled`; 5 stuck at `checkin`. Appointments **cannot** establish historical attendance today.
- **Plan status `ongoing`** means "accepted", not "in progress" — set at acceptance before any clinical work.
- **Opportunity `completed`** means "sale converted", not "treatment finished" — a genuinely confusing overload (`TreatmentPlanOpportunitySync` maps accepted→completed).

---

## 2. Proposed canonical concepts

Notation — **E** = event (immutable, historical), **S** = state (current, derived unless noted).

| Concept | Precise meaning | Source of truth | E/S | Stored or derived | Confidence today |
|---|---|---|---|---|---|
| **Consulted** | A consultation record exists with status `completed` | `consultations` | E | stored (exists) | High |
| **Plan Created** | A plan exists for the patient | `treatment_plans` | E | stored (exists) | High |
| **Plan Presented** | The plan was actually explained to the patient on a date | **NEW** `presented_at` on plan + `plan.presented` activity event | E | stored (new, minimal) | None today |
| **Decision Pending** | Presented, and no decision record exists | derived: presented_at ≠ null AND no decision row | S | derived | None today |
| **Accepted** | Patient agreed to proceed with the plan (or all of it) | **NEW** `plan_decisions` row `accepted` (keeps `accepted_at` in sync for compatibility) | E→S | stored event, derived state | High (as `accepted_at`) |
| **Partially Accepted** | Patient accepted some items, not others | derived from **existing** `treatment_plan_items.status` (accepted items ongoing/…; declined items `cancelled`) + a plan-level decision of `partially_accepted` | E→S | derived from existing item rows | Structurally possible today, semantically unused |
| **Deferred** | Interested, intentionally postponed, optionally until a date | **NEW** decision `deferred` + `defer_until` | E→S | stored | None today |
| **Rejected** | Patient explicitly declined | **NEW** decision `rejected` + reason | E→S | stored | Only as opportunity `declined` (sales, not clinical) |
| **Treatment Started** | First `treatment_visits` row for that plan reaching `in_chair` or `completed` | `treatment_visits` | E | derived (existing data) | High post-go-live |
| **Treatment Ongoing** | Started AND at least one plan item still `pending`/`ongoing` | items + visits | S | derived | High |
| **Treatment Completed** (plan) | Every non-cancelled item of the plan is `completed` | `treatment_plan_items.status` | S | derived (today: flag-written) | Medium — see §7 |
| **No Active Treatment** (patient) | No plan in accepted-and-unfinished state | across plans | S | derived | Medium |
| **No Treatment Needed** | Doctor's explicit clinical disposition after consultation | **NEW** consultation disposition (or a `no_treatment_needed` decision on a zero-item plan) | E | stored (new, minimal) | None today |
| **Future Appointment** | `appointment_date` ≥ today AND status ∈ {scheduled, checkin, in_chair} | `appointments` | S | derived | High going forward; historical attendance unusable |
| **Recall Eligible** | See §8 | derived | S | derived | Blocked on Gate A |

**Lost / Unreachable is NOT clinical truth.** A patient who stops answering has an unchanged clinical state (typically Decision Pending). "Unreachable/Lost" belongs to PRE/opportunity state. Keeping them separate is what prevents the system from telling a doctor "patient rejected treatment" when the patient merely went quiet.

---

## 3. Treatment plan decision model

**Recommended: `presented` is a plan lifecycle event; `decision` is a separate append-only record.** (This is the recommendation the architecture proposal already carried into Gate B.)

```
plan created ──► presented (event, repeatable) ──► decision (append-only)
                    │                                 ├─ accepted
                    │                                 ├─ partially_accepted (+ per-item cancels)
                    │                                 ├─ deferred (+ defer_until, reason)
                    │                                 ├─ rejected (+ reason)
                    │                                 └─ no_treatment_needed
                    └─ may be presented again (re-presentation is a new event)
```

Rules:
- **Presented ≠ decided.** Presented + no decision row = **Decision Pending**. This is a first-class, legitimate, long-lived state.
- **Never infer.** Not-accepted ≠ rejected; presented ≠ accepted; paid ≠ started; no future appointment ≠ finished.
- **Decisions are append-only.** A patient may reject in July and accept in October — both survive; current state = latest decision.
- **`accepted_at` stays** on the plan as a compatibility mirror of the latest `accepted` decision, so every existing consumer (billing guard, opportunity sync, mobile) keeps working untouched.
- **Partial acceptance needs no new item table** — `treatment_plan_items.status` already carries pending/ongoing/completed/**cancelled** per item. Partial acceptance = plan decision `partially_accepted` + declined items set to `cancelled`. **This is the strongest reuse finding in this audit.**

---

## 4. Patient vs plan vs item — where each truth belongs

| Level | What it owns | Example |
|---|---|---|
| **Item** (`treatment_plan_items`) | Clinical progress of one procedure: pending → ongoing → completed, or cancelled if declined | Implant on 46 deferred; RCT on 26 completed |
| **Plan** (`treatment_plans` + decisions) | Presentation, patient decision, plan-level completion | Plan B accepted 28 Jul |
| **Patient** | **Nothing stored.** A *derived summary* over that patient's plans/items/visits/appointments | "1 ongoing, 1 deferred, recall due Nov" |

**Journey state therefore lives per plan and per item; the patient-level view is a derived summary — never a stored column.** This is precisely why `patients.journey_status` must not be created: Sushil is simultaneously ongoing (RCT), deferred (implant) and completed (scaling), and any single column would lie about two of the three.

---

## 5. Last visit semantics (Gate A input)

**Recommendation: derive, then cache as a rebuildable projection — never hand-maintain.**

- **Canonical definition:** the most recent *clinical encounter that actually happened* = `MAX(treatment_visits.visit_date WHERE status = completed, consultations.consultation_date WHERE status = completed)`.
- **Appointments excluded** until appointment lifecycle is trustworthy (0 `done` in production). Once appointments are reliable, `done` appointments join the definition without changing consumers.
- **Storage:** keep the `patients.last_visit_date` column as a **cache** written by a projector on the same clinical events, rebuildable from source at any time by command. Reason: the recall engine scans ~3,500 patients nightly; a derived-per-query MAX across two tables is fine for one patient but wasteful for a full scan, and the column already exists and is already read by several consumers.
- **Non-negotiable:** the cache must never be the source of truth, must be rebuildable, and must have a parity check (the pattern Phase 0 already uses for `today_actions`).
- **No production backfill in Gate B.** Backfill is its own CEO-gated slice, and for 3,538 imported patients with no digital history it will legitimately produce NULL — which then must **not** be read as "recall everybody" (the exact bug that created the 1,810-item backlog).

---

## 6. Appointment truth requirement

Journey state needs appointments for exactly **one** question in V1.1: *does this patient have a future appointment?* That is answerable **today** and reliably: `appointment_date ≥ today AND status ∈ {scheduled, checkin, in_chair}`. Cancelled and no_show are excluded by `AppointmentStatus::terminalValues()`, which already exists.

What is **not** answerable and must not be used: historical attendance ("did they come?"). 143 past appointments sit at `scheduled` because nobody closes them. Until the appointment lifecycle is fixed (a Phase 4 item, not this phase), **attendance evidence comes from treatment_visits and consultations only**.

Minimum requirement before Journey State can be fully trusted: appointments must be *closed* — either by staff workflow (Done/No-show at checkout) or an automatic end-of-day sweep. Recommend surfacing it as a daily "unclosed appointments" prompt rather than silently mutating history. **Not in Phase 2 scope; flagged as a dependency.**

---

## 7. Treatment start / completion semantics

**Started** = the first `treatment_visits` row for the plan reaching `in_chair` or `completed`. Not acceptance (agreeing isn't doing), not payment (paying isn't doing), not an appointment being booked.

**Plan completed** = every non-cancelled item of that plan is `completed`. Today, completion is instead *asserted* by `TreatmentVisitService::completePlanAndQueueRecall()` when a visit is flagged as finishing the plan — which can mark a plan complete while items remain pending. Recommendation: keep the write (compatibility) but make the derived rule authoritative, and add a parity check to expose disagreement rather than silently trusting the flag.

**Patient has no active treatment** = no plan in `accepted-and-not-completed` state. Distinct from plan completion: Sushil can finish his RCT plan while his implant plan remains deferred, so the patient is *not* "done".

---

## 8. Recall eligibility (concept only)

Two distinct doors, both currently missing:

**Post-treatment recall:** treatment completed → no active treatment → no future appointment → recall interval elapsed since last visit → recall obligation.

**Preventive recall:** patient with *no* plan at all (or a `no_treatment_needed` disposition) → last visit ≥ interval → recall obligation. This is why recall cannot be built solely on treatment completion.

**Hard rule for both:** eligibility keys off **last visit**, never off record-creation date, and NULL last-visit means *unknown* → do **not** recall (log for review). That single rule is what prevents a repeat of the 1,810 mass-queue.

---

## 9. PRE mapping — clinical truth → future human obligation

| Clinical truth | PRE obligation | Today's Actions? |
|---|---|---|
| Presented + Decision Pending | Follow up on the estimate | Yes — after a grace period, with the plan value and what was discussed |
| Deferred (until date) | Nothing until the date; then re-open | Only on/after `defer_until` |
| Rejected | **No chasing.** Optional long-horizon preventive recall only | No |
| Accepted + no visit yet + no future appointment | Booking obligation ("they said yes — get them in") | Yes, high priority |
| Ongoing + no future appointment | **Treatment recovery** — the census's 7/7 cohort | Yes, highest priority |
| Ongoing + future appointment | Nothing (system is working) | No |
| Plan completed + no active treatment + interval reached | Recall | Yes, at the due date |
| No treatment needed | Preventive recall only | No |
| Unreachable (PRE state, clinical unchanged) | Attempt cadence, then close as unreachable — clinical state stays Decision Pending | Yes, until attempts exhausted |

**PRE consumes; it never authors clinical truth.** Nothing in this table writes to a clinical table.

---

## 10. Sushil Patil — required scenarios

| # | Situation | Clinical truth | Patient journey summary | PRE obligation | On Today's Actions? | Historical events that must survive |
|---|---|---|---|---|---|---|
| **A** | Lead → booked → consulted → plan presented → "let me think" | Consulted ✓; Plan A created + **presented 26 Jul**; decision **pending** | "Estimate given — awaiting decision" | Estimate follow-up after grace period | **Yes** — with ₹45,000 and what was discussed | lead.created, appointment.booked, consultation.completed, **plan.presented** |
| **B** | Accepts, treatment not started | Decision **accepted 28 Jul**; no visit yet | "Accepted — not yet started" | Booking obligation | **Yes**, high | + **plan.decision.accepted** |
| **C** | Started, next appointment booked | First visit in_chair 30 Jul; items ongoing; future appointment exists | "Treatment ongoing — next visit 5 Aug" | None | **No** | + treatment.started, appointment.booked |
| **D** | Started, **no** next appointment | Started; items ongoing; **no future appointment** | "Treatment ongoing — NO next visit" ⚠ | **Treatment recovery** | **Yes, highest** | unchanged |
| **E** | RCT+crown done, implant deferred | Items: RCT completed, crown completed, implant **deferred until Nov** | "Partially completed — implant deferred to Nov" | Nothing until Nov, then re-open | **No** now; **yes** in Nov | + item completions, **decision.deferred(defer_until)** |
| **F** | Everything done | All items completed; no active treatment; last visit = today | "Treatment completed — recall Feb" | Recall at interval | **No** now; **yes** at recall | + treatment.completed |
| **G** | Explicitly rejects | Decision **rejected 26 Jul** (+ reason) | "Declined — no active treatment" | **No chasing**; preventive recall only | **No** | + **plan.decision.rejected** |
| **H** | Never responds after presentation | **Still Decision Pending** — clinically nothing changed | "Estimate given — unreachable" | Attempt cadence → close as unreachable (PRE state only) | Yes until attempts exhausted | plan.presented + each contact attempt + PRE closure |

The G vs H distinction is the whole point of separating clinical truth from PRE state: in G the doctor knows the patient said no; in H nobody knows anything, and the record must not claim they did.

---

## 11. Existing schema reuse — what stays untouched

`treatment_plans` (all columns incl. `accepted_at`, `status`) · `treatment_plan_items` (**status carries partial acceptance and per-item completion already**) · `treatment_visits` · `consultations` · `appointments` · `treatment_opportunities` (remains the *sales* view) · `activities` ledger + `UnifiedTimelineService` + `PatientJourneyService` (the journey read model already exists and is canonical) · `TreatmentPlanAcceptanceService` (single acceptance door — extend, don't replace) · `patients.last_visit_date` (repurposed as a rebuildable cache).

## 12. Minimum schema changes (proposed only — NOT implemented)

1. **`treatment_plans.presented_at`** (nullable timestamp) — makes presentation a clinical fact rather than a sales side-effect.
2. **`plan_decisions`** (append-only): `plan_id`, `decision` (accepted | partially_accepted | deferred | rejected | no_treatment_needed), `decided_at`, `recorded_by`, `reason`, `defer_until`, `source` (clinic | presentation | mobile).
3. *(Only if you accept it as clinical rather than plan-level)* a consultation-level `no_treatment_needed` disposition — otherwise it is expressible as a decision on a zero-item plan.

That's it. **No `patients.journey_status`. No new journey table. No item-level decision table** (items already carry status).

## 13. Proposed Phase 2 slices

| Slice | Objective | Touches | Migration | Tests | CEO manual check | Rollback |
|---|---|---|---|---|---|---|
| **2.1** | Characterize current clinical truth (tests only): what accepted/ongoing/completed mean today, item-status behaviour, visit→plan completion flag | `tests/Feature/Clinical/*` | none | the deliverable | none | n/a |
| **2.2** | `presented_at` + `plan.presented` event; `markPresented` writes clinical fact as well as opportunity stage | TreatmentPlanController, sync service, plan model | additive column | presented ≠ accepted; re-presentation appends | Present a plan → profile shows "Presented 26 Jul", decision still pending | drop column, revert |
| **2.3** | `plan_decisions` + `PlanDecisionService` (3 verbs) — accept routes through it, `accepted_at` mirrored | new table + service; acceptance service extended | additive table | append-only; latest wins; accepted_at stays in sync; reject/defer don't touch items | Record accept / defer-until / reject; check timeline shows all three | drop table; acceptance path unchanged |
| **2.4** | Partial acceptance via existing item statuses (declined items → cancelled) + derived plan state | PlanDecisionService, item writes | **none** | partial accept cancels only declined items; plan totals unaffected | Accept RCT+crown, defer implant; verify plan reads "partially accepted" | revert commit |
| **2.5** | Derived read model: `ClinicalJourneyService` (per plan + patient summary), web + API, plus parity check against today's flag-written completion | new service; consumed by profile + API | none | started/ongoing/completed rules; 7/7 no-future-appointment cohort detected read-only | Open Sushil-like patients; verify summary matches reality | revert commit |
| **2.6** | `last_visit_date` as rebuildable projection + `journey:parity` command (**no backfill**) | projector, command | none | rebuild idempotent; parity detects drift | Run command; spot-check 5 patients | delete projector; column unchanged |

Backfill of production `last_visit_date`, recall redesign, treatment-recovery automation, and appointment-lifecycle repair remain **out of Phase 2** and individually CEO-gated.

## 14. Risks / ambiguities

Opportunity semantics will double up with plan decisions (accepted→`completed` sales stage vs clinical accepted) — 2.3 must define which is authoritative for the pipeline UI, or the Opportunity board will disagree with the clinical record. Item-level status is currently written by billing/visit flows, so 2.4 must not let a billing action look like a patient decision. Historical plans (27 rows) have no presentation or decision data and will read as "created, never presented" — accurate, but staff may read it as data loss unless labelled. Derived completion may disagree with today's flag-written `completed` on existing rows — 2.5's parity check will expose it; correcting it is a data decision for you, not a code fix.

## 15. CEO decisions required

1. **Presented = lifecycle event, decision = separate append-only record?** (recommended)
2. **Partial acceptance via existing item statuses** rather than any new structure? (recommended — zero schema cost)
3. **Deferred carries `defer_until`,** and PRE stays silent until that date?
4. **Rejected means no chasing** — preventive recall only?
5. **`no_treatment_needed`** — a clinical disposition worth storing, or acceptable as a decision on an empty plan?
6. **`last_visit_date` = rebuildable cache** over treatment_visits + consultations, appointments excluded until reliable? (recommended)
7. **Plan completion authority** — derived from item statuses, with today's flag kept only as a compatibility write?
