# Slice 2.4c — Clinical Progress Derivation: Design Contract

**Status:** DESIGN ONLY. No code, schema, migration, UI or data changed.
**Revision:** CEO review revision, 29 Jul 2026.
**This document is the frozen implementation contract for Slice 2.4d and later.**

---

## Canonical principle (constrains everything below)

> **Captured facts are canonical. Derived progress is never stored.**

Derivation may read **only** what a clinician recorded about work actually done.
It may never read `current_stage`, `completed_stages`, `billing_progress`,
`treatment_plan_items.status`, or `treatment_plans.status`.

---

## 1. Canonical derivation inputs

**Exactly two columns, from one table, through one join:**

| Input | Why it is required |
|---|---|
| `treatment_visit_items.treatment_plan_item_id` | The only fact that ties work to a *planned* treatment |
| `treatment_visit_items.work_outcome` | The only fact that says *what happened* to it |
| `treatment_visits.deleted_at IS NULL` (join) | ⚠️ see trap below |
| `treatment_visits.visit_date`, `treatment_visit_items.id` | Ordering only — to find the latest valid fact, never as truth |

### ⚠️ TRAP — visit items do NOT cascade on soft delete

`TreatmentVisit` uses `SoftDeletes`. `TreatmentVisitItem` **does not**. Deleting a
visit leaves its items fully readable. Any derivation that queries
`treatment_visit_items` directly will count work from deleted visits.

**Every derivation query MUST join `treatment_visits` and filter
`deleted_at IS NULL`.** This is the single most likely implementation defect in
2.4d and it fails silently.

### Inputs deliberately REJECTED

| Rejected | Reason |
|---|---|
| `current_stage` / `completed_stages` | Per-visit, keyed to the primary treatment only, and **carries forward cumulatively** — progress-flavoured, not a record of today |
| `billing_progress` / `invoiced_units` | Money. Frozen invariant: BILLING PROGRESS ≠ CLINICAL PROGRESS |
| `treatment_plan_items.status` | Inert — no writer since inception |
| `TreatmentPlanItemTooth.status` | `STATUS_COMPLETED` is **declared but never written**; only `invoiced` is, by billing. A dead constant beside a live one — do not mistake it for clinical truth |
| `treatment_plans.status` | Written by acceptance and by two completion paths; the thing being replaced |
| `visit.treatment_plan_id` alone | Says an encounter belonged to a plan, not that any planned treatment was worked on |

### `is_repeat` — read, but not as an input

Not part of the rule. It matters only because latest-valid-fact-wins handles it
correctly: redone work produces a newer fact, which reopens the item. Worth an
explicit test in 2.4d.

---

## 2. STARTED — the canonical rule

> **A treatment plan has STARTED when at least one visit item, on a
> non-deleted visit, links to one of its plan items AND carries a
> non-null `work_outcome`.**

```
started(plan) :=
  EXISTS (
    treatment_visit_items vi
    JOIN treatment_visits v ON v.id = vi.treatment_visit_id
    JOIN treatment_plan_items pi ON pi.id = vi.treatment_plan_item_id
    WHERE pi.treatment_plan_id = plan.id
      AND vi.work_outcome IS NOT NULL
      AND v.deleted_at IS NULL
  )
```

### Edge cases audited

| Case | Verdict |
|---|---|
| Visit linked to plan, but no itemised work | **Not started.** An encounter is not treatment |
| Visit item linked, `work_outcome` NULL | **Not started.** Linkage alone is a billing convenience, not evidence of work |
| Ad-hoc work (no `treatment_plan_item_id`) | Irrelevant to any plan |
| Work on a **cancelled** plan | Rule still fires. Correct — treatment genuinely happened. Display concern, not a truth concern |
| Work on a **never-accepted** plan (emergency) | Rule still fires. Correct, and worth surfacing: a real workflow signal |
| Soft-deleted visit | **Excluded** — see trap above |
| Repeat work only (`is_repeat`) | Started. You cannot redo what was never done |

**Consequence to accept openly:** every pre-2.4b visit item has
`work_outcome = NULL`, so **no historical plan derives as started**. That is
honest, not broken — nothing recorded what happened. No backfill.

