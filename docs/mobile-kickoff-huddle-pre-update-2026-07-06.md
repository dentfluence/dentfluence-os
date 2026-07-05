# Mobile Kickoff Prompt — Daily Huddle + PRE Update (2026-07-06)

Copy everything below the line into a **new chat session** that has access to both
`E:\Dentfluence\Dentfluence_OS\Dentfluence Web` and `dentfluence_mobile`. It is
self-contained — the new session has no memory of the web-side conversation that
produced these changes.

---

## Context

Dentfluence is a Laravel dental-clinic OS with a companion Flutter mobile app
(`dentfluence_mobile`). On 2026-07-06, a web-only session made a significant set
of changes to two areas: the **Daily Huddle** dashboard (`/huddle`) and the
**PRE / Relationship Engine** (`/relationship/*`), especially the **Opportunity
Pipeline**. None of this has been ported to mobile yet. Your job is to bring
mobile up to parity — but **audit what already exists on mobile first**; per
`docs/mobile-parity-build-sequence.md`, mobile already has a Daily Huddle screen
(partial) and a full PRE module (8 Flutter screens, built 2026-07-05, one day
*before* these changes) with its own `Api/V1/RelationshipController` on the
backend. Some of what's below may already be half-built on mobile and just need
extending; don't assume a blank slate, and don't assume full parity either.

Read the actual current mobile code before making changes — this doc describes
what changed on web, not what already exists on mobile.

## Working rules (same as web)

- Read before writing. Don't assume mobile's current structure — check it.
- Build complete vertical slices (API + model + screen), not partial UI.
- Never run destructive commands. For Flutter, `flutter pub get` before running
  is fine/expected; don't run `flutter clean`, delete migrations, or touch the
  database without asking first.
- If a task looks large, say so and propose slices before starting, same as the
  web project's pre-flight rule.
- No field omission vs. the web Blade views/behavior described below — they are
  ground truth.

---

## Part A — Database schema changes (extend the API first)

Two new migrations landed on web. Mobile's API layer will need to expose these
fields wherever it currently serializes the affected models:

```
database/migrations/2026_07_06_000001_add_declined_reason_to_treatment_opportunities.php
    → treatment_opportunities.declined_reason  (nullable text)

database/migrations/2026_07_06_000002_add_flow_details_to_appointments_table.php
    → appointments.amount_to_collect       (decimal 10,2, nullable)
    → appointments.prep_item               (string 255, nullable)
    → appointments.chairside_assistant_id  (nullable FK → users.id)
```

Check `app/Models/TreatmentOpportunity.php` and `app/Models/Appointment.php` for
the new fillable/casts/relations (`chairsideAssistant()` on Appointment).

## Part B — PRE / Opportunity Pipeline (this is the biggest change)

**The old `/communication/opportunities` "Opportunity Engine" screen is fully
retired.** It now redirects to `/relationship/opportunities`, which is the new,
single, fully-interactive Opportunity Pipeline. If mobile's existing PRE module
has an Opportunity screen, it was almost certainly built against the OLD
read-only `relationship.opportunities` route (per the 2026-07-05 PRE build) —
it needs to be upgraded to match the new write-capable behavior below.

Ground truth files (web):
- `app/Http/Controllers/Relationship/OpportunityPipelineController.php`
- `resources/views/relationship/opportunities/index.blade.php`
- `resources/views/relationship/opportunities/_detail-card.blade.php`
- Routes in `routes/relationship.php` (search `opportunities.`)

What changed / what mobile needs:

1. **Kanban board** — 6 columns: Identified, Nurturing, Estimate Given,
   Committed, Converted, Declined. (Model: `TreatmentOpportunity::STAGES`.)
2. **Add Opportunity** — patient search/autocomplete, treatment type, priority
   (high/medium/low), estimated value, follow-up date + time, assign-to staff,
   notes. `POST relationship.opportunities.store`.
