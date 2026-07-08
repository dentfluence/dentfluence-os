# Feature Spec — Notes + Dismiss on Today's Action Board

> **Created:** 2026-07-08 (revised same day — simplified per Sumit's correction)
> **Author:** Sumit / Dentfluence
> **Module:** PRE — Today's Action Board
> **Status:** Not built. Spec only.

## 0. Revision note

The first draft of this spec proposed two separate things: a static per-category
"instruction/script" card (new `action_instructions` table) plus a note-log card.
Sumit corrected that — **don't complicate it.** What's wanted is exactly the popup
already built and live on the Opportunity Pipeline / Lead Pipeline detail cards:
the same "Notes" block (Suggestion / Patient Response toggle + Add Note button),
ported as-is to the Today's Action Board drawer, alongside the Dismiss option that
already exists there. No new table, no separate instruction card. This revision
replaces the first draft below.

## 1. What exists today (verified 2026-07-08)

- **Lead Pipeline** (`resources/views/relationship/pipeline/_detail-card.blade.php`) and
  **Opportunity Pipeline** (`resources/views/relationship/opportunities/_detail-card.blade.php`)
  both have a live "Notes" block: a reverse-chronological list of timestamped,
  attributed notes, each tagged **Suggestion** or **Patient Response**, plus an
  "Add Note" form (type dropdown + textarea + button) — see
  `OpportunityPipelineController::addNote()` / `notesFor()`, event
  `opportunity.note_added` written via `ActivityEngine::log()`.
- **Today's Action Board drawer** (`TodayController`, `resources/views/relationship/today/index.blade.php`)
  already has a call-outcome dropdown and a **Dismiss** option (with required reason,
  `TodayController::dismiss()`) — but no Notes block. That's the only gap.

## 2. What changes — port the existing Notes block, don't redesign it

Copy the same UI/behavior from the Opportunity detail card into the Today's Action
Board drawer:

- Same two note types: **Suggestion** (staff observation) / **Patient Response**.
- Same list rendering (type badge, text, author, timestamp).
- Same Add Note form (dropdown + textarea + button), same disabled-until-text
  behavior, same inline error handling.

**Backend**
- New endpoint `POST /relationship/today/notes` — mirrors
  `OpportunityPipelineController::addNote()` exactly: validates `note_type`
  (`suggestion`|`response`) and `text` (required, max 1000 — same cap as
  Lead/Opportunity, no need for a different limit), resolves the subject using
  the category→model logic `TodayController` already has for `logAction()` /
  `dismiss()`, and calls `ActivityEngine::log($subject, 'today_action.note_added', auth()->user(), [...])`.
- A `notesFor()`-equivalent read helper, same shape as the Opportunity one, filtered
  to `today_action.note_added` for the resolved subject.
- No migration. No new model. `today_action.note_added` is just a new event key on
  the existing `activities` table, exactly like `opportunity.note_added`.

**Frontend**
- Add the same block (list + type dropdown + textarea + Add Note button) to the
  Today's Action Board drawer, wired to the new endpoint. Visually and
  behaviorally identical to the Opportunity/Lead version — same badge colors,
  same layout.
- Dismiss stays exactly as it already works today — this spec doesn't touch it.

**Optional, not required for V1:** this is now the third screen with the same
markup (Lead, Opportunity, Today's Actions). Worth extracting into one shared
Blade component (`<x-relationship.note-log>` or similar) to avoid a third copy of
the same ~50 lines — a genuine "minimal duplication" win, but purely a
refactor, not a functional change, so it's optional and can be done in the same
pass or skipped without affecting behavior.

## 3. Where AI fits later (no work now)

No separate instruction/script card. If/when the AI Copilot (Tulip) is ready to
suggest what to say, it writes a **Suggestion**-type note itself (as a system
actor, same `ActivityEngine::log()` call, same UI) — the mechanism already
supports an authored note appearing before a human writes one. No new card, no
new table, no UI change required at that point.

## 4. Out of scope (V1)

- No AI-authored suggestions yet (see above — future, zero new plumbing needed).
- No note edit/delete — same audit-trail principle as Lead/Opportunity.
- No shared-component refactor required to ship this (optional cleanup only).

## 5. Build size estimate

Small: 1 new controller endpoint (near copy-paste of an existing one), 1 read
helper, 1 Blade block ported into an existing drawer. No migration.

## 6. Artisan commands to run (after implementation)

```
php artisan route:clear
```
