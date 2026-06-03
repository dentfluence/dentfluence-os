# Tulip Dental — Huddle Module Build Summary
**Database:** `dentfluence` | **PHP:** 8.3.30 | **MySQL:** 8.4.3  
**Last updated:** Session 3 complete

---

## 1. What Was Built

### Overview
The Huddle Module is an **orchestration and visualization layer** on top of Tulip Dental's existing ERP. It aggregates, transforms, and surfaces data from existing modules (Appointments, Patients, Tasks, etc.) into a role-based Kanban board for daily clinic huddles.

---

## 2. Database

### Existing Tables (referenced only — never modified)
| Table | Used For |
|---|---|
| `appointments` | Source of patient flow cards |
| `patients` | Patient name, phone, alerts |
| `users` | Auth, roles (`users.role`), branch scoping (`users.branch_id`) |
| `tasks` | Source of task cards |
| `treatment_types` | Treatment name on cards |
| `patient_alerts` | Alert flags shown on cards |
| `consultations`, `treatment_plans`, `patient_notes` | Expandable drawer data (Phase 2) |

### New Tables to Create (migrations needed)
| Table | Status | Purpose |
|---|---|---|
| `huddle_boards` | ⬜ Migration needed | One board per branch + role + date |
| `huddle_cards` | ⬜ Migration needed | Cards on the board (FK to appointments/tasks) |
| `huddle_task_logs` | ⬜ Migration needed | Huddle-specific task metadata (proof, carry-forward) |
| `huddle_comments` | ⬜ Migration needed | Board/card comments and hurdles |
| `huddle_settings` | ⬜ Migration needed | Branch + role key-value config |

> **Note:** `huddle_notes` already exists in the DB — inspect before touching.

### Users Table (confirmed schema)
| Field | Notes |
|---|---|
| `id` | PK |
| `name`, `email`, `password` | Standard auth |
| `role` | Single string — `admin`, `doctor`, `front_desk`, `assistant` |
| `branch_id` | Multi-branch scoping |
| `is_active`, `last_login_at` | Status fields |

---

## 3. Key Architectural Decisions

| Decision | Choice |
|---|---|
| Role system | No Spatie — custom `users.role` string column |
| Auth guard | Session-based `web` guard (no API tokens) |
| Branch scoping | All huddle queries scoped by `auth()->user()->branch_id` |
| Task storage | Tasks live in existing `tasks` table; `huddle_task_logs` for huddle metadata only |
| Data flow | Read-only through Transformers → DTOs → Resources. Never write to `appointments`, `patients`, `tasks` |
| Query approach | Repositories only — no `DB::table()` in controllers or services |
| Existing controller | **Extend** existing `HuddleController` — do not replace it |
| `huddle_notes` | Already exists — inspect schema before any migration |
| Multi-branch | Build multi-branch ready even though only 1 branch currently exists |

---

## 4. Backend Files Built

### Controllers (`app/Modules/Huddle/Controllers/`)
| File | Routes Handled | Status |
|---|---|---|
| `HuddleController.php` | `GET /huddle`, plus existing `accountability`, `updateInstruction`, `storeNote` | ✅ Built (Session 3) |
| `HuddleTaskController.php` | Full task CRUD + proof upload + carry-forward | ✅ Built |
| `HuddleCommentController.php` | Comment index, store, resolve, destroy | ✅ Built |
| `HuddleSettingsController.php` | Settings index + PATCH upsert (admin only) | ✅ Built |

### Services (`app/Modules/Huddle/Services/`)
| File | Responsibility | Status |
|---|---|---|
| `HuddleAggregationService.php` | Builds full board DTO: syncs appointments + tasks, computes stats, groups by column | ✅ Built |
| `RoleBasedHuddleService.php` | Column visibility per role, labels, admin/clinical checks | ✅ Built |
| `TaskAutomationService.php` | Auto-complete for call/whatsapp tasks | ⬜ Pending (Phase 3) |
| `CommunicationSyncService.php` | CRM call log sync | ⬜ Pending (Phase 3) |
| `HuddleReportGeneratorService.php` | Daily/weekly/monthly reports | ⬜ Pending (Phase 4) |

