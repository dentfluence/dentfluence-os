# Patient Journey V1.1 — Slice 0.2: VPS Live Data Census

**Date:** 2026-07-25 · **Read-only:** yes — zero writes, zero migrations, zero deploys.
**VPS:** `srv1791841.hstgr.cloud`, Docker `/opt/dentfluence` · `env=production` · `db=dentfluence` (MySQL 8.0)
**Deployed commit:** `db2d4d9` — one commit-set behind baseline `8573615`; delta is docs + smoke suite + one test fix only (zero production code). No deploy performed.
**Migrations:** current through `2026_07_24_130000` (all Ran, none pending).
**Host observations:** modified `deploy.sh` + untracked `backups/` on the host clone (outside app image).

## 1. Top-level production counts

| Table | Rows | Table | Rows |
|---|---|---|---|
| patients | 3,538 | consultations | 23 |
| relationships | 3,556 | treatment_plans | 27 (25 live) |
| leads | 7 | treatment_plan_items | 101 |
| follow_ups | 63 | treatment_visits | 45 (30 patients) |
| tasks | 153 | prescriptions | 16 |
| communication_queue | 3,693 | lab_cases | 5 |
| treatment_opportunities | 24 | appointments | 185 (179 live) |
| comm_activity_logs | 6,398 | today_actions | 110 |
| activities | 4,513 | wa_threads / wa_messages | 46 / **0** |
| relationship_journeys | 24 | patient_communications | **0** |
| relationship_contact_log | 1 | relationship_notifications / escalations | 0 / 0 |

Materially participating stores confirmed: the 4 obligation systems (communication_queue, follow_ups, tasks, treatment_opportunities), 2 history stores (activities, comm_activity_logs), today_actions projection, clinical spine (consultations → treatment_plans → treatment_visits), appointments, leads/relationships.

## 2. communication_queue (proposed spine)

- **3,689 of 3,693 rows (99.9%) are recall** (`comm_type=existing_patient`, `source_engine=recall`); manual use is 4 rows lifetime (2 vendor, 1 lab, 1 b2b lead).
- Status: `closed` 3,618 · `pending` 74 · `waiting_for_patient` 1. Open = **75 rows**.
- **`due_at` is NULL on 100% of rows** — scheduling rides on `follow_up_date` instead (set on 74/75 open rows; **71 already past**, 3 future).
- **`assigned_to` empty on 3,689 rows** — ownership is not used in practice.
- Outcomes: `outcome` set on 11 rows lifetime; `response_notes` on 1. Effectively no outcome history.
- **The audit-era "1,810 recall backlog" no longer exists as open work: 3,511 rows were bulk-closed on 2026-07-05** (`dismissed_at` set on 3,607; comm_activity_logs shows 6,395 `closed` actions, all by Dr. Sumit Firke). The queue has been re-accumulating since 2026-07-17 at ~9/day → current 74.
- Oldest open: b2b lead item 2026-07-11; recall items 2026-07-17 → 2026-07-25 (daily 07:00 cron).
- Linkage: only 4 rows lack patient_id; only 4 patients lack relationship_id. Structural linkage is clean.

## 3. follow_ups

63 total (0 trashed): pending 18 (**17 overdue**, 1 future), completed 45. Triggers: manual 39, `prm_stage_changed` 24 (all auto). Linkage: 39 patient-linked, 24 lead-linked, 0 orphans; 38/39 patient-linked reach a relationship. **Anomaly: lead #24 has two identical auto follow-ups (ids 70 & 72, same due date 07-18) — the `prm_stage_changed` trigger double-fired.**

## 4. tasks

153 total: pending 118, done 35 — **112 of 118 pending are overdue (95%)**. By category×type: call/human 68 (41 pending, all overdue) · whatsapp/system 64 (**64 pending, 61 overdue** — auto-created WhatsApp tasks are effectively never worked) · follow_up/human 12 · call/system 7 · other/human 2. Conceptual split: ~144/153 (94%) are patient-contact work (A); ~9 internal (B). Linkage: 148 patient-linked, 71 relationship-linked. Only 3 tasks are recall-titled.

