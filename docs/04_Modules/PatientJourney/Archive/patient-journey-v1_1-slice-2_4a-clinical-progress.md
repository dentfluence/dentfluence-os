# Slice 2.4a — Clinical Progress Truth: Characterization & Production Audit

**Status:** CHARACTERIZATION ONLY. No code, schema, migration, data or deployment changed.
**Purpose:** establish the real source of truth for treatment progress before any
canonical rule is introduced. This document is the frozen input to Slice 2.4b.
**Production measured:** 29 Jul 2026 (read-only queries).

---

## 0. The one-line answer

> **"Treatment started" is derivable in principle but NOT in practice today, and
> "treatment completed" is not derivable at all.**
>
> The blocker is not schema. It is that clinical work is largely not being
> recorded against plans, and that nothing anywhere records *finished*.

---

## 1. Current clinical truth model

| Concept | Where it lives | Reality |
|---|---|---|
| Plan presented | `treatment_plans.presented_at` | ✅ canonical (Slice 2.2) |
| Patient decision | `plan_decisions` (+ items) | ✅ canonical, append-only (Slice 2.3) |
| Plan accepted | `accepted_at` | ✅ mirror of the decision ledger |
| Treatment **started** | — | ❌ no fact. `status='ongoing'` is written by *acceptance*, not by work |
| Treatment **completed** | `treatment_plans.status='completed'` | ⚠️ written by TWO contradicting paths |
| Item progress | `treatment_plan_items.status` | ❌ **inert** — no writer (confirms Slice 2.1) |
| Item billing | `treatment_plan_items.billing_progress` | ✅ but it is MONEY, not clinical work |

---

## 2. Every lifecycle writer of `treatment_plans.status`

| Writer | Value | Class |
|---|---|---|
| `TreatmentPlanAcceptanceService::accept` | `ongoing` | **A** clinical |
| `TreatmentPlanAcceptanceService::acceptPartially` | `ongoing` | **A** clinical |
| `TreatmentPlanAcceptanceService::revert` | `pending` | **A** clinical |
| `TreatmentVisitService::completePlanAndQueueRecall` | `completed` | **C** manual |
| `TreatmentPlanBillingService` | `completed` | **B** billing |
| ~~web `update()`~~ / ~~API `update()`~~ | any | **blocked, Slice 2.3e** |
| plan creation (`store`) | `pending` | initial state, not a lifecycle mutation |

**`accept → 'ongoing'` is itself a collapse.** A plan reads as *in progress* the
moment the patient says yes, before any treatment happens — the same
acceptance/start conflation that C-1 fixed on the PRE side, still present
clinically.

## 3. Every completion writer — and they can disagree

1. **Billing** — completes the plan when *every* item reaches
   `billing_progress = 'invoiced'`. Money, not work.
2. **Visit** — completes the plan when a user ticks **`mark_treatment_complete`**
   on a visit. That is a *manual assertion*, not a derived fact, and it completes
   the **whole plan** regardless of whether every item was actually done.

Neither consults the other. Neither writes an activity event. Production shows no
contradiction *yet* (see §6) only because volume is low.

---

## 4. Visit characterization

`treatment_visits`: `patient_id`, `appointment_id`, `consultation_id`,
`doctor_id`, `visit_date`, `procedure`, `tooth_number`, `visit_number`,
`clinical_notes`, `next_visit_plan`, `status` (`started|ongoing|completed|abandoned`),
soft deletes.

- `treatment_plan_id` — **nullable, unvalidated**. Nothing checks that a visit's
  plan matches the plan its items belong to.
- **A visit means "an encounter happened"**, not "these procedures were finished".
- Multiple visits per plan: **yes**. One visit performing multiple plan items:
  **yes**, via `treatment_visit_items`.

### The real derivation path (one level deeper than expected)

`treatment_visit_items.treatment_plan_item_id` — fillable, validated
(`nullable|exists`), and genuinely populated by the UI when staff pick a treatment
off the plan (`treatment-visits-tab.blade.php:1891`). Null only for ad-hoc "Other"
work.

**This is the only place the system can say "this specific planned treatment was
worked on".** It is the correct foundation — if it were used.

## 5. Plan item characterization

| Column | Writer | Reader | Verdict |
|---|---|---|---|
| `status` | **none** | deletion protection only | **inert** — cannot become canonical |
| `billing_progress` | `TreatmentPlanBillingService` | plan completion, UI | billing truth, not clinical |
| `invoiced_units` | same | same | billing truth |

Neither can carry clinical progress. One is dead; the other is money.

---

## 6. Production coverage (29 Jul 2026)