3. **Move stage** — `PATCH relationship.opportunities.update-stage` with
   `{ status, reason? }`. The `reason` field is new and important:
4. **Decline reason (new)** — when moving an opportunity to "Declined" status,
   the UI should prompt for an **optional** free-text reason before submitting.
   Skipping is fine (declines with no reason). This reason is stored in
   `declined_reason` and should be shown wherever opportunity detail is
   displayed (see #6). **Bug that was just fixed on web**: the UI used to show
   a "✓ Converted" badge for BOTH completed and declined opportunities — make
   sure mobile distinguishes them (✓ Converted vs ✗ Declined) from day one.
5. **Convert to Lead** — `POST relationship.opportunities.convert` with
   `{ stage, assigned_to? }`. Creates a `Lead` record, marks the opportunity
   `completed`. Small modal: pick an initial pipeline stage (New Lead /
   Contacted / Consultation Booked).
6. **Opportunity detail** — tapping a card should show: patient header (name,
   phone, treatment, priority tag), a stage progress bar, a details grid
   (status/priority/estimated value/follow-up date/assigned to/created
   by/created/last updated), notes, decline reason (if declined), quick
   stage-move actions, and linked treatment plan (if any). On web this opens as
   a popup via `GET relationship.opportunities.detail-modal` — on mobile this
   is naturally just a detail screen/sheet, same content.
7. **Patient search** — `GET relationship.opportunities.patient-search?q=`

## Part C — Today's Actions: new "Follow-up Calls" category

`app/Services/Relationship/TodayActionsEngine.php` gained a new category,
`follow_up_calls`, ranked **#2** (right after "Today's Appointments — Confirm").
It reads the `FollowUp` model (pending, due today or overdue) — a general
call-back/reminder record used by several flows (Huddle's "Yesterday's Flow"
booking a follow-up call, the standalone Follow-up Engine, PRM lead
follow-ups). **Before this change, zero categories read this model at all** —
if mobile's Today's Actions / PRE screens don't show a "Follow-up Calls" bucket,
they're missing this entire class of reminder.

Check `app/Http/Controllers/Relationship/TodayController.php` for the full
`CATEGORY_LABELS` / `CATEGORY_ICONS` / `CATEGORY_PRIORITY` maps — the priority
order matters and was deliberately re-ranked (see the comment in that file).
There's also a `logged_communications` category (manually-logged calls that
don't fall into another category) that may predate mobile's last PRE sync —
verify mobile has it.

## Part D — Missed Calls list (`/relationship/today/missed-calls`)

- "Bulk WhatsApp" was removed entirely — don't build it if mobile doesn't have
  it yet; if it does, remove it.
- A "select all matching filter" bulk-dismiss was added, bypassing the 50-per-
  page pagination cap (server does a chunked update against the active filter,
  not just the visible page). Relevant if mobile has any bulk-dismiss UI here.

## Part E — Daily Huddle (`/huddle`)

Ground truth: `app/Modules/Huddle/Controllers/HuddleController.php` (the one
under `app/Modules/Huddle/`, not the legacy `app/Http/Controllers/Communication/HuddleController.php`)
and `resources/views/huddle/index.blade.php`.

1. **Stat row** — "Critical Alerts", "Birthdays Today", "Pending Estimates",
   and "Overdue Follow-ups" cards are gone. Replaced with **"Open Leads"**
   (links to Lead Pipeline) and **"Open Opportunities"** (links to Opportunity
   Pipeline) — both real counts, not date-scoped. If mobile's Huddle stat row
   still shows the old four, update it.

2. **"Collections (Today)" is now real** (was 100% hardcoded placeholder text
   before). It's the sum of `amount_to_collect` across **today's scheduled
   (non-walk-in) appointments only** — walk-ins are deliberately excluded since
   they aren't planned ahead of time. Subtext shows "`X` of `Y` appointments
   logged". See Part F for where `amount_to_collect` comes from.

3. **Comms List widget redesigned** — no longer synthetic
   Reminders/Yesterday's-Follow-ups/Special-Day-Calls sections. It's now a
   compact **scrollable glimpse of Today's Actions** (same data source as
   `/relationship/today`), grouped by category. The old "+ Add Communication"
   link and the old "Relationship Actions" bottom board section were both
   removed (redundant with Today's Actions). If mobile has any of these old
   sections, retire them the same way.

4. **"Yesterday's Flow" card** — the "Add to Call List" quick-action
   button/feature was removed (dead/duplicate functionality — it doesn't exist
   in the source Blade partial anymore: `resources/views/partials/yesterday-followup-card.blade.php`).
   The main popup — Task (optional) / "Book Follow-up call?" toggle + date /
   Reason / Assign To — is unchanged. It saves via
   `POST huddle.yesterday-flow.log` and creates a `Task` and/or `FollowUp`
   record. If mobile doesn't have this popup at all yet (per the parity doc,
   it may not), this is a good one to build now — it directly feeds Part C's
   new "Follow-up Calls" category.

