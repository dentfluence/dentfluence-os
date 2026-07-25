# Dentfluence — End-to-End Patient Journey Forensic Audit

**Date:** 2026-07-24 · **Mode:** read-only, audit + discussion only. No code changed.
**Baseline:** `docs/patients-v1_1-clinical-care-audit.md` (Patients V1.1 clinical audit) — findings reused, not re-derived.
**Method:** three parallel read-only traces (funnel front: lead→appointment→patient→consultation; funnel back: decision→scheduling→recall→follow-up→reactivation; connection verifier: FKs, parity, tests, inbound routes), then independent spot-verification of the highest-impact claims.

**Spot-verified directly:** `RecallEngineService` "approved plan" trigger queries `status='approved'` (lines 203/206 — a value nothing ever writes → dead); `routes/relationship.php` gated only `['web','auth']` including `convertToPatient` (line 147); `OutcomeAutomationService` consumed only by `Api/V1/RelationshipController` (web Action Board never calls it); `next_visit_date` has zero consumers outside the visit model/validator/formatter.
**Sub-agent claims REFUTED during verification (excluded from findings):** (1) "two competing `Schema::create` migrations for treatment_visits" — false, `2026_05_26_100006` is `Schema::table` (an ALTER with a misleading filename, exactly as the V1.1 audit recorded); (2) "Task category enum narrower than code CATEGORIES → silent truncation" — false, migration `2026_06_18_300001` expands the enum.

---

## A. Actual Journey Diagram

Legend: `→` reliable · `⇢` manual/partial · `✕` broken/disconnected · `◇` derived/illusory

```
[Website / Meta / WhatsApp enquiry]
        ✕  (webhook controllers fully coded, but NO routes registered — dead imports, parked V1.5)
        │
[Lead]  (leads.stage: new_lead→contacted→appointment→plan_given→converted→lost)
        │  → LeadObserver: relationship link, activity ledger, flag-gated auto-assign
        │  ⇢ follow-ups + Today's Actions (new_enquiries / lead_followups)
        │
        ✕  Lead → Appointment: NO FK either direction; stage moved by manual card drag;
        │                      booking never touches the lead; no dedup on manual leads
        ▼
[Appointment] (patient_id NOT NULL — appointment cannot exist without a Patient)
        │  ⇢ Patient minted at: walk-in (always NEW patient, no phone dedup),
        │     lead convert (reuses patient only via shared relationship_id; no lead_id stored),
        │     or pre-existing registration. PatientService::register() invariant HOLDS.
        │  → check-in stamps status+checked_in_at (creates nothing downstream)
        │  → no_show → 'appointment.missed' → RulesEngine → urgent reschedule Task
        │     (SILENTLY SKIPPED if patient has no relationship_id)
        │  ✕ cancelled → no rule listens; nothing generated
        │
        ✕  Appointment → Consultation: appointment_id set ONLY via optional dropdown on the
        │     patient-profile form (web); API never sets it; no "Start Consultation" from the
        │     appointment screen; done-without-consultation never reconciled
        ▼
[Consultation] → [Diagnosis = columns on consultation]        (V1.1 baseline)
        ⇢  "Save & Start Treatment Plan" = redirect + flash id; ALL clinical data re-entered
        ▼
[Treatment Plan]
        │  → markPresented → Opportunity stage 'quoted' … ◇ but follow_up_date NULL →
        │     INVISIBLE on Today's Actions; nudge rule matches stage 'prospect' only → never fires
        │  → Accept: TreatmentPlanAcceptanceService (stored fact) → Opportunity 'completed'
        │  ✕ Reject (in-clinic): generic update status='cancelled' — opportunity NEVER declined,
        │     no decision event, no work item        ◇ Defer: derived at read time only
        ▼
        ✕  Accepted plan → Treatment scheduling: NO code link (appointments has no
        │     treatment_plan_id; next_visit_date/type are write-only dead fields).
        │     100% manual module-hopping.
        ▼
[Treatment Visit]  (TreatmentVisitService — the solid node)
        │  ⇢ appointment_id nullable FK, populated only if form supplies it;
        │     visit completion never updates the appointment; appointment 'done' never creates a visit
        │  ✕ plan-item progress: items never advance from visits (V1.1 baseline)
        │  → lab_case draft → LabCase state machine → final_received ⇢ manual delivery scheduling
        │  → BillingPrompt → patient Billing tab card → front desk Build Invoice (prompt→invoice_id)
        ▼
[Clinical completion] = mark_treatment_complete checkbox (whole plan)
        │  → inline 6-month recall Task (tasks table)
        │  → 'treatment.completed' → post-tx +3d / implant +7d Tasks (skipped if no relationship_id)
        ▼
[Recall]   TWO UNCOORDINATED PRODUCERS:
        │    tasks table (inline 6-mo Task) ← surfaced on Tasks board / Huddle ONLY
        │    communication_queue (RecallEngine: no_visit 6-mo, post-op, lab_received, birthday)
        │        ← surfaced on Today's Actions / Recall Pipeline ONLY
        │    no cross-dedup → duplicate recall at the 6-month mark is a real path
        │  ✕ approved_plan_no_appt trigger: DEAD (queries status 'approved', never written)
        ▼
[Follow-up]
        │  ⇢ staff logs call outcome; closes queue row iff outcome.closes_task
        │  ✕ WEB: "appointment booked" outcome closes the recall but does NOT book anything —
        │     OutcomeAutomationService (books appt, 12-mo re-recall, decline opp, deceased) is
        │     wired to MOBILE ONLY
        │  ✕ booking/attending an appointment NEVER auto-closes the prompting recall/Task
        ▼
[Reactivation] ⇢ no_visit_6months scan (30-day requeue cooldown) — works, one-way reminder
[Reviews]      ⇢ daily command scans appointments 'done' → one WhatsApp per appointment ever;
               keyed to appointment only, blind to visits/plans; zero tests
```

