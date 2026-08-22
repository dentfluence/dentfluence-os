# PRE — RECALL ENGINE: COMPLETE REFERENCE
**Traced from source 21 August 2026 · `E:\Dentfluence\Dentfluence_OS\Dentfluence Web`**

Everything the Recall Engine is, how it runs, what it reads and writes, where its output surfaces, and every defect found in this trace. Read this before touching any recall code.

> **Companion documents.** Prioritised backlog: `Dentfluence_V1_Master_Gap_Register_2026-08-21.md` (frozen). Backlog-dump history: memory `project_recall_migration_backlog_throttle`. Phase-2 cutover: `project_phase2_automation`.

---

## 1. WHAT IT IS

The Recall Engine is the **demand-generation half of PRE**. Once a day it scans the clinical database for patients who should be contacted, and writes a work item into `communication_queue` for each one. That is the whole job.

Three things it deliberately is **not**:

- **It is not a sender.** No recall message is ever transmitted. Every item is queued as `channel = 'call'` — a task for a human. The `note` field holds staff-facing copy (rendered from a `MessageTemplate` where one is configured) so the receptionist reads and sends the same wording by hand, usually via WhatsApp click-to-chat.
- **It is not the consent gate.** `CommunicationGuard` (with `guard.consent_required` ON in production) governs *sends*. Because recall items are phone-call tasks rather than messages, the guard is never consulted at queue time. A patient who has withdrawn WhatsApp consent still appears on the call list — correct, but worth knowing.
- **It is not the owner of "who is due".** It owns *"who should we contact today"*. The clinical notion of a recall interval — `patients.next_recall_date`, `treatment_types.recall_days` — is a separate, and currently **unwired**, concept. See §9 R-6 and R-7.

**Position in the loop:** `TREATMENT VISIT → last_visit_date → RECALL ENGINE → communication_queue → Today's Actions / Recall Pipeline → staff call → outcome → close or re-book`.

---

## 2. RUNTIME PATH

```
Laravel scheduler  routes/console.php:26
   Schedule::command('recall:run')
       ->dailyAt('07:00')
       ->withoutOverlapping()
       ->runInBackground()
       ->appendOutputTo(storage_path('logs/recall-engine.log'))
                 │
                 ▼
  app/Console/Commands/RunRecallEngine.php        `php artisan recall:run [--dry-run]`
                 │
                 ▼
  app/Services/RecallEngineService::runAll()      ← the orchestrator, 6 triggers
                 │
         ┌───────┴────────────────────────────────┐
         │ trigger 1 only                          │ triggers 2–6
         ▼                                         ▼
  Feature::enabled('automation.engine') ?    (always legacy, in-service)
         │
     ON  ├─► Automation\RecallAutomationRunner::runNoVisit()   ← LIVE in production
    OFF  └─► RecallEngineService::recallNoVisit6Months()       ← dormant fallback
                 │
                 ▼
        communication_queue rows  +  ActivityEngine 'recall.queued'  +  cooldown stamp
```

**Ordering matters.** `recall:run` is scheduled at **07:00**; the morning briefing runs at **07:05** and the membership scan at **07:10**, both deliberately after it so the day's recall items already exist when those read the queue.

**`--dry-run`** is a real preview: it executes `runAll()` inside a transaction that is always rolled back, so the printed counts are exactly what a live run would queue, with no surviving rows.

**Trigger order inside `runAll()`:** no_visit → approved_plan → post_op → lab_received → recent_tx → birthday. The order is significant — see §9 R-2.

---

## 3. THE TWO-PATH CUTOVER

Phase 2 Slice 4 moved **trigger 1 only** to the Automation Engine. The mechanism is a hard either/or so exactly one path can ever write the item — no double-queueing:

```php
// RecallEngineService::runAll()
if (app(AutomationEngine::class)->enabled()) {
    $this->summary['no_visit_6months'] =
        app(RecallAutomationRunner::class)->runNoVisit();
} else {
    $this->recallNoVisit6Months();
}
```