### Repositories (`app/Modules/Huddle/Repositories/`)
| File | Status |
|---|---|
| `HuddleBoardRepository.php` | ✅ Built — findOrCreateForToday, findByDateAndRole, lock, getByDateRange |
| `HuddleCardRepository.php` | ✅ Built — getByBoard, firstOrCreateFromSource, move, bulkUpdatePositions |
| `HuddleTaskRepository.php` | ✅ Built — forBoard, create, updateStatus, storeProof, markCarriedForward |
| `HuddleCommentRepository.php` | ✅ Built — unresolvedForBoard, create, resolve, delete |

### Models (`app/Modules/Huddle/Models/`)
| File | Status |
|---|---|
| `HuddleBoard.php` | ✅ Built — scopes: forBranch, forRole, forDate, today |
| `HuddleCard.php` | ✅ Built — scopes: inColumn, ofType, flagged, carriedForward |
| `HuddleComment.php` | ✅ Built — SoftDeletes, scopes: topLevel, unresolved, hurdles |
| `HuddleSetting.php` | ✅ Built — getValue/setValue static helpers, forBranch/forRole scopes |
| `HuddleTaskLog.php` | ✅ Built — relationships to card, performedBy; hasProof(), isAutoCompleted() |

### DTOs (`app/Modules/Huddle/DTOs/`)
| File | Status |
|---|---|
| `HuddleBoardDTO.php` | ✅ Built — board + stats + role-keyed columns array |
| `HuddleCardDTO.php` | ✅ Built — full card value object with `toArray()` |
| `HuddleStatsDTO.php` | ✅ Built — top strip stats (appointments + tasks breakdown) |

### Transformers (`app/Modules/Huddle/Transformers/`)
| File | Status |
|---|---|
| `AppointmentToCardTransformer.php` | ✅ Built — maps raw appointment row → HuddleCardDTO |
| `TaskToCardTransformer.php` | ✅ Built — maps raw task row → HuddleCardDTO |

### Resources (`app/Modules/Huddle/Resources/`)
| File | Status |
|---|---|
| `HuddleBoardResource.php` | ✅ Built — serializes HuddleBoardDTO to full JSON response |
| `HuddleCardResource.php` | ✅ Built — serializes HuddleCardDTO with nested patient/schedule/appointment objects |

### Requests (`app/Modules/Huddle/Requests/`)
| File | Status |
|---|---|
| `StoreHuddleTaskRequest.php` | ✅ Built |
| `StoreHuddleCommentRequest.php` | ✅ Built |
| `UpdateTaskStatusRequest.php` | ✅ Built |
| `AssignTaskRequest.php` | ✅ Built |
| `UpdateHuddleSettingsRequest.php` | ✅ Built — admin-only via `authorize()` |

### Routes (`app/Modules/Huddle/Routes/`)
| File | Status |
|---|---|
| `huddle.php` | ✅ Built — all routes under `auth + web` middleware, `/huddle` prefix |

---

## 5. Routes Registered

```
GET    /huddle                                → HuddleController@index
GET    /huddle/accountability                 → HuddleController@accountability (existing)
PATCH  /huddle/appointments/{id}/instruction  → HuddleController@updateInstruction (existing)
POST   /huddle/notes                          → HuddleController@storeNote (existing)

GET    /huddle/tasks                          → HuddleTaskController@index
POST   /huddle/tasks                          → HuddleTaskController@store
PATCH  /huddle/tasks/{taskId}/status          → HuddleTaskController@updateStatus
PATCH  /huddle/tasks/{taskId}/assign          → HuddleTaskController@assign
POST   /huddle/tasks/{taskId}/proof           → HuddleTaskController@uploadProof
POST   /huddle/tasks/{taskId}/carry-forward   → HuddleTaskController@carryForward

GET    /huddle/comments                       → HuddleCommentController@index
POST   /huddle/comments                       → HuddleCommentController@store
PATCH  /huddle/comments/{commentId}/resolve   → HuddleCommentController@resolve
DELETE /huddle/comments/{commentId}           → HuddleCommentController@destroy

GET    /huddle/settings                       → HuddleSettingsController@index
PATCH  /huddle/settings                       → HuddleSettingsController@update
```

---

## 6. Role-Based Column Visibility