---

## B. Journey Connection Matrix

| From | To | Trigger | Data Link | Automatic? | Historical Fact? | Status |
|---|---|---|---|---|---|---|
| Enquiry (web/Meta/WA) | Lead | webhook | — | — | — | ✕ DISCONNECTED (routes never registered; parked V1.5, undocumented in code) |
| Manual enquiry | Lead | staff form / mobile | leads row | manual | lead_activities ledger | CONNECTED (no dedup on manual add) |
| Lead | Appointment | staff card drag + separate booking | **none** (no lead_id on appointments, no appointment_id on leads) | manual ×2 | stage string only | ✕ DISCONNECTED |
| Lead | Patient | convertToPatient | shared relationship_id only; source string copied; no lead FK | manual | lead stage='converted' persists | ⇢ PARTIAL |
| Appointment | Patient | booking requires existing patient / walk-in mints one | patient_id NOT NULL FK | at booking | yes | CONNECTED (duplicate-prone: no phone uniqueness anywhere) |
| Appointment | Check-in | staff updateStatus | status+checked_in_at | manual | timeline event | CONNECTED (no downstream effect) |
| Appointment | Consultation | optional dropdown on consultation form (web only) | nullable appointment_id, else null; API never sets | manual | orphans undetected both directions | ✕ / ◇ ILLUSORY |
| Consultation | Treatment Plan | redirect + flash from_consultation_id | consultation_id nullable FK | manual, data re-entered | FK only | ⇢ PARTIAL (V1.1 baseline) |
| Plan | Presented | markPresented | Opportunity('quoted') | manual | opp status only; no follow_up_date | ◇ ILLUSORY on action board |
| Plan | Accepted | accept endpoint → AcceptanceService | accepted_at + opp 'completed' | manual, stored | yes (activity, in txn) | CONNECTED |
| Plan | Rejected | generic update status='cancelled' | **opp untouched** | manual | no decision event | ✕ DISCONNECTED |
| Plan | Deferred | nothing | nothing | — | read-time derivation | ◇ DERIVED |
| Accepted plan | Treatment appointment | nothing | none (no treatment_plan_id on appointments; next_visit_* dead) | manual only | no | ✕ DISCONNECTED |
| Appointment | Treatment Visit | form may carry appointment_id | nullable FK, one-way | manual | no lifecycle sync either way | ⇢ PARTIAL |
| Visit | Plan-item progress | nothing | visit_items.treatment_plan_item_id (one-way) | — | item.status never written | ✕ (V1.1 baseline) |
| Visit | Lab case | lab_case.enabled in visit form | treatment_visit_id FK, teeth copied | auto in visit txn | lab_case_events ledger | CONNECTED |
| Lab final_received | Clinical continuation | notification to front desk + patient WA | none (no auto appointment) | notify only | lab event | ⇢ PARTIAL |
| Visit | BillingPrompt | unbilled visit items | prompt→invoice_id on Build Invoice | auto create; manual consume | resolved_at/by | CONNECTED (single consumer screen) |
| Plan complete | Recall Task | mark_treatment_complete | patient_id; title-LIKE dedup | auto | task row | CONNECTED (tasks-board silo) |
| 'treatment.completed' | Post-tx/implant Tasks | RulesEngine | relationship_id required | auto **iff relationship_id** | task + activity | ⇢ CONDITIONAL |
| Dormancy | no_visit recall | daily RecallEngine scan | communication_queue | auto | queue row + activity | CONNECTED (queue silo) |
| Recall | Contact attempt | Today's Actions / Recall Pipeline | comm_queue | staff | call.logged activity | ⇢ PARTIAL |
| Contact outcome | Appointment | outcome 'appointment_booked' | **mobile:** OutcomeAutomationService creates appt; **web:** closes row, books nothing | mobile auto / web manual | mobile yes / web no | ✕ web, CONNECTED mobile |
| New appointment | Close prompting recall/Task | nothing (no AppointmentObserver, no closes-link) | none | — | no | ✕ DISCONNECTED |
| No-show | Follow-up task | status→no_show (manual) → missed rule | task via relationship | auto after manual flag | yes | CONNECTED (conditional) |
| Cancelled | Follow-up | nothing | — | — | derived Yesterday board only | ✕ DISCONNECTED |
| Appointment 'done' | Review request | daily reviews:request scan | appointment_id, once ever | auto (flag-gated) | review row | CONNECTED (appointment-keyed only; zero tests) |

