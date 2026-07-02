# Dentfluence OS — Project Summary
> Generated: May 2026 | Stack: Laravel 13 · Alpine.js · Tailwind CSS · FullCalendar · MySQL

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP) |
| Frontend | Alpine.js + Tailwind CSS |
| Calendar | FullCalendar (day/week/month views) |
| Database | MySQL (via Laragon local) |
| Fonts | Cormorant Garamond (headings) + DM Sans (body) |
| Brand Colors | `#6a0f70` (primary purple) · `#380740` (dark) |
| Local URL | `http://dentfluence.test` |
| Version Control | Git (key commit: `9e88aab` — "Full backup v1") |

---

## Database Tables

### Core Tables

| Table | Key Columns | Notes |
|---|---|---|
| `users` | `id`, `name`, `email`, `password`, `role` | Auth; doctors use this table |
| `patients` | `id`, `name`, `phone`, `dob`, `gender`, `address`, `tags` | Core patient record |
| `appointments` | See below | Central operational table |
| `treatment_categories` | `id`, `name`, `default_duration_minutes`, `color` | e.g. Consultation, RCT, Implant |
| `treatments` | `id`, `treatment_category_id`, `name`, `default_duration_minutes` | Belongs to a category |
| `patient_notes` | `id`, `patient_id`, `user_id`, `note`, `created_at` | Clinical/operational notes |
| `consultations` | `id`, `patient_id`, `doctor_id`, `date`, `chief_complaint`, `notes` | Consultation records |
| `treatment_plans` | `id`, `patient_id`, `doctor_id`, `title`, `status`, `items` (JSON) | Treatment plan per patient |
| `tags` | `id`, `name`, `color` | Patient tagging system |
| `patient_tag` (pivot) | `patient_id`, `tag_id` | Many-to-many |

### `appointments` Table — Full Column List

```
id
patient_id          → FK to patients
doctor_id           → FK to users (doctor)
treatment_category_id → FK to treatment_categories
treatment_id        → FK to treatments (nullable)
appointment_date    → date
appointment_time    → time
duration_minutes    → integer (default 30)
status              → enum: scheduled, checkin, in_chair, checkout, done, cancelled, no_show
notes               → text (nullable)
chief_complaint     → text (nullable, legacy field)
is_walkin           → boolean (default false)        ← Phase 2 migration
checked_in_at       → timestamp (nullable)           ← Phase 2 migration
in_chair_at         → timestamp (nullable)           ← Phase 2 migration
completed_at        → timestamp (nullable)           ← Phase 2 migration
queue_position      → smallint (nullable)            ← Phase 2 migration
estimated_wait_minutes → smallint (nullable)         ← Phase 2 migration
chair_number        → tinyint (nullable)             ← Phase 2 migration
created_at / updated_at
```

> **Migration Note:** Phase 2 columns were written but hit a class-name error during `php artisan migrate`. Verify these columns exist in your DB: `SHOW COLUMNS FROM appointments;`

---

## Routes

```php
// Auth
GET  /login                    → AuthController@showLogin         (login)
POST /login                    → AuthController@login             (login.post)
POST /logout                   → AuthController@logout            (logout)

// Dashboard
GET  /dashboard                → DashboardController@index        (dashboard)

// Patients
GET  /patients                 → PatientController@index          (patients.index)
GET  /patients/create          → PatientController@create         (patients.create)
POST /patients                 → PatientController@store          (patients.store)
GET  /patients/{patient}       → PatientController@show           (patients.show)
GET  /patients/{patient}/edit  → PatientController@edit           (patients.edit)
PUT  /patients/{patient}       → PatientController@update         (patients.update)
GET  /patients/search          → PatientController@search         (patients.search)  ← added separately

// Appointments
GET  /appointments             → AppointmentController@index      (appointments.index)
POST /appointments             → AppointmentController@store      (appointments.store)
GET  /appointments/today       → AppointmentController@today      (appointments.today)
GET  /appointments/queue/today → AppointmentController@todayQueue (appointments.queue.today) ← Phase 2
GET  /appointments/{id}        → AppointmentController@show       (appointments.show)
PATCH /appointments/{id}/status → AppointmentController@updateStatus (appointments.updateStatus)

// Consultations, Treatment Plans, Tags, Settings
// (standard resource routes — all under auth middleware)

// Stub / Coming Soon (return "Coming soon" view)
/billing, /inventory, /lab, /reports, /analytics, /crm
```