## 5. Recall — production truth

- **Exact open recall population: 74** (74 patients, 0 with multiple open rows — the post-reset queue is deduplicated). Lifetime queued: 3,689. Physical home: communication_queue only; tasks hold just 3 recall-titled items (3 patients overlap task+queue).
- Of the 74 open: **5 have a future scheduled appointment** (queued despite a booked visit), **0 in active treatment**, **0 returned since queued** (via treatment_visits).
- Sample trace: patient 3525 visited 2026-07-16 and was recall-queued 2026-07-17 — because the engine reads `last_visit_date`, which is NULL for everyone (§6). The engine effectively samples the whole patient base dateless; the 07-05 mass-closure was the manual cleanup of exactly that behavior.
- Due dates: `due_at` never set; `follow_up_date` mostly already past on creation. "Overdue/today/future" is not currently a meaningful recall dimension in production.

## 6. last_visit_date — forensic report

- **3,538 of 3,538 patients NULL (100%). The column has never been written.** No non-NULL values, no impossible values — there are no values at all.
- Cross-checks: appointments provide **zero** attendance evidence (no `done` rows exist, §8), so "NULL but attended appointment" = 0 by vacuity. Against treatment_visits: **30 patients have real visit evidence newer than their (NULL) last_visit_date**; 23 consultations similarly.
- Implication for Gate A: the only reliable attendance signals in production are `treatment_visits.visit_date` (45 rows/30 patients, since go-live) and `consultations.consultation_date` (23). Appointment statuses cannot back-fill attendance. Pre-go-live history does not exist digitally.

## 7. treatment_opportunities

24 total: completed 9, quoted 8, discussed 4, accepted 2, declined 1 (status vocab: prospect/discussed/quoted/accepted/declined/completed). 15/24 linked to treatment plans; 0 orphaned from patients; 2 lack relationship_id. **0** open opportunities with a dead plan; **0** stale (>60d without update). Small and clean.

## 8. Appointments

179 live rows, range **2026-06-30 → 2026-07-25** (module in real use only since go-live; no pre-go-live history). Status: scheduled 157, checkin 5, cancelled 17, **done 0, no_show 0 — no appointment has ever been completed or no-showed in the system**. **143 past appointments are stuck `scheduled`**; 5 stuck `checkin`. Future scheduled: 14. Orphans: 0.
"Does this patient have a future appointment?" — **RELIABLY COMPUTABLE** as `status='scheduled' AND appointment_date>=today` (past rows never resolve, so date-filtering is mandatory). Attendance/no-show truth — **NOT COMPUTABLE** from appointments at all.

## 9. Clinical journey states

| State | Count | Confidence |
|---|---|---|
| Consulted, no treatment plan | 4 patients | RELIABLE |
| Plan presented/open (pending) | 15 plans / 14 patients (0 accepted stamps) | RELIABLE |
| Plan accepted & ongoing | 7 plans / 7 patients (all `accepted_at` stamped) | RELIABLE |
| Plan rejected | not representable (no rejected status/stamp in plans; 1 declined opportunity) | GAP |
| Treatment started (has visits) | 30 patients | RELIABLE |
| Treatment completed | 3 plans / 3 patients | RELIABLE |
| **Ongoing treatment with NO future appointment** | **7 of 7 (100%)** | RELIABLE |

The headline dropout-risk cohort Journey V1.1 targets is not hypothetical: every single mid-treatment patient currently lacks a next appointment, and none has any open obligation in communication_queue (per-patient trace: open_cq=0 for all 7; 6 of 7 have an open task, mostly unworked/overdue).

## 10. Activity history

