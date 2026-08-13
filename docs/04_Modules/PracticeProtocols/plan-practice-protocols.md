# Practice Protocols — Full Build Plan (All Phases)

**Module:** Practice Protocols (staff / operational standard duties)
**Owner:** Sumit (solo builder)
**Created:** 2026-06-26
**Status:** PLAN — not yet built.

---

## 0. What this is (plain language)

Right now Dentfluence has **ad-hoc tasks** — a person types a task and assigns someone. Practice Protocols adds a **catalog of standard recurring duties** defined once per *role* (e.g. "Assistant — prepare ultrasonic bath", daily). Each protocol can carry its own **SOP / guide**, and the system **auto-creates the task** for the right staff each day/week/month, optionally requiring **proof of completion**.

It sits *on top of* the existing `tasks` engine — it does not replace it.

### Originality / no-plagiarism principles (non-negotiable)
The functional concept (role-based recurring duties + SOP + proof) is generic and industry-standard. To keep this unambiguously our own work:
- No third-party code is used — everything is written fresh against Dentfluence's own stack.
- No copied content — we do **not** import any external SOP/policy/template text. Seed content is original and generic.
- Our own naming and screens — we do **not** mirror any competitor's terminology, layout, or branding.
- No competitor names/trademarks appear in code, comments, or UI.

### Naming (locked)
- Display name: **Practice Protocols** (distinct from existing clinical `documentation_protocols` / `treatment_sops`).
- Tables: `practice_protocols`, `practice_protocol_materials`.
- Module dir: `app/Modules/PracticeProtocols/`.
- RBAC module slug: `practice_protocols`.

### Key decisions (locked)
| Decision | Choice |
|---|---|
| What a protocol is keyed to | Existing `role_id` (drives *who* receives it) + a `category` label for grouping. No new categories table. |
| Branches | Branch filter built in (`branch_id` nullable = all branches). |
| Seed content | Yes — a small original starter set, editable/deletable. |
| Evidence storage | Reuse existing `huddle_task_logs.proof_path`. |

---

## 1. Reuse vs new (so scope is clear)

| Concern | Status | Where |
|---|---|---|
| Task instances, due dates, priority, status | Reuse | `tasks` / `Task` |
| Recurrence / auto-spawn pattern | Reuse pattern | `Task::spawnNext()` |
| Roles | Reuse (read-only) | `roles` / `Role` |
| Proof / evidence | Reuse | `huddle_task_logs.proof_path` |
| Structured SOP shape (JSON steps) | Mirror | `treatment_sops.doctor_steps` |
| Module layout | Mirror | `app/Modules/Huddle/*` |
| Route registration | Add 1 line | `routes/web.php` (~line 229) |
| RBAC slug | Add 1 entry | `database/seeders/RolePermissionSeeder.php` |
| **Protocol catalog** | NEW | `practice_protocols` |
| **Protocol SOP/materials** | NEW | `practice_protocol_materials` |
| **Auto-generation** | NEW | `ProtocolGenerationService` + command |

### Existing files that change, by phase
- **Phase 1:** `routes/web.php` (one `require` line) + `RolePermissionSeeder.php` (one slug). HR is read-only.
- **Phase 2:** `tasks` table (+2 columns), `Task` model (fillable + relation), tasks board view (filter + SOP link), reuse Huddle proof flow.
- **Phase 3:** Huddle `report` view (one reporting hook).
- **HR module code is never modified** — only its `roles` table is read, plus the one RBAC slug.

---

## PHASE 1 — Catalog (define protocols + attach SOPs)

**Goal:** Admin can create role-based protocols and attach SOP steps / files / links. **No automation yet — completely safe.**

Split into 1a (data layer) and 1b (UI) to keep each change-set small.

### Phase 1a — data layer

