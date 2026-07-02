# Build Plan — Role-Based Job Library + SOPs

**Borrowed from:** DentTrack's strongest idea — standard recurring duties keyed to a *role*, each bundling its SOP / help materials, that flow automatically to whoever holds that role.

**Status:** PLAN ONLY. No code written yet. Review before we build.

---

## 1. The idea in one line

Today Dentfluence has **ad-hoc tasks** (someone types a task and assigns a person). DentTrack adds a **catalog of standard jobs** defined once per *role* (e.g. "Decon Nurse: prepare ultrasonic bath", daily), each carrying its **SOP/guide**, which the system **auto-generates** for the right staff every day/week/month — with optional **proof-of-completion**.

We add that catalog layer *on top of* your existing `tasks` engine instead of replacing it.

---

## 2. What we reuse vs build new

| Concern | Already in Dentfluence | Plan |
|---|---|---|
| Task instances, due dates, priority, status, soft-delete | `tasks` table + `Task` model | **Reuse** |
| Recurring / auto-spawn next | `Task::spawnNext()`, `parent_task_id` | **Reuse the pattern** (templates drive generation) |
| Roles (doctor/assistant/front_desk…) | `roles` + `Role` model | **Reuse** as the "job category" key |
| Proof / evidence capture | `huddle_task_logs.proof_path` | **Reuse** for evidence |
| Structured SOP (steps, pre/post) | `treatment_sops` (clinical only) | **Mirror the shape** for job SOPs |
| Module structure (Controllers/Models/Repos/Services/Routes) | `app/Modules/Huddle/*` | **Mirror** as `app/Modules/JobLibrary/*` |
| **Role-keyed job catalog** | ❌ none | **NEW** |
| **SOP/help-material attached to a job** | ❌ none (clinical SOPs only) | **NEW** |
| **Auto-generate role jobs into tasks** | ❌ none | **NEW** |

So ~70% is reuse. The genuinely new parts are: the catalog, its materials, and the generator.

---

## 3. Data model (new migrations)

### 3a. `job_templates` — the catalog (one row = one standard duty)

```
id
title                  string        // "Prepare Ultrasonic Bath"
description            text  null
role_id               FK roles      // WHO performs it (the "job category")
branch_id             unsignedBigInt null   // null = all branches
category              enum(clinical,admin,lab,decon,reception,maintenance,other) default admin
frequency             enum(once,daily,weekly,monthly) default daily
weekday               tinyInt  null  // 0-6, used when frequency=weekly
day_of_month          tinyInt  null  // 1-28, used when frequency=monthly
default_due_time      time     null  // e.g. 09:00
priority              enum(urgent,high,medium,low) default medium
requires_evidence     boolean  default false
is_active             boolean  default true
sort_order            unsignedInt default 0
created_by            FK users
timestamps / softDeletes
```

### 3b. `job_template_materials` — SOP + help files per job

```
id
job_template_id       FK job_templates  cascadeOnDelete
type                  enum(sop_steps, file, link)
title                 string
body                  json   null   // for type=sop_steps: ["Step 1...","Step 2..."]
file_path             string null   // for type=file (stored upload)
url                   string null   // for type=link
sort_order            unsignedInt default 0
timestamps
```

> This mirrors `treatment_sops.doctor_steps` (JSON step array) and DentTrack's "View Materials (N)" — a job can carry several materials.

### 3c. `tasks` — two additive columns (no breaking change)

```
job_template_id       FK job_templates  null  // set when task was generated from catalog
requires_evidence     boolean default false   // copied from template at generation
```

> Evidence itself stays in `huddle_task_logs.proof_path` (already built). For non-huddle jobs we reuse the same log row keyed by `task_id`.

**You run (yourself, in Laragon terminal):** `php artisan migrate` — 3 new migrations, all additive. No `migrate:fresh`, no data loss.

---

## 4. Models & relationships

- **`App\Modules\JobLibrary\Models\JobTemplate`** — `fillable` per 3a; `role()` BelongsTo Role, `materials()` HasMany JobTemplateMaterial, `creator()` BelongsTo User; scopes `active()`, `forBranch()`, `dueOn(Carbon $date)`.
- **`App\Modules\JobLibrary\Models\JobTemplateMaterial`** — `template()` BelongsTo; `casts body => array`; helper `isSop()/isFile()/isLink()`.
- **`Task` (existing)** — add `job_template_id`, `requires_evidence` to `$fillable`; add `jobTemplate()` BelongsTo. Nothing else changes.

---

## 5. The generator (how catalog → real tasks)

A single command materializes due jobs into `tasks`, one per active user holding the role.

- **`App\Modules\JobLibrary\Services\JobGenerationService::generateFor(Carbon $date)`**
  1. Load `JobTemplate::active()->dueOn($date)` (frequency/weekday/day_of_month logic).
  2. For each template, find active users whose `role_id` matches (and branch if set).
  3. For each user, **skip if a task already exists** for that `job_template_id` + user + date (idempotent — safe to re-run).
  4. Create a `Task` with `job_template_id`, `requires_evidence`, due_date=$date, due_time=default_due_time, copied priority/category, `created_by` = system/admin.
