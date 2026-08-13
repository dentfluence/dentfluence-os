# FROZEN FUTURE INTEGRATION CONTRACT
## Treatment Plan → PRE → Patient Microsite → Appointment Handoff

**Status:** FROZEN PRODUCT RULES · CEO ruling 27 Jul 2026
**Scope:** Patient Journey V1.1 / Phase 2 design documentation
**Implementation status:** DESIGN ONLY. The microsite is **not** built in this
phase. No microsite UI, routes, QR generation, public endpoints, preferred-slot
storage, or appointment-preference UI exists or may be built now.

Why freeze now: so that today's Clinical Truth and PRE architecture stay
compatible with the microsite, and so no current slice quietly makes a decision
that the microsite would later have to undo.

---

## 1. Canonical Treatment Plan → PRE journey

| Clinical event | Canonical record | PRE projection |
|---|---|---|
| Treatment Plan created | `treatment_plans` row | **Nurturing** |
| **Present Plan / Give Estimate** (ONE action) | `presented_at` + `treatment_plan.presented` | **Estimate Given** |
| Patient accepts | canonical acceptance/decision | **Committed** |
| Treatment actually starts | (trigger TBD — see §11) | **Converted** |

**Plan Presented = Estimate Given = PRE Estimate Given.** One business event,
represented differently in clinical and commercial UI. Forbidden: a second
"Estimate Given" button, an `estimate_given_at` column, a duplicate event, or a
duplicate staff action.

**COMMITTED** means: *the patient has agreed to proceed, but treatment delivery
has not started.* **Acceptance must not mean Converted.**

**CONVERTED** means treatment actually began. It may **never** be inferred from
acceptance, invoice, payment, or `billing_progress`.
Billing progress ≠ clinical progress.

After Converted the sales Opportunity has fulfilled its purpose. The continuing
lifecycle is: Active Treatment → Treatment Completion → Post-Treatment Follow-up
(where required) → Recall / Maintenance. **No duplicate chasing obligations**
across Opportunity, Follow-up and Recall.

---

## 2. Microsite is a channel, never a source of truth

A presented plan may later be given to the patient as a secure link / QR code.
The microsite is a **patient-facing interface to the same canonical Treatment
Plan**.

Web · Mobile/API · Microsite must all call the same domain services and produce
the same business truth, the same activity history, and the same downstream PRE
projection.

## 3. Microsite acceptance

Patient acceptance via microsite uses the **same canonical acceptance/decision
service** as clinic-side acceptance. Accepted in clinic OR via microsite →
same clinical decision → same audit trail → PRE = Committed.

**Do not create a `microsite_accepted` state.** Channel may be recorded as
metadata for audit (the existing `via` / `source` pattern), never as a separate
business state.

---

## 4–7. Appointment handoff — FROZEN

> **PATIENTS MUST NOT AUTOMATICALLY BOOK OR CONFIRM A TREATMENT APPOINTMENT
> THROUGH THE MICROSITE.**

After accepting, the microsite may offer *"Choose your preferred appointment
date & time"* from a **clinic-controlled calendar**, respecting clinic working
days, working hours, and holidays/closed periods. Later versions may filter by
doctor availability and procedure requirements.

Regardless of sophistication:

```
Patient selection  =  PREFERENCE          (an input to scheduling)
Patient selection  ≠  CONFIRMED APPOINTMENT
```

Recording *"Preferred appointment: 29 Jul 2026, 6:30 PM"* must **not** create an
Appointment record.

**Handoff.** Plan Accepted + preferred time submitted → derive an operational
obligation, e.g. *"Call patient to confirm treatment appointment"*, carrying:
patient, treatment plan, accepted treatment/value, acceptance time, preferred
slot, doctor/treatment context.

> "Ratna Mahajan accepted the Implant treatment plan.
> Preferred appointment: 29 Jul, 6:30 PM.
> Call patient and confirm treatment appointment."

Surfaced as actionable work in PRE / Today's Actions. **Do not create a
duplicate task if the obligation already exists.**

**Human confirmation gate.** Front desk / authorized staff verifies availability,
doctor, chair, duration, sequencing and clinical requirements — and only then
creates the Appointment.

```
Patient Preference → Staff Confirmation → Appointment Booked
```

## 8. Future AI Secretary

May identify the obligation, prepare communication, suggest options, and remind
staff. **Must never interpret a preferred slot as a booked appointment**, and
must reason from these canonical events rather than inventing parallel states.

---

## 10. Architectural invariants (frozen)

1. Clinical Truth is authoritative.
2. PRE is a projection/action layer, not a competing clinical record.
3. Present Plan + Give Estimate = ONE event.
4. Plan Accepted = **Committed**.
5. Treatment actually started = **Converted**.
6. Billing progress ≠ clinical progress.
7. Microsite is a channel, not a source of truth.
8. Microsite and clinic acceptance use the SAME domain service.
9. Patient-selected date/time = PREFERENCE only.
10. Preferred Slot ≠ Appointment.
11. Only authorized clinic workflow creates/confirms the actual appointment.
12. Opportunity ends as a sales journey after conversion; later obligations
    belong to active treatment / follow-up / recall.
13. Web + Mobile/API + future Microsite: same business truth, same authorization
    rules, same activity history.
14. The AI Secretary reasons from canonical events, never parallel states.

---

## 11. CURRENT CODE vs THIS CONTRACT — conformance audit (27 Jul 2026)

Audited against the code at commit `cb3a045`. **Nothing was changed as a result
of this audit** — findings are recorded for the slice that owns each area.