- **activities:** 4,513 rows, 2026-07-03 → today. 82% is `recall.queued` noise (3,689). Meaningful events exist but are thin: appointment.booked 144, call.logged 95, task.auto_created 71, rule.fired 71, birthday.approaching 64, payment.received 35, treatment_plan.created 24, consultation.completed 18… Actor attribution 478/4,513 (11%); description 16%.
- **comm_activity_logs:** 6,398 rows, 07-05 → 07-22. 99.8% is `closed` (6,384), essentially all from the one-day bulk cleanup; user + description present 100% but content is closure boilerplate.
- Verdict for "Why are we calling / what happened last time / who / outcome": **history is ~3–4 weeks deep, attribution-poor, and outcome-empty (11 outcomes lifetime).** The future context requirement must be built going forward; production history cannot retroactively supply it.

## 11. Duplicate obligations

- **High confidence:** follow_ups 70 & 72 (identical auto pair, lead 24) — 1 pair. Recall present in both task and queue — 3 patients.
- **Probable:** 60 of 74 open-recall patients also carry ≥1 pending task (mostly system WhatsApp/birthday — purpose overlap needs item-level triage); 7 patients with pending task + pending follow_up; 5 patients with open opportunity + pending task; 1 patient with open queue row + pending follow_up.
- **Manual review:** the 60-patient task∩queue set; patients 2859/447/2170 with 3–5 pending tasks each.
- Multi-row same-purpose within the queue itself: 0 (post-reset).

## 12. Migration risk map

**GREEN** — treatment_opportunities (24, clean, linked); leads (7, all relationship-linked); clinical spine plan/acceptance data (stamps consistent with statuses); structural linkage patient↔relationship↔queue (≤4 exceptions each); follow_ups volume (63, 0 orphans).

**AMBER** — tasks: 118 pending with 95% overdue means "pending" ≠ "actionable"; needs disposition triage before any compatibility migration (especially 64 dead system-WhatsApp tasks). follow_ups pending 18 (17 overdue) — small, hand-reviewable. activities — usable as event log, but recall noise must be filtered and attribution is weak. Open queue items (74) — valid population but validity per row is doubtful (5 have future appointments; all queued off a dead column).

**RED** — `last_visit_date`: 100% NULL, never written; recall semantics must be decided at Gate A before any backfill/migration (candidate truth sources: treatment_visits > consultations; appointments unusable). Appointment lifecycle: 0 done / 143 past-stuck-scheduled — any journey logic keyed on appointment completion is unsafe until statuses are resolved by workflow or convention. Recall engine output as a whole: 3,689 queued from a NULL column ≈ "everyone", already mass-closed once (3,511 on 07-05); migrating open recall rows as-is migrates noise, not clinical truth. comm_activity_logs closure history: not meaningful as outcome data.

## 13. Unexpected findings (not predicted by the audits)

1. The 1,810 backlog is gone — resolved by a one-day manual mass-closure (07-05, 3,511 rows) — and the engine has been silently rebuilding it (~9/day).
2. Appointment status flow is unused in practice: zero `done`, zero `no_show` ever, 143 past rows frozen at `scheduled` — despite complete status machinery and passing tests.
3. `last_visit_date` is NULL for literally every patient (audits said "no writer"; census confirms "never once written").
4. 100% of in-treatment patients lack a future appointment, and none has an open queue obligation — the exact failure mode V1.1's treatment-recovery detector targets, live today.
5. `due_at` is dead across the entire queue; `follow_up_date` carries scheduling, usually already-past at creation.
6. Ownership (`assigned_to`) is unused across the queue.
7. `prm_stage_changed` auto follow-up can double-fire (lead 24).
8. Outbound message stores are empty (wa_messages 0, patient_communications 0) while wa_threads=46 — click-to-chat leaves no message history.
9. Digital history starts ~2026-06-30; the 3,538 imported patients carry no attendance history — recall for them can only ever be inferred, not read.

## Data changed: **NO** (SELECT/SHOW only, verified per query).