---

## 3. LATEST VALID CLINICAL FACT WINS

**This is the architectural rule. It is not "the latest row".**

> **The derived state of an item is determined by the latest VALID clinical
> fact recorded about it.**

A clinical fact is **valid** when it is currently in force — recorded, not
withdrawn, and not superseded by a correction.

### Why the distinction matters now, before it is needed

Today "latest valid fact" and "latest row" resolve to the same query, because
the only invalidation that exists is the visit soft-delete. There is no concept
of a *voided* or *corrected* visit item.

They will diverge the moment either is introduced — and clinical systems always
introduce them, because clinicians mis-tap and record work on the wrong tooth,
the wrong item or the wrong patient. If the contract said "latest row", every
consumer written between now and then would hard-code an assumption that a
correction feature must later break.

**Implementation note for 2.4d:** implement latest-valid-fact by *ordering and
filtering inside the canonical service only*. The rule for validity today is:

```
valid(visit_item) :=
      visit_item.work_outcome IS NOT NULL
  AND visit.deleted_at IS NULL
```

When correction or void concepts arrive, `valid()` changes **in one place** and
every consumer inherits it. No consumer may re-implement ordering or validity.

---

## 4. ITEM PROGRESS — derived, never stored

Take that item's **valid** clinical facts, ordered by `visit_date`, then `id`.
The latest valid fact determines the state.

| Derived state | Rule |
|---|---|
| **Not Started** | no valid facts |
| **Started** | exactly one valid fact, and it is `started` |
| **In Progress** | ≥1 valid fact; latest is `started` (with earlier facts) or `worked_on` |
| **Completed** | latest valid fact is `completed_today` |

**Why latest-valid-wins rather than "any completed_today ever":** it makes repeat
work correct without special-casing. An item completed in March and redone in
July reads **In Progress** again, because the newest valid clinical fact says so.
"Any completed ever" would freeze it as Completed and hide the redo.

### Known imprecision — multi-unit items

`treatment_plan_items.units` may be > 1 and an item may span several teeth
(`TreatmentPlanItemTooth`). Nothing captures *which unit or tooth* was finished.
So **item-level "Completed" is the clinician's assertion about that item**, not a
counted total.

Do not paper over this with arithmetic. If per-tooth completion is later
required, it needs a *captured fact*, exactly as 2.4b added one — never a
derivation.

---

## 5. PLAN PROGRESS — derived from items in scope

**Scope is decided by the patient's decision (Slice 2.3), not by the item list.**

| Derived plan state | Rule |
|---|---|
| **Not Started** | no in-scope item has any valid fact |
| **In Progress** | ≥1 in-scope item has work; not all are Completed |
| **All Work Recorded** | every in-scope item derives as Completed |

### Why the ceiling is "All Work Recorded" and never "Completed"

These are different assertions, and conflating them is precisely the ambiguity
this phase exists to remove.

**"All work recorded"** is a statement about *data*: every treatment the patient
agreed to has a clinical fact whose latest valid value is `completed_today`. The
derivation can prove this from what was captured.

**"Completed"** is a statement about *clinical judgement*. It implies things
nobody has recorded and the derivation cannot see:

- the clinician is **satisfied with the result** — not merely that work happened
- any **laboratory work is delivered and fitted**, not just prescribed
- **post-operative review** has occurred where the procedure requires it
- **follow-up or healing checks** are done or deliberately not required
- future business rules may attach further conditions (consent closure,
  radiographic verification, handover to a maintenance plan)

A derivation that called this "Completed" would be **claiming more than the
captured facts support** — asserting professional judgement on the clinician's
behalf. That is exactly the failure mode of the current system, where billing
completes a plan because every item was invoiced.

**Rule:** derivation reports what was recorded. Completion, when it is
introduced, must be its own captured clinical assertion — a fact, not an
inference. Until then, "All Work Recorded" is the honest ceiling, and it must be
worded that way in every UI, API and report.

---

## 6. Interaction with patient decisions (Slice 2.3)

In-scope items, by the plan's **current** decision:

