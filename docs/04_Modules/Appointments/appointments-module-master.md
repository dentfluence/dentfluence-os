# Appointments Module — Master Reference (FROZEN)

**Status:** FROZEN · **Frozen on:** 2026-07-24 · **Authority:** CEO Directives #004 (audit) → #015 (final freeze)
**Scope of freeze:** the Appointments corrective plan, Slices 1–12.
**Companion docs:** `docs/appointments-module-audit.md` (forensic baseline), `docs/appointments-module-corrective-plan.md` (the 12-slice plan).

This document is the single source of truth for how Appointments works after the corrective programme. Changes to anything below require a design amendment, not an ad-hoc edit.

---

## 1. Module purpose
Appointments is the clinic's scheduling workflow engine: it books, checks-in, moves through chair, completes, cancels, no-shows, reschedules and reverts patient appointments across web (FullCalendar) and the mobile API, and feeds the patient Timeline, the Daily Huddle, dashboards, reports and reminder tasks. It is not merely a calendar — it is the daily operational heartbeat of the front desk.

## 2. Final architecture (one-line map)
Controllers/AI tool/Console **coordinate**; `AppointmentService` **owns all writes** (transactional); `AppointmentStatus` enum + model scopes **own the vocabulary and reads**; `AppointmentActivityLogger` **owns lifecycle events**; `ReminderAutomationRunner` **owns reminder generation**; `PatientService::register()` **owns patient minting**. Web presentation (`formatAppointment`, Blade) and the API contract (`AppointmentResource`) are the only presentation layers and are held stable.

## 3. Appointment write architecture (WRITE SSoT)
`app/Services/AppointmentService.php` is the **sole** appointment writer. Every create/update/status/reschedule/cancel/revert/hide/assign-operatory/delete/block-slot goes through it. Verified: **zero** `Appointment::create` / `$appointment->update` / `$appointment->delete` outside the service. Callers:
- Web `AppointmentController` — validates + formats responses; delegates writes.
- API `Api\V1\AppointmentController` — thin over the service + FormRequests + `AppointmentResource`.
- AI `BookAppointmentTool` — delegates to `AppointmentService::create()` (same guards/timeline/transaction).
Service methods: `create`, `createWalkIn`, `update`, `updateStatus`, `reschedule`, `cancel`, `revert`, `hide`, `assignOperatory`, `delete`, `blockSlot`.

## 4. Read architecture (READ SSoT)
Canonical **model scopes** define each shared read fact: `scopeActive` (excludes cancelled/no_show), `scopeToday`, `scopeForDate`, `scopeInDateRange`, `scopeVisibleOnCalendar` (excludes hidden), `scopeForBranch`, `scopeForDoctor`. The web calendar/today reads and `AppointmentService::filteredQuery()` / `todayCounts()` consume them. Web `getTodayStatusCounts()` delegates its counters to `todayCounts()` (+ merges a web-only chair-utilization KPI).
**Legitimately separate reads (left as-is):** Huddle raw-SQL single-query aggregation (joins for card rendering), `KpiController`/`ReportsController` `SUM(CASE)` KPI/report builders, Insights `EloquentAppointmentReadContract` (last completed visit). These compute different metrics or use a different strategy by design.