| | Legacy `RecallEngineService` | `RecallAutomationRunner` |
|---|---|---|
| Owns | Triggers 2–6 always; trigger 1 when flag OFF | Trigger 1 when flag ON |
| Cooldown | Inline SQL `whereDate('recall_no_visit_queued_at', '<=', $cooldown)` | `AutomationEngine::inCooldown($queuedAt, 30, $now)` |
| Chunking | `chunk(100)` | `chunkById(100)` |
| Flag | `automation.engine` (`config/features.php:60`, default **false**, enabled per-branch in `feature_flags`) | same |

Rollback is a flag flip. **But the two paths have since diverged and are no longer equivalent — see §9 R-5. The rollback is not currently safe.**

The other five triggers were meant to cut over the same way in later slices. They have not.

---

## 4. THE SIX TRIGGERS

Every trigger follows the same shape: **find candidates → check cooldown → check for an open duplicate → compose a note → write the queue item → log the activity → stamp the source record**.

### Trigger 1 — `no_visit_6months` → `purpose = 'recall_no_visit'`

The core retention trigger, and the only one under the Automation Engine.

| | |
|---|---|
| **Source** | `patients` |
| **Condition** | `last_visit_date <= now() - 6 months` (or NULL, when no go-live date is set) |
| **Cooldown** | 30 days, stamped on `patients.recall_no_visit_queued_at` |
| **Dedup** | No open `communication_queue` row with the same `patient_id` + `purpose` |
| **Priority** | `medium` · **Tags** `['recall','no_visit_6m']` · **SLA** 24 h |
| **Note** | `MessageTemplate` type `recall`, else *"Patient has not visited since {date}. Recall after 6 months of no activity."* |

The 6-month cutoff is a **hardcoded const in both files** (`NO_VISIT_MONTHS = 6` / `NO_VISIT_COOLDOWN_DAYS = 30`). The Settings field "General Recall periodicity" (`recall.general_days`, default 180) does **not** drive it — see §9 R-8.

**The go-live guard.** `AppSetting::get('recall.effective_from')` is the fix for the July backlog dump:

```php
if ($effectiveFrom) {                                  // migrated data excluded
    $q->whereDate('last_visit_date', '>=', $effectiveFrom)
      ->whereDate('last_visit_date', '<=', $cutoff);
} else {                                               // original behaviour
    $q->whereDate('last_visit_date', '<=', $cutoff)
      ->orWhereNull('last_visit_date');
}
```

Set it and imported patients with a null or pre-go-live `last_visit_date` are excluded from automatic recall until a real post-go-live visit is logged for them. Blank restores the old unrestricted scan. **It is still code-done-untested and, as far as this trace can tell, has never been set** — see §9 R-10.

**Live-path extra filters** (automation runner only, not legacy): `whereNotNull('phone')`, `->automationsEnabled()` (skips deceased / opted-out), `whereNull('contact_invalid_at')` (skips numbers flagged bad by call outcomes).

---

### Trigger 2 — `approved_plan_no_appt` → `purpose = 'recall_approved_plan'`

Accepted treatment with no appointment booked. Commercially the most valuable recall a dental practice has.

| | |
|---|---|
| **Source** | `treatment_plan_items` joined to `treatment_plans` |
| **Condition** | item `status = 'approved'` **AND** plan `status = 'approved'` **AND** plan created within 90 days |
| **Cooldown** | One-shot — `treatment_plan_items.recall_queued_at` is stamped and never cleared |
| **Priority** | `high` when item total ≥ ₹10,000, else `medium`; writes `opportunity_value` |
| **Note** | *"Approved treatment plan: {name} (Tooth #N). No appointment booked. Plan value: ₹X"* |

> 🔴 **This trigger is permanently dead.** `'approved'` is not a member of either status enum. See §9 R-1.

---

### Trigger 3 — `post_op_followup` → `purpose = 'recall_post_op'`

| | |
|---|---|
| **Source** | `treatment_visits` |
| **Condition** | `visit_date` **exactly** 14 days ago **AND** `visit_type` or `procedure` LIKE any of `extraction, implant, rct, surgery, surgical, post_op` |
| **Cooldown** | One-shot — `treatment_visits.recall_queued_at` |
| **Priority** | `high` |

