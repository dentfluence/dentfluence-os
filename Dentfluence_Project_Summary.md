# Dentfluence — Project Build Summary
**Generated:** May 27, 2026  
**Status:** Product Freeze Layer — Phase 1 Complete  
**Tagline:** Built by dentists, for dentists · tulipdental.in · Dombivli East

---

## 1. What Dentfluence Is

A **Dental Operating System** — not a practice management tool, not a CRM, not a billing app. It is the execution layer for a dental clinic's entire daily operation: every role, every workflow, every patient interaction, coordinated in one system.

**Target clinic:** 1–3 chair Indian dental clinic, owner-operated, 1–5 staff, no IT department.

**Core philosophy:** Every screen answers one question — *What does this role need to execute right now?*

---

## 2. Documents Completed

| ID | Document | Status |
|----|----------|--------|
| DOC01 | Master Vision Document | ✅ LOCKED |
| DOC02 | Complete System Architecture | ✅ LOCKED |
| DOC03 | UI/UX Design System | ✅ LOCKED (rebuilt from image reference) |
| DOC04 | Feature Lock & Governance | ✅ LOCKED |
| DOC05 | AI Context Master | ✅ LOCKED |
| SCREEN_REGISTRY_MASTER.md | Screen Registry — M01, M02, M03 | ✅ Built |
| Master Module Map (docx) | Phase 1 Freeze Layer — Module Architecture | ✅ Built |

---

## 3. Module Architecture (All 22 Modules)

### Primary Modules (built first — used every session by all roles)

| ID | Module | Roles |
|----|--------|-------|
| M01 | Daily Huddle Engine | All roles — primary entry point |
| M02 | Appointment System | Front Desk, Manager, Dentist |
| M03 | Patient Relationship Manager (PRM) | All roles |
| M04 | Treatment Planning | Dentist (owner), Assistant (read) |
| M05 | Treatment Presentation Engine | Dentist, Front Desk |
| M06 | AI Assistant Layer | All roles (contextual) |
| M07 | Financial Tracking | Front Desk, Manager, Owner |
| M09 | Follow-Up & Recall Engine | Front Desk, Manager |
| M10 | Communication Hub | Front Desk, Manager |
| M11 | Task Management System | All roles |
| M12 | Workflow Automation Engine | Manager, system-triggered |
| M16 | Consent & Documentation | Dentist, Front Desk |

### Secondary Modules (used regularly, not every session)

| ID | Module | Primary Role |
|----|--------|--------------|
| M08 | Inventory System | Manager, Assistant |
| M13 | Staff SOP Engine | Manager (authoring), All (consumption) |
| M14 | Patient Education Library | Dentist, Front Desk |
| M15 | Fee & Pricing Engine | Manager, Front Desk |
| M17 | Imaging Integration | Dentist, Assistant |
| M19 | Doctor Instructions System | Dentist → Assistant |
| M20 | Clinic Analytics | Owner, Manager |

### Shared Systems (infrastructure — no direct user screen)

| ID | Module |
|----|--------|
| M18 | Internal Notes System |
| M21 | Audit Log System |
| M22 | Notification Engine |

### Explicitly NOT in Scope (frozen exclusions)

- Insurance billing / TPA processing
- Accounting / payroll / HR
- Marketing / social media tools
- Teledentistry / video consult
- Patient-facing mobile app
- Lab management (beyond task/tracking)

---

## 4. Key Architecture Decisions (Frozen)

| ID | Decision |
|----|----------|
| FRZ-M-001 | 22 modules only — no additions without full review process |
| FRZ-M-002 | M21 (Audit Log) receives write events from ALL modules — never bypassed |
| FRZ-M-003 | M22 (Notification Engine) is role-based — staff cannot configure individually |
| FRZ-M-004 | Treatment cannot be scheduled until plan created, presented, consented (M04→M05→M16 gate) |
| FRZ-M-005 | M01 Daily Huddle is the primary home screen for ALL roles — not a dashboard |
| FRZ-M-006 | PRM (M03) is single source of truth for all patient data — no duplication |
| FRZ-M-007 | AI layer (M06) is assistive only — no autonomous actions, no patient-facing output |
| FRZ-M-008 | Financial module (M07) displays outstanding balance — does NOT replace accounting software |
| AI-C-001–010 | 10 universal AI constraints: no clinical diagnosis, no autonomous messaging, human-in-loop always |

---

## 5. User Roles & Permission Model