**Migration: `create_practice_protocols_table`**
```
id
title                  string(255)
description            text  null
role_id                FK roles                       // who performs it
branch_id              unsignedBigInteger null        // null = all branches
category               enum(clinical,admin,lab,decon,reception,maintenance,other) default 'admin'
frequency              enum(once,daily,weekly,monthly) default 'daily'
weekday                tinyInteger null               // 0-6 when frequency = weekly
day_of_month           tinyInteger null               // 1-28 when frequency = monthly
default_due_time       time null
priority               enum(urgent,high,medium,low) default 'medium'
requires_evidence      boolean default false
is_active              boolean default true
sort_order             unsignedInteger default 0
created_by             FK users
timestamps + softDeletes
```

**Migration: `create_practice_protocol_materials_table`**
```
id
practice_protocol_id   FK practice_protocols  cascadeOnDelete
type                   enum(sop_steps, file, link)
title                  string
body                   json null      // sop_steps: ["Step 1","Step 2"]
file_path              string null    // type = file
url                    string null    // type = link
sort_order             unsignedInteger default 0
timestamps
```

**Models**
- `App\Modules\PracticeProtocols\Models\PracticeProtocol`
  - fillable per schema; casts `requires_evidence`/`is_active` bool, `default_due_time` string.
  - `role()` BelongsTo Role, `branch()` BelongsTo Branch, `materials()` HasMany, `creator()` BelongsTo User.
  - scopes: `active()`, `forBranch($id)`, `dueOn(Carbon $date)` (frequency/weekday/day_of_month logic).
  - const CATEGORIES, FREQUENCIES arrays for labels.
- `App\Modules\PracticeProtocols\Models\PracticeProtocolMaterial`
  - fillable per schema; cast `body => array`; helpers `isSop()/isFile()/isLink()`.

**Seeder: `PracticeProtocolSeeder`** — original generic starters, e.g.:
- Assistant / daily: "Open surgery & switch on equipment" (08:30)
- Assistant / daily: "Run autoclave test cycle & log result" (evidence required)
- Front desk / daily: "Check & reply to overnight enquiries" (09:00)
- Accounts / daily: "End-of-day cash & card reconciliation" (18:00, evidence required)
- Manager / weekly (Mon): "Stock level check & reorder"
- Manager / monthly (1st): "Fire-safety walk-around & log"

**RBAC:** add `practice_protocols` module slug in `RolePermissionSeeder.php`; grant manage to `admin`/`manager`.

**Commands you run (Laragon terminal — I will NOT run these):**
```
php artisan migrate
php artisan db:seed --class=PracticeProtocolSeeder
php artisan db:seed --class=RolePermissionSeeder   # re-run to register the slug
```
All additive. No `migrate:fresh`, no data loss.

**Verify 1a:** tables exist; seeded rows present; `PracticeProtocol::active()->dueOn(today())` returns the daily ones.

### Phase 1b — admin UI

**Module routes file** `app/Modules/PracticeProtocols/Routes/practice-protocols.php`, guarded by `module:practice_protocols`, registered via one `require` line in `routes/web.php`:
```
GET    /practice-protocols                          index    // catalog grouped by role
GET    /practice-protocols/create                   create
POST   /practice-protocols                          store
GET    /practice-protocols/{protocol}/edit          edit
PUT    /practice-protocols/{protocol}               update
DELETE /practice-protocols/{protocol}               destroy  // soft delete, confirm in UI
POST   /practice-protocols/{protocol}/materials     materials.store
DELETE /practice-protocols/materials/{material}     materials.destroy
```

**Controllers**
- `PracticeProtocolController` (index/create/store/edit/update/destroy)
- `PracticeProtocolMaterialController` (store/destroy)

**Requests:** `StorePracticeProtocolRequest`, `UpdatePracticeProtocolRequest` (validate role_id, frequency + conditional weekday/day_of_month, time, enums).

**Views** (match existing Bootstrap/Tailwind — check `resources/views` first):
- `index.blade.php` — catalog grouped by role; cols: Title, Role, Frequency, Evidence?, Materials(count), Active toggle, Edit/Delete; "Add Protocol" button.
- `form.blade.php` — create/edit; conditional weekday/day-of-month fields; requires-evidence + active switches.
- `_materials.blade.php` partial — add SOP step list (repeatable rows → JSON) / upload file / add link.

