# Feature Spec — Manual "+ Add Call" on Today's Actions, Unified with Task Creation

> **Created:** 2026-07-08
> **Author:** Sumit / Dentfluence
> **Module:** PRE — Today's Action Board + Communication (Tasks)
> **Status:** Not built. Spec only.

## 1. Problem

Today's Action Board only ever shows system-generated rows (recall due, follow-up due, opportunity overdue, etc.) — there's no way for staff to add an ad-hoc call themselves. Two concrete cases Sumit named:

1. **Treatment follow-up** — staff decides on their own initiative to call a patient about their treatment, outside anything the system already queued.
2. **Vendor / Lab / Doctor / Other** — a call to a lab, a supplier, a referring doctor, or anyone else who isn't a patient.

Separately, the existing **Create Task** modal already has a Category dropdown grouped `— Communication —` (Call, WhatsApp, Follow-up) and `— Internal —` (Admin, Clinical, Lab, Maintenance, Other) — implying task creation should feed the call/communication workflow. It doesn't today: `TaskController::store()` (verified — `app/Http/Controllers/Communication/TaskController.php` lines 69-158) only ever creates a `Task` row, a `HuddleTaskLog`, and an in-app notification. `Task.php`'s `COMM_CATEGORIES` constant has a comment claiming these categories "auto-create a CommunicationQueue entry" — that's stale/aspirational; no such code exists. So Tasks and Today's Actions are two disconnected systems, which is exactly why there's no single place to add a manual call.

## 2. What's already in place (grounded 2026-07-08 — no migration needed)

This turns out to be a much smaller build than it first looks, because both target tables already have everything needed:

- **`FollowUp`** (backs the `follow_up_calls` category — `TodayActionsEngine::followUpCalls()`, `app/Services/Relationship/TodayActionsEngine.php` lines 308-347): has `patient_id`, `due_date`, `label`, `note`, `channel`, `priority`, `status`, `trigger_type`. Query is `status='pending' AND due_date <= today` — **already overdue-inclusive, not date-exact.**
- **`CommunicationQueue`** (backs `logged_communications` — `loggedCommunications()`, same file lines 357-396): `person_name` is a **plain string column, not a foreign key** — a typed-in name like "ABC Dental Lab" or "Dr. Mehta" is already valid, no linked record required. `contact_type` is **a plain string column, not a DB enum** (`add_b2b_fields_to_communication_queue.php` line 26: `$table->string('contact_type')->default('patient')`) — so `vendor` / `lab` / `consultant` / `other` are all already valid values with zero migration. Query is `source_engine='manual' AND status='pending'`, **no date filter at all.**
- **Rollover is already solved.** Neither query above is date-exact ("only exactly yesterday") — both persist a pending row indefinitely until it's actually closed. Combined with today's earlier fix (logging an outcome no longer auto-closes the row — see `feature-spec-action-board-log-close-split` equivalent memory), a manually-added call that goes unanswered will **already** carry forward to tomorrow with no extra work. (The separate `missed_calls_yesterday` category, which genuinely is date-exact, tracks *inbound* missed phone calls — a different concept, out of scope here.)
- **The Create Task modal already has the exact toggle point needed** — `resources/views/partials/create-task-modal.blade.php` has a "linked patient" toggle (`linkedPatient`, line 18) that shows/hides a patient search and sets `patient_id`. When it's off, there's currently no equivalent field for a non-patient contact — that's the one real gap.
- The modal is triggered via `window.dispatchEvent(new CustomEvent('open-create-task', {...}))` — currently only wired up from `huddle/index.blade.php`. **Today's Actions page has no trigger for it at all today.**

## 3. Design

Per Sumit's decision: **reuse the Create Task modal as-is**, rather than building a second, separate "Add Call" popup. One form, one validation path.

### 3a. New "+ Add Call" button on Today's Actions

Add a button near the existing header controls (`resources/views/relationship/today/index.blade.php`, next to Today/Tomorrow/Refresh) that dispatches the same `open-create-task` event the Huddle page already uses, with `detail: { category: 'call' }` so it opens pre-set to the Communication group (staff can still change it).