Permissions and tests per transition: appointments are the model citizen (view/edit/delete-split routes + 15 Feature test files incl. permission matrix and web/mobile booking parity). Leads/PRE are the outlier: **the entire pipeline including `convertToPatient` — which mints Patients — is gated by `auth` only** (`routes/relationship.php:26,147`; mobile lead API explicitly ungated). Clinical writes remain view-gated (V1.1 C1). Tests: leads/recall/follow-up have characterization suites; Lead→Appointment and Appointment→Consultation links have no tests because the links effectively don't exist; Reviews have zero tests.

---

## C. Top Journey Breaks

**P0 — data/safety/invariant**
1. **Lead conversion mints Patients behind an auth-only gate.** Any logged-in user can create leads and convert to patients — no module/role check web or mobile (`routes/relationship.php:26,147`; `routes/api.php:378`). Same class as V1.1 C1/C2; extends the permission hole to patient identity creation.
2. **Patient identity has no duplicate defense at the front door.** Walk-in always mints a new Patient by design (family-share rationale), manual leads have no dedup, no unique phone constraint on leads or patients, booking matches patients by eyeball. `register()`'s fuzzy linker is flag-gated. Duplicate patients are structurally likely — and every downstream journey artifact (recalls, opportunities, timelines) then splits across duplicates.