| Measure | Value |
|---|---|
| Treatment plans | **34** |
| …accepted | **11** |
| …marked completed | **4** |
| Treatment visits (not deleted) | **46** |
| …linked to a plan (`treatment_plan_id`) | **12 → 26%** |
| Visit **items** (itemised work) in total | **11** |
| …linked to a plan item | **11 → 100%** |
| Plan items worked on in exactly 1 visit | 9 |
| Plan items spanning 2 visits | 1 |
| **Completed plans with NO visit** | **0** ✅ |

### What these numbers actually say

**a. The link works; the habit barely exists.** 100% of visit items carry a plan
item — but there are only **11 visit items across 46 visits**. Most clinical work
is recorded as a bare visit row (procedure/tooth on the visit itself) and is never
itemised. The 100% is a real signal about UI correctness and a *very weak* signal
about coverage.

**b. Three-quarters of visits are unattributable.** 34 of 46 visits carry no
`treatment_plan_id`. Some of that is legitimate (consultations, emergencies,
walk-ins are not plan work) — **the split is unknown and 2.4b must establish it.**

**c. Completion is not secretly billing-driven.** Zero completed plans lack a
visit. The two completion writers are not yet in open conflict. This is the
healthiest number in the census, and it will stop being true as volume grows.

**d. Multi-session treatment is already present at n=11.** One plan item spans two
visits. Even in this scrap of data ~10% of itemised work is multi-visit — enough
to prove that "a visit item exists" can never mean "the item is finished".

---

## 7. Contradictions found

| # | Contradiction | Severity |
|---|---|---|
| K-1 | Acceptance writes `status='ongoing'` — accepted is treated as started | 🟡 |
| K-2 | Two independent completion writers (billing, manual checkbox), neither aware of the other, neither audited | 🔴 |
| K-3 | `mark_treatment_complete` completes the WHOLE plan irrespective of per-item reality | 🔴 |
| K-4 | `treatment_plan_items.status` exists, is indexed, and is inert — a trap for the next developer | 🟡 |
| K-5 | `visits.treatment_plan_id` nullable and unvalidated against its items' plan | 🟡 |
| K-6 | 74% of visits carry no plan attribution at all | 🔴 |

---

## 8. Can completion already be derived? **No.**

The chain exists: `visit → visit_items → treatment_plan_item_id → plan item`.
What is missing is a **completion signal per item**.

A visit item records that work *happened*. Nothing records that it *finished*.
An RCT over three appointments produces three visit items against one plan item,
and nothing distinguishes session 1 from the last. `treatment_plan_items.units`
compounds it — 4 units of crown work cannot be counted from visit-item rows.

- **Started** = "≥1 visit item links to this plan item" → logically sound.
- **Completed** = requires a fact **nobody records**.

## 9. What blocks canonical clinical progress

1. **No per-item completion fact.** (the hard blocker)
2. **Itemisation is barely used** — 11 items / 46 visits. Deriving anything today
   would be correct and almost empty.
3. **No validation** tying a visit to the plan its items belong to.
4. **Two completion writers** that must be reduced to one — but only *after* a
   real clinical completion fact exists to replace them.

---

## 10. Risk

| Action | Risk |
|---|---|
| Derive **treatment started** from visit items | 🔴 **RED — coverage**, not logic. Only 11 items exist |
| Derive **treatment started** from `visit.treatment_plan_id` | 🟡 **AMBER** — 26%, coarse (visit-level, not item-level) |
| Derive **treatment completed** | 🔴 **RED** — the required fact does not exist |
| Leave K-2 / K-3 as they are | 🔴 **RED** — silent divergence as volume grows |
| Do nothing this slice | 🟢 **GREEN** — nothing is actively corrupting data today |

---

## 11. Recommended implementation order

**The census changed my recommendation.** Before this, 2.4b looked like a
derivation slice. It is not — deriving from 11 rows would be theatre.

1. **2.4b — CAPTURE, not derivation.** Make clinical work recordable against the
   plan as the natural path: itemisation on the visit screen, and a per-item
   outcome (`in progress` / `completed this visit`). Without this, every later
   rule computes over an empty set. *This is a UI + one small fact, not a rules
   engine.*
2. **2.4c — one completion rule**, derived from those per-item facts, retiring the
   `mark_treatment_complete` checkbox and billing's claim. Not before 2.4b, or one
   wrong source is swapped for another.
3. **2.4d — treatment started → PRE Converted.** Cheap once 2.4b lands, and it
   closes the frozen contract's last open projection.
4. **2.5 — stored-vs-derived comparison report** (read-only) before any historical
   row is touched.
5. **Later / separate:** retire `treatment_plan_items.status` or give it a writer;
   validate `visit.treatment_plan_id` against its items.

**Do not attempt derivation before capture.** The honest finding of this slice is
that Dentfluence does not yet know what treatment was done — not because the
schema cannot express it, but because nothing asks the clinician.
