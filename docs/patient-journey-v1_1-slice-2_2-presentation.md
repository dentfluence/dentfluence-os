# Patient Journey V1.1 — Slice 2.2: Plan Presentation Truth

**Status:** code complete, locally verified, NOT pushed, NOT deployed
**Phase:** 2 (Clinical Truth) · follows Slice 2.1 characterization
**Frozen invariant carried forward:** BILLING PROGRESS != CLINICAL PROGRESS
**New invariant established:** PRESENTED IS NOT A DECISION

---

## 1. What was wrong

Slice 2.1 proved by test that the system had **no clinical record of a plan
having been shown to the patient**. "Presented" existed only as a side-effect:
the linked `treatment_opportunities` row moved to stage `quoted`.

Three consequences:

1. **The fact lived in the sales pipeline, not the clinical record.** Anything
   that edited the opportunity board could create or destroy the apparent truth
   that a patient had been counselled.
2. **Mobile could not record it at all.** `markPresented` existed only on web.
   A dentist who presented a plan chairside on the mobile app left no trace.
3. **No time anchor.** "Plan created 40 days ago" was being used as a proxy for
   "patient has been thinking about it for 40 days" — which is false whenever
   the plan sat undiscussed before presentation.

---

## 2. The clinical fact

`treatment_plans.presented_at` — nullable timestamp, indexed, added after
`accepted_at`. Reversible migration. **Nothing was backfilled.**

| State | Meaning |
|---|---|
| `presented_at IS NULL` | Plan exists; patient has not been shown it (or it predates this slice) |
| `presented_at` set, `accepted_at` NULL, status not cancelled | **Decision Pending** — the patient knows, and has not decided |
| `presented_at` set, `accepted_at` set | Presented and accepted |

Model accessors: `is_presented`, `is_decision_pending`.

### First presentation is immutable

The first call establishes `presented_at`. Every later call **returns
`first_presentation: false` and leaves the timestamp untouched** — re-presenting
a plan to a hesitant patient must never erase when they first saw it.

Re-presentation is not lost: every explicit call writes a
`treatment_plan.presented` Activity row. The ledger carries the repetition
history; the column carries the anchor.

---

## 3. Canonical service

`App\Services\TreatmentPlan\TreatmentPlanPresentationService::markPresented()`

One door, used by web and API alike — the same pattern as
`TreatmentPlanAcceptanceService`. Transactional. Returns
`['plan' => TreatmentPlan, 'first_presentation' => bool]`.

**Guards** (throw `RuntimeException`, surfaced as HTTP 422):

- a `cancelled` plan cannot be presented
- a plan with no `patient_id` cannot be presented

**What it deliberately does NOT do:** set `accepted_at`, change plan `status`,
create a visit, create an invoice, or complete an opportunity.

---

## 4. Opportunity: projection, not source

Marking presented still moves the linked opportunity to `quoted`, so the
existing pipeline board and staff workflow are unchanged.

Two deliberate differences from before:

1. The opportunity stage is now **downstream** of the clinical fact, not the
   storage location for it.
2. The sync is **skipped entirely when the plan is already accepted**, so a
   re-presentation can never downgrade a converted opportunity back to `quoted`.

This is a documented compatibility bridge. The full Opportunity redesign remains
out of scope.

---

## 5. Surfaces

| Surface | Route | Gate |
|---|---|---|
| Web | `POST /treatment-plans/{plan}/mark-presented` | `module:patients,edit` |
| API (**new**) | `POST /api/v1/treatment-plans/{plan}/mark-presented` | `api.role:module:patients,edit` |

The API route did not exist before this slice. Web/mobile parity for
presentation is now closed.

Authorization follows the owner-configured `role_module_permissions` table —
never a role name. A role called "Evening Unicorn Clinician" with `patients`
edit can mark presented on both surfaces; a view-only role is refused on both.

Activity metadata records `source` (`clinic` vs `mobile`) so the two surfaces
remain distinguishable in the ledger.

---

## 6. Patient journey

`UnifiedTimelineService` now emits a distinct **"Treatment plan presented to
patient"** entry, separate from plan creation and from acceptance.

One honesty fix included: the existing pending-decision timeline entry claimed
to measure days "after presentation" while actually measuring from `plan_date`.
It now prefers real `presented_at`, falling back to `plan_date` only for
historical plans that have no presentation fact.

---

## 7. Historical data

**No backfill was performed, and none is proposed here.**