Two structural problems: it shares its cooldown column with trigger 5 (§9 R-2), and it fires on a single exact date (§9 R-4). Its docblock also promises *"with no follow-up appointment booked"* — the query never checks appointments (§9 R-12).

---

### Trigger 4 — `lab_received_no_appt` → `purpose = 'recall_lab_received'`

| | |
|---|---|
| **Source** | `lab_cases` |
| **Condition** | status `final_received` within the last 30 days, patient has no upcoming appointment |
| **Cooldown** | One-shot — `lab_cases.recall_queued_at` |

Historically matched on `'received'`, which was never a v2 status value and therefore never fired. **Already fixed** and recorded in the code comment — a useful precedent for R-1.

---

### Trigger 5 — `recent_tx_followup` → `purpose = 'recall_7day_followup'`

| | |
|---|---|
| **Source** | `treatment_visits` |
| **Condition** | `visit_date` **exactly** 7 days ago, `visit_type`/`procedure` NOT LIKE `consultation, exam, xray, review, checkup, check_up` |
| **Cooldown** | One-shot — `treatment_visits.recall_queued_at` (shared with trigger 3) |

> 🔴 The `NOT LIKE` exclusion silently drops every row where `visit_type` or `procedure` is NULL. See §9 R-3.

---

### Trigger 6 — `birthday` → `purpose = 'recall_birthday'`

| | |
|---|---|
| **Source** | `patients` |
| **Condition** | `DATE_FORMAT(date_of_birth,'%m-%d')` within the next N days (`recall.birthday_window_days`, default 3); requires a phone |
| **Cooldown** | Once per calendar year — `patients.recall_birthday_queued_at` |
| **Gate** | `AppSetting recall.birthday_enabled` (default `'1'` = on) |

The phone filter here is **intentional and documented**: a birthday greeting is a nicety, so queueing one for a patient with no number is pure noise. Clinical recalls take the opposite stance (see §5).

---

## 5. SUPPRESSION — THE FIVE GATES

In evaluation order:

1. **Eligibility** — the trigger's own SQL condition.
2. **Go-live scope** (trigger 1 only) — `recall.effective_from` excludes migrated history.
3. **Cooldown** — 30 days for no-visit, once-per-year for birthday, one-shot `recall_queued_at` for the record-based triggers.
4. **Dedup** — `hasOpenQueueItem($patientId, $purpose)`: never a second open item for the same patient and purpose. `status = 'closed'` releases the lock.
5. **Patient-level automation flags** (live path only) — `automationsEnabled()` scope and `contact_invalid_at`, both written by the 2026-07-05 call-outcome automations.

**No-phone handling differs by path.** Legacy `createQueueItem()` does *not* drop a patient with no number — it downgrades the item to `priority = 'low'`, sets `next_action = 'update_contact'` and prefixes the note with *"⚠ No contact number on file"*. The rationale in the code: *"a patient with no number who hasn't visited in 6 months is exactly the patient the clinic is losing money on."* The live automation runner filters those patients out entirely and has no such flagging.

**There is no daily cap on any trigger.** Ordinary day-to-day volume is small; the risk is bulk history — which is exactly what produced the 1,810-item dump on 2026-07-04.

---

## 6. WHAT IT WRITES

### `communication_queue` — one row per recall

| Field | Value written by the engine |
|---|---|
| `purpose` | `recall_no_visit` · `recall_approved_plan` · `recall_post_op` · `recall_lab_received` · `recall_7day_followup` · `recall_birthday` |
| `source_engine` | `'recall'` — distinguishes engine output from manual entries |
| `channel` / `direction` | `'call'` / `'outbound'` |
| `comm_type` | `'existing_patient'` |
| `status` | `'pending'` |
| `next_action` | `'call_back'` (or `'update_contact'` when no phone, legacy path) |
| `follow_up_date` | **`today()` — hardcoded**, which is what makes items land in the "today" view |
| `sla_deadline` / `sla_breached` | `now()->addHours(24)` / `false` |
| `priority` | per trigger |
| `note` | template-rendered or hardcoded fallback |
| `tags` | JSON, e.g. `['recall','no_visit_6m']` |
| `opportunity_value` | trigger 2 only |
| `created_by` | `null` — system-generated |