- **`App\Console\Commands\GenerateDailyJobs`** (`php artisan jobs:generate`) calls the service for `today()`.
- **Schedule** in `app/Console/Kernel.php`: `->dailyAt('00:05')`. (You enable the scheduler; I'll document it — no terminal action from me.)

> Reuses your existing `tasks` board, filters, and huddle integration automatically, because generated rows are ordinary tasks with an extra link.

---

## 6. Controllers + routes (module: `app/Modules/JobLibrary/`)

Mirror the Huddle module layout. Routes file `Routes/job-library.php`, guarded by `module:job_library`.

**Admin (manage the catalog):**
```
GET    /job-library                       JobTemplateController@index    // list catalog, grouped by role
GET    /job-library/create                JobTemplateController@create
POST   /job-library                       JobTemplateController@store
GET    /job-library/{template}/edit       JobTemplateController@edit
PUT    /job-library/{template}            JobTemplateController@update
DELETE /job-library/{template}            JobTemplateController@destroy   // ask-before-delete UX, soft delete
POST   /job-library/{template}/materials  JobMaterialController@store     // add SOP step list / file / link
DELETE /job-library/materials/{material}  JobMaterialController@destroy
POST   /job-library/generate              JobTemplateController@runGenerate // manual "generate now" button
```

**Staff (do the jobs) — reuse existing Tasks UI:**
- Generated jobs already appear in your current `/tasks` board and Huddle board. We add a **"Jobs" filter** (tasks where `job_template_id` is not null) and a **"View SOP"** link on those task rows that opens the template's materials in a drawer/modal.

---

## 7. Views (Blade)

Check existing files first; match current Bootstrap/Tailwind usage in `resources/views`.

1. **`job-library/index.blade.php`** — catalog grouped by role; columns: Job, Role, Frequency, Evidence?, Materials (count), Active toggle, Edit/Delete. "Add Job" + "Generate now" buttons.
2. **`job-library/form.blade.php`** — create/edit one template: title, role dropdown, branch, frequency (+ weekday/day-of-month conditional), due time, priority, requires-evidence switch, active switch.
3. **`job-library/_materials.blade.php`** (partial) — manage a template's materials: add SOP step-list (repeatable text rows → JSON), upload file, or add link.
4. **`tasks/_sop-drawer.blade.php`** (partial) — read-only SOP/materials viewer opened from a job-task row (the "View Materials" equivalent).

No new staff page needed — staff keep using the Tasks/Huddle boards they already know (respects your "data entry = dead simple" rule).

---

## 8. Permissions / module registration

- Add a `modules` row slug **`job_library`** (so it slots into your `role_module_permissions` RBAC).
- Catalog management (create/edit/delete templates) → restrict to `admin` / `manager`.
- Doing jobs → any role (they only see their own generated tasks).
- Register the routes file in the same place Huddle's is registered (module service provider / RouteServiceProvider).

---

## 9. Phased build (avoids one giant change-set)

**Phase 1 — Catalog (no automation yet).** Migrations 3a+3b, models, admin CRUD controller + 2 views, module slug + permissions. Outcome: you can define role-based jobs and attach SOP/files. *Nothing auto-runs yet — fully safe.*

**Phase 2 — Generation + evidence.** Migration 3c (tasks columns), `JobGenerationService`, `jobs:generate` command + scheduler entry, "Generate now" button, Tasks "Jobs" filter, SOP drawer on task rows, evidence enforcement on completion for `requires_evidence` jobs (reusing huddle proof upload). Outcome: jobs flow to staff daily with proof.

**Phase 3 — Reporting hook (optional, ties to your other DentTrack gap).** A per-role completion view: which standard jobs were done/missed, by whom, with evidence — surfaced in the existing Huddle `report` view rather than a new page.

Each phase is independently shippable and testable. I'll do **one phase per message** and stop at a clean point.

---

## 10. Verification per phase

- Phase 1: create a template + material, reload, confirm persistence; soft-delete restores cleanly.
- Phase 2: run `jobs:generate` twice for the same day → confirm **no duplicate** tasks (idempotency); confirm a `requires_evidence` job blocks completion until proof is attached.
- Phase 3: spot-check counts against raw `tasks` rows.

---

## 11. Decisions I need from you before Phase 1

1. **Scope of "role" = "job category".** Use your existing `roles` (admin/manager/doctor/assistant/front_desk/accounts) as the job categories? Or do you want finer DentTrack-style categories (Decon Nurse, Reception…) that don't map 1:1 to your login roles? If finer, we add a small `job_categories` table instead of pointing at `roles`.
2. **Seed content.** Want me to seed a starter set of standard jobs (opening checklist, decon routine, autoclave test, end-of-day cash-up) so it's useful on day one?
3. **Branch handling.** Single branch (Tulip) for now, or build the branch filter in from the start?
