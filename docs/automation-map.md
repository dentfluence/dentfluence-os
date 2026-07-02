# Dentfluence — Automation / Interconnection Map

> Every "when X happens, Y happens automatically" in the app.
> Generated 2026-06-22 as the basis for Layer 4 (interconnection / side-effect) testing.
> Status legend: ✅ wired & looks correct · ⚠️ confirmed/suspected bug · 🔍 needs verification

---

## A. Model-event automations (fire automatically on save/update)

| # | Trigger | Action | Where | Status |
|---|---------|--------|-------|--------|
| A1 | Lab case **status changes** | Create/find a B2B comm in Comms Manager, log the status change, update notes, **auto-close** comm when finished | `LabCaseObserver::updated` | ⚠️ auto-close checks `received/delivered/closed` — NOT valid v2 statuses (`final_received/complete`). Comm never auto-closes. |
| A2 | Lab case **created** | Auto-generate `case_number`; log creation event | `LabCase::booted` (creating/created) | 🔍 |
| A3 | Stock movement **created** | Adjust the inventory item's on-hand quantity | `StockMovement::booted (created)` | 🔍 (core inventory math) |
| A4 | Patient **created** | Auto-generate `patient_id` (TDC-xxxxx) | `Patient::booted` | ✅ (used by all Dusk tests) |
| A5 | Treatment plan **created** | Auto-generate `plan_uuid` / `plan_name` / `display_order` | `TreatmentPlan::booted` | ✅ |
| A6 | Tag **created** | Auto-generate `slug` | `Tag::booted` | ✅ |
| A7 | HR staff profile **created** | Auto-generate staff code | `HrStaffProfile::booted` | 🔍 |

## B. Inline action chains (one user action triggers another record)

| # | User action | Auto-creates | Where | Status |
|---|-------------|--------------|-------|--------|
| B1 | Treatment visit saved **+ "mark complete"** (with a plan) | 6-month **recall Task** | `TreatmentVisitController::store` | ✅ |
| B2 | Treatment visit saved **with procedures** | **Billing prompt** (front desk invoices from it) | `TreatmentVisitController` | ✅ |
| B3 | Record **invoice payment** | **Receipt** (+ Final Bill) | `BillingController::recordPayment` | ✅ (verified earlier) |
| B4 | Lab case create/update | **Task(s)** (vendor follow-up / trial review) | `LabController`, `LabAlertService` | 🔍 |
| B5 | Create **Purchase Order** (with expected date) | **Task** to call vendor to check delivery | `InventoryController` | 🔍 |
| B6 | Stock in / out / count | **StockMovement** → updates stock | `InventoryController`, `StockCountController` | 🔍 |
| B7 | Log a communication / Huddle action | **CommunicationQueue** entry | `CommunicationController`, `HuddleController` | ✅ (Comms test) |
| B8 | Assign a **Task** | **Notification** to the assignee | `TaskController` | 🔍 |

## C. Scheduled automations (run by `php artisan schedule:run`)

| # | When | Action | Command | Status |
|---|------|--------|---------|--------|
| C1 | Daily 07:00 | Recall engine → creates follow-up comms (no-visit 6mo, approved plan no appt, post-op, lab received, birthday…) | `recall:run` | ✅ (17 pending seen) — 🔍 audit rules for column drift |
| C2 | Daily 09:00 | Auto-create Tasks for **overdue lab cases** + stale trials | `lab:create-overdue-tasks` | ⚠️ likely uses old `expected_date` column → may error/miss (verify) |
| C3 | Daily 07:05 / 18:00 | Morning briefing / evening summary emails to staff | `comm:morning-briefing`, `comm:evening-summary` | 🔍 |
| C4 | Daily 14:00 | SLA breach alert to manager | `comm:sla-alert` | 🔍 |
| C5 | (interval) | Auto-escalate ₹30k+ leads not contacted in 2h | `comm:auto-escalate` | 🔍 |
| C6 | Daily 10:30 | Auto-mark absent staff who didn't check in | `hr:mark-absent` | 🔍 |
| C7 | Every 5 min / 2h | Shift + periodic task reminders → notifications | `tasks:shift-reminder`, `tasks:periodic-reminder` | 🔍 |
| C8 | Daily 02:00 | Route health crawler | `app:crawl-routes` | ✅ |

> ⚠️ NOTE: All scheduled jobs only fire if Windows Task Scheduler runs `php artisan schedule:run` every minute. Also: `tasks:shift-reminder` and `tasks:periodic-reminder` are registered **twice** in `routes/console.php` (double-firing) — see earlier finding.

## D. Notifications

In-app notifications are created via `AppNotification::notify(...)`. Current senders: task assignment (B8), shift reminders, periodic reminders (C7). Surfaced through `/notifications/unread`.

---

## Known interconnection bugs (drift from Lab Module v2 / schema changes)

1. **A1 — Lab→Comms auto-close broken.** `LabCaseObserver` closing statuses are old (`received/delivered/closed`); v2 uses `final_received/complete`. Finished lab cases never close their linked comm.
2. **C2 — Overdue-lab-task command** may reference old `expected_date` (v2 = `expected_return_date`) — verify.
3. Duplicate schedule entries for task reminders (double notifications).

These are the same "old column/status name" drift that broke the lab form and the analytics pages — worth a systematic pass.