> **Critical Route Order:** `queue/today` must appear BEFORE `/{appointment}` wildcard, or Laravel routes it as an ID.

---

## Controllers & Key Methods

### `AppointmentController`

| Method | Route | Description |
|---|---|---|
| `index()` | GET /appointments | Returns appointments JSON for FullCalendar; accepts `start`, `end`, `doctor_id` params |
| `create()` | — | Passes `doctors`, `treatmentCategories` to view |
| `store()` | POST /appointments | Creates appointment; handles `is_walkin`, auto-sets `checked_in_at` for walk-ins; auto-calculates duration from treatment category |
| `show()` | GET /{id} | Returns appointment detail JSON |
| `updateStatus()` | PATCH /{id}/status | Updates status + writes operational timestamps (`checked_in_at`, `in_chair_at`, `completed_at`) |
| `today()` | GET /today | Returns today's appointments |
| `todayQueue()` | GET /queue/today | Returns today's appointments sorted by priority for sidebar queue (Phase 2) |
| `formatAppointment()` | private | Shared helper — maps Appointment model to consistent JSON shape |
| `autoDuration()` | private | Keyword-matches treatment category name → returns default duration in minutes |

### `PatientController`

- Standard CRUD
- `search()` added for appointment booking autocomplete (`GET /patients/search?q=`)

---

## Models

### `Appointment`
- `$fillable` includes all appointment columns including Phase 2 fields
- `$casts`: `is_walkin` → boolean; `checked_in_at`, `in_chair_at`, `completed_at` → datetime
- Relationships: `patient()`, `doctor()` (→ User), `treatmentCategory()`, `treatment()`
- Scopes: `scopeToday()`, `scopeForDoctor($id)`, `scopeForBranch()`
- Helpers: `isActive()`, `getEndTimeAttribute()`

### `TreatmentCategory`
- `id`, `name`, `default_duration_minutes`, `color`
- `hasMany(Treatment::class)`

### `Treatment`
- `id`, `treatment_category_id`, `name`, `default_duration_minutes`
- `belongsTo(TreatmentCategory::class)`

---

## Modules — Completion Status

### ✅ Fully Complete
| Module | Notes |
|---|---|
| Auth (Login/Logout) | Working |
| Dashboard | Complete |
| Patients | List, profile, notes, tags, add/edit |
| Appointments | Phase 1 + Phase 2 (see status below) |
| Consultations | Complete |
| Treatment Plans | Complete |
| Tags & Settings | Complete |
| Huddle Module | Complete |
| Communication OS | Complete |
| PRM (Patient Relationship Management) | Complete |
| Timeline | Complete |
| Content Management | Complete |

### 🚧 Stubs (Placeholder Only)
| Module | Status |
|---|---|
| Billing | Route exists, returns "Coming soon" |
| Inventory | Route exists, returns "Coming soon" |
| Lab | Route exists, returns "Coming soon" |
| CRM | Route exists, returns "Coming soon" |
| Reports | Route exists, returns "Coming soon" |
| Analytics | Route exists, returns "Coming soon" |

---

## Appointment Module — Phase 2 Detail Status

