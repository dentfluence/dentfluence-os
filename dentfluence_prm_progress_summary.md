# Dentfluence — Communication OS + PRM Module
## Project Progress Summary
> Last updated: May 2026 | Format: Save-ready markdown reference

---

## 1. Project Identity

| Item | Detail |
|------|--------|
| **Product** | Dentfluence — Healthcare SaaS for Indian dental clinics |
| **Module** | Communication OS (also called PRM — Patient Relationship Manager) |
| **Stack** | PHP 8.x · Laravel 11 · Blade · Vanilla JS · Plain CSS · MySQL · Vite |
| **Local env** | Laragon on Windows · `http://dentfluence.test` |
| **Philosophy** | "No communication leakage. Always know the next required action." |

---

## 2. Architecture Rules (Non-Negotiable)

- Controllers → `app/Http/Controllers/ModuleName/`
- Models → `app/Models/` (flat, no subfolders)
- Services → `app/Services/ModuleName/`
- Form Requests → `app/Http/Requests/ModuleName/`
- Routes → `routes/communication.php`, `routes/prm.php` (never in `web.php`)
- Views → `resources/views/communication/`, `resources/views/prm/`
- No `.tsx`, `.jsx`, `.ts` files ever
- No Tailwind, Livewire, Inertia, Filament, or npm UI frameworks
- No Repository pattern unless explicitly asked
- No business logic in controllers — use Services

---

## 3. URL / Route Structure

All PRM screens are accessed from within the module — **no main sidebar links** beyond the top-level entry point.

| Route | Screen |
|-------|--------|
| `/communication/manager` | Communication Manager (main queue) |
| `/communication/prm` | PRM main hub / Pipeline Board |
| `/communication/prm/leads` | Leads list |
| `/communication/prm/leads/create` | Add Lead (full form) |
| `/communication/prm/leads/{id}` | Lead Detail Screen |
| `/communication/prm/leads/{id}/edit` | Edit Lead |
| `/communication/prm/pipeline` | Pipeline Board (Kanban) |
| `/communication/followup-engine` | Follow-up Calendar |
| `/communication/call-manager` | Call Manager |
| `/communication/call-manager/{id}` | Call Detail Screen |
| `/communication/activity-log` | Activity Log |
| `/communication/tasks` | Tasks & Assignments |
| `/communication/prm/settings` | PRM Settings |

Route prefix: `communication.` | Middleware: `auth`

---

## 4. Database Tables (Session 9 — Migrations)

### Core Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `persons` | Master identity entity — one record per human | `id`, `name`, `phone`, `alt_phone`, `email`, `dob`, `gender`, `source`, `type` (lead/patient/both) |
| `communication_threads` | Groups related communications | `id`, `person_id`, `subject`, `type`, `status`, `assigned_to` |
| `communications` | Individual call/WA/note records | `id`, `thread_id`, `person_id`, `type` (call/whatsapp/note/email), `direction` (in/out), `duration`, `notes`, `outcome`, `logged_by` |
| `prm_stages` | Pipeline stage definitions | `id`, `name`, `slug`, `color`, `sort_order`, `is_active` |
| `prm_leads` | Lead records in pipeline | `id`, `person_id`, `stage_id`, `source`, `treatment_interest`, `secondary_interest`, `urgency`, `assigned_to`, `follow_up_at`, `status`, `tags`, `notes` |
| `followups` | Follow-up schedule records | `id`, `lead_id`, `person_id`, `type` (call/whatsapp/clinic_visit), `scheduled_at`, `completed_at`, `outcome`, `next_step`, `assigned_to`, `status` |
| `tasks` | Assignable tasks | `id`, `lead_id`, `person_id`, `title`, `description`, `due_at`, `assigned_to`, `assigned_by`, `status`, `priority`, `escalated_to` |
| `opportunities` | Future treatment intents | `id`, `person_id`, `lead_id`, `treatment`, `intent_level`, `notes`, `status`, `assigned_to` |
| `activity_logs` | Immutable audit trail | `id`, `person_id`, `lead_id`, `user_id`, `module`, `action`, `details` (JSON), `ip_address` |
| `communication_templates` | Message templates | `id`, `name`, `type` (whatsapp/note/recall), `body`, `variables` (JSON), `is_active` |

