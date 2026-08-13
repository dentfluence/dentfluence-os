# PRE / Relationship Engine — Forensic Audit

**Date:** 2026-07-25 · **Mode:** read-only evidence gathering. No code changed.
**Companion documents:** `docs/patients-v1_1-clinical-care-audit.md` (clinical truth), `docs/patient-journey-audit.md` (journey connections). This audit completes the trilogy for Patient Journey V1.1 scoping.
**Method:** three parallel read-only traces (work-surface inventory, six-month recall lifecycle, ten patient-persona simulations), then independent spot-verification of every headline claim.

**Spot-verified directly (not just sub-agent claims):** `patients.last_visit_date` has **no writer anywhere in app/** (30 grep hits — all reads/sorts/casts/fillable); `leads.stage='new_enquiry'` is written by nothing (only the TodayActionsEngine filter and a RelationshipJourney constant reference it); `TreatmentOpportunity::isOverdue()` and `FinancePatientMembership::daysUntilExpiry()` do not exist as methods anywhere (only the TodayActionsEngine call sites) — both categories die in a swallowed exception.

---

## 1. PRE ARCHITECTURE MAP

**Five work stores** (no shared key, no cross-reference, no union query anywhere):

| Store | Owned by | Work it represents | Due date | Priority | Owner | Attempts |
|---|---|---|---|---|---|---|
| `leads` (+`lead_activities`, `followup_date`) | PRE | enquiry pipeline | free field, filter-only | `urgency` (unused by board) | name string (unfiltered) | lead_activities (pipeline modal only) |
| `follow_ups` | PRE/Comm | rule- and staff-created call-backs | rule offset / task due | rule config (unused) | user FK (never filtered) | none on row |
| `tasks` | Comm/Tasks | staff + RulesEngine tasks | required; buckets | set, filter-only, not sorted | user FK, **enforced for front-desk roles** | none |
| `communication_queue` | PRE | recalls + manual comms — the biggest store | **hardcoded `today()` at creation, never advanced on web** | trigger-based; ignored by board sort | **name string**, bulk-assign only | `attempt_count`/`last_attempt_at` — **incremented only by mobile** |
| `treatment_opportunities` | PRE | revenue pipeline | staff-set; **NULL from plan sync** | set, unused | user FK, unfiltered | none |

**Eight staff-facing surfaces** reading them in different combinations:

1. **Today's Actions / Action Board** (`TodayController` → `TodayActionsEngine`, 13 categories + 2 Yesterday cards) — reads leads, follow_ups, comm_queue, opportunities, appointments, invoices, lab_cases, memberships, visits, patients. **Never reads `tasks`.** No assignee filter — every user sees everything. `limit(50)` per category with **no "+N more"** (header shows the capped count). Per-category try/catch silently blanks broken categories.
2. **Recall Pipeline** (`/relationship/recalls`) — comm_queue, paginate(30), the only surface with truthful backlog counts and priority sorting. **Has no outcome-logging method at all** (index/store/ignore/unignore/bulkDismiss/bulkAssign/convertToOpportunity only).
3. **Missed Calls page** — comm_queue `follow_up_date <= yesterday`, paginate(50) — overlaps Recall Pipeline almost entirely.
4. **Lead Pipeline** — unbounded `Lead::get()`, 40 cards/column.
5. **Opportunity Pipeline** — same pattern, all opportunities ever.
6. **Tasks board** — the only surface with real ownership enforcement (front_desk/assistant/accounts see only `assigned_to=me`) — which combined with RulesEngine's `assigned_to=NULL` means **every automation-created task is invisible to reception**.
7. **Huddle** — four independent implementations (blade, aggregation JSON, mobile API, text briefing) that disagree on task filters and date ranges; builds four comm lists it never renders; "0 pending" pill reads a key that doesn't exist; `ongoing_treatments` widget queries a retired status → permanently 0.
8. **Dashboards/badges** — PRE dashboard shows the true backlog number; comm sidebar counts every pending task in the DB across all branches/time; main sidebar badges hardcoded 0.

**Three of the 13 Action Board categories are dead** (verified): `new_enquiries` (filters `stage='new_enquiry'`; every writer sets `new_lead`), `opportunities` (`$opp->isOverdue()` — method doesn't exist → BadMethodCallException → swallowed → `[]`), `membership_renewals` (`daysUntilExpiry()` — doesn't exist). The board silently drops fresh leads, the entire revenue pipeline, and membership renewals — while showing 50 stale recalls.

**Outcome engine:** `OutcomeAutomationService` — the only code that schedules a next contact from an outcome (will-call-back +2d, next-week +7d, next-month +30d, not-interested → 12-month recall, deceased → automations off, wrong number → contact invalid, appointment_booked → **actually creates the appointment**) — has **exactly one caller: the mobile API**. Its docblock claims web+mobile share it; that claim is false. Web logging = write an Activity row + (iff `closes_task`) close the queue row. Nothing in between, nothing scheduled next.

---

## 2. STAFF-DAY SIMULATION (why staff are overwhelmed — the mechanical answer)

A receptionist opens Today's Actions at 09:00:

- She sees ~50 recall calls — the **same 50 oldest rows as yesterday and every day before**, because `recallCalls()` has no date filter, every row's `follow_up_date` is frozen at its creation day, sorting is date-ASC, and the cap hides rows 51–1810. Working the list top-down never reveals row 51.
- The card header says "50". The PRE dashboard tile says "1810". The Recall Pipeline says 61 pages. The Missed Calls page says 36 pages. Nothing reconciles; three of the four numbers are the same backlog.
- Items she "Ignored" on the Recall Pipeline still appear on the board (`recallCalls()` omits `notIgnored()`).
- She calls a recall she remembers trying before. The drawer shows **no attempt count and no history older than midnight** (`annotateCallState` filters `whereDate(occurred_at, today())`; comm_queue `attempt_count` is not rendered and is never incremented on web anyway). Every call starts from zero.
- She logs "No answer" (`closes_task=false`): the system writes an Activity row and **nothing else**. Tomorrow the card looks untouched. Her only real choices are "closed forever" or "identical tomorrow".
- She logs "Will call back": on web this **closes the row permanently** (`connected_callback` closes_task=true) with no callback scheduled. Her colleague on mobile logging the same words gets a +2-day reschedule. Same situation, opposite outcomes by device.
- She ticks a task done on the Tasks board: the FollowUp and queue rows created by the same "+ Add Call" click stay open on the Action Board — one click created a Task + a FollowUp + a HuddleTaskLog, visible on 4–6 surfaces, and closing one closes none of the others.
- Meanwhile, lab-ready and overdue-invoice cards **reappear every morning forever** (dismissals are scoped to one calendar day), and the automation tasks that should route her missed-appointment calls are invisible to her role (unassigned system tasks + assignee-scoped board).

**Maximum simultaneous work items for one patient: 15+** across the five stores (open lead + 2 follow_ups + 2 tasks + up to 6 recall-purpose queue rows + opportunity + no-show card + payment reminder + lab-ready card), with zero cross-surface dedup and **no per-patient open-work view anywhere** — the Patients profile and the PRE relationship profile both cannot see `communication_queue` or `follow_ups` at all. Realistic everyday overlap: 6 cards on 4 screens for one quiet post-crown patient, two of them describing the same lab case.

Staff overwhelm is not a training problem. It is: no ownership on the main board, no aging, caps that hide backlog, one action fanning into 2–4 rows, dead categories hiding real work, and an outcome system that either kills an item or changes nothing.

---

## 3. SIX-MONTH RECALL FORENSIC MAP

**Eligibility root defect (P0):** both recall paths (`RecallEngineService::recallNoVisit6Months`, `RecallAutomationRunner::runNoVisit`) compute "last visit" from **`patients.last_visit_date` only** — a column **no code ever writes** (verified: no observer, no service, no controller; only imports/seeders could populate it). Consequences: (a) app-registered patients have NULL → under the no-`effective_from` branch, `orWhereNull` makes them **perpetually eligible regardless of attendance**; (b) imported patients' dates never advance — a patient treated yesterday re-enters recall on every 30-day cooldown cycle **forever**, since closing a recall guarantees re-entry (the column that would stop it never moves). The `recall.effective_from` AppSetting (the 1810-spike fix) bounds the imported population but also silently **drops never-visited patients entirely**.

**Producers (8 entry paths):** `recall_no_visit` (legacy engine daily 07:00 OR AutomationRunner when `automation.engine` flag ON — confirm live path against the `feature_flags` DB row, not config default); `recall_approved_plan` (**DEAD** — queries `status='approved'`, never written); `recall_post_op` (−14d, exact-day match); `recall_7day_followup` (exact-day; **shares `treatment_visits.recall_queued_at` stamp with post-op — whichever fires first blocks the other**); `recall_birthday` (annual); `recall_manual` (**no dedup — unlimited duplicates possible**); `recall_long_term` (mobile 'not interested' → +12mo); plus the **inline 6-month Task** from `TreatmentVisitService` (tasks table — never appears on Action Board or Recall Pipeline; title-LIKE dedup can match unrelated tasks).

**Engine-vs-runner parity is broken:** the legacy path (live by default) does **not** exclude deceased (`automations_disabled_at`), invalid-contact, or no-phone patients; the runner does. The parity comment in the code is false; the shadow-runner that "proved" parity compared cooldown semantics only and matches neither path's filters. The legacy path also uses offset `chunk(100)` while mutating a queried column → **skips patients mid-iteration**.

**Exclusions that don't exist:** patients with **future appointments** are NOT excluded (the guard exists — `recallLabReceivedNoAppointment` has it — and is applied to only that one trigger of eight); patients with **open/ongoing treatment plans** are NOT excluded (mid-implant osseointegration = "we've lost this patient"); create-time-only checks mean booking later never removes an existing row.

**Aging:** none. `recalculateOverdue()` is called from one legacy screen only — no scheduled sweep — so `is_overdue`/`status='overdue'` and the Recall Pipeline's overdue count are effectively always zero for engine rows. `sla_breached` only computes inside `logAttempt()` (mobile-only) → untouched items never breach. No expiry, no escalation, no attempt ceiling (`DEFAULT_MAX_ATTEMPTS=5` exists, never called; "Unreachable (3+ attempts)" is a label with no logic).

**Closure matrix:** closes via web Log-with-closing-outcome / Close / Dismiss, Recall Pipeline bulk-dismiss / convert-to-opportunity, mobile outcome automations. Does **NOT** close on: appointment created (AppointmentService has zero comm_queue references), visit/consultation attended, or any daily reconciliation sweep (none exists). "Ignore" hides the row from the pipeline while leaving it `pending` — still on the Action Board, still blocking re-queue. Mobile 'no answer' flips status to `waiting_for_patient`, which **removes the row from the Action Board** (filters `status='pending'`) while it stays open on the pipeline — a failed call *hides* the work.

**Simultaneity:** dedup is `patient_id+purpose` only → one patient can legitimately hold the inline recall Task + `recall_no_visit` + `recall_birthday` + `recall_long_term` at once, appearing on Tasks board, Action Board (two cards), Recall Pipeline, and Missed Calls simultaneously. No claimed-by/locking — two receptionists can both call the same patient; last write wins on the outcome.

**Backlog dynamics:** with ~1810 pending: Action Board renders 50 (uncounted remainder), pipeline 61 pages, missed-calls 36 pages. Clear-rate is bounded by staff clicks; re-entry is guaranteed every 30 days by the dead `last_visit_date`; steady state grows. **The backlog is not a staffing problem — it is the arithmetic of a dead input column + no closure hooks + a 50-row window.**

---

## 4. DUPLICATE-WORK MATRIX

| One real-world obligation | Rows created | Stores | Surfaces showing it | Cross-close? |
|---|---|---|---|---|
| "+ Add Call" for a patient | Task + FollowUp + HuddleTaskLog | 3 | Tasks board, Huddle tasks, Today `follow_up_calls`, Follow-up Engine, Huddle comms, sidebar count | none |
| Lead stage moved | old FollowUp stays + new FollowUp + `leads.followup_date` untouched | 2 | Today `lead_followups` + `follow_up_calls` (both) | none |
| Missed appointment | Yesterday board row (derived) + RulesEngine Task | 2 | Today board + Tasks board (admin only) | none |
| Post-treatment day-7 (implant) | RulesEngine Task (+7d) + `recall_7day_followup` queue row (+7d) | 2 | Tasks board + Action Board, same day, same purpose | none |
| Treatment completed | inline 6-mo Task + (later) `recall_no_visit` queue row | 2 | Tasks board vs Action Board/Pipeline | none |
| Lab work ready, no appointment | `recall_lab_received` queue row + `lab_ready` live category | 2 | two cards on the same board | none |
| Recall pending since yesterday | `recall_calls` card + `missed_calls_yesterday` card | 1 store, 2 cards | same page, twice | n/a |
| Same backlog, four numbers | — | — | board "50", dashboard "1810", pipeline 61 pages, missed-calls 36 pages | — |

---

## 5. MISSING-ACTION MATRIX (work that should exist and doesn't)

| Situation | Work generated today | Verified cause |
|---|---|---|
| Fresh lead (Sushil) | Not on "New Enquiries" (dead category); surfaces only via a rule-created FollowUp with a wellness-call outcome vocabulary (no "booked"/"not interested" options for leads) | `stage='new_enquiry'` never written |
| Estimate given, undecided (Rajesh) | **Nothing** | plan-sync leaves `follow_up_date` NULL; both board categories require it; nudge rule stage mismatch (`prospect` vs `quoted`); `estimate_followup_3d` keys on Smart-Presentation send only |
| Accepted, never scheduled (Anita) | **Nothing** — worse, shown in "Converted" column as if finished | sync sets opp `completed`, which the board excludes; `recall_approved_plan` dead |
| Mid-plan dropout (Prakash) | **Nothing, for ~5 months** | no ongoing-plan-no-appointment scan; post-op/7-day triggers are exact-single-day matches long past |
| In-clinic rejection (Ramesh) | **Negative work** — opp stays open at 'quoted', inflating pipeline value and potentially generating chase calls for refused treatment | update() never calls syncStage('declined') |
| Defer (Kavita) | **Nothing** unless staff improvise — and each improvisation path (opp date / Add Call / manual recall) creates a different row type with no shared state | no defer state exists anywhere |
| Cancelled appointment | Nothing (only no-show has a rule) | no listener for `appointment.cancelled` |
| Any of the above for a patient without `relationship_id` | Nothing at all | RulesEngine skips all rules silently |

---

## 6. TODAY'S ACTIONS FORENSIC ASSESSMENT

The engine is the right idea with five mechanical defects: (1) three dead categories via one wrong string and two nonexistent method calls, hidden for months by the per-category try/catch (defensive catch is correct; it needs an operator-visible health signal); (2) no date semantics on `recall_calls` — no due-window, no aging, priority ignored in sort; (3) caps without counts — `limit(50)` and a header that shows the capped number; (4) no ownership dimension at all; (5) dismissals scoped to one calendar day for live-computed categories → immortal cards (lab_ready, payment_reminders reappear daily forever). The `includeDone`/`last_call` annotation and the mandatory-reason dismiss flow are good design. The **TodayActionsProjector** (flag `today.projection`, off) is a genuinely disposable, transaction-rebuilt read model — the correct architecture, already built, currently unused (and the projection path drops the done/last-call annotations, so it isn't a drop-in switch yet).

---

## 7. STAFF COGNITIVE-LOAD FINDINGS

Reception must reconcile **8 surfaces over 5 stores** where: the same obligation appears 2–6 times; resolved work stays visible (done tasks bucket, closed rows in pipeline default query) while unresolved work disappears prematurely (mobile `waiting_for_patient` rows vanish from the board; Yesterday cards expire after one day regardless of resolution; completed-FollowUp leads vanish with the lead still open); due dates are largely meaningless (frozen at creation); priority is set but almost never sorted on; ownership is enforced only on the board reception doesn't use — and there enforced *against* them (unassigned system tasks invisible); attempt history is invisible at the point of calling. Every one of these is a code fact, not a perception. The CEO's operating principle (one patient / one context / one next action / one owner / one due date / complete history) is violated on all six axes today.

---

## 8. SOURCE-OF-TRUTH MAP (PRE concepts)

| Concept | Today | Trustworthy? |
|---|---|---|
| Lead status | `leads.stage` | ✅ (one writer path, ledger) — but `new_enquiry` ghost value in board code |
| "Last visit" | `patients.last_visit_date` | ❌ **dead column — no writer** |
| Recall eligibility | derived from dead column | ❌ |
| Recall work item | comm_queue `pending` — but recall also lives in `tasks` (inline 6-mo) | ❌ two stores |
| Follow-up work | `follow_ups` + `tasks` + `leads.followup_date` + `opportunities.follow_up_date` | ❌ four stores |
| Attempt history | comm_queue counters (mobile-only) + activities (today-only visibility) + lead_activities | ❌ fragmented |
| Outcome semantics | `action_option_lists.closes_task` (web) vs `OutcomeAutomationService` map (mobile) | ❌ two vocabularies, opposite behaviors ("will call back") |
| Opportunity stage | `treatment_opportunities.status` | ⚠️ single store, but rots on in-clinic rejection and misrepresents accepted-unscheduled as "Converted" |
| Overdue-ness | `is_overdue`/`sla_breached` | ❌ computed nowhere on a schedule — always false |
| Ownership of relationship work | comm_queue name-string / tasks FK / nothing on board | ❌ |

---

## 9. PATIENTS ↔ PRE ↔ APPOINTMENTS INTEGRATION REQUIREMENTS

What each module must PROVIDE (not merge) for the journey to close:

**Patients/Clinical provides (all already scoped in V1.1):** stored plan decisions (presented/accepted/rejected/deferred) as consumable facts + events; real item-level treatment progress; one clinical-completion rule; and — new requirement surfaced by this audit — **a reliable "last clinical activity" fact** (either write `patients.last_visit_date` transactionally on visit/consultation completion, or expose a derived query PRE can consume). PRE must stop deriving decisions (defer heuristic) and consume these facts.

**Appointments provides:** "future appointment exists for patient X" as a queryable contract (exists today as a query — used by exactly one recall trigger; must be applied to all); appointment created/attended/cancelled/no-show **events** that PRE can subscribe to for reconciliation (today only no_show emits a consumed event). Booking/attendance must be able to close or annotate open PRE work — the reconciliation hook lives on the Appointments side of the contract, the closure decision on PRE's side.

**PRE consumes and owns:** the recovery obligation itself — one open "relationship obligation" per patient situation, its owner, due date, attempts, outcomes, and next action. PRE must not re-derive clinical or scheduling truth (it currently re-derives both, badly, from a dead column and no data).

**Contract style:** the ActivityEngine event spine + `DB::afterCommit` → RulesEngine pattern already works (missed-appointment proves it end-to-end). The integration requirement is not new machinery — it is more producers (Patients: decision events; Appointments: booked/attended events), more listeners (PRE reconciliation), and fixed inputs.

---

## 10. RECOMMENDED TARGET MODEL — DISCUSSION ONLY

Target operating picture (no module merges): **one relationship-work spine, one daily read model, event-driven reconciliation.**

- `communication_queue` becomes the **only** store for relationship work (it already has purpose, priority, attempts, outcomes, SLA fields, close semantics — the richest of the five). `tasks` returns to clinical/admin/maintenance work. `follow_ups` folds into the queue over time. Leads and opportunities keep their pipelines but express "someone must act today" as queue rows, not parallel date fields.
- **Today's Actions becomes a projection, not 13 live queries** — the existing `TodayActionsProjector` pattern, extended: collapsed to **one card per patient** (all open obligations grouped), due/overdue semantics, honest counts ("50 of 1,810"), an owner on every card, attempt history in the drawer.
- **Outcomes route through one engine on both web and mobile** (`OutcomeAutomationService`), so every outcome either closes-with-reason or schedules-the-next-touch. No third state.
- **Reconciliation listeners:** appointment booked/attended → close or annotate the patient's open recall/queue rows; decision recorded → close estimate-chase; plan completed → schedule recall from a real clinical fact.
- **Detectors** for the two invisible queues: active-plan-without-future-appointment; estimate-given-undecided (replaces the dead trigger + NULL-date invisibility).

## FINAL QUESTION — the MINIMUM architecture change

> If we designed Patient Journey V1.1 around current working code, what is the minimum change that makes the journey understandable and actionable for staff?

**Not a rewrite, not a merge. Five surgical moves, in order:**

1. **Fix the inputs (days, not weeks).** Write `last_visit_date` on visit/consultation save (one service hook — Patients V1.1 owns it); fix the three dead board categories (one string + two method names); record plan decisions (already V1.1 Slice 2); make the in-clinic reject path call the existing `syncStage('declined')`.
2. **One work spine.** Stop producing relationship work into `tasks` (three producers move to `communication_queue` rows); reception's world becomes one store. No table merges — just redirect the producers.
3. **One read model.** Turn on and extend the already-built `TodayActionsProjector`: one card per patient, honest counts, due/overdue, owner. This is the smallest path to "minimum number of correct actions today" because the projection architecture already exists and is tested.
4. **One outcome engine on both surfaces.** Point web `logAction` at `OutcomeAutomationService`. Every outcome then closes or schedules — the backlog stops being immortal.
5. **Two reconciliation hooks + two detectors.** Appointment booked/attended closes matching open queue rows; scans for ongoing-plan-no-appointment and quoted-no-decision write queue rows using the existing engine pattern.

Everything else — pipelines, Huddle consolidation, lead dedup, projection polish — is iteration on top. These five moves alone make the six CEO axes (one context, one action, one owner, one date, history, nothing silent) mechanically true using code that already exists.

---

*Audit complete. No code, migrations, tasks, queue rows, or rules were changed. Runbook note for the review: confirm on the production `feature_flags` table whether `automation.engine` is ON (determines which of the two divergent recall paths is live) before interpreting prod backlog behavior.*