| Role | Primary Modules |
|------|----------------|
| Owner | M20 (Analytics), read-all, governance |
| Manager | M01, M02, M07, M08, M11, M12, M20 |
| Dentist | M01, M03, M04, M05, M06, M17, M19 |
| Front Desk | M01, M02, M03, M07, M09, M10, M16 |
| Assistant | M01, M03, M08, M11, M17, M19 (read) |
| Hygienist | M01, M03, M04 (read), M14 |
| Intern/Trainee | M01 (limited), M03 (read), SOPs only |

Full permission matrix (read/write/admin per module per role) is in DOC02.

---

## 6. UI/UX Design System (DOC03 — Locked)

**Color Palette**
- Primary Purple: `#4A2D82`
- Dark Purple (backgrounds): `#1E0A3C`
- Mid Lavender: `#9B7FD4`
- Soft Lavender (section bg): `#F3EEFF`
- Status Red: `#E53935`
- Status Green: `#43A047`
- Status Amber: `#FB8C00`

**Typography**
- Headings H1–H2: Cormorant Garamond
- All UI text (body, labels, buttons, H3–H4): DM Sans

**Layout Rules**
- 8px base spacing grid
- 3-zone layout: Sidebar (fixed) · Main Content · Contextual Panel
- Section background sequence: White → Soft Lavender → White → Dark Purple (repeating)
- Square buttons everywhere — `border-radius: 0` (no rounding, ever)
- Lucide outline icons only — no filled variants, no mixed libraries

**Component Locks**
- Two-column forms with inline error states
- Sticky headers and search bars on all tables
- Modal structure: Title → Content → Actions (no freeform layouts)
- Status badges: locked colors only (green/amber/red/grey)
- Empty states must include action CTAs

---

## 7. Screen Registry — Completed Routes

### M01 — Daily Huddle Engine
| Screen ID | Route | Screen |
|-----------|-------|--------|
| SCR-HUD-001 | `/huddle` | Role-Filtered Daily Huddle (all roles) |
| SCR-HUD-002 | `/huddle/doctor` | Doctor Huddle View |
| SCR-HUD-003 | `/huddle/frontdesk` | Front Desk Huddle View |
| SCR-HUD-004 | `/huddle/assistant` | Assistant Huddle View |
| SCR-HUD-005 | `/huddle/manager` | Manager Huddle View |
| SCR-HUD-006 | `/huddle/end-of-day` | End-of-Day Closing Checklist |

### M02 — Appointment System
| Screen ID | Route | Screen |
|-----------|-------|--------|
| SCR-APT-001 | `/appointments` | Appointment Calendar (default view) |
| SCR-APT-002 | `/appointments/day` | Day View |
| SCR-APT-003 | `/appointments/week` | Week View |
| SCR-APT-004 | `/appointments/new` | New Appointment Booking |
| SCR-APT-005 | `/appointments/:apptId` | Appointment Detail |
| SCR-APT-006 | `/appointments/:apptId/edit` | Edit Appointment |
| SCR-APT-007 | `/appointments/waitlist` | Waitlist Manager |
| SCR-APT-008 | `/appointments/no-shows` | No-Show Queue |
| SCR-APT-009 | `/appointments/emergency` | Emergency Slot Management |
| SCR-APT-010 | `/appointments/recurring` | Recurring Appointment Setup |
| SCR-APT-011 | `/appointments/:apptId/arrival` | Patient Arrival Flow |
| SCR-APT-012 | `/appointments/:apptId/checkout` | Appointment Checkout Workflow |
| SCR-APT-013 | `/appointments/status-board` | Chair Status Board |

### M03 — Patient Relationship Manager
| Screen ID | Route | Screen |
|-----------|-------|--------|
| SCR-PRM-001 | `/patients` | Patient Search & List View |
| SCR-PRM-002 | `/patients/new` | New Patient Intake Form |
| SCR-PRM-003 | `/patients/:patientId` | Patient Profile — Overview Tab |
| SCR-PRM-004 | `/patients/:patientId/timeline` | Patient Profile — Timeline Tab |
| SCR-PRM-005 | `/patients/:patientId/clinical` | Patient Profile — Clinical Tab |
| SCR-PRM-006 | `/patients/:patientId/treatment` | Patient Profile — Treatment Tab |
| SCR-PRM-007 | `/patients/:patientId/financial` | Patient Profile — Financial Tab |
| SCR-PRM-008 | `/patients/:patientId/communications` | Patient Profile — Communications Tab |
| SCR-PRM-009 | `/patients/:patientId/recall` | Patient Profile — Recall Tab |
| SCR-PRM-010 | `/patients/:patientId/quick-drawer` | Quick Patient Drawer |
| SCR-PRM-011 | `/patients/:patientId/alerts` | Patient Alerts Modal |
| SCR-PRM-012 | `/patients/:patientId/family` | Family Group View |
| SCR-PRM-013 | `/patients/:patientId/edit` | Edit Patient Demographics Drawer |
| SCR-PRM-014 | `/patients/merge` | Duplicate Patient Resolution |
| SCR-PRM-015 | `/patients/:patientId/reactivate` | Inactive Patient Reactivation |
| SCR-PRM-016 | `/patients/:patientId/archive` | Patient Archive Modal |
| SCR-PRM-017 | `/patients/:patientId/tags` | Patient Tags & Preferences |