| Current decision | Items in the progress denominator |
|---|---|
| Accepted | **all** items |
| Partially accepted | only items whose latest `plan_decision_items.decision = accepted` |
| Deferred | none — nothing was agreed to |
| Rejected | none |
| No decision recorded | none |

**Deferred / Rejected / Not-Yet-Decided items must be excluded from the
denominator and shown separately.** Counting a rejected crown against progress
would make every partially-accepted plan permanently incomplete.

### Contradiction to surface, not resolve

Work may exist on an item the patient **rejected or never decided** (emergency
treatment, or a decision recorded after the fact). The derivation must not hide
it. Recommended handling in 2.4d: keep it out of the percentage, surface it as
*"work recorded outside the accepted plan"*. **Never invent a rule that
auto-accepts an item because work happened** — that would let clinical work
overwrite a patient decision, inverting Slice 2.3.

---

## 7. Interaction with billing

**None. In either direction.**

Derived progress must not read `billing_progress`, and must not write anything
billing reads. The two completion writers (`TreatmentPlanBillingService`,
`TreatmentVisitService::completePlanAndQueueRecall`) stay exactly as they are
until a later slice retires them — and only after Slice 2.5's comparison report.

## 8. Interaction with the stage tracker

**None.** `current_stage` / `completed_stages` remain untouched, and are not
derivation inputs. They are richer per-visit clinical detail for the primary
treatment; `work_outcome` is the per-item fact. They coexist.

Flagged for a future decision, not this contract: two overlapping notions of
"progress" on one screen is UX debt, and `completed_stages` carrying forward
makes it the more misleading of the two.

## 9. Interaction with the Journey

Read-only. The `treatment_visit.work_recorded` activity (2.4b) already narrates
work as it happens. Derived progress is a **read model for screens**, never a
new event. Do not emit "plan became In Progress" events — derived states have no
moment of occurrence.

## 10. Interaction with PRE

**Started is the correct future trigger for Converted.** It matches the frozen
integration contract exactly: *Converted = treatment actually started*, never
inferred from acceptance, invoice, payment or billing.

**The blocker is adoption, not logic.** Every existing visit item has
`work_outcome = NULL`, so on the day a derivation ships, **zero** plans derive as
started. PRE must not begin consuming this truth until real usage exists — see
the rollout sequence in §13.

---

## 11. THE CANONICAL READER RULE

> **Clinical progress may be consumed ONLY through the canonical Derived
> Progress Service.**

After Slice 2.4d there is **exactly one authoritative reader** of clinical
progress. Every screen, endpoint, report and future module asks that service.
None of them computes progress for itself.

### These must NEVER become progress readers

| Forbidden as a progress source | What it actually is |
|---|---|
| `treatment_plans.status` | Legacy lifecycle column, written by acceptance and two completion paths |
| `treatment_plan_items.status` | Inert; no writer |
| `billing_progress` / `invoiced_units` | Money |
| `current_stage` | Per-visit clinical detail for the primary treatment |
| `completed_stages` | Cumulative, carries forward; not a record of today |
| `TreatmentPlanItemTooth.status` | Billing (`invoiced`); `completed` never written |

### These must consume the canonical read model

Timeline · Dashboard · PRE · Analytics · Reports · Patient Portal / future
microsite · Mobile & API · AI Secretary / Copilot · any new module.

**No consumer may re-implement the derivation, re-order the facts, or define its
own validity rule.** A second implementation is a second truth, and the whole of
Phase 2 exists because this system had several.

---

## 12. Production risks

| # | Risk | Severity |
|---|---|---|
| R-1 | Derivation forgets the soft-delete join and counts deleted visits | 🔴 **RED** — silent, plausible, wrong |
| R-2 | Adoption: all historical rows are NULL, so everything reads Not Started | 🟡 AMBER — honest, but visibly empty at launch |
| R-3 | Multi-unit items: "Completed" is an assertion, not a count | 🟡 AMBER — document; do not compute around it |
| R-4 | Work recorded on rejected / undecided items | 🟡 AMBER — surface separately; never auto-accept |
| R-5 | Deriving into a stored column "for performance" | 🔴 **RED** — the mistake this phase exists to undo |
| R-6 | Two progress notions on one screen (stages vs work) confusing staff | 🟡 AMBER |
| **R-7** | **A future developer reads `treatment_plans.status` (or any legacy column) instead of the canonical service** | 🔴 **RED** |

