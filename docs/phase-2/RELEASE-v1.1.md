# Dentfluence v1.1 — Release Notes

**Theme:** Phase 2 — Automation Engine consolidation.
**Risk profile:** Additive + flag-gated. Ships **dormant** (both flags default off) →
zero behaviour change on deploy. Activated on production separately, after parity.

---

## What's in this release

- **Automation Engine** (`app/Services/Automation/AutomationEngine.php`) — one owner
  for time-based work: schedule / retry (capped exponential backoff) / cooldown / expire.
- **Recall cutover (no-visit)** — `RecallAutomationRunner` owns the `no_visit_6months`
  trigger when `automation.engine` is on; legacy stays warm. No double-write.
- **Appointment reminders fixed + cut over** — `ReminderAutomationRunner` resolves the
  long-standing `created_by = null` crash (system actor) so reminder-call tasks are
  actually created.
- **Follow-up rules ported** — `FollowUpRuleEngine` (Rules-Engine-owned) reproduces the
  legacy `FollowUpRulesService` exactly; gated by `rules.single_engine`.
- **Reminder-overlap guard** — RulesEngine defers reminder rules to Automation when it
  owns them → no patient gets two reminders for one appointment.
- **Parity tooling** — `php artisan automation:parity recall|reminders|rules` (read-only).
- **Observability** — shadow log table + suppression decision summaries in logs.

## Migrations

- `2026_07_02_500001_create_automation_shadow_log_table` — additive, observational.
  No changes to existing tables. Non-destructive.

## Feature flags (production)

| Flag | Ship as | Activate when |
|------|---------|---------------|
| `automation.engine` | off | `automation:parity recall` = 0 divergences |
| `rules.single_engine` | off | `automation:parity rules` = 0 divergences |

Flags are DB-backed and per-environment — production starts clean (off) regardless of
local state. Instant rollback = set the flag back to `false` (no redeploy).

## API / mobile impact

None. No `/api/v1` contract changed; all changes are backend services + new tables.
The mobile app is unaffected by this release.

## Rollback

- Behaviour: flip flags off (instant).
- Code: standard redeploy of the previous tag; the new table can remain (harmless).

See `docs/phase-2/go-live-runbook.md` for the full deploy + activation procedure.