### ✅ Already conformant

| Invariant | Evidence |
|---|---|
| 1, 2 | `presented_at` is authoritative; Opportunity is a one-way projection (Slice 2.2). |
| 3 | One column, one event `treatment_plan.presented`, one button. No `estimate_given_at` anywhere — grep-verified and test-locked. |
| 7, 8 | `TreatmentPlanAcceptanceService` is already the single acceptance door, used by clinic web **and** the existing public case route (`PublicCaseController::accept`). A microsite can call it unchanged. |
| 13 | Presentation and acceptance both run through one service across web + API, gated on the same owner-configured `patients` permissions (Phase 1). |

The Case Acceptance public route is the strongest evidence the contract is
achievable: a patient-facing channel already accepts through the canonical
service without a parallel state.

### ⚠️ CONTRADICTIONS — recorded, NOT fixed in this phase

**C-1 · Acceptance jumps straight to Converted. (Invariants 4 & 5)**
`TreatmentPlanAcceptanceService` syncs the Opportunity to `completed`, and the
PRE UI labels `completed` as **"✓ Converted"**. So today, *accepting* a plan
displays as *converted* — exactly the collapse the contract forbids. The service
comment even reads "Accepted maps to 'completed' (Converted)".
→ Owner: the slice that introduces the decision record.

**C-2 · ~~No `nurturing` or `committed` state exists.~~ — CORRECTED 27 Jul (2nd pass)**

**This finding was WRONG.** I checked the enum values only and never read the
label map. The vocabulary this contract requires **already exists in full**:

```php
TreatmentOpportunity::STAGES = [
    'prospect'  => 'Identified',
    'discussed' => 'Nurturing',        // invariant: Plan Created
    'quoted'    => 'Estimate Given',   // invariant: Presented
    'accepted'  => 'Committed',        // invariant 4 — EXISTS, NEVER WRITTEN
    'completed' => 'Converted',        // invariant 5
    'declined'  => 'Declined',
];
```

**No migration is required for C-1.** `TreatmentPlanAcceptanceService` simply
writes the wrong value: `'completed'` (Converted) instead of `'accepted'`
(Committed). The correct slot has existed unused since the 2024 table creation.

⚠️ **Blast radius:** "open opportunity" is defined in **14 places** as
`NOT IN ('completed','declined')` — `TodayActionsEngine` (×2), `HuddleController`,
`DashboardController`, `OpportunityPipelineController`, `ProfileController`,
`OutcomeAutomationService`, and four scopes on the model. Writing `'accepted'`
would make every accepted plan re-enter the open pool, so patients who already
said yes would be chased to accept again — violating the "no duplicate chasing
obligations" rule. The fix needs a canonical closed-set
`['accepted','completed','declined']`, not just a changed value.

**C-3 · Plan creation does not create an Opportunity, so "Plan Created →
Nurturing" is not implemented.** Verified: `TreatmentPlanController::store`
touches no Opportunity. The first Opportunity appears on presentation. This is a
gap rather than a wrong value — no false state is displayed today.

**C-4 · No writer exists for "treatment actually started → Converted".**
No visit path touches the Opportunity at all.

**C-5 · ~~STRUCTURAL BLOCKER — `treatment_visits` has no `treatment_plan_id`.~~
— CORRECTED 27 Jul (2nd pass). THIS FINDING WAS WRONG.**

I read only `2026_05_26_100005_create_treatment_visits_table.php` and concluded
the column did not exist. It is added by
`2026_05_27_000003_add_treatment_plan_id_to_visits.php` — one day later in the
migration sequence.

The link **exists**, is `fillable`, is validated in `TreatmentVisitService`
(`'treatment_plan_id' => ['nullable','exists:treatment_plans,id']`), and already
drives `completePlanAndQueueRecall()`. Visit status vocabulary is
`started / ongoing / completed / abandoned`, defaulting to `started`.

There is **no structural blocker** to invariant 5. The real constraint is
weaker and empirical: the column is **nullable**, so before Converted can be
projected from visits, production coverage must be measured — what proportion of
treatment visits actually carry a plan id. Sparse attribution would produce
silent under-counting rather than an error.

Read-only census for that decision:
```sql
SELECT COUNT(*) visits,
       SUM(treatment_plan_id IS NOT NULL) linked_to_plan
FROM treatment_visits;
```

**C-6 · Plan status becomes `ongoing` immediately on acceptance** (Slice 2.1
characterization). At the clinical layer this conflates *accepted* with
*treatment underway*, the same collapse as C-1.

**C-7 · Plan completion is written by BILLING** when every item is invoiced
(Slice 2.1). Direct tension with invariant 6. Already frozen as a known finding:
BILLING PROGRESS != CLINICAL PROGRESS.

### Assessment

Slice 2.2 moved the system **toward** this contract and contradicts none of it.
Every contradiction above is pre-existing and sits in the acceptance/treatment-
start region, which later slices own.

**C-5 is the one worth acting on early**, because it is a schema prerequisite:
until a treatment visit can be attributed to a treatment plan, invariants 4 and 5
cannot be separated no matter how the PRE vocabulary is relabelled.

---

## 12. Current-phase scope

**Do now:** document these rules · keep domain services channel-agnostic ·
ensure current clinical events can later be called by microsite/API without
duplicating business logic.

**Do NOT build now:** microsite · QR codes · public treatment-plan pages ·
patient calendar/time picker · preferred-slot storage · automated WhatsApp
delivery · microsite acceptance endpoint · AI Secretary scheduling · automatic
appointment booking.