## Part F — Daily Huddle: new "Today's Patient Flow" popup (biggest UI addition)

This is brand new — almost certainly does not exist on mobile yet. Ground
truth: `resources/views/partials/today-flow-card.blade.php` (new file) and
`HuddleController::updateInstruction()` (extended, despite the name — it now
saves 4 fields in one call).

Tapping a **scheduled appointment** card (not a walk-in, not a treatment
visit/consultation) should open a popup — replacing whatever "tap → go to
patient profile" behavior exists today — with:

- **Notes** — free text (`staff_instruction` column, already existed).
- **Amount to Collect** — number input, ₹. **Hidden entirely for walk-in
  appointments** (`is_walkin = true`) — show a small note instead ("Not
  tracked for walk-ins…"). This is what feeds the Collections (Today) stat.
- **Essential Item / Task** — free text (`prep_item`), e.g. "Carry surgical
  kit, OPG needed". Deliberately a lightweight text field, not the full Task
  system — front desk fills this in for many appointments every morning, so it
  needs to be fast.
- **Chairside Assistant** — dropdown of active branch staff
  (`chairside_assistant_id`), separate from the primary doctor already shown
  on the appointment.

Save via `PATCH /huddle/appointments/{id}/instruction` with body
`{ staff_instruction, amount_to_collect, prep_item, chairside_assistant_id }`.
Server ignores `amount_to_collect` for walk-ins even if sent (defensive). The
appointment card's preview line should then show the note, a 📌 prep-item
snippet, and "₹X to collect" inline; the meta row should show the chairside
assistant's name alongside the doctor.

## Part G — "+ Add New Issue" (Failures/Maintenance)

On web, the previously-dead "+ Add New Issue" link in the Huddle's
Failures/Maintenance section now opens the global Create Task modal with
category pre-set to "Maintenance". Low priority for mobile unless mobile
already has an equivalent Maintenance list with the same dead-link problem —
check `resources/views/partials/create-task-modal.blade.php` for the pattern
(`open-create-task` event with an optional `category` detail).

---

## Suggested slicing

1. Extend the API (`Api/V1/RelationshipController`, `Api/V1/HuddleController`
   or equivalent) for the new fields/endpoints in Parts A, B, C, F — audit
   what's already exposed before adding.
2. Opportunity Pipeline screen(s) — Part B (largest single chunk).
3. Today's Actions — add/verify Follow-up Calls category — Part C.
4. Daily Huddle stat row + Collections (Today) + Comms List — Part E.
5. Today's Patient Flow popup — Part F.
6. Yesterday's Flow popup, if not already built — Part E.4.
7. Missed Calls bulk-dismiss parity — Part D (lowest priority, desk-only
   feature).

Flag before starting each slice if it looks like it'll exceed one response.