### Key Relationships
- One **Person** → many Leads, Communications, Follow-ups, Tasks, Opportunities
- One **PrmLead** → one Stage, one Person, many Follow-ups, many Tasks
- One **CommunicationThread** → many Communications
- **ActivityLog** is append-only — never updated, only inserted

---

## 5. Pipeline Stages (Seeded)

```
New Lead → Contacted → Appointment → Consultation → Plan Given → Converted → Ongoing Treatment → Lost
```

Additional statuses: `Interested`, `Visited Clinic`, `Not Interested`, `Lost / No Response`, `Price Concern`, `Treatment Fear`, `Second Opinion`, `Delayed`

---

## 6. Screens Built (UI Complete — Visual Approval Stage)

### Session 1 — Module Foundation
- Module shell layout
- Communication sidebar (left nav)
- Topbar with search, notifications, user avatar
- Route scaffolding
- Navigation structure

**Sidebar sections:**
- COMMUNICATION: Overdue (18), Today (34), Long Term 6M+ (23), Ongoing Treatment (16), Yesterday (12), Special Days (7)
- LEADS & CALLS: Call Manager, Leads, Pipeline
- TOOLS: Activity Log, Follow-up Calendar, Tasks
- SETTINGS: PRM Settings
- Fixed bottom: "Make a Call" quick dialer

### Session 2 — Communication Manager UI
- Main execution queue screen
- Queue cards with source icons (Call/WA/Instagram)
- Status chips, overdue badges, urgency colors
- Quick actions per card (Call, WhatsApp, Note, Schedule, Assign, Move, Complete, Escalate)
- Filter bar, WhatsApp action buttons
- Manual call log form

### Session 3 — PRM Pipeline UI ✅
- **Pipeline Board** (Kanban): Board View + List View toggle
- Stats bar: Total Leads 128, Converted 26, In Pipeline 86, Lost 16
- Stage columns: New Lead (20), Contacted (18), Appointment (15), Consultation (12), Plan Given (9), Converted (26)
- Lead cards: name, phone, stage badge, follow-up date
- `+ Add Lead` per column
- Pipeline Summary donut chart at bottom
- **Add Lead screen** (full form — 8 sections):
  1. Lead Type (New Lead / Existing Patient)
  2. Basic Information (name, mobile +91, alternate, preferred contact, email, DOB, gender)
  3. Treatment Interest (primary + secondary)
  4. Source (lead source + referred by)
  5. Lead Details (urgency Low/Medium/High, preferred time to contact, how did they contact)
  6. Follow-up & Assignment (assign to, follow-up date + time)
  7. Notes + Tags
  8. Additional Info (occupation, location, language)
- **Edit Lead screen** (same structure, pre-populated, Delete Lead button)
- **Lead Detail Screen** (mobile-style):
  - Header: avatar, name, status badge, pipeline stage (2/6), lead ID
  - Call + WhatsApp action buttons
  - Treatment Interest, Source, Assigned To, Lead Created metadata
  - Next Follow-up with Reschedule button + Due Today badge
  - Last Interaction summary
  - Tabbed content: Activity & Notes | Patient Info | Documents | Tasks
  - Activity timeline: Call Done, Follow-up Scheduled, WhatsApp Sent, Call Attempted
  - Bottom action bar: Add Note | Not Reachable | Reschedule | Mark as Done
  - Convert to Patient button
- **Quick Add Lead popup** (from Call Manager) — compact modal version
- **Convert to Patient modal** — pre-fills patient details, schedules first appointment

### Session 4 — Follow-up Calendar UI ✅
- **Follow-up Calendar** main screen:
  - Stats bar: Total 128, Due Today 34, Overdue 12, Completed 82, Upcoming 52
  - Week/Day/Month/Agenda view toggle
  - Weekly calendar grid with color-coded events (Call=blue, WhatsApp=green, Clinic Visit=orange, Overdue=red)
  - Mini calendar (right panel)
  - Today's Follow-ups list (right panel) with "Due in Xh Ym" timers
  - Quick Actions panel (right): Schedule Follow-up, Add Note, Send WhatsApp, Make a Call
  - Overdue Follow-ups strip at bottom (horizontal scroll)
