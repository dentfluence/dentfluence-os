# Dentfluence Appointment Module — Phase 2 Spec (archived)

> Archived from the Claude project **"Dentfluence Appointment module"** before it is deleted.
> Status at time of archive: **MODULE COMPLETED**.
> Paste into the `app-dentfluence` project docs or keep in the repo under `docs/appointments/`.

## Framing

Not a rebuild. An enhancement of the existing appointment calendar and operational workflow.

Already existed: Add Patient popup, Add Appointment popup, basic calendar, right appointment detail drawer, appointment statuses, week/day/month views.

Goal: evolve from "basic appointment calendar" into a **real operational command center** — live reception desk, operational monitoring screen, quick-action scheduling, chair utilization tracker, doctor workflow manager — without cluttering the UI.

## 1. Right sidebar becomes "Today Patient Queue"

The old behaviour (sidebar only shows the clicked appointment) is wrong workflow. The sidebar is always visible and shows the today queue, live counts, quick actions and status controls. Cleaner and more premium than the inspiration reference — operational, not cramped.

### Section 1 — Header
- TODAY
- Current date
- Live clock, auto-updating every second (e.g. `Monday, 19 May 2026` / `11:42:08 AM`)

### Section 2 — Live status counters
Horizontal compact cards: Total, Scheduled, Checked In, In Chair, Completed, Cancelled, No Show, Walk-In.

Colour coded, live updating, clickable as instant calendar filters, tiny but highly readable.

| Status | Colour |
|---|---|
| Scheduled | Blue |
| Checked In | Amber |
| In Chair | Purple |
| Completed | Green |
| Cancelled | Red |
| No Show | Gray |
| Walk-In | Teal |

### Section 3 — Doctor filter
Dropdown or chips. All Doctors; single-select now, multi-select later. Filtering affects calendar, queue and metrics together.

### Section 4 — Today patient queue (most important)
Scrollable list of all today's appointments. Each card shows patient name, time, doctor, treatment, duration, status, chair.

**Card design**
- Left border = doctor colour (Dr Sumit → blue outline, Dr X → green outline) for rapid visual recognition
- Main fill = treatment category, very soft pastels, never saturated: Consultation → light blue, Implant → light purple, RCT → light orange, Surgery → light red, Follow-up → light gray, Cleaning → mint

**Inline actions on the card itself** (no popup for status changes, single click): Check In, In Chair, Done, Cancel, No Show, Edit, WhatsApp, View.

## 2. Remove click-to-open dependency

Hover or single click shows a floating compact quick card — instant, lightweight, non-blocking. Not a giant modal.

**Quick view content:** patient name, age, phone, doctor, treatment, duration, notes, advance paid, pending balance, next appointment.

**Quick view actions:** Check In, In Chair, Done, Cancel, Reschedule, WhatsApp, Open Patient.

Feels fast, compact, operational — not CRM-heavy. The old large right detail panel is replaced by the persistent operational queue sidebar.

## 3. Calendar improvements

- **Full slot visualization** — a 90-minute appointment occupies the full 90-minute block height, exactly like Google Calendar. No tiny labels.
- **Block content** — patient name, treatment, doctor, duration, status indicator.
- **Colour system** — outline = doctor, inner fill = treatment category. Doctor recognition plus treatment recognition without visual chaos.
- **Status visuals** — Checked In: top status stripe. In Chair: glowing left border. Completed: muted opacity. Cancelled: faded + strikethrough. No Show: gray with dashed border.

## 4. Walk-in quick add

Floating quick-add button so reception can add walk-ins instantly:
`+ Walk In` → search existing patient or quick-add patient → assign doctor → assign treatment → added immediately to calendar. Target: under 20 seconds.

## 5. Today reminders widget

Mini checklist in the sidebar as reception's operational memory — call lab, patient payment reminder, implant kit pending, consent pending. Simple checklist, not a full task module.

## 6. Top header

Live clock, current date, doctor filter, quick search. Search instantly matches patient, phone, treatment, appointment ID.

## 7. Appointment creation

- **Auto duration** from treatment category, manually editable: Consultation → 30 min, RCT → 60 min, Implant Surgery → 90 min, Cleaning → 45 min.
- **Smart conflict detection** for doctor overlap, chair overlap and unrealistic stacking. Non-blocking warnings, not hard errors initially.

## 8. Queue ordering logic

Priority order: Checked In waiting → upcoming within 30 min → In Chair → Scheduled later → Completed.

## 9. Performance

Calendar must stay smooth, instant, lightweight. Avoid excessive Livewire re-renders, giant queries and heavy modals. Use lazy loading, AlpineJS interactions, lightweight floating cards.

## 10. Database additions

`appointment_color_categories`, `walk_in_flag`, `queue_position`, `estimated_wait_time`, `checked_in_at`, `in_chair_at`, `completed_at`.

## 11. Backend services

`AppointmentQueueService`, `LiveStatusCounterService`, `WalkInAppointmentService`, `AppointmentQuickViewService`, `DoctorAvailabilityService`.

## 12. UX philosophy

High-end dental reception OS + air traffic control + Google Calendar simplicity. Not a hospital ERP.

**Do:** keep whitespace, soft colours, prioritise readability, optimise receptionist speed, reduce clicks, support touch.

**Do not:** giant tables, text overload, enterprise CRM look, excessive popup chains, actions hidden behind menus.

**Responsive:** desktop-first, sidebar collapses elegantly on tablet.

## 13. Implementation priority

- **Phase A** — persistent right sidebar queue, live counters, doctor filter, clock/date, full-height appointment blocks
- **Phase B** — quick hover card, inline status actions, walk-in quick add
- **Phase C** — reminders widget, smarter queue sorting, analytics hooks

## 14. Tech notes

Use FullCalendar customization, Tailwind, AlpineJS, lightweight AJAX updates. Avoid full page reloads, modal-heavy UX, deeply nested Livewire components.

## Final UX target

A receptionist can see the entire clinic flow, update statuses, manage the queue, add walk-ins, identify delays, contact patients and monitor doctors — without opening multiple screens.