**Routes for M04–M22 are defined in DOC02 but not yet broken into individual screen registry documents.**

---

## 8. Key Workflow Trigger Chains (Frozen)

```
Appointment Completed → Follow-Up Task Created (M09) → Audit Written (M21) → Notification Sent (M22)

Treatment Plan Created (M04) → Presentation Required (M05) → Consent Gate (M16) → Scheduling Unlocked (M02)

Low Stock Detected (M08) → Procurement Task Created (M12) → Alert Surfaced in Huddle (M01) → Notification to Manager (M22)

Missed Appointment (M02) → Missed Appointment Workflow Triggered (M12) → Follow-Up Task Generated (M09) → Communication Queued (M10)

Any Action by Any User → Audit Log Entry Written (M21) → Analytics Updated (M20)
```

---

## 9. AI Features Defined (DOC05)

| Feature | Module | Constraint |
|---------|--------|------------|
| Appointment gap filler suggestion | M02 | Suggests only — human approves |
| Treatment plan summary generator | M04 | Summarizes existing data — no clinical decisions |
| Follow-up message draft | M09/M10 | Draft only — staff sends manually |
| Recall priority scoring | M09 | Score surfaces in huddle — no auto-action |
| Inventory reorder suggestion | M08 | Flags threshold — no auto-order |
| SOP contextual surfacing | M13 | Surfaces relevant SOP in workflow context |
| Patient communication tone check | M10 | Flags tone — staff decides |
| End-of-day summary generation | M01 | Read-only summary |
| Anomaly flagging in financials | M07 | Flags only — no financial decisions |

**Universal AI constraints (AI-C-001–010):** No clinical diagnosis, no autonomous patient messaging, no financial decisions, always human-in-loop, all AI output labeled as AI-generated.

---

## 10. What's Pending

### Immediate next deliverables
- [ ] Screen Registry — M04 Treatment Planning (routes + screen specs)
- [ ] Screen Registry — M05 Treatment Presentation Engine
- [ ] Screen Registry — M07 Financial Tracking
- [ ] Screen Registry — M09 Follow-Up & Recall Engine
- [ ] Screen Registry — M10 Communication Hub
- [ ] Screen Registry — M11 Task Management System
- [ ] Screen Registry — M12 Workflow Automation Engine
- [ ] Screen Registry — M16 Consent & Documentation
- [ ] Screen Registry — Secondary modules (M08, M13–M15, M17, M19, M20)
- [ ] Screen Registry — Shared systems (M18, M21, M22)

### After screen registry is complete
- [ ] Database schema — table definitions, relationships, indexes
- [ ] API contract — endpoint specs per module
- [ ] Role-based access control (RBAC) implementation spec
- [ ] Developer handoff package (per module)
- [ ] Wireframes (M01–M03 first, then primary modules)

### Still open decisions
- [ ] Tech stack not yet selected (frontend framework, backend language, DB)
- [ ] Hosting / deployment model not yet decided
- [ ] Authentication provider not selected
- [ ] WhatsApp API integration approach (M10/M22) not finalized
- [ ] Imaging integration (M17) — DICOM vs. simple file store — not decided

---

## 11. File Reference

| File | Location | Format |
|------|----------|--------|
| DOC01_Master_Vision.docx | /mnt/user-data/outputs/ | Word |
| DOC02_System_Architecture.docx | /mnt/user-data/outputs/ | Word |
| DOC03_UI_UX_Design_System.docx | /mnt/user-data/outputs/ | Word |
| DOC04_Feature_Lock_Governance.docx | /mnt/user-data/outputs/ | Word |
| DOC05_AI_Context_Master.docx | /mnt/user-data/outputs/ | Word |
| Master Module Map (docx) | /mnt/user-data/outputs/ | Word |
| SCREEN_REGISTRY_MASTER.md | Built in session | Markdown |
| This summary | Dentfluence_Project_Summary.md | Markdown |

---

*This document is a living summary. Update it at the start of each new build session.*