**P1 — workflow breaks**
3. **Mid-treatment dropout is invisible.** An `ongoing` plan with no future appointment is detected by nothing; the one automation aimed at it (`approved_plan_no_appt`) is dead code — it queries plan/item `status='approved'`, a value never written (verified; root cause = V1.1 D1/D2 status model).
4. **In-clinic rejection is silent.** No reject endpoint; generic `update(status='cancelled')` never calls `syncStage('declined')` → opportunity stays open-'quoted' forever, no decision event, no follow-up work, acceptance metrics rot. (Public Smart-Presentation decline DOES sync — only the in-clinic path is broken.)
5. **Presented-but-undecided plans are invisible on the Action Board.** Plan-sync opportunities get `follow_up_date=NULL`; both Today's-Actions queries require `whereNotNull('follow_up_date')`; the nudge rule matches stage `'prospect'` while sync passes `'quoted'` → never fires. The clinic's hottest revenue queue (estimate given, no answer) surfaces nowhere except manual pipeline browsing.
6. **Accepted plan → scheduling has zero code.** No plan↔appointment link; `next_visit_date`/`next_visit_type` are written and formatted but consumed by nothing (verified).
7. **The follow-up loop never closes on web.** `OutcomeAutomationService` (books the appointment, schedules 12-mo re-recall, declines opp, handles deceased) is wired to the mobile API only; web Action Board `logAction` just auto-closes the queue row. And booking/attending never auto-closes the prompting recall/Task (no AppointmentObserver, no reconciliation). Web/mobile behavioral divergence on the funnel's money path.
8. **Two recall systems that don't know about each other.** Inline 6-mo Task (tasks table → Tasks board/Huddle) vs RecallEngine queue rows (communication_queue → Today's Actions/Recall Pipeline). No cross-dedup → duplicate patient contact at 6 months; staff watch two screens for one job.
9. **RulesEngine silently skips patients with null `relationship_id`** — missed-appointment, post-treatment, and implant follow-up tasks just don't fire for unlinked patients, with no log or fallback.

**P2 — manual inefficiency / UX**
10. Appointment→Consultation link is cosmetic (optional dropdown, web only, API never sets it); check-in triggers nothing; done-without-consultation never reconciled.
11. Cancelled appointments produce no follow-up (only no-show does, and only after staff manually flag it — there is no auto no-show detection either).
12. Lab `final_received` → delivery appointment is notify-only.
13. Reviews keyed to appointments only, fire only if staff marked 'done', zero tests.
14. Inbound lead webhooks (website/Meta/WhatsApp/chatbot) fully coded but unreachable — dead imports in `routes/api.php` with no route registration and no code comment saying "parked V1.5". Fine as a decision, invisible as a fact.

**P3 — cleanup**
15. `recall_6months` rule disabled with no producer for its event; `recall.general_days` setting is display-only while the engine hardcodes 6 months; lead stage strings partially duplicated in board config; `queue_position` columns already correctly dropped (good hygiene example).

---

## D. Patient Leakage Points

| Leakage | Detected? | Actionable work created? | Owner? | Completion recorded? |
|---|---|---|---|---|
| Enquiry never books | ✅ lead followups + Today's Actions categories | ✅ follow-ups, board rows | Reception | Lead stage → converted/lost (manual) |
| Booked, no-show | ⚠️ only if staff manually set `no_show`; no auto-detection of past-due scheduled appts | ✅ urgent reschedule Task (**iff relationship_id**) + Yesterday board (derived, parallel) | Reception | Task done (manual) |
| Booked, cancelled | ⚠️ derived Yesterday board only | ❌ none | nobody | no |
| Consulted, no plan made | ❌ nothing compares consultations to plans | ❌ | nobody | no |
| Estimate given, undecided | ⚠️ opportunity exists but invisible on action board (NULL follow_up_date; nudge rule stage-mismatch) | ❌ effectively none (unless staff used the separate Smart-Presentation *send* flow → 3-day task) | nobody in practice | no |
| Rejected in clinic | ❌ no decision recorded; opp stays open-quoted | ❌ | nobody | no |
| Deferred | ❌ derived label on timeline only | ❌ | nobody | no |
| Partially accepted (one option of several) | ❌ not modeled (one opp per plan; items never tracked) | ❌ | nobody | no |
| **Started treatment, disappeared mid-plan** | ❌ **nothing scans ongoing plans without future appointments; intended trigger dead** | ❌ | **nobody** | no |
| Completed, ignores recall | ⚠️ 6-mo Task sits pending forever (no escalation/re-queue); no_visit queue item held open by cooldown | ⚠️ one task + one queue row (uncoordinated, possible duplicate) | Reception (two screens) | only on manual close; booking never auto-closes |
| Dormant >6 months | ✅ no_visit scan (30-day requeue cooldown; no-phone patients queued+flagged) | ✅ queue row on Recall Pipeline | Reception | manual outcome log; mobile can auto-book, web cannot |

