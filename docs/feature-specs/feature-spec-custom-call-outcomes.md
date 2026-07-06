# Feature Spec — Customizable Call Outcomes

> **Created:** 2026-07-06
> **Author:** Sumit / Dentfluence
> **Module:** PRE — Today's Action Board
> **Status:** Not built. Spec only.

## 1. Problem

The "Call outcome" dropdown in the Today's Action Board drawer (`resources/views/relationship/today/index.blade.php`, ~line 847) is meant to be per-category, and technically it already *can* be — `config/relationship_rules.php` has a `response_options` array keyed by category, with `birthday` and `payment_reminders` already overridden with their own option sets. But every other category (`recall_calls`, `follow_up_calls`, `appointment_reminders`, `lead_followups`, `opportunities`, `pending_estimates`, `membership_renewals`, etc.) silently falls back to the same `default` array:

```
connected_booked, connected_callback, connected_not_interested,
no_answer, busy, wrong_number, voicemail
```

Two real problems follow from that:

1. **Wrong outcomes for the workflow.** "Connected — Appointment booked" doesn't make sense for a payment reminder call, and "Left voicemail" isn't a useful outcome for a Follow-up Call where the real question is "is the patient recovering okay?"
2. **Config-file-only, not clinic-editable.** Even the two categories that do have custom options can only be changed by editing PHP and redeploying. There's no Settings UI, which matters once Dentfluence has more than one clinic on it — every clinic's front desk phrases outcomes slightly differently, and this is exactly the kind of thing an admin should be able to tune without a dev.

## 2. Why this is worth building (and what it unlocks)

This is not just cosmetic. The `response` value logged in `logAction()` is what `ActivityEngine::log()` and `RulesEngine` key off of (`config('relationship_rules.next_actions.' . $response)`) to decide the next automated action. Cleaner, more specific per-category outcomes mean:

- **Better next-action automation** — a `follow_up_calls`-specific outcome like "Patient doing well — no action" can map to closing the loop cleanly instead of forcing staff into a generic "connected_booked" that doesn't fit and pollutes reporting.
- **Real outcome analytics later** — once outcomes are meaningful per category, "% of recall calls converting to bookings" or "% of payment reminders resulting in promised payment" become reportable KPIs. That's a feature clinics will actually look at monthly and a natural upsell surface (Analytics tab already exists).
- **Reduces the "fake outcome" problem** — staff currently jam a recall call into an option that half-fits because nothing else is offered. Bad data in means bad automation and bad reports out.

Commercial read: this is infrastructure for outcome analytics and cleaner automation, not a flashy feature by itself. Worth building, but the win is in what it enables next — don't stop at "dropdown is now editable" and call it done; the analytics view is the real payoff. Flag that as a fast-follow, not scope creep now.

## 3. Recommended data model

Rather than a one-off table just for call outcomes, generalize slightly so the same table can also serve the Dismiss-reason list in the companion spec (`feature-spec-action-board-dismiss.md`) — one settings surface, one CRUD pattern, instead of two near-identical tables.

```
action_option_lists
  id
  option_type       enum('call_outcome', 'dismiss_reason')   -- discriminator
  action_category   string, nullable   -- 'recall_calls', 'birthday', 'default', ... ; null = dismiss reasons (shared across categories)
  key               string             -- e.g. 'connected_booked' (stable, used in stored Activity metadata)
  label             string             -- e.g. 'Connected — Appointment booked'
  closes_task       boolean, default true   -- if false, the action stays open (rare, but e.g. "will call back" shouldn't fully close some categories)
  requires_notes    boolean, default false  -- force a note before allowing submit (e.g. "Dispute raised", "Other")
  next_action_key   string, nullable   -- optional override of config('relationship_rules.next_actions'); falls back to config if null
  sort_order        integer, default 0
  is_active         boolean, default true
  timestamps
  unique(option_type, action_category, key)
```

Seed this table from the current `config/relationship_rules.php` `response_options` array on migration (one seeder, one-time data migration — not a rewrite). Keep `config/relationship_rules.php` as the fallback if a category has zero active rows in the DB (belt-and-braces, and avoids a hard cutover).

## 4. What changes

**Backend**
- Migration: `action_option_lists` table (see above).
- `ActionOptionList` model with scopes: `forCallOutcomes($category)`, `forDismissReasons()`.
- `TodayController::index()` — swap `config('relationship_rules.response_options')` for a DB-backed read (with config fallback), keyed the same way so the Alpine `responseOptions` binding in the Blade view needs no JS changes.
- `TodayController::logAction()` — if the selected option has `requires_notes = true` and `notes` is blank, reject with a validation message ("This outcome requires a note").
- New `RelationshipSettingsController` methods (or extend the existing one at `relationship.settings`) for CRUD on outcome options: add/edit/reorder/deactivate. Reuse the same page pattern as the existing Recall/Birthday Settings tabs (`relationship.settings.recall-birthday` etc. in `routes/relationship.php`) — this is already a tabbed settings page, so this is one more tab, not a new page.

**Frontend**
- New "Call Outcomes" tab on `/relationship/settings`, one simple table per action category: label, sort order (drag or up/down), active toggle, requires-notes toggle. No conditional-logic builder, no drag-and-drop framework — a plain table with inline edit is enough for ~10 categories × ~5-8 options each.
- No changes needed to the Today's Action drawer itself beyond consuming the new source — the Alpine binding (`responseOptions`, `x-for`) already iterates a key→label map.

**Suggested defaults to seed per category** (replacing generic fallback where it currently doesn't fit):

| Category | Suggested outcomes |
|---|---|
| `appointment_reminders` | Confirmed attendance · Asked to reschedule · No answer · Wrong number |
| `follow_up_calls` | Patient doing well · Has a concern — noted · No answer · Left voicemail |
| `recall_calls` | Booked recall appointment · Will call back · Not interested right now · No answer · Wrong number |
| `lead_followups` / `new_enquiries` | (keep current `default` — already fits) |
| `opportunities` / `pending_estimates` | Still deciding · Booked consultation · Declined · No answer |
| `membership_renewals` | Renewed on call · Will decide by [date] · Not renewing · No answer |
| `lab_ready` | Booked pickup appointment · Will collect later · No answer |
| `birthday` | (keep existing custom set) |
| `payment_reminders` | (keep existing custom set) |

## 5. Out of scope (V1)

- No conditional branching between outcomes (e.g. "if X then show sub-question Y") — not requested, adds real complexity for no proven demand.
- No per-clinic outcome sets yet (single-clinic today) — the schema supports it later via a `clinic_id` column addition, but don't build multi-tenant plumbing before there's a second clinic.
- No outcome-level analytics dashboard yet — flagged above as the natural next phase, not bundled here.

## 6. Build size estimate

Small–medium: 1 migration, 1 model, 1 seeder, ~2 controller methods extended, 1 new Settings tab (view + routes), 1 validation rule addition. Roughly a single focused session, no destructive changes, additive only.

## 7. Artisan commands to run (after implementation, user-executed — not run by the assistant)

```
php artisan make:migration create_action_option_lists_table
php artisan migrate
php artisan db:seed --class=ActionOptionListSeeder   (new seeder, seeds from config)
```