### ✅ Phase 2A — Confirmed Built & Delivered
- Persistent right sidebar (replaced click-drawer)
- Live clock in sidebar (auto-updates every second)
- Live status counters (Total, Scheduled, Checked In, In Chair, Completed, Cancelled, No Show)
- Today patient queue in sidebar
- Doctor filter (single-select)
- Walk-in quick-add button + modal
- Inline status actions on queue cards (Check In, In Chair, Done, Cancel)
- Status color system (Blue, Amber, Purple, Green, Red, Gray, Teal)
- Doctor color-coded left border on queue cards
- Treatment category pastel fill on queue cards
- Full-height appointment blocks (day view — via `apptStyle()`)
- Patient search autocomplete in booking modal (`/patients/search`)
- Treatment category → treatment cascading dropdowns
- Auto-duration from treatment category
- Notes field (mandatory)

### ⚠️ Rollback Event
After Phase 2A was built, a route error (`communication.manager.index`) and `@json()` Blade issues caused a full git rollback to commit `9e88aab`. Post-rollback, some fixes were reapplied in smaller sessions. **Current state of `appointments/index.blade.php` needs verification** — share the file to confirm what's actually live.

### ❌ Phase 2B/C — Not Yet Built
| Feature | Phase |
|---|---|
| Hover quick-card (floating, non-blocking) | B |
| Full-height blocks in week view | B |
| Smart conflict detection (non-blocking warning) | B |
| Reminders/checklist widget in sidebar | C |
| Priority queue ordering (Checked-In first → Upcoming 30min → In Chair → Scheduled → Done) | C |
| `queue_position`, `estimated_wait_time`, `walk_in_flag` DB columns (verify migration ran) | A |
| Auto-populate patient details in walk-in from search | B |

---

## Key Decisions Made

1. **No Livewire** — all interactivity via Alpine.js + lightweight AJAX. Avoids rerender overhead.
2. **Single Alpine component** — `appointmentCalendar` data object holds all state. Clean, no cross-component events.
3. **FullCalendar** for calendar rendering — day/week/month views.
4. **Doctor = User model** — no separate doctors table; `users` with role filter.
5. **Treatment duration auto-fill** — keyword match in `autoDuration()` helper, user can override.
6. **Status timestamps** — written automatically on `updateStatus()`, not manual input.
7. **Walk-ins** — set `is_walkin = true` + status auto-set to `checkin` + `checked_in_at` stamped.
8. **Patient search** — live AJAX autocomplete, separate from "Add Patient" flow.
9. **Notes mandatory** — enforced client-side + server-side; `chief_complaint` kept as legacy fallback.

---

## Pending Phase 2 Work — Next Steps

### Immediate (to close Phase 2B)

1. **Confirm migration ran** — verify `is_walkin`, `checked_in_at`, `in_chair_at`, `completed_at` columns exist
2. **Hover quick-card** — floating card on appointment hover; shows patient name, age, phone, doctor, treatment, advance paid, pending balance, actions
3. **Week view full-height blocks** — apply `apptStyle()` to week view (currently chip layout only)
4. **Conflict detection** — non-blocking warning when doctor/chair overlap detected on booking

### Phase 2C (operational polish)

5. **Priority queue sort** — reorder sidebar queue: Checked-In waiting → Upcoming ≤30min → In Chair → Scheduled → Completed
6. **Reminders widget** — simple localStorage checklist in sidebar (lab call, payment reminder, etc.)
7. **Quick search** in top bar — instant filter by patient name, phone, treatment, appointment ID

### Future Modules (priority order)

1. **Billing** — invoice generation, payment recording, balance tracking (most critical gap)
2. **Reports / Analytics** — daily/monthly appointment + revenue summaries
3. **Inventory** — consumable stock tracking
4. **Lab** — lab work order tracking

---

## Git Reference

| Commit | Description |
|---|---|
| `9e88aab` | Full backup — Dentfluence OS v1 — all modules stable |
| HEAD (after rollback) | Post-rollback state; Phase 2 partially reapplied |

**Recommended next action before building:**
```bash
git status
git log --oneline -10
```
Then share `resources/views/appointments/index.blade.php` to confirm current working state.

---

*Document generated from full conversation history — May 2026*