- **Complete Follow-up modal**:
  - Patient info header
  - Follow-up Type, Date & Time, Duration display
  - Call Outcome dropdown (Connected, Not Answered, etc.)
  - Result dropdown (Interested, Not Interested, etc.)
  - Next Step dropdown
  - Next Follow-up Date & Time
  - Notes textarea
  - "Schedule next follow-up" checkbox with notification info
- **Reschedule Follow-up modal**:
  - Current follow-up info display
  - Reason (optional), New Date & Time, Follow-up Type, Notes
- **Add Note modal**:
  - Note Type, Note textarea (1000 chars)
  - Add to Follow-up option
  - Notes Visibility: Only me / My team / Everyone
- **Change Status modal**:
  - Current status display
  - New Status dropdown with full stage list + descriptions
- **Create Case / Trigger modal**:
  - Patient info header
  - Reason for Case, Case Category, Priority
  - Assign To, Case Description, Attachments
- **Filters & Sort modal**:
  - Status, Source, Follow-up Type, Priority, Assigned To, Team, Lead Owner, Lead Score, Date Range, Next Follow-up Date, Communication Channel (checkboxes), Tags

### Session 7 — Call Manager UI ✅
- **Call Manager** main screen:
  - Filter tabs: All Calls | New (Leads) | Existing Patients | Spam | Missed
  - Date picker + Filters + Export
  - Call list table: Time, Phone Number, Tag (New Lead/Existing/Spam), Type (↑↓), Duration, Actions
  - Right panel (call detail slide-in): caller info, call type, notes, tags, Convert to Lead button, Link to Existing Patient, Quick Actions
  - Call Summary stats: Total 52, New Leads 18, Existing 22, Missed 7, Spam 5, Total Duration
  - Top Sources donut chart
- **Call Detail Screen** (full page):
  - Call direction badge (Outgoing/Incoming), phone, timestamp
  - Tabs: Call Information | Notes & Activity | Call History
  - Call Info table: phone, caller name, direction, duration, date/time, answered by, device
  - Call Notes section (editable)
  - Call Tag section (editable)
  - Call Disposition (outcome, follow-up date, assigned to)
  - Right panel: Lead Information card, Quick Actions, Call History for this number
  - Linked Lead section with Convert to Lead option
  - Bottom bar: Add Note | Mark as Not Reachable | Save & Close

### Session 11 — Activity Log UI ✅
- Full-width activity log table
- Filters: Date Range, All Users, All Modules, All Actions, Search + Filters button
- Export button
- Columns: Date & Time, Who (avatar + name + role), What They Did, Details, Module (badge), Lead/Patient (linked), IP Address, Actions (…)
- Pagination: showing X to 10 of 245 activities

### Session 12 — PRM Settings UI ✅
- **PRM Settings** with tabs:
  - Follow-up Defaults: type, date, time, duration, reminder, notes template, schedule toggle, working hours, working days
  - Assignment Defaults: lead assignment (Round Robin), order, reassign rules, escalation
  - Follow-up Outcome Defaults: default outcome, next step, next follow-up date/time
  - Communication Defaults (tab)
  - Tags & Reasons (tab)
  - Other Settings (tab)

---

## 7. Sessions Pending

| Session | Focus | Status |
|---------|-------|--------|
| Session 5 | Communication Timeline UI | ⏳ Pending |
| Session 6 | Tasks & Assignment UI | ⏳ Pending |
| Session 8 | Daily Huddle Integration | ⏳ Pending |
| Session 9 | Laravel Backend — Migrations, Models, Services | ⏳ Pending (schema designed) |
| Session 10 | Workflow Engine + Event System | ⏳ Pending |
| Session 11 | Real Data Wiring (connect UI to DB) | ⏳ Pending |
| Session 13 | Optimization + Cleanup | ⏳ Pending |

> Sessions 2, 3, 4, 7, 12 are **UI-complete**. Sessions 9–13 are **backend/wiring** phases.

---

## 8. Key Design Decisions

### Identity Model
- **Master Person entity** — one identity across lead, patient, opportunity, recall
- A person can be: lead + patient + referral source simultaneously
- No duplicate records; `persons` table is the single source of truth

### Communication Thread Model
- Groups related calls/notes/WA messages under one thread (e.g. "Implant Consultation Inquiry")
- Scales better than isolated communication records

### No Dead-End Interactions
- Every communication must produce: outcome + next action + follow-up date or closure
- Enforced via Completion Popup after every call/action

