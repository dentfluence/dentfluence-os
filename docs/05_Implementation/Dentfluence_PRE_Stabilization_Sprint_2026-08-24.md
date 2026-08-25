# PRE STABILIZATION SPRINT — EXECUTION PLAN **v2**
**Prepared 24 August 2026 · revised same day after red-team review · Go-live 14 September 2026 (15 working days)**
**Honest total: ~6 dev days in TWO ISOLATED WINDOWS (Sprint A: 3d core · Sprint B: 3d recall surgery + activation) + a slip-safe polish bucket.**

> **v2 changes from v1 (red-team accepted):** real calendar math replaces "net +1–1.5 days" · R-5/backlog/flag-activation isolated into Sprint B with canary criteria · 1,810-row close is a formal data-change event · pre/post leakage baseline defined · staged flag activation with rollback playbook · finance-vs-PRE decision forced with a recommendation · named human-verification owners. **Rejected:** WhatsApp "credibility hit" as DoD scope (nothing has ever sent in prod — no regression exists), load-testing the aging job (wrong scale), chaos suite (downgraded to one idempotency guard + test).
>
> **Diagnosis sources (do NOT re-derive):** `docs/04_Modules/Dentfluence_PRE_Full_Audit_2026-08-21.md` (P-1…P-10) · `docs/04_Modules/Dentfluence_PRE_Recall_Engine_Reference.md` (R-1…R-13) · `docs/04_Modules/Communication/pre-relationship-engine-audit.md` (07-25) · `docs/05_Implementation/Dentfluence_V1_Master_Gap_Register_2026-08-21.md` (FROZEN backlog — this sprint executes G-11 + appends G-24…G-31; each fix updates its row).

---

## 0. THE FORCED DECISION (CEO — one answer required before Day 1)

15 working days remain. Sprint A (3d) + Sprint B (3d) + remaining finance P0 work (~8–10d per register) = 14–16 days against 15. **It fits only with formal drops.** Recommendation:

- **Day 1 morning, before PRE:** knock out the two tiny finance P0 wins — **G-03** (Huddle → ReportMetricsService, ~2h) and **G-12** (CA export column, ~15min). They're register Week-1 items and too cheap to sequence-fight over.
- **Then Sprint A (PRE core, 3d) → finance heavy week (G-01/G-02/G-04/G-05) → Sprint B (PRE recall surgery + activation, 3d) → cutover week.** Interleaving Sprint B after the finance week also gives the WABA application time to come back.
- **Drop NOW, formally, in the register:** G-10 (chair time) and G-14 (acceptance reporting) — already the register's named candidates — plus this plan's polish bucket (§5) becomes slip-safe.
- If you refuse the drops, something else slips past 14 Sep. There is no third option; pick.

## 0.5 BASELINE — captured Day 0, BEFORE any code (≈1h, SQL only)

Five numbers, re-measured after each sprint. These, not "suite green", define success:
1. **Reappearing-action rate:** count of queue items dismissed/closed in last 7 days that re-rendered on Today the following day.
2. **Aging honesty:** count of open queue rows with `follow_up_date < today` (the invisible backlog; currently ~1,810 + whatever has accrued).
3. **Per-trigger fire rate:** rows created per trigger per `recall:run` over last 14 days (expected today: trigger 2 = 0, trigger 3 ≈ 0 — the dead ones; after Sprint B both > 0).
4. **Outcome divergence:** count of web 'will call back' outcomes that closed with no rescheduled follow-up (mobile reschedules +2d; web closes forever).
5. **Orphaned opportunities:** active opportunities with zero open queue item/task.
Also Day 0: SSH VPS — `docker compose ps` + scheduler log **history** (not just "up now": pull the last 14 days of `recall:run`/`schedule:run` evidence). If the scheduler has been intermittent, all staff bug reports and the counts above are suspect and Sprint A scope gets re-checked against reality first.
And: **start the Meta WABA application + template submissions** — the only third-party clock; runs in parallel with everything.

---

## SPRINT A — THE LEAKAGE CORE (3 days, committed)
*No flag flips, no migration, no backlog close, no parity re-convergence in this window. Pure correctness on the live paths.*

### A1 — Input correctness (Day 1, after G-03/G-12)
- G-11: TreatmentVisit observer advances `last_visit_date` (mirror the Consultation observer). **[B]**
- 3 dead Today categories: `new_enquiry`/`new_lead` stage mismatch · implement `isOverdue()` · implement `daysUntilExpiry()`. **[B]**
- R-3: NULL-safe `visit_type` filter. **[B]**
- Test: feature test per fix; `today-actions:health --fresh` 12/12 healthy, real counts.

