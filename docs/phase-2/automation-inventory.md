# Phase 2 — Automation: Surface Inventory & Characterization

> **Status:** Sprint 1 (characterization + design). **No cutover.** Additive only.
> Generated 2026-07-02 as the source-of-truth map for consolidating every
> "when X happens (or when time T arrives), do Y" pathway into **one Rules Engine
> (decides)** + **one Automation Engine (executes time-based work)**.
>
> Governing docs: `docs/implementation-blueprint-v1.md` §3 "Phase 2 — Automation",
> `docs/target-architecture-engine-first.md` (Rev 3) §B1 Rules / §B2 Automation.
>
> Flags (already declared in `config/features.php`, default **off** = today's behaviour):
> - `automation.engine`   — Automation Engine owns recall/reminders/retries/cooldowns
> - `rules.single_engine` — legacy `FollowUpRulesService` retired in favour of Rules Engine

---

## 1. The two roles Phase 2 sorts everything into

| Role | Owns | Never does |
|------|------|-----------|
| **Rules Engine** (`app/Services/Relationship/RulesEngine.php`) — *reactive* | A fact occurred → decide what should happen; request the action | Send, schedule, retry, cooldown timers |
| **Automation Engine** (to be built) — *temporal* | Run at time T; recall scheduling; reminders; retries; cooldowns; expirations; nightly "who is due?" sweeps | Decide policy, send directly, create tasks directly |

The tell for mis-placement: if a change can't say whether it belongs in Rules or
Automation, it is conflating **decision** with **timing** — split it.

---

## 2. Existing automation surfaces (what we consolidate, not rebuild)

### A. Decision layer (reactive)

| Surface | File | Lines | What it does today | Target |
|---------|------|-------|--------------------|--------|
| **RulesEngine** | `app/Services/Relationship/RulesEngine.php` | 316 | Config-driven (`config/relationship_rules.php['rules']`, 11 rules). Invoked from `ActivityEngine::log()` inside `DB::afterCommit()`. Per-rule: cooldown check (`relationship_rule_logs`) → condition match → `fireAction` (create_task / create_reminder / send_notification / compound) → log firing + `rule.fired` activity. Never throws (FailSafe job). | **KEEP** — this is the single Rules Engine. Legacy decision logic folds into its config. |
| **FollowUpRulesService** | `app/Services/Communication/FollowUpRulesService.php` | 147 | Legacy. Reads `config/followup_rules.php` + `followup_settings.php`; `resolve($triggerType,$value,$sub,$ctx)` returns follow-up rows (working-day-aware due dates). Header explicitly says "logic being migrated to RulesEngine (Phase 5). Do NOT add new rules here." | **RETIRE** behind `rules.single_engine` after parity. Callers routed to Rules Engine. |
| **LeadFollowUpService** | `app/Services/Prm/LeadFollowUpService.php` | 69 | PRM wrapper: on lead stage change, resolves `prm_stage_changed` rules via `FollowUpRulesService` and inserts `FollowUp` rows (dup-guarded). Gated by `config('prm.followups.enabled')`. | Follows FollowUpRulesService — re-point to Rules Engine after parity. |

### B. Time-based execution layer (temporal) — today scattered across services + commands

| Surface | Service file | Command | Schedule | Writes | Dedup / cooldown today | Target |
|---------|-------------|---------|----------|--------|------------------------|--------|
| **Recall Engine** | `app/Services/RecallEngineService.php` (504) | `recall:run` (`RunRecallEngine.php`) | daily 07:00 | `communication_queue` rows (`source_engine='recall'`) | Per-trigger `hasOpenQueueItem(patient,purpose)` + `recall_*_queued_at` stamps on source records; `NO_VISIT_COOLDOWN_DAYS=30`, birthday once/yr | **MOVE** under Automation Engine (first cutover). |
| **Appointment reminders** | `app/Services/Relationship/AppointmentReminderEngine.php` (129) | `relationship:appointment-reminders` (`RunAppointmentReminders.php`) | daily (doc says 08:00) | `tasks` rows (category `call`, "Reminder call: {name}") | Task existence check: same patient + `due_date=today` + title prefix | **MOVE** under Automation (second cutover). |
| **WhatsApp reminders** | (command-owned) | `whatsapp:send-reminders` (`WhatsAppSendReminders.php`, 106) | daily 10:00 | WhatsApp sends for tomorrow's SCHEDULED appts | Idempotent dedup key; DPDP-gated; dormant unless `WHATSAPP_ENABLED` | Second cutover (reminders). Routes send via Communication later (Phase 4). |
| **Reminder task creator** | `app/Services/Relationship/ReminderEngine.php` (129) | (none — called by RulesEngine action `create_reminder`) | on-event | `tasks` (category `follow_up`, desc `[reminder:{type}]`) | Open-task dedup by relationship + type | Stays as an *executor* invoked by Automation/Rules; not a scheduler. |
| **High-value lead escalation** | (command-owned) | `comm:auto-escalate` (`AutoEscalateHighValueLeads.php`, 178) | every 30 min | escalations / notifications for ₹30k+ leads uncontacted 2h | (internal) | Temporal sweep → Automation (later cutover / leave as-is initially). |
| **Task reminders** | (command-owned) | `tasks:shift-reminder` (5 min), `tasks:periodic-reminder` (2h) | interval | in-app notifications | `withoutOverlapping` | Temporal → Automation candidates; **known double-registration risk** (see §4). |
| **Lab overdue tasks** | `LabAlertService` / cmd | `lab:create-overdue-tasks` | daily 09:00 | `tasks` for overdue lab cases | active-task check | Temporal sweep → Automation candidate (later). |

### C. Shared sink

| Surface | File | Role |
|---------|------|------|
| **CommunicationQueue** | `app/Models/CommunicationQueue.php` (535) | The queue table recall writes into and reception works from. Not an engine — a store. Untouched by Phase 2 mechanics; only its *producers* get consolidated. |

### D. Wiring already in place (reuse, don't reinvent)

- **Domain-event bus + `ActivityEngine::log()`** already fans events to RulesEngine in `afterCommit` with an outer safety net. This is the publisher Phase 2 consolidates onto.
- **`relationship_rule_logs`** table (+ decision columns) already backs cooldown + Decision Log.
- **Feature flags** via `App\Support\Features\Feature::enabled()/set()`; per-branch scope supported.
- **FailSafe** via `RelationshipAutomationFailedJob`.

---

## 3. Duplication / overlap findings (the "why" for consolidation)

1. **Two decision engines.** `RulesEngine` (config `relationship_rules.php`) vs legacy
   `FollowUpRulesService` (config `followup_rules.php`). Both answer "what follow-up
   for this trigger?" → double source of truth. `rules.single_engine` retires the legacy one after parity.
2. **Overlapping reminder producers.** RecallEngine (→ `communication_queue`),
   AppointmentReminderEngine (→ `tasks`), WhatsApp reminders (→ WhatsApp), and the
   RulesEngine `appointment_reminder` rule (→ `tasks`, "Appointment reminder call")
   can all fire around the same appointment → **risk of double-contact**. No shared
   "already contacted, hold" arbiter across them. This is the headline Phase 2 fixes.
3. **Scheduling scattered across `routes/console.php`** with per-command dedup logic
   reimplemented each time, instead of one Automation owner of cooldown/retry/expiry.
4. **`tasks:shift-reminder` / `tasks:periodic-reminder` registered twice** historically
   (double-firing) — see `automation-map.md`. Consolidation removes the duplicate emitter.
5. **LATENT BUG — `AppointmentReminderEngine` cannot create tasks (discovered Sprint 1).**
   It hardcodes `created_by => null`, but `tasks.created_by` is `NOT NULL` with an FK to
   `users`. So on a real appointment the task INSERT throws `QueryException` — the
   `relationship:appointment-reminders` path is effectively broken on the current schema
   (the WhatsApp reminder path, `whatsapp:send-reminders`, is the one actually live). This
   is pinned by `AppointmentReminderCharacterizationTest` as a *known* current behaviour.
   **Fix candidate for the reminders cutover slice:** give system-generated tasks a system
   actor (a designated system user id) or make `tasks.created_by` nullable — this ties
   directly into the blueprint's "System Tasks" concept. Decide with Sumit before changing.

---

## 4. Characterization safety net (Sprint 1 deliverable)

Tests that pin **today's** behaviour BEFORE any refactor, under
`tests/Feature/Characterization/` (picked up by the existing "Feature" suite):

| Test file | Pins |
|-----------|------|
| `RecallEngineCharacterizationTest.php` | no-visit-6mo queues a `recall_no_visit` item + stamps `recall_no_visit_queued_at`; re-run is idempotent (no duplicate); a recent visitor is not queued. |
| `AppointmentReminderCharacterizationTest.php` | tomorrow's scheduled appt → one `call` task "Reminder call: {name}" due today; re-run is idempotent; a cancelled appt gets no reminder. |
| `RulesEngineCharacterizationTest.php` | `getRulesForEvent()` returns enabled rules matching a trigger and is empty for unknown events; `checkCooldown()` is true when never fired and false after a recent firing. |

These describe behaviour as it is **today** — a safety net, not a spec of the target.
Every subsequent slice must keep them green.

---

## 5. Proposed Phase 2 slice plan (each additive, flag-gated, reversible)

> Sprint 1 = slices 1–2 below (characterization + design). No production behaviour changes.
> Nothing after slice 2 is built without explicit confirmation.

- **Slice 1 — Characterization + inventory** ✅ DONE. This document + 3 characterization tests.
- **Slice 2 — Automation Engine skeleton** ✅ DONE. `app/Services/Automation/AutomationEngine.php` (4 primitives) + 11 unit tests.
- **Slice 3 — Shadow dual-run (recall)** ✅ DONE. `RecallShadowRunner` + `automation:parity recall` + `automation_shadow_log` table.
- **Slice 4 — Recall cutover** ✅ DONE & LIVE. `RecallAutomationRunner` + flag-gated `runAll()`; parity on real data = 3,830 candidates / 0 divergence; `automation.engine` flipped on.
- **Slice 5 — Reminders cutover** ✅ DONE. `ReminderAutomationRunner` (fixes the `created_by` NULL bug via system actor) + flag-gated command + `automation:parity reminders`.
- **Slice 6 — Rules consolidation** ✅ DONE (awaiting flag flip). `FollowUpRuleEngine` (config ported) + `rules.single_engine` seam at the 2 call sites + `automation:parity rules`. Legacy kept warm.
- **Wrap-up** ✅ reminder-overlap guard (`RulesEngine::shouldDeferToAutomation`), suppression Decision-Log summary in the recall runner, and `docs/phase-2/go-live-runbook.md`. Confirmed: task-reminder commands are single-registered (old double-fire already fixed); `appointment.booked` is not dispatched (that rule was dormant).

**Guardrails on every slice:** shadow + parity before any flip; characterization tests
stay green; small and individually verifiable; instant rollback by flipping the flag off;
legacy paths keep working throughout; journeys / PRE screens untouched.