### Cooldown stamps

| Column | Written by |
|---|---|
| `patients.recall_no_visit_queued_at` | trigger 1 |
| `patients.recall_birthday_queued_at` | trigger 6 |
| `treatment_plan_items.recall_queued_at` | trigger 2 |
| `lab_cases.recall_queued_at` | trigger 4 |
| `treatment_visits.recall_queued_at` | **triggers 3 and 5 — shared** |

All added by `2026_06_13_300001_add_recall_tracking_columns.php`.

### Activity log

`ActivityEngine::log($patient, 'recall.queued', null, ['trigger' => ..., 'owner' => ...], $relationshipId)` — wrapped in try/catch so a logging failure can never abort a recall run.

### What it reads and never writes

`patients.last_visit_date` · `date_of_birth` · `phone` · `contact_invalid_at` · automation flags · `treatment_plan_items.status`/`total` · `treatment_visits.visit_date`/`visit_type`/`procedure` · `lab_cases.status` · `message_templates` · `app_settings`.

---

## 7. WHERE RECALLS SURFACE

| Surface | Route / class | Reads |
|---|---|---|
| **Today's Actions** | `TodayActionsEngine::recallCalls()` | `communication_queue` where `purpose LIKE '%recall%'` and `status = 'pending'`; category `recall_calls`, capped at `relationship_rules.today_actions.max_per_category` (default 50) |
| **Recall Pipeline** | `/relationship/recalls` → `RecallPipelineController` | same table, `purpose = 'recall'` OR `LIKE '%recall%'`; filters, bulk assign, bulk dismiss, ignore/unignore, convert-to-opportunity |
| **Action Board / Yesterday Review** | `YesterdayReviewService::missedCalls()` | queue items due within `ClinicFlowRange`, honouring per-item Ignore. **Uncapped** |
| **Huddle** | `HuddleService` | ⚠ reads `patients.next_recall_date` / `follow_up_date`, **not** the queue — see §9 R-6 |
| **Patient profile** | `patients/profile/header.blade.php` | ⚠ `next_recall_date` badge — same dead column |
| **Consultation view** | `consultations/show.blade.php` | ⚠ `recall_status` badge with colour — same dead column |
| **Mobile** | `Api/V1/RelationshipController`, `RelationshipRecallSettingsController` | queue + settings parity |

**Lifecycle of one item:** `pending` → staff calls → outcome logged → `closed` (releasing the dedup lock) — or `ignored` (hidden but retained), or converted to an opportunity via `RecallPipelineController::convertToOpportunity()`. Call outcomes feed `OutcomeAutomationService`, which can set `contact_invalid_at`, mark a patient deceased/opted-out, or schedule the next follow-up — which in turn changes what the engine queues next.

---

## 8. SETTINGS

Relationship → Settings, all stored as `app_settings` key-values (no migration).

| Key | Default | Effect |
|---|---|---|
| `recall.effective_from` | blank | **Live.** Go-live date; scopes trigger 1 away from migrated history |
| `recall.birthday_enabled` | `'1'` | **Live.** Master switch for trigger 6 |
| `recall.birthday_window_days` | `3` | **Live.** Days ahead trigger 6 looks |
| `recall.general_days` | `180` | ⚠ **DISPLAY-ONLY** — does not drive the 6-month cutoff (R-8) |
| `recall.channel_whatsapp` / `_sms` / `_email` | — | Channel preferences; no send pipeline consumes them |
| `automation.engine` (feature flag) | `false` in config, ON per-branch in `feature_flags` | Routes trigger 1 to the Automation Engine |
| `treatment_types.recall_days` | column | ⚠ **ZERO usage anywhere** (R-7) |

---

## 9. DEFECTS FOUND IN THIS TRACE

New findings, not previously recorded. Numbered `R-n` for reference; **none has been added to the frozen V1 Gap Register** — that requires a decision.

---