The two worst by revenue impact: **mid-plan disappearance** (patient already committed, partially treated, highest recovery value, zero detection) and **estimate-given-undecided** (the case-acceptance queue the whole PRE exists for, structurally invisible on the daily board).

---

## E. Source-of-Truth Map

| Concept | Canonical source today | Trustworthy? |
|---|---|---|
| Lead status | `leads.stage` (one string, one mover, activity ledger) | ✅ yes — best-modeled state in the funnel |
| Appointment status | `appointments.status` enum, one writer path (`AppointmentService`) | ✅ yes |
| Patient identity | `patients` via `register()` invariant (holds) — but no uniqueness constraint, walk-in always-mint, lead provenance soft | ⚠️ process SoT, no data SoT for "same human" |
| Consultation status | none — hardcoded `'completed'` everywhere (V1.1) | ❌ |
| Diagnosis | columns on consultations; dead child tables (V1.1) | ⚠️ single store, no structure |
| Treatment-plan status | `accepted_at` vs `status` vs opportunity mirror (V1.1 H) | ❌ fragmented |
| Acceptance decision | accepted_at = fact; reject/defer = not stored | ❌ half a truth |
| Treatment progress | none — item.status never written; visits don't report back | ❌ **does not exist** |
| Clinical completion | 4 unrelated definitions (V1.1) | ❌ |
| Follow-up status | split: `tasks` vs `communication_queue` vs derived Yesterday board | ❌ competing |
| Recall status | same split, two producers, two screens | ❌ competing |

No trustworthy source of truth exists for: treatment progress, patient decision (beyond accept), clinical completion, follow-up/recall state, and "same human" identity.

---

## F. What Patients V1.1 Should Own

**1. Already covered by Patients V1.1 (no scope change):** decision recording (accept/reject/defer as stored facts — Slice 2); item-progress writer from visits; single plan-completion rule; ConsultationService; clinical permission hardening; journey event completeness. Note: fixing item/plan status truth **automatically revives** the dead `approved_plan_no_appt` recall trigger's prerequisites — the trigger rewrite itself stays in PRE.