### A2 — One outcome path + automatic accountability (Day 1–2)
- ALL web outcomes route through `OutcomeAutomationService` (kills web-closes-'will call back'-forever). **[B/D-lite — the riskiest Sprint A item; budget half a day, it has multiple call sites]**
- Outcome advances `follow_up_date`. **[B]**
- **Idempotency guard:** double-submit / simultaneous web+mobile outcome on the same queue item resolves to one outcome, one activity row. One guard, one test. **[B]**
- P-6: real `Auth::id()`/branch attribution in `TaskEngine::autoCreate()`. **[B]**
- Verify ActivityEngine stamps who/when on every mutation path (verify, don't rebuild).

### A3 — Today vs Pending + dismissal that sticks (Day 2–3)
- Today = open ∧ due today · **Pending = open ∧ due < today** — same card component, one query difference. **[C — but verify the health checks and bulk-dismiss path tolerate overdue semantics; the "simple filter" is where the hidden assumption lives]**
- Schedule the aging pass (`recalculateOverdue`/`sla_breached`) — currently fires from one legacy screen only. **[B]**
- Dismiss writes persistent closed state honoring cooldown (with G-11 = the reappearing-actions kill shot). **[B]**
- Honest counts ("+N more"), cap YesterdayReview (R-11). **[B]**
- Pending double-count check: assert no row renders in both Today and Pending, and none in neither while open. **[test]**
- P-4/G-27: Task category card on Today (~4h). **[D-lite — last item; slips to Sprint B if A2 expanded]**

**Sprint A DoD:** baseline metrics 1, 2 (partially), 4, 5 improve measurably; dismissed action gone next day; missed call in Pending only; web=mobile outcomes; every action auto-attributed. **892-suite green. No flags flipped yet.**
**Human verification (owners, week after Sprint A):** reception (Samiksha) works ONLY from Today+Pending for one week; CEO reviews two numbers daily — Pending count and %-of-actions-with-recorded-response. If staff route around the queue, that's a defect report, not a training issue.

---

## SPRINT B — RECALL SURGERY + ACTIVATION (3 days, separate window, after the finance week)
*Everything the red-team correctly called the highest-risk cluster, isolated with canary criteria.*

### B1 — Trigger repair (Day 1)
- R-1/G-28: trigger 2 reads `plan_decisions` (canonical — NEVER widen the plan enum; PlanLifecycleService is sole status writer). **[B]**
- R-2/G-29: second cooldown stamp — **the sprint's only migration**, deployed alone, `mysqldump` first per standing rule. **[D-lite]**
- R-4: exact-date → lookback window. **[B]**
- Opportunity reconciliation hook: stage change closes its open action. **[D-lite]**

### B2 — The 1,810-row close as a FORMAL DATA-CHANGE EVENT (Day 2)
1. `mysqldump` (standing rule — no automated backup exists, P0-1 open).
2. `recall:run --dry-run` on current code — record expected next-day creation volume.
3. Close a **sample of 50** stale rows → verify next `recall:run` does NOT recreate them (proves G-11 + cooldown + dismiss fixes hold against history).
4. Staged apply: close by cohort (oldest month first), re-check creation volume after each cohort.
5. Set `recall.effective_from` (CODE-DONE-UNTESTED — test it now) + R-10 daily cap.
6. **Rollback:** restore from dump; abort criteria = any closed row reappearing, or next-day creation volume > 2× dry-run expectation.

### B3 — Parity + staged activation (Day 2–3)
- R-5: re-converge the two recall paths; `automation:parity recall` until clean — until then `automation.engine` rollback stays UNSAFE and no recall-related flag moves.
- R-6/G-31: repoint Huddle:190 + profile badges to live queue state. **[B]**
- `relationship:backfill` dry-run → `--apply`.
- **Flag activation — one at a time, 24–48h observation each, in this order:**
  1. `today.projection` (after `today:rebuild-projection --check` clean; biggest win, instant rollback).
  2. `activity.single_ledger_reads` (after `relationship:timeline-parity --sample=25` clean).
  3. The two journey-column display flags (read-only).
- **Post-flip playbook (on-call = whoever deployed):** Today shows duplicates → flag OFF, rerun `--check`, diff. Empty/missing category → `today-actions:health --fresh`, flag OFF if a category went dark. Counts diverge from pre-flip baseline by >10% with no data cause → flag OFF. Every rollback is one flag flip; that is why they go one at a time.

**Sprint B DoD:** metrics 2 and 3 land (backlog ≈ 0 and stable; all six triggers fire > 0 where cohorts exist); parity clean; flags live and stable through their observation windows; register rows updated.

---

## §5 POLISH BUCKET — slip-safe, do only if a window underruns
₹ value-bucket filter · opportunity stage labels (config-only, verify vocabulary first) · recall type filter chips · remove 7 inert flags (G-25, 2h) · route/delete webhooks (G-26, 1h) · hide Score on All Relationships. None of these breaks the leakage invariant by slipping to the pre-cutover week or V1.1.

## §6 FREEZE / §7 DEFER — unchanged from v1
Freeze: ActivityEngine · 9 rules+emitters · TaskEngine dedup · CommunicationGuard precedence · OutcomeAutomationService internals · 12-category fault-tolerance pattern · identity stack · parity toolkit · queue schema · screen layouts · Treatment Visit UX · WhatsApp code (channel only, NO inbox, ever).
Defer: protocol packs (V1.1 — as data on the existing queue; first verify what `protocols:generate` at 00:10 already does) · WhatsApp live sending beyond staff test (Meta's clock; `guard.fail_closed` only after one real delivery) · analytics build-out · scoring/`insights.signals` (zero work_outcome data) · `search.index` · `workflow.engine` · `rules.single_engine` · all `integration.*` · AI scripts · automation builder.

## §7.5 LEAKAGE MODEL — unchanged from v1
One invariant: every important relationship has ≥1 open queue item with due date + owner, or no open work by explicit decision. One spine (`communication_queue`) · one read model (TodayActionsProjector) · one outcome path (OutcomeAutomationService) · one ledger (ActivityEngine). Closing events: lead stage change · consultation done · plan decision (`plan_decisions`) · opportunity stage change · appointment booked/attended/missed · visit completed · outcome recorded · dismissal-with-cooldown. Detectors: ongoing-plan-no-appointment · plan-given-no-decision.

---
*v2, 24 Aug 2026. Supersedes v1 (same file, same day). New findings during execution get the next register ID — they never trigger a new audit.*