### 3b. One new field on the Task modal: Contact (only for non-patient Communication tasks)

When `category` is `call` or `whatsapp` **and** `linkedPatient` is off, show one small additional pair of fields:
- **Contact name** (text, required in that state) — e.g. "ABC Dental Lab", "Dr. Mehta"
- **Contact type** (select: Vendor / Lab / Doctor / Other)

`follow_up` category keeps requiring a linked patient (a treatment follow-up is inherently about a specific patient) — no contact-name fallback needed there.

### 3c. `TaskController::store()` — additive slice, real vertical wiring

After the existing `Task::create()` call, for the three Communication categories only:

- **`category = follow_up`** (patient linked): also create a `FollowUp` row — `patient_id`, `due_date` = task's due_date, `label` = task title, `note` = task description, `channel = 'call'`, `priority` = task priority, `trigger_type = 'manual'`, `status = 'pending'`, `assigned_to` = task's assignee. Surfaces via the existing `follow_up_calls` category — **zero changes to `TodayActionsEngine`.**
- **`category = call` or `whatsapp`**, patient linked: same as above but `channel` = `call` or `whatsapp` respectively.
- **`category = call` or `whatsapp`**, no patient (Contact name/type filled instead): create a `CommunicationQueue` row — `person_name` = contact name, `contact_type` = selected type, `phone` = blank/optional (no field for it today — out of scope V1, can be added later), `source_engine = 'manual'`, `purpose = null`, `status = 'pending'`, `follow_up_date` = task's due_date, `priority` = task priority, `note` = task description. Surfaces via `logged_communications` — **zero changes to `TodayActionsEngine`.**
- `category = admin/clinical/lab/maintenance/other` — **unchanged**, Task-only, exactly as today.

The `Task` row is still always created regardless of category — this is additive, not a replacement. Existing Huddle/Task-list behavior is untouched.

### 3d. Cosmetic

Consider renaming the `logged_communications` category label from "Logged Communications" to something closer to Sumt's own wording, e.g. "Other Calls" (`TodayController::CATEGORY_LABELS`) — purely a label change, one line.

## 4. Category → engine mapping (final)

| Task category | Patient linked? | Creates | Shows up in |
|---|---|---|---|
| `follow_up` | Yes (required) | `FollowUp` | `follow_up_calls` |
| `call` / `whatsapp` | Yes | `FollowUp` (channel=call/whatsapp) | `follow_up_calls` |
| `call` / `whatsapp` | No — Contact name/type instead | `CommunicationQueue` | `logged_communications` |
| `admin` / `clinical` / `lab` / `maintenance` / `other` | n/a | Task only | Task list / Huddle only, not Today's Actions |

## 5. Out of scope (V1)

- No phone number field for the Contact name/type case — can be added to `CommunicationQueue.phone` later without any architecture change.
- No editing/deleting a manually-added call after creation beyond the existing Log/Close/Dismiss actions already on the drawer.
- Not touching `missed_calls_yesterday` (inbound missed calls) — different concept, not part of this ask.
- Not building a saved "Vendor"/"Lab"/"Consultant" directory to pick from — free-text contact name only, matching how `CommunicationQueue.person_name` already works everywhere else in the app.

## 6. Build size estimate — ⚠️ spans several files, propose slicing

No migration (confirmed above — both tables already have every column needed). Still touches: `create-task-modal.blade.php` (new conditional fields), `TaskController::store()` (new branching logic, 3 categories), `today/index.blade.php` (new button + event dispatch), `TodayController::CATEGORY_LABELS` (optional rename). Roughly:

- **Slice 1** — Modal: add Contact name/type fields (shown conditionally), add the "+ Add Call" trigger button on Today's Actions.
- **Slice 2** — Backend: `TaskController::store()` branching to create `FollowUp`/`CommunicationQueue` alongside the `Task` row.
- **Slice 3** — (Optional, cosmetic) rename `logged_communications` label.

Each slice is small on its own; doing all three in one pass is still a multi-file change touching a shared, already-live modal — worth building and testing slice by slice rather than all at once, per the project's large-task rule.

## 7. Artisan commands to run (after implementation)

None — no migration.