| Role | Columns |
|---|---|
| `admin` | today_flow, yesterday_flow, critical_alerts, tasks, lab, inventory, marketing, maintenance, comms, quick_actions |
| `doctor` | today_flow, yesterday_flow, critical_alerts, tasks, lab, inventory, marketing, maintenance, quick_actions |
| `front_desk` | today_flow, comms, tasks, yesterday_flow, quick_actions |
| `assistant` | today_flow, assist_assignments, tasks, comments |

---

## 7. Board JSON Response Shape

```json
{
  "board": { "id": 1, "date": "2025-01-15", "branch_id": 1, "is_locked": false },
  "role": "doctor",
  "date": "2025-01-15",
  "stats": {
    "total_appointments": 12,
    "confirmed": 8,
    "checked_in": 3,
    "in_chair": 2,
    "done": 1,
    "cancelled": 1,
    "no_show": 0,
    "pending_tasks": 5,
    "overdue_tasks": 1,
    "escalated_tasks": 0
  },
  "columns": {
    "today_flow": [ ...HuddleCardResource ],
    "tasks":      [ ...HuddleCardResource ]
  }
}
```

---

## 8. What's Pending

### Immediate (before Phase 2)
- [ ] Write migrations for all 5 huddle tables
- [ ] Run seeders: create test users for `front_desk`, `assistant`, `doctor` roles
- [ ] Register `huddle.php` routes in `web.php` or bootstrap/app.php
- [ ] Inspect `huddle_notes` table schema before adding note functionality
- [ ] Confirm `tasks` table schema (columns: `branch_id`, `assigned_to`, `type`, `requires_proof`, `due_time`, `category`, `priority`, `escalated_at`, `escalation_note`)
- [ ] Confirm `appointments` table column names match what `HuddleAggregationService` expects (`appointment_date`, `appointment_time`, `doctor_id`, `treatment_id`, `branch_id`)

### Phase 2 (Sessions 9–15)
- [ ] Task actions in detail (reassign, escalate, proof flow)
- [ ] Comment threading (replies via `parent_id`)
- [ ] Proof upload — sterilization auto-done on upload
- [ ] Patient detail drawer (pulls consultations, treatment_plans, patient_notes)
- [ ] Drag-and-drop card reorder (`HuddleCardRepository::move` + `bulkUpdatePositions` ready)

### Phase 3 (Sessions 16–20)
- [ ] `TaskAutomationService` — auto-complete call/whatsapp tasks
- [ ] `CommunicationSyncService` — CRM sync
- [ ] `CarryForwardTasksJob` — scheduled job, overnight run
- [ ] `AutoMarkOverdueJob`
- [ ] Escalation logic + `EscalateTask` listener

### Phase 4 (Sessions 21–28)
- [ ] Laravel Echo + Reverb WebSocket realtime sync
- [ ] Events: AppointmentConfirmed, TaskCompleted, PatientWaitingTooLong, etc.
- [ ] `HuddleReportGeneratorService` + `GenerateDailyReportJob`
- [ ] Weekly / Monthly / Quarterly / Annual report pages

### Phase 5 (Sessions 29–32)
- [ ] Settings engine (column visibility overrides, proof requirements, escalation thresholds)
- [ ] `HuddlePolicy` authorization
- [ ] Final seeders (demo data)
- [ ] Frontend wiring — all hooks, stores, API services

### Frontend (not started)
- [ ] All files under `src/modules/huddle/` — pages, components, hooks, services, store, types

---

## 9. Known Issues / Watch Points

| Item | Note |
|---|---|
| `HuddleTaskRepository::forBoard` | References `huddle_board_id` on `HuddleTaskLog` — confirm this column name in migration |
| `HuddleCardRepository::updatePayload` | Updates `payload` column but `HuddleCard` model has `snapshot` in `$fillable` — reconcile column name |
| `HuddleCardRepository::move` + `getByBoardAndColumn` | Uses `column` column name; model scopes use `column_key` — pick one and be consistent in migration |
| `HuddleCommentRepository::unresolvedForBoard` | Checks `resolved_at IS NULL` but `HuddleComment` model has `is_resolved` boolean — decide which field is authoritative |
| `HuddleBoardRepository::findOrCreateToday` | Public method is `findOrCreateForToday` but `HuddleCommentController` calls `findOrCreateToday` — fix the call or add an alias |
| Task proof storage | Currently using `local` disk — confirm whether `public` or S3 is required for production |

---

*Build phase: Sessions 1–3 complete | Next: Migrations + Seeders*