**R-1 · Trigger 2 can never fire. 🔴 High**
`recallApprovedPlanNoAppointment()` requires `treatment_plan_items.status = 'approved'` **and** `treatment_plans.status = 'approved'`. Both enums are `['pending','ongoing','completed','cancelled']` (`2026_05_27_000001`, `2026_05_27_000002`); no later migration adds `'approved'`, and no service writes it. The trigger returns 0 every run, silently. This is the same defect class already fixed in trigger 4 (`'received'` → `'final_received'`).
*Impact:* the highest-value recall in the practice — accepted treatment with no appointment — has never fired. Overlaps register **G-15 (Pending Treatment)**.
*Direction:* source acceptance from `plan_decisions` (canonical since Slice 2.3), not from a status string. Do **not** add `'approved'` to the enum — `PlanLifecycleService` is the sole status writer and the lifecycle is frozen.

**R-2 · Trigger 3 is cannibalised by trigger 5. 🔴 High**
Both stamp and both filter on the single column `treatment_visits.recall_queued_at`. A surgical visit is picked up by trigger 5 at day 7 (extractions and implants are not in trigger 5's exclusion list), which stamps the column; at day 14 trigger 3 requires `whereNull('recall_queued_at')` and skips it. **Post-op follow-up is structurally dead for exactly the visits it exists to catch.**
*Direction:* separate cooldown stamps per purpose, or key the one-shot on `communication_queue` history rather than a shared column.

**R-3 · Trigger 5 silently drops NULL visit types. 🔴 High**
`where('visit_type','not like','%consultation%')` — in SQL, `NULL NOT LIKE '...'` is NULL, not true, so **every visit with a NULL `visit_type` or `procedure` is excluded**. Both columns are nullable, and after the 2026-08-19 Today's-Procedures unification many visits will carry neither. The 7-day follow-up therefore matches far fewer visits than intended, with no signal that it is doing so.
*Direction:* `whereRaw("COALESCE(visit_type,'') NOT LIKE ?")`, or invert to an explicit include-list.

**R-4 · Triggers 3 and 5 fire on one exact date. 🟠 Medium**
`whereDate('visit_date', $targetDate)` where `$targetDate` is exactly 14 or 7 days ago. If `recall:run` does not execute on a given day — server down, deploy window, a `withoutOverlapping()` skip — that day's cohort is **never** picked up, because tomorrow's run looks at a different single date. Silent permanent loss.
*Direction:* a window (`between N and N+lookback`) plus the existing `recall_queued_at` guard to prevent repeats.

**R-5 · Legacy and automation paths have diverged, breaking the documented parity contract. 🟠 Medium**
`RecallAutomationRunner`'s docblock states behaviour is *"deliberately identical"* to legacy and *"proven equivalent by the shadow parity run in Slice 3"*. They now differ in four ways:

| | Legacy (dormant) | Automation runner (live) |
|---|---|---|
| Phone filter | none — no-phone patients queued and flagged (2026-07-14 change) | `whereNotNull('phone')` — excluded |
| Deceased / opted-out | not checked | `automationsEnabled()` |
| `contact_invalid_at` | not checked | `whereNull(...)` |
| No-phone flagging in `createQueueItem()` | yes | no |

The 2026-07-14 no-phone improvement landed **only on the dormant path**; the patient-safety filters landed **only on the live path**. Flipping `automation.engine` off — the advertised instant rollback — would immediately start recalling deceased and opted-out patients.
*Direction:* re-converge both, then re-run `php artisan automation:parity recall` before relying on the rollback.

**R-6 · `recall_status` and `next_recall_date` have zero writers and three readers. 🔴 High**
Both columns exist on `patients` (`2024_01_02_000001`) and are `$fillable`, but **no service, controller, observer, form field or validation rule ever writes them**. They are nonetheless read by:
- `HuddleService:190` — the Huddle's "recalls/follow-ups due today" list queries `whereDate('next_recall_date', $day)`
- `patients/profile/header.blade.php:308` — a "Due in N days" badge
- `consultations/show.blade.php:1103` — a coloured recall-status badge

*Impact:* the Huddle's recall list is permanently empty from that column, and two patient-facing badges render a confident state derived from data nothing maintains. **Same defect class as register G-01** — a UI reading a table with no writer.
*Direction:* either derive both from `last_visit_date` + interval, or remove the readers. Do not leave them half-alive.

**R-7 · `treatment_types.recall_days` is completely unused. 🟠 Medium**
Added by `2026_07_05_100003_add_recall_days_to_treatment_types_table.php`. Repository-wide search across `app/`, `resources/views/` and `routes/` returns **zero references**. Per-treatment recall intervals — an implant recalled at 6 months, a scaling at 6, an ortho review at 1 — are the natural clinical model, and the column for it exists and is ignored.
*Direction:* this is the honest input to R-6. Deferred, but note it before anyone adds a second interval field.

**R-8 · `recall.general_days` is display-only.** 🟡 Low
Documented in the code: the Settings field does not drive the hardcoded 6-month cutoff, because that const is duplicated across both paths under a parity contract. Users can set it and nothing changes. Fixing it requires changing both files plus a fresh parity run — blocked behind R-5.

**R-9 · Trigger 1's only input is stale for treatment patients. 🔴 High — already registered**
`last_visit_date` is advanced by `ConsultationClinicalWiringObserver` only, never by a treatment visit. This is **register G-11**, already scheduled for Week 1. Recorded here because it is the single largest determinant of whether trigger 1 is correct — every other recall fix is downstream of it.

**R-10 · No daily cap, and the backlog guard is unset and untested.** 🟠 Medium
No trigger caps its output. `recall.effective_from` mitigates the bulk-import case but is code-done-untested and appears never to have been set. The 1,810 items dumped on 2026-07-04 are still sitting in `communication_queue` — that fix stops new dumps and does not clean up existing rows.
*Direction:* set the date to the real migration date (not "today"), then decide what to do with the existing backlog rows.

**R-11 · `YesterdayReviewService::missedCalls()` — partially fixed.** 🟡 Low
The old "exactly yesterday" filter that made unactioned items vanish after 24 h is **fixed**: it now resolves a range via `ClinicFlowRange`. It remains **uncapped** — every other Today's Actions category respects `max_per_category` (50); this one calls `->get()` unbounded. With the 1,810-item backlog still present, this is the surface that renders it.

**R-12 · Trigger 3 docblock does not match its query.** 🟡 Low
The comment promises *"with no follow-up appointment booked"*. The query never joins or checks `appointments`. Patients who already re-booked still get queued.

**R-13 · Recall items bypass `CommunicationGuard` by construction.** ℹ️ Note, not a defect
Items are `channel = 'call'`, so the consent gate never evaluates them. Correct today. It becomes a real question the moment anyone builds an automatic send on top of this queue — at which point consent must be checked at send time, never at queue time.

---

## 10. SUMMARY — HOW HEALTHY IS IT?

| Trigger | State |
|---|---|
| 1 · no_visit_6months | **Working**, but fed by a stale input (R-9) and gated by an unset guard (R-10) |
| 2 · approved_plan_no_appt | 🔴 **Never fires** (R-1) |
| 3 · post_op_followup | 🔴 **Effectively never fires** (R-2), plus R-4, R-12 |
| 4 · lab_received_no_appt | **Working** — its equivalent bug was found and fixed |
| 5 · recent_tx_followup | 🟠 **Fires for a minority of visits** (R-3), plus R-4 |
| 6 · birthday | **Working** |

**Two of six triggers are dead and a third is badly degraded — and every one of them fails silently, returning 0 and logging a clean run.** The pattern is identical to the finance findings in the frozen gap register: the engine's architecture is sound (one writer, honest cooldowns, real dedup, explainable suppression tallies, a genuine dry-run), and the defects are all in the seams — a status value that no longer exists, a cooldown column shared by two owners, a NULL-unsafe predicate, a column with readers and no writer.

None of this needs a redesign. It needs the joins reconnected.

---

*Traced 21 August 2026. Findings R-1 to R-13 are recorded here only; the V1 Master Gap Register is frozen and unchanged.*