Historical plans keep `presented_at = NULL` even where an opportunity sits at
`quoted`. An opportunity stage is *evidence that someone moved a sales card*,
not proof that a patient was counselled on a date — converting one into the
other would manufacture clinical truth that was never recorded.

`ClinicalTruthCharacterizationTest` and
`PlanPresentationTest::test_historical_plans_are_not_backfilled` both lock this.

Read-only production evidence query for the CEO (does not modify anything):

```sql
SELECT
  (SELECT COUNT(*) FROM treatment_plans)                                   AS total_plans,
  (SELECT COUNT(*) FROM treatment_plans WHERE presented_at IS NOT NULL)    AS with_presentation,
  (SELECT COUNT(*) FROM treatment_opportunities WHERE status = 'quoted')   AS quoted_opportunities,
  (SELECT COUNT(*) FROM activities WHERE event = 'treatment_plan.presented') AS presentation_events;
```

Expected immediately after deploy: `with_presentation = 0`. Any historical
inference, if ever wanted, must be a separate CEO-approved decision with its own
audit trail.

---

## 8. Tests

`tests/Feature/Clinical/PlanPresentationTest.php` — 13 tests covering the 15
required proofs:

| # | Proof | Test |
|---|---|---|
| 1 | Creating a plan does not mark it presented | `creating_a_plan_does_not_mark_it_presented` |
| 2 | Marking presented sets `presented_at` | `marking_presented_sets_presented_at` |
| 3–5 | Does not accept / start treatment / convert | `presentation_does_not_accept_start_or_convert` |
| 6 | Repetition preserves original truth | `presenting_again_never_overwrites_the_first_presentation` |
| 7 | Activity event recorded | `presentation_records_one_meaningful_activity_event` |
| 8 | Web uses canonical service | `web_route_records_the_clinical_fact` |
| 9 | API uses canonical service | `api_route_records_the_same_clinical_fact` |
| 10 | View-only refused (both surfaces) | `view_only_role_cannot_mark_presented_on_either_surface` |
| 11–12 | Arbitrary custom role with edit works | `edit_role_with_an_arbitrary_name_can_mark_presented` |
| 13 | Journey distinguishes Created vs Presented | `patient_journey_shows_created_and_presented_as_distinct_facts` |
| 14 | Acceptance behaviour intact | `acceptance_still_works_and_presentation_does_not_disturb_it` |
| — | Cancelled plan guard | `a_cancelled_plan_cannot_be_presented` |
| — | No backfill | `historical_plans_are_not_backfilled` |

Proof 15 (Phase 1 wall green) = the regression wall in §9.

### Superseded characterization test

`ClinicalTruthCharacterizationTest::test_plan_has_no_presented_column_...` was
rewritten, not deleted. It originally asserted that no clinical presentation
fact existed — true before this slice, and exactly what 2.2 set out to change.
It is now
`test_presentation_remains_undecided_and_no_decision_record_exists_yet`, which
asserts the column DOES exist, that `plan_decisions` still does NOT (a later
slice), and that presentation still decides nothing. The docblock records what
was superseded and why.

---

## 9. Regression wall (local, all green)

| Suite | Result |
|---|---|
| Clinical (2.1 + 2.2) | 21 passed |
| Access (Phase 1 wall) | 48 passed |
| Relationship | 85 passed |
| Patients | 53 passed |
| Appointments + Automation | 103 passed |
| API | 11 passed |
| Characterization | 16 passed |
| Foundation + Phase 1/2/3 | 52 passed |
| **Total** | **389 passed** |
| Smoke (rollback) | PASS 66/66, 0 residue |
| Today's Actions health | 14/14 OK |
| Route crawler | 303 pages, **0 broken**, 24 pre-existing warnings |

---

## 10. Out of scope (unchanged, still open)

- `plan_decisions` / `plan_decision_items` (append-only patient decision record)
- `consultations.clinical_disposition`
- Treatment start / completion derivation
- `last_visit_date` projection
- Recall redesign, treatment recovery, appointment lifecycle repair
- Full Opportunity redesign

## 11. Known debt introduced

1. **Opportunity double-write.** Presentation writes both the clinical fact and
   the opportunity stage. Intentional for compatibility; removable once the
   Opportunity board reads from clinical truth.
2. **UI does not yet surface `presented_at`.** The API returns `presented_at`
   and `decision_pending`; no blade template displays the date yet. Deliberately
   deferred — "Decision Pending" as a visible work state belongs with the
   decision record, not here.
3. **`is_presented` retains an opportunity fallback** in `formatPlan()` so
   historical plans still render as presented on the existing screen. Removable
   after any future backfill decision.