**Verify 1b:** create a protocol + a material via UI, reload, confirm persistence; soft-delete then confirm it disappears from the list.

**Phase 1 deliverable:** a working protocol catalog with SOPs. Still nothing auto-runs.

---

## PHASE 2 — Generation + evidence

**Goal:** active protocols automatically become real tasks for the right staff, and evidence-required ones can't be closed without proof.

### Migration: `add_protocol_fields_to_tasks` (additive)
```
job_template_id  → renamed to → practice_protocol_id   FK practice_protocols null
requires_evidence                                       boolean default false
```
(Set on the task when generated; nullable so all existing tasks are unaffected.)

### Model change
`Task` — add `practice_protocol_id`, `requires_evidence` to `$fillable`; add `protocol()` BelongsTo. No other change.

### Generation service
`App\Modules\PracticeProtocols\Services\ProtocolGenerationService::generateFor(Carbon $date)`
1. `PracticeProtocol::active()->dueOn($date)`.
2. For each, find active users whose `role_id` matches (+ branch if set).
3. **Idempotency:** skip if a task already exists for that `practice_protocol_id` + user + date.
4. Create a `Task` (link protocol, copy priority/category/requires_evidence, due_date=$date, due_time=default_due_time, created_by=system).

### Command + schedule
- `App\Console\Commands\GenerateProtocolTasks` → `php artisan protocols:generate`.
- `app/Console/Kernel.php`: `->command('protocols:generate')->dailyAt('00:05')`.
- Manual "Generate now" button → `POST /practice-protocols/generate`.

### Staff experience (reuse existing boards — no new staff page)
- Generated protocols appear in the current `/tasks` board and Huddle board.
- Add a **"Protocols" filter** (tasks where `practice_protocol_id` is not null).
- Add a **"View SOP"** link on those rows → read-only drawer showing the protocol's materials.
- For `requires_evidence` tasks: block "mark done" until proof is attached (reuse Huddle proof upload → `huddle_task_logs.proof_path`).

**Commands you run:** `php artisan migrate`

**Verify 2:**
- Run `php artisan protocols:generate` twice for the same day → **no duplicate** tasks (idempotency).
- An evidence-required protocol task refuses completion until a file is attached.
- A daily protocol shows up for every active user of its role.

---

## PHASE 3 — Reporting hook (optional)

**Goal:** see which standard protocols were done vs missed, by whom, with evidence — without building a new page.

- Extend the existing Huddle `report` view (`HuddleController@report`) with a "Protocol compliance" section:
  - per role / per person: generated vs completed vs missed, in the selected period.
  - evidence link where present.
- Pure read/aggregation over `tasks` (filtered on `practice_protocol_id`) + `huddle_task_logs`. No new tables.

**Verify 3:** spot-check counts against raw `tasks` rows for a known day.

---

## 4. Build order & cadence
1. **Phase 1a** — migrations + models + seeder + RBAC slug.
2. **Phase 1b** — routes + controllers + requests + views.
3. **Phase 2** — tasks columns + generation service + command + schedule + board filter + SOP drawer + evidence gate.
4. **Phase 3** — Huddle report section.

One phase per message; stop at a clean, testable point each time. Always read existing files before editing. I provide the `php artisan` commands; Sumit runs them in Laragon.

## 5. Safety
- Every migration is additive — no `migrate:fresh`, no rollback of existing data.
- Phase 1 changes no existing module logic (only one route line + one RBAC slug).
- Generation is idempotent and only ever *creates* tasks; it never edits or deletes existing ones.
- Files are never deleted without asking.

## 6. Open items
- Confirm starter-seed list wording (Phase 1a) — all original/generic.
- Confirm which roles may manage the catalog (default: admin + manager).