### R-7 — architectural drift, in detail

This is the risk most likely to undo Phase 2, because it requires no bad
intent — only convenience. `treatment_plans.status` will still exist after
2.4d. It is indexed, it is one join shallower, and it *looks* authoritative. A
developer adding a dashboard tile, a report filter or an AI tool call will reach
for it, and the resulting number will be plausible enough that nobody notices it
disagrees.

The system has already demonstrated this failure mode three times:
`treatment_plan_items.status` (inert but indexed, and read once for deletion
protection), `TreatmentPlanItemTooth::STATUS_COMPLETED` (declared, never
written), and the Opportunity board being read as clinical presentation truth
until Slice 2.2 caught it.

**Mitigations required in 2.4d:**

1. **One service, no alternatives.** Derivation lives in exactly one class, and
   nothing else may query `work_outcome`.
2. **Deprecation docblocks** on `treatment_plans.status`,
   `treatment_plan_items.status` and `TreatmentPlanItemTooth::STATUS_COMPLETED`
   naming the canonical service.
3. **A guard test** asserting that no file outside the canonical service reads
   `work_outcome`, mirroring the existing role-name and patient-minting guards.
4. Legacy status columns are retired only after Slice 2.5's comparison report —
   and until then they must never be described as progress.

---

## 13. Recommended implementation sequence

```
2.4d   Derived Progress Service         (read model only; shipped UNUSED)
  ↓
2.4e   Read Model UI                    (plan card + visit screen surface it)
  ↓
       PILOT ADOPTION                   (clinic uses the 2.4b control in real work)
  ↓
       USAGE VERIFICATION               (measure real work_outcome coverage)
  ↓
2.4f   Started → PRE Converted          (only once the data is real)
  ↓
2.5    Comparison report                (derived vs stored completion, read-only)
  ↓
later  Retire the second completion writer, then billing's claim
```

**Why adoption must be verified before PRE consumes this truth.** The
architecture is correct; the data is not yet there. Wiring Converted to an empty
derivation would empty the PRE board of a stage that currently shows something,
making the board *worse* than today while looking like a regression to staff.

Verification is a measurement, not a feeling: re-run the 2.4a census and confirm
`work_outcome` coverage is rising on real visits before 2.4f begins.

**Do not reorder 2.4f before 2.4e.**

## 14. Risk assessment

| Item | Verdict |
|---|---|
| Deriving **Started** | 🟢 **GREEN** — rule sound, inputs exist, edge cases audited |
| Deriving **Item progress** | 🟢 **GREEN** — latest-valid-fact handles multi-visit and repeats correctly |
| Deriving **Plan progress** | 🟡 **AMBER** — sound, but depends on decision scope being read correctly |
| Deriving **Completion** | 🔴 **RED** — do not attempt. "All Work Recorded" is the honest ceiling |
| **Data readiness** | 🔴 **RED today** — zero rows carry `work_outcome`. The model is correct and the tank is empty |
| **Architectural drift (R-7)** | 🔴 **RED** — must be actively guarded, not assumed |

---

## 15. FROZEN ARCHITECTURAL INVARIANT

> **There shall be exactly one canonical derivation model for clinical progress.**
>
> **Captured facts remain canonical.**
>
> **Derived progress remains read-only.**
>
> **No future feature may derive clinical progress independently, or read legacy
> status fields as substitutes.**

Any future slice, module, report, integration or AI capability that needs to know
how far a treatment has progressed asks the canonical Derived Progress Service.
If that service cannot answer the question, the answer is that **the fact was
never captured** — and the correct response is to capture it, never to infer it
from billing, stages or a legacy status column.

---

## The one-line contract

> **Derive from `work_outcome` on non-deleted visits, scoped by the patient's
> accepted items, latest valid clinical fact wins, nothing is stored, exactly one
> reader, and the ceiling is "All Work Recorded" — never "Completed".**