## 5. Status model (STATUS SSoT)
`app/Enums/AppointmentStatus.php` (backed string enum, mirroring the DB enum) is the single definition of the vocabulary: `scheduled, checkin, in_chair, checkout, done, cancelled, no_show`. It owns labels, calendar colours (`calendarMeta()` injected into the calendar JS), the `terminalValues()` (cancelled/no_show), `inProgressValues()`, `completedValues()`, and `validationRule()`. Canonical spelling is **`no_show`** (the stray `noshow` was normalised away). The status column is **not** cast to the enum (deliberate — preserves the app's string comparisons and `whereIn`). No status state machine is enforced (arbitrary transitions remain permitted, as pinned by characterization — a deliberate non-change).

## 6. Permission model
Action-gated on both surfaces via existing middleware (no new permission system):
- **Web** (`routes/web.php`): reads → `module:appointments`; writes → `module:appointments,edit`; delete → `module:appointments,delete`.
- **API** (`routes/api.php`): writes → `api.role:module:appointments,edit`; delete → `api.role:module:appointments,delete`; reads → `auth:sanctum`.
Both resolve through `User::canAccess()` / the `RoleModulePermission` table. Admins bypass. Permissions are independent (view/edit/delete are separate flags) — see the recorded project-wide note on whether to make them hierarchical (out of module scope).

## 7. Validation invariants
Canonical rules live on `AppointmentService`: `durationRule()` = `nullable|integer|min:10|max:480`; `typeRule()` = `in:consultation,treatment,follow-up`; status = `AppointmentStatus::validationRule()`; time = `date_format:H:i` on all write paths, with a lenient parse-guard in the service converting malformed input to 422 (never 500). Duration default = explicit value → treatment-category default (`resolveDuration`) → 30, applied identically web/API/walk-in. Overlap semantics: strict interval overlap excluding cancelled/no_show, overridable with `allow_overlap` (the mobile client string-matches the literal `allow_overlap` token in the 422 message — preserve it).

## 8. Lifecycle-event ownership
`AppointmentActivityLogger` (→ `ActivityEngine`, the `activities` table) is the single producer, invoked only from `AppointmentService` inside its transactions. One event per action, no duplicates:

| Action | Event |
|---|---|
| create / walk-in | `appointment.booked` |
| check-in | `appointment.checked_in` |
| done | `appointment.completed` |
| cancel (status or reason) | `appointment.cancelled` |
| no-show | `appointment.missed` |
| reschedule | `appointment.rescheduled` (from/to slot + actor) |
| revert | `appointment.reverted` (from/to status + actor) |
| delete | `appointment.deleted` (actor) |

No `AppointmentObserver` exists (deliberate — would double-log). `in_chair`/`checkout`/full-`update` intentionally emit nothing.

## 9. Reminder architecture
One canonical producer: `ReminderAutomationRunner::generateAppointmentReminders()`, run unconditionally by the `relationship:appointment-reminders` command (daily 08:00, `routes/console.php`, foreground with `onFailure` critical logging). It creates a staff "Reminder call" **Task** for tomorrow's non-terminal appointments — **no outbound patient communication**. Idempotent (one per patient per day via `alreadyReminded()`). Attribution: `created_by` = system actor (`Auth::id()` → admin → any user; a type-safety floor), `branch_id` = the appointment's branch, `patient_id` linked, appointment referenced in the description. **Decoupled from the `automation.engine` flag** — recall/retry/other automation remain flag-gated and unaffected. The legacy broken engine was retired.

## 10. Patient-registration dependency
Walk-in new patients are minted **only** through `PatientService::register()` (TDC assignment + Relationship linkage) — never `Patient::create` in appointment code. The `PatientInvariantCheck` whitelist no longer contains any appointment file; `php artisan patients:invariant-check` passes with Appointments un-whitelisted.

## 11. Performance / index decisions
- **Composite index** `appointments_branch_doctor_date_index (branch_id, doctor_id, appointment_date)` — serves the doctor-scoped overlap/reschedule guard (`branch_id = ? AND doctor_id = ? AND appointment_date = ?`). Not redundant with the pre-existing `(branch_id, appointment_date)` / `(appointment_date, status)` indexes.
- **Overlap in SQL** — `overlapConflict()` does the interval math with `TIMESTAMP(appointment_date, appointment_time)` + `DATE_ADD(...)` predicates (was a PHP hydrate-and-loop). Returns ≤1 row; identical strict-inequality semantics.
- **Edit page** — no longer loads the entire branch patient list into a `<select>`; a datalist typeahead reuses `/patients/search`.
Both migrations are additive/reversible with `information_schema`/`hasColumn` guards.

## 12. API / web boundaries
Shared business rules (write path, guards, status, duration/type, reads) are canonical and consumed by both. Presentation is **not** shared: web uses `formatAppointment()` (calendar payload) + Blade; API uses `AppointmentResource` (mobile contract — **unchanged**, exact key set locked by characterization). Legitimate surface differences retained: web `type` is `required` vs API `nullable`+default; `appointment_time` web `date_format:H:i` vs API `string|max:8` (mobile-lenient, service-guarded); API reads are `auth:sanctum`-only (no module view gate).

## 13. Test / characterization safety net
`tests/Feature/Appointments/` — 89 tests (301 assertions) plus adjacent suites: creation, status flow, serialization (exact key sets), responses, permissions (7-persona matrix), timeline/lifecycle, read-model parity, booking parity, reminder generation. `AppointmentReminderCharacterizationTest` (canonical reminder behaviour), `AppointmentStatusFlowTest`, `Insights\ReadContractsTest`, `Automation\ReminderCutoverTest`/`RecallCutoverTest`, `Relationship\HuddleSnapshotTest` (repaired) all green. These are the behavioural contract — future changes must keep them green unless an intentional change is explicitly approved.

## 14. Known accepted technical debt
- The standalone `appointments/edit.blade.php` is a legacy stub, unreachable from the live UI (the modal is the live edit path); only its patient-load was fixed.
- Huddle/KPI/report per-status `SUM(CASE)` aggregations compute the same fact via a different single-query strategy — intentionally not unified (would be a perf/architecture change).
- `systemActorId()`'s `?? 1` is an unreachable type-safety floor for `created_by`.
- Independent (non-hierarchical) permission engine — recorded as a project-wide decision, not module-scoped.
- `AutomationParity` console hint text is now stale for reminders (recall preview still valid).

## 15. Explicitly deferred features (NOT in this freeze)
Status state machine (valid-transition enforcement); `appointment.in_chair`/`checkout`/`update` lifecycle events; the dormant `appointment_confirmed` doctor notification (no producer, ambiguous semantics — deferred, unwired); appointment search integration; blocked-slot delete UI; queue/ETA feature (dead columns dropped); field-level PHI redaction; `allow_overlap` audit trail; consultation→appointment status auto-sync; holiday/working-hours settings.

## 16. Production deployment requirements
Run the two additive migrations on production (both idempotent/reversible):
`php artisan migrate` → `2026_07_24_120000_add_branch_doctor_date_index...` and `2026_07_24_130000_drop_dead_queue_columns...`.
No config, env, or feature-flag changes required. The reminder scheduler (`relationship:appointment-reminders`, 08:00) now always produces valid tasks — ensure the scheduler (`schedule:run` / cron) is active. `automation.engine` may remain OFF. Clear caches after deploy: `php artisan optimize:clear`.

## 17. Rollback notes
- Every code slice is behaviour-preserving except the documented, tested corrections; revert per commit if needed.
- Index migration: `down()` drops the index. Dead-column migration: `down()` restores `queue_position`/`estimated_wait_minutes` (nullable, empty).
- Deleted files (4 dead modals + legacy reminder forwarder) are recoverable from git history; none had live consumers.
- The reminder cutover is code-level (command always uses the runner); to revert reminder behaviour, restore the previous command + engine from history.

## 18. Final freeze statement
Appointments Slices 1–12 are complete. Write, status, and read single-sources-of-truth are established and verified; patients are minted only via `PatientService::register()`; permissions are action-gated on web and API; lifecycle events and reminders each have exactly one producer; writes are transactional; validation is canonical; the approved performance work landed; proven-dead code was removed. The characterization safety net is green and 0 routes are broken. **The Appointments Module is FROZEN.** Further changes require a design amendment referencing this document.
