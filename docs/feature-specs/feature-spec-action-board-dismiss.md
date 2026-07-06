# Feature Spec — Dismiss Action on Today's Action Board

> **Created:** 2026-07-06
> **Author:** Sumit / Dentfluence
> **Module:** PRE — Today's Action Board
> **Status:** Not built. Spec only.

## 1. Problem

Today, the only way to clear a row off the Action Board (`resources/views/relationship/today/index.blade.php`) is `TodayController::logAction()` — which *requires* a call outcome (`form.response`, validated as `required` in the controller). There is no way to say "this doesn't need action" without picking a call outcome that didn't actually happen. In practice staff will pick whatever's closest ("No answer") just to make a row disappear — e.g. a recall call for a patient who already came in through a different channel, a duplicate row, or an opportunity that's genuinely stale and not worth chasing.

This directly undermines the value of the customizable-call-outcomes work (`feature-spec-custom-call-outcomes.md`): if staff are logging fake outcomes to clear rows, the outcome data — and anything built on top of it (analytics, automation) — is unreliable. These two specs reinforce each other; dismiss-with-reason is what keeps outcome data honest.

## 2. Good news: half of this already exists

Two of the board's categories are already backed by `CommunicationQueue` rows, which already have a working ignore/dismiss pattern used by Missed Calls and Recall Pipeline today:

- `CommunicationQueue::ignore($userId)` / `unignore()` — soft, reversible, logged via `CommActivityLog` (`app/Models/CommunicationQueue.php` ~line 591)
- `CommunicationQueue::dismiss($userId, string $reason = '...')` — already accepts a reason string (~line 614), used by `RecallPipelineController::bulkDismiss()` / `MissedCallsController::bulkDismiss()`

So `recall_calls` and `missed_calls_yesterday` (both sourced from `CommunicationQueue` — see `TodayActionsEngine::recallCalls()`) can call the *existing* `dismiss($userId, $reason)` method directly. No new mechanism needed for these two.

## 3. The gap: everything else is computed live, with no row to mark

`opportunities` (`TreatmentOpportunity`), `appointment_reminders` (`Appointment`), `birthdays` / `wellness_check_yesterday` (`Patient`), `pending_estimates`, `membership_renewals`, `lab_ready`, `payment_reminders` — these categories are generated fresh every page load by querying their own source table (see `TodayActionsEngine::opportunities()` as an example: a live query against `TreatmentOpportunity`, no `CommunicationQueue` row involved). There's nothing to flag "ignored" on, because the "action" is really just "this row met the query condition today," recomputed daily.

**Recommendation: one small generic table**, rather than adding a bespoke dismiss column to seven different models:

```
today_action_dismissals
  id
  category          string       -- 'opportunities', 'appointment_reminders', ...
  subject_type       string       -- morph type, e.g. App\Models\TreatmentOpportunity
  subject_id         unsignedBigInteger
  dismissed_for_date date         -- the date this specific occurrence was dismissed (today's run)
  reason             string
  dismissed_by       foreignId -> users
  created_at
  unique(category, subject_type, subject_id, dismissed_for_date)
```

`TodayActionsEngine::generate()` filters each category's live query against this table (a single `whereNotIn` / left-join anti-pattern per category, cheap at clinic scale). Dismissing is for *today's occurrence only* — if the same underlying condition is still true tomorrow (e.g. an opportunity still overdue), it reappears, which is correct: dismiss means "not today," not "never show me this patient again." Anyone wanting the CommunicationQueue-backed categories' existing `ignore()` (a longer-lived suppression) can still use that separately — the two mechanisms serve different intents and that's fine, they already coexist for Missed Calls / Recall Pipeline today.

## 4. Reason list — reuse the same settings table as call outcomes

Per `feature-spec-custom-call-outcomes.md`, `action_option_lists` is designed with an `option_type` discriminator (`call_outcome` | `dismiss_reason`). Dismiss reasons don't need to vary per category — a single shared short list is enough and keeps the UI simple:

- Already handled elsewhere / not needed
- Duplicate entry
- Patient no longer reachable / wrong contact on file
- Staff error — added in error
- Other (free text required)

Seed these as `option_type = 'dismiss_reason'`, `action_category = null` rows. "Other" should have `requires_notes = true` so a free-text reason is captured instead of a meaningless "Other."

## 5. What changes

**Backend**
- Migration: `today_action_dismissals` table (as above).
- `TodayActionsEngine` — each category method (`opportunities()`, `appointmentReminders()`, `birthdays()`, etc.) gets a `whereNotIn('id', ...)` / anti-join against today's dismissals for that category, mirroring how `recallCalls()` / missed-calls queries already exclude ignored rows.
- `TodayController` — new `POST /relationship/today/dismiss` endpoint: validates `category`, `subject_type` (or infer from category — a small category→model map, same idea as the existing `patient_id` / `lead_id` resolution in `logAction()`), `subject_id`, `reason_key`, optional `notes`. For `recall_calls` / `missed_calls_yesterday`, route straight to `CommunicationQueue::dismiss()` instead of the new table, so the existing Missed Calls / Recall Pipeline "Show ignored" toggle continues to work as the single source of truth for those two.
- Log the dismissal to `ActivityEngine` too (`event: 'today_action.dismissed'`) so it shows up in the patient Timeline — consistent with how every other action on this board is already logged.

**Frontend**
- Drawer footer (currently: Cancel / Log & Close) gets a third, visually de-emphasized option: "Dismiss" — opens a small inline reason picker (dropdown of `dismiss_reason` options + optional note) instead of the full call-outcome form. Keep it a lightweight sub-state of the same drawer rather than a second modal — no new modal component needed.
- Reason is **required** to submit (button disabled until a reason is picked, same disabled-state pattern already used for `form.response` on the Log & Close button).

## 6. Out of scope (V1)

- No bulk-dismiss from the Action Board itself (Missed Calls / Recall Pipeline already have their own bulk-dismiss screens for that; the daily board is a one-at-a-time workflow by design).
- No "never show this again" permanent suppression from this button — that's what the existing per-category "ignore" (Missed Calls, Recall Pipeline) is for. Keep the mental model simple: Action Board dismiss = "not today."
- No dismiss-reason analytics dashboard yet — worth doing once there's a few weeks of data, not before.

## 7. Build size estimate

Small–medium: 1 migration, small model/scope additions to `TodayActionsEngine` (7 category methods touched, each a 2-3 line addition), 1 new controller endpoint, 1 small drawer UI addition. Depends on `action_option_lists` from the call-outcomes spec being built first (or build a minimal standalone reason list now and fold it in later — either order works, they don't block each other).

## 8. Artisan commands to run (after implementation)

```
php artisan make:migration create_today_action_dismissals_table
php artisan migrate
php artisan route:clear
```