### WhatsApp = Action Button Only
- No WhatsApp API, no webhook, no chatbot
- Just "Open WhatsApp Web" button with pre-filled number
- Quick template copy support

### Manual-First Phase
- All workflows are manual for now (call logs, follow-ups, pipeline movement)
- Architecture is event-ready for future automation

### Event-Driven Architecture (Future-Ready)
- Key events designed: `CommunicationLogged`, `LeadStageChanged`, `FollowUpOverdue`, `AppointmentMissed`, `TreatmentCompleted`, `OpportunityCreated`, `TaskEscalated`
- Listeners + Jobs + Scheduled Commands planned for Session 10

---

## 9. File Structure Summary

```
app/Http/Controllers/Communication/
    CommunicationController.php
    DashboardController.php
    ManagerController.php
    PrmController.php
    FollowUpController.php
    TimelineController.php
    TaskController.php
    OpportunityController.php
    HuddleController.php
    TemplateController.php

app/Models/Communication/
    Person.php
    CommunicationThread.php
    Communication.php
    PrmLead.php
    PrmStage.php
    FollowUp.php
    Task.php
    Opportunity.php
    ActivityLog.php
    CommunicationTemplate.php

app/Services/Communication/
    CommunicationService.php
    PrmService.php
    FollowUpService.php
    TaskService.php
    OpportunityService.php
    ActivityLogService.php
    TemplateService.php
    SmartDefaultsService.php

routes/
    communication.php
    prm.php

resources/views/communication/
    manager/ (index, queue, overdue, log-form)
    prm/ (index, board, lead-detail, add-lead)
    followup/ (index, queue, overdue, calendar, recall-queue)
    timeline/ (index, patient-timeline)
    tasks/ (index, queue, my-tasks, escalated)
    opportunities/ (index, board, detail)
    huddle/ (widgets, overdue-summary, communication-alerts)
    templates/ (index, editor)

resources/views/components/
    prm/ (pipeline-column, lead-card, stage-badge, source-tag, lead-drawer, stage-selector)
    followup/ (followup-card, type-badge, due-indicator, recall-card, add-followup-modal)
    communication/ (queue-card, quick-actions, source-icon, status-chip, filter-bar, whatsapp-button)
    timeline/ (timeline-wrapper, timeline-item, call-block, whatsapp-block, note-block, etc.)
    tasks/ (task-card, assignment-modal, escalation-flag, due-date-picker, assignee-avatar)

database/migrations/communication/
    (10 migration files — persons through communication_templates)

database/seeders/Communication/
    PrmStagesSeeder.php
    CommunicationTemplatesSeeder.php
```

Total: ~180 files across 13 sessions

---

## 10. Integration Points (Existing Dentfluence Modules)

| External Module | Integration |
|----------------|-------------|
| **Patients** | Convert Lead → creates Patient; Person entity links to Patient |
| **Appointments** | Missed appointment triggers follow-up; appointment booked triggers reminder workflow |
| **Daily Huddle** | Feeds overdue callbacks, VIP patients, birthdays, escalations, pending estimates |
| **Treatment Plans** | Treatment completed → triggers recall cycle; opportunity linked to treatment |
| **Billing** | Pending estimate → appears in communication queue |
| **Dashboard** | PRM stats widget, conversion rate, overdue count |
| **Notifications** | Follow-up reminders, escalation alerts, reassignment notifications |

---

## 11. What NOT to Build (Current Phase)

- ❌ Mobile app / Android app
- ❌ Auto call sync from mobile
- ❌ WhatsApp API / webhooks / chatbot
- ❌ AI suggestions or voice summaries
- ❌ Predictive recalls
- ❌ Advanced analytics / coordinator analytics
- ❌ Telephony integrations
- ✅ These are architecture-ready but implementation is parked

---

## 12. Next Immediate Sessions

1. **Session 5** — Communication Timeline UI (unified patient history: calls, WA, notes, appointments, tasks, opportunities)
2. **Session 6** — Tasks & Assignment UI (task queue, assignment modal, escalation flags)
3. **Session 8** — Daily Huddle widgets (overdue summary, VIP, birthdays, escalations)
4. **Session 9** — Laravel backend: run migrations, build models + relationships, wire services

---

*Document generated from project design files, session plans, screen designs, and architectural decisions. Update after each session.*
