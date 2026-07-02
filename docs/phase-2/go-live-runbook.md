# Phase 2 — Automation: Go-Live Runbook

> Deployment + cutover guide for the Automation Engine consolidation.
> Companion to `docs/phase-2/automation-inventory.md`. Everything here is
> additive and flag-reversible.

---

## 1. What Phase 2 shipped

One **Rules Engine** (decides) + one **Automation Engine** (owns time-based work:
schedule / retry / cooldown / expire) + a single, deduped reminder path.

| Area | Before | After |
|------|--------|-------|
| No-visit recall | inline SQL cooldown in `RecallEngineService` | owned by `AutomationEngine` (cooldown/dedup), flag-gated |
| Appointment reminders | `AppointmentReminderEngine` (threw — `created_by` NULL bug) | `ReminderAutomationRunner`, bug fixed via system actor |
| Follow-up rules | legacy `FollowUpRulesService` | ported to `FollowUpRuleEngine` (Rules-Engine-owned), flag-gated |
| Reminder overlap | RulesEngine `appointment_reminder` rule could double up | deferred to Automation when it owns reminders |

New code is inert until its flag is on. Default = pre-Phase-2 behaviour.

---

## 2. Feature flags

| Flag | Controls | Deploy default | Flip to go live |
|------|----------|----------------|-----------------|
| `automation.engine` | Automation owns no-visit recall + appointment reminders + reminder-overlap guard | your call (see §5) | after `automation:parity recall` = 0 |
| `rules.single_engine` | Follow-up rules resolve via `FollowUpRuleEngine` | **off** | after `automation:parity rules` = 0 |

Flags are DB-backed (`feature_flags`) and persist across deploys. Set them with:

```
php artisan tinker --execute="\App\Support\Features\Feature::set('FLAG', true);"
```

Instant rollback = set the same flag to `false`. No redeploy needed.

> **Note — shared flag.** `automation.engine` currently governs BOTH recall and
> reminders. If you want to roll back only one, ask for the optional
> `automation.reminders` split before go-live.

---

## 3. Deploy steps

1. Pull `feature/communication-os` (or merge to your deploy branch).
2. **Migrate** (adds one additive table, `automation_shadow_log`; nothing destructive):
   ```
   php artisan migrate
   ```
3. Clear caches:
   ```
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   ```
4. Confirm the test suite is green on the server:
   ```
   php artisan test
   ```

No new `.env` variables are required. The system actor for automated tasks is
resolved automatically (`Auth::id()` → admin user → lowest user id).

---

## 4. Verify BEFORE flipping (parity gates)

All read-only — they write only to `automation_shadow_log`, never to the live queue:

```
php artisan automation:parity recall       # expect: Divergences = 0
php artisan automation:parity reminders     # preview count for tomorrow
php artisan automation:parity rules         # expect: Divergences = 0
```

Only flip a flag once its parity check shows **0 divergences**.

---

## 5. Recommended cutover order

1. Confirm `automation:parity recall` = 0 → `Feature::set('automation.engine', true)`.
   (Recall no-visit + appointment reminders now Automation-owned.)
2. Watch one daily cycle: `storage/logs/recall-engine.log` and
   `storage/logs/appointment-reminders.log`. Confirm counts look sane.
3. Confirm `automation:parity rules` = 0 → `Feature::set('rules.single_engine', true)`.
4. Soak for a few days. If clean, schedule the legacy-class cleanup (§7).

Rollback at any step: set the relevant flag to `false`.

---

## 6. What you'll SEE (functional expectations)

Phase 2 is a **backend consolidation** — the screens are the same; what changes is
*who* fills them and that nothing double-fills. After go-live:

- **Communication queue / Today's Actions** — no-visit recall items appear exactly
  as before (channel "call", purpose `recall_no_visit`). The daily 7:00am
  `recall:run` now produces them via the Automation Engine. On the first live run
  after go-live, expect a burst (the parity run counted ~3,830 eligible patients —
  same number legacy would have produced).
- **Task list / Daily Huddle** — "Reminder call: {patient}" tasks for tomorrow's
  appointments now actually get created (the daily 8:00am job previously errored on
  the `created_by` bug). Each task has a valid creator and shows in reception's list.
- **Follow-up queue** — unchanged rows; once `rules.single_engine` is on they're
  produced by the ported engine (identical labels/timing/channel).
- **No duplicates** — a patient won't get two reminders for the same appointment.

Nothing new appears on screen that wasn't there before — the win is reliability,
single-ownership, and no double-contact. Per-decision "why" (queued vs suppressed
for cooldown/duplicate) is visible via the `automation:parity` commands and the
`automation_shadow_log` table.

---

## 7. Post-soak cleanup (deferred, needs sign-off)

- Delete legacy `app/Services/Communication/FollowUpRulesService.php` once
  `rules.single_engine` has soaked (its callers already route through the ported
  engine when the flag is on).
- Retire the broken `app/Services/Relationship/AppointmentReminderEngine.php`.
- Update the characterization tests that pin the legacy throw when the legacy class
  is removed.

---

## 8. Out of scope for Phase 2 (tracked for later)

- **Remaining 5 recall triggers** (`approved_plan`, `post_op`, `lab_received`,
  `recent_tx`, `birthday`) still run on legacy. They behave identically today;
  moving them under Automation is architectural tidy-up, not a correctness fix —
  do it with the same shadow→parity→flag recipe when convenient.
- **WhatsApp reminder unification** (`whatsapp:send-reminders`) belongs with the
  Phase 4 Communication Engine (single send gateway + Guard), not Phase 2.
