# Phase 1 · Sprint 3 — Timeline Cutover (flag-gated, parity-first)

**Status:** built (code + tests). **Cutover NOT yet enabled** — the flag `activity.single_ledger_reads` is OFF by default, so the profile still uses the legacy timeline until parity is confirmed and you flip it.

## What changed

| Concern | Change |
|---|---|
| Data source (behind flag) | `ProfileController::show()` serves the timeline from `UnifiedTimelineService` when `activity.single_ledger_reads` is ON; otherwise the legacy inline `buildTimeline` (unchanged). |
| Faithful mirror | `UnifiedTimelineService` rewritten to match `buildTimeline` **exactly** — same sources, per-source limits (activities 60, lead 30, appts 30, comms 20, tasks 20, notes 10), `sortByDesc('date')->take(100)`, same icon/actor/meta formatting, and the same `$relationship->lead`/`->patient` selection (so the 18 household relationships resolve identically). PHP 8.3's stable sort ⇒ identical input produces identical order. |
| Parity harness | `TimelineParityService` + `relationship:timeline-parity` command compare legacy vs unified per relationship. |
| Legacy | `buildTimeline` kept verbatim (made `public` only so the harness can call it). **Nothing deleted.** |
| UI | **Unchanged** — only the data source is swapped; entry shape is identical. |

## Requirement mapping

1. ✅ Uses `UnifiedTimelineService` behind `activity.single_ledger_reads`.
2. ✅ Parity validation before enabling — `relationship:timeline-parity` checks representative records (new lead, converted lead, existing patient, RCT patient, implant opportunity, recall patient, lab patient, membership patient) **plus** a random spot-check batch.
3. ✅ On mismatch: command exits non-zero, prints a parity report, and the flag stays OFF (legacy stays active). Do not cut over.
4. ✅ On parity pass: flip the flag to switch reads. Legacy intact, no deletions, instant rollback by disabling the flag.
5. ✅ No UI layout change — data source only.
6. ✅ Receptionist workflow unchanged (flag default OFF = today's behaviour exactly).

## Procedure (you run these)

```
# 1. Full test suite (must be green):
php artisan test

# 2. Parity report on real data (exit 0 = safe, exit 1 = do not cut over):
php artisan relationship:timeline-parity

# 3. ONLY if parity passed — enable the cutover:
php artisan tinker --execute="\App\Support\Features\Feature::set('activity.single_ledger_reads', true);"

# 4. Verify the profile still shows the full timeline (open a patient's profile).

# 5. Rollback verification — disable and confirm it reverts instantly:
php artisan tinker --execute="\App\Support\Features\Feature::set('activity.single_ledger_reads', false);"
```

Per-clinic cutover is also possible: `Feature::set('activity.single_ledger_reads', true, branchId: <id>)`.

## Rollback

Instant and data-free: set the flag to `false` (globally or per clinic). The profile immediately returns to the legacy `buildTimeline`. No data was migrated or deleted, so there is nothing else to undo.

## Files changed

- `app/Services/Relationship/UnifiedTimelineService.php` — rewritten as a faithful mirror.
- `app/Http/Controllers/Relationship/ProfileController.php` — flag switch in `show()`; `buildTimeline` made `public` (body unchanged).
- `app/Services/Relationship/TimelineParityService.php` — new.
- `app/Console/Commands/TimelineParityCheck.php` — new (`relationship:timeline-parity`).
- `tests/Feature/Phase3/TimelineParityTest.php` — new.

## Definition of Done

- [ ] `php artisan test` fully green.
- [ ] `php artisan relationship:timeline-parity` exits 0 (parity passed).
- [ ] Flag ON → profile timeline identical to before; flag OFF → legacy. Rollback verified.