**2. Missing from V1.1 — SHOULD be added (small, in-scope):**
- The reject path must call `TreatmentPlanOpportunitySync::syncStage('declined')` — one line inside the new decision-recording work; without it the decision record and the pipeline still disagree.
- `doctor_id` fallback in the main consultation store (typed variants have it; standard doesn't) — belongs in ConsultationService.
- Decide `next_visit_date`/`next_visit_type` fate: either PRE consumes them (preferred, but PRE's work) or V1.1 documents them as dead. V1.1 should at least stop presenting them as functional in the visit form.

**3. Belongs to PRM/PRE (a "Journey Wiring" sprint, NOT V1.1):** lead dedup + phone normalization; lead→appointment linkage (lead_id or via relationship); module/role gate on relationship routes incl. convertToPatient (could also ship early with V1.1's permission slice since it's a route-middleware change — CEO call); opportunity `follow_up_date` on plan-sync + fix the nudge rule stage mismatch; web parity for `OutcomeAutomationService`; one recall SoT (merge inline 6-mo Task into the queue, or teach the two to dedupe); recall escalation/aging; RulesEngine null-relationship fallback (or backfill + guard); ongoing-plan-without-appointment detector (the mid-plan dropout scan); cancelled-appointment follow-up rule.

**4. Belongs to Appointments (redesign already has its own audit):** "Start Consultation" handoff from the appointment screen (sets appointment_id); done-without-consultation reconciliation; auto no-show detection for past-due scheduled appointments; appointment-created-closes-prompting-recall reconciliation (needs an observer/service hook — coordinate with PRE); optional lead/source reference on appointments.

**5. Belongs to Finance:** BillingPrompt ↔ plan-invoicing reconciliation (V1.1 audit Section O unchanged); `amount_to_collect` flow.

**6. Belongs to Lab:** final_received → delivery-appointment prompt (today notify-only); already-catalogued lab hardening (V1.1 C4/C6).

**7. Later-version work:** inbound webhooks + public booking (V1.5 Connect — routes intentionally unregistered; add a code comment saying so); Case Acceptance Engine activation (V-later; depends on V1.1 decision facts); review-request journey linkage + tests; AI follow-up automation (V3/V4).

---

## G. Final CEO Summary

**1. Do we have a genuine Lead → Follow-up journey, or connected modules?**
Connected modules. The *nodes* are mostly strong (leads board, appointments, TreatmentVisitService, RecallEngine internals, acceptance service). The *edges* are where the product story fails: of ~24 journey transitions, roughly 7 are reliably connected, 8 are manual/partial, and 9 are disconnected, derived, or dead. The middle of the funnel (patient→consultation→plan→visit) works as a manual clinical workflow with good bones. The front (lead→appointment→consultation) and the back (decision→scheduling→recall closure) are held together by staff memory, not by the system.

**2. Where does the journey first break?**
At the very first handoff: Lead → Appointment. No FK, no automatic stage change, no dedup, and (because inbound webhooks are unrouted) most real-world enquiries can't even enter the system without manual typing. The second break follows immediately: Appointment → Consultation is a cosmetic optional dropdown.

**3. Most dangerous patient leakage point?**
Mid-treatment disappearance: an accepted, `ongoing`, partially-delivered plan with no future appointment is detected by nothing — the one automation designed for it queries a status value that is never written. Runner-up: estimate-given-undecided patients, invisible on the daily action board due to the NULL `follow_up_date` + rule stage mismatch. These are precisely the two highest-revenue queues a paid "patient journey OS" must own.

**4. Most dangerous clinical-truth defect?**
Unchanged from the V1.1 audit, now with proven downstream blast radius: plan-item progress and patient decisions are not stored facts. That single truth gap kills the recall trigger, blinds the dropout detector, rots the opportunity pipeline, and makes "defer" a UI illusion. Fix the truth model once (V1.1 Slice 2) and three downstream modules get their food supply.

**5. Minimum work before we can say "Dentfluence manages the complete patient lifecycle":**
In dependency order: (a) **Patients V1.1 Slice 1–2 as already scoped** — permission hardening (extend the route-gate fix to `routes/relationship.php`) and the clinical truth model (decisions recorded, items progress, one completion rule, +the one-line opportunity-decline sync). (b) **A PRE "Journey Wiring" sprint** — opportunity follow_up_date + nudge fix, web outcome-automation parity, one recall source of truth, mid-plan dropout detector, lead dedup + lead→appointment link. (c) **Two Appointments-redesign items** — start-consultation handoff and booking-closes-recall reconciliation. That is three bounded pieces of work, not a rewrite: (a) makes the facts true, (b) makes the facts actionable, (c) closes the loop. Until (b) and (c) exist, the honest claim is "Dentfluence records the patient lifecycle"; after them, it manages it.

---

*Audit complete. No code, migrations, files, or permissions were changed. Two sub-agent claims were refuted during verification and excluded (noted in the header). All other headline findings were spot-verified or carry file:line evidence.*
