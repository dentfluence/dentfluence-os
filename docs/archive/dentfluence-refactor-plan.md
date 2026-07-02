# Dentfluence — Safe Laravel Refactor Execution Plan
**Prepared by:** Senior Laravel SaaS Architect  
**Project:** Dentfluence — Healthcare SaaS for Dental Clinics  
**Status:** Active Production App — Zero Downtime Required  
**Philosophy:** Stabilize → Consolidate → Systemize. Never rewrite. Never break.

---

## TABLE OF CONTENTS

1. Current Architecture Analysis
2. High-Risk Areas
3. Final Target Architecture
4. Safe Migration Phases (0–7)
5. File Movement Map
6. Safe Order of Execution
7. Reusable Component Strategy
8. Service Layer Strategy
9. Route Standardization Plan
10. Final Cleanup Phase

---

# 1. CURRENT ARCHITECTURE ANALYSIS

## What Is CLEAN (do not touch yet)

| Area | Status | Notes |
|---|---|---|
| `app/Http/Controllers/Communication/` | Clean subfolder | Well-separated already |
| `app/Modules/Huddle/` | Best-structured module | Has DTOs, Services, Repos, Routes, Resources |
| `app/Models/` | Flat but acceptable | All models in one folder — fine for this scale |
| `app/Providers/` | Minimal, clean | AppServiceProvider + CommunicationServiceProvider |
| `resources/views/` | Mostly organized | Good subfolder structure per module |
| `routes/communication.php` | Extracted | Already split from web.php |
| `config/` | Well populated | clinic, communication, constants, permissions — good domain configs |
| `database/migrations/` | Functional | Sequential, well-named |

## What Is DANGEROUS (must address)

| Problem | Location | Risk Level |
|---|---|---|
| Loose PHP files at project ROOT | `dashboard.php`, `login.php`, `logout.php`, `index.php` | 🔴 CRITICAL |
| Orphaned filename artifacts | `name('patients.search')`, `now()]`, `prefix('hud` | 🔴 CRITICAL |
| Duplicate module system | `app/Modules/` (proper) AND flat controllers in `app/Http/Controllers/` | 🔴 HIGH |
| Empty `app/Modules/` directories | Appointment, Lab, Patient, Treatment — scaffolded but EMPTY | 🟡 MEDIUM |
| `modules/` folder at root | Parallel PHP module system outside Laravel | 🔴 HIGH |
| `services/`, `rules/`, `workflows/`, `cron/` at root | Outside Laravel conventions | 🟡 MEDIUM |
| Duplicate PRM views | `resources/views/prm/` AND `resources/views/communication/prm/` | 🟡 MEDIUM |
| Duplicate sidebar partials | `partials/sidebar.blade.php` AND `components/sidebar.blade.php` | 🟡 MEDIUM |
| `.bak` file in views | `sidebar.blade.php.bak` | 🟢 LOW |
| JS file inside views folder | `resources/views/prm/lead-drawer.js` | 🟡 MEDIUM |
| Duplicate CSS | `resources/css/communication/` copied into `public/css/communication/` | 🟡 MEDIUM |
| DashboardController duplicated | Root level AND inside Communication/ | 🔴 HIGH |
| PrmController duplicated | Root level AND inside Communication/ | 🔴 HIGH |
| TaskController duplicated | Root level AND inside Communication/ | 🔴 HIGH |
| HuddleController triplicated | Communication/ AND Modules/Huddle/Controllers/ | 🔴 HIGH |

## What Is SCALABLE (keep these patterns)

- `app/Modules/Huddle/` — DTOs/Services/Repos/Transformers pattern is exactly right
- `app/Http/Controllers/Communication/` — subdirectory namespacing is correct
- `routes/communication.php` — split route file pattern is correct
- `config/clinic.php`, `config/communication.php` — domain-specific configs
- `app/Providers/CommunicationServiceProvider.php` — module-level provider pattern

## What Is SCATTERED (needs consolidation)

- Business logic spread across controllers with no service layer
- Form Requests: only `StoreConsultationRequest.php` at root level (Huddle has proper Requests folder)
- Views: PRM views duplicated in two locations
- Routes: `tags-routes.php` non-standard naming, inconsistent registration
- Actions/DTOs/Enums/Traits: folders exist but mostly empty
- JS: `prm-board .js` has a space in the filename — this is a bug

---

# 2. HIGH-RISK AREAS

## 🔴 RISK 1: Duplicate Controller Names

```
app/Http/Controllers/DashboardController.php
app/Http/Controllers/Communication/DashboardController.php   ← same class name

app/Http/Controllers/PrmController.php
app/Http/Controllers/Communication/PrmController.php         ← same class name

app/Http/Controllers/TaskController.php
app/Http/Controllers/Communication/TaskController.php        ← same class name

app/Http/Controllers/Communication/HuddleController.php
app/Modules/Huddle/Controllers/HuddleController.php          ← same class name
```

**Risk:** Silent wrong-controller dispatch if routes use short names. Moving any of these without updating routes causes immediate 500s.

## 🔴 RISK 2: Dual Module System

```
app/Modules/           ← Laravel-native module folders (partially filled)
modules/               ← Root-level parallel "module" system
```

The root `modules/` folder's purpose is unclear. DO NOT delete without investigating.

## 🔴 RISK 3: Root-Level PHP Files

```
dashboard.php    ← Potentially web-accessible, bypasses Laravel
login.php        ← Could bypass Laravel auth
logout.php       ← Could bypass CSRF protection
index.php        ← Naming conflict with public/index.php
```

If your web server root points to `/` instead of `/public`, these are a security vulnerability.

## 🟡 RISK 4: Orphaned Filename Artifacts

Files literally named:
```
name('patients.search')
now()]
prefix('hud
```
These are route code fragments accidentally created as files. Verify then delete.

## 🟡 RISK 5: Empty Module Scaffolding Competing With Active Controllers

`app/Modules/Appointment/`, `app/Modules/Lab/`, `app/Modules/Patient/`, `app/Modules/Treatment/` are fully scaffolded but EMPTY — while functional flat controllers exist at `app/Http/Controllers/AppointmentController.php` etc.

Future AI-assisted or team development may put code in the wrong place.

## 🟡 RISK 6: Route File Fragmentation

```
routes/web.php           ← Unknown scope
routes/communication.php ← Communication module ✓
routes/prm.php           ← PRM ✓
routes/tags-routes.php   ← Non-standard naming
```

No clear contract for what goes where.

---

# 3. FINAL TARGET ARCHITECTURE

```
dentfluence/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/AuthController.php
│   │   │   ├── Appointments/AppointmentController.php
│   │   │   ├── Billing/BillingController.php
│   │   │   ├── Communication/          ← ALREADY EXISTS — keep as-is
│   │   │   │   ├── CommunicationController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── ManagerController.php
│   │   │   │   ├── OpportunityController.php
│   │   │   │   ├── PrmController.php
│   │   │   │   ├── TaskController.php
│   │   │   │   └── TimelineController.php
│   │   │   ├── Consultations/ConsultationController.php
│   │   │   ├── Dashboard/DashboardController.php
│   │   │   ├── Huddle/                 ← Migrated FROM app/Modules/Huddle/Controllers/
│   │   │   │   ├── HuddleController.php
│   │   │   │   ├── HuddleCommentController.php
│   │   │   │   ├── HuddleSettingsController.php
│   │   │   │   └── HuddleTaskController.php
│   │   │   ├── Inventory/InventoryController.php
│   │   │   ├── Lab/LabController.php
│   │   │   ├── Patients/
│   │   │   │   ├── PatientController.php
│   │   │   │   └── PatientNoteController.php
│   │   │   ├── Reports/ReportsController.php
│   │   │   ├── Settings/
│   │   │   │   ├── SettingsController.php
│   │   │   │   └── TagController.php
│   │   │   └── Treatments/
│   │   │       ├── TreatmentCategoryController.php
│   │   │       └── TreatmentPlanController.php
│   │   ├── Middleware/CommunicationModuleAccess.php
│   │   ├── Requests/
│   │   │   ├── Consultations/StoreConsultationRequest.php
│   │   │   └── Huddle/                 ← From app/Modules/Huddle/Requests/
│   │   │       ├── AssignTaskRequest.php
│   │   │       ├── StoreHuddleCommentRequest.php
│   │   │       ├── StoreHuddleTaskRequest.php
│   │   │       ├── UpdateHuddleSettingsRequest.php
│   │   │       └── UpdateTaskStatusRequest.php
│   │   └── Resources/
│   │       └── Huddle/
│   │           ├── HuddleBoardResource.php
│   │           └── HuddleCardResource.php
│   ├── Models/                         ← Keep FLAT — fine for ~20 models
│   │   ├── Appointment.php
│   │   ├── HuddleBoard.php             ← Move from Modules/Huddle/Models/
│   │   ├── HuddleCard.php
│   │   ├── Patient.php
│   │   └── ... (all models here)
│   ├── Services/
│   │   ├── Huddle/
│   │   │   ├── HuddleAggregationService.php
│   │   │   └── RoleBasedHuddleService.php
│   │   └── Patients/
│   │       └── PatientProfileService.php
│   ├── Repositories/
│   │   └── Huddle/
│   │       ├── HuddleBoardRepository.php
│   │       ├── HuddleCardRepository.php
│   │       ├── HuddleCommentRepository.php
│   │       └── HuddleTaskRepository.php
│   ├── DTOs/
│   │   └── Huddle/
│   │       ├── HuddleBoardDTO.php
│   │       ├── HuddleCardDTO.php
│   │       └── HuddleStatsDTO.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── CommunicationServiceProvider.php
│   ├── Actions/
│   ├── Enums/
│   ├── Helpers/
│   ├── Traits/
│   └── Workflows/
├── config/
│   ├── clinic.php
│   ├── communication.php
│   ├── constants.php
│   └── permissions.php
├── resources/
│   ├── css/
│   │   ├── app.css
│   │   ├── dentfluence.tokens.css
│   │   └── communication/
│   ├── js/
│   │   ├── app.js
│   │   └── communication/
│   └── views/
│       ├── appointments/
│       ├── auth/
│       ├── billing/
│       ├── communication/              ← All comm views + communication/prm/
│       ├── components/                 ← Reusable Blade components
│       ├── consultations/
│       ├── dashboard/
│       ├── huddle/
│       ├── inventory/
│       ├── lab/
│       ├── layouts/
│       ├── patients/
│       ├── reports/
│       ├── settings/
│       └── tasks/
└── routes/
    ├── web.php                         ← Auth + Dashboard ONLY
    ├── appointments.php
    ├── billing.php
    ├── communication.php               ← Already exists
    ├── consultations.php
    ├── huddle.php                      ← From Modules/Huddle/Routes/
    ├── inventory.php
    ├── lab.php
    ├── patients.php
    ├── prm.php                         ← Already exists
    ├── reports.php
    ├── settings.php                    ← Absorbs tags-routes.php
    ├── tasks.php
    └── console.php
```

---

# 4. SAFE MIGRATION PHASES

---

## PHASE 0 — SAFETY BASELINE

### Goal
Create a rollback point and document current working state.

### Exact Actions

```bash
# 1. Git snapshot
git add -A
git commit -m "chore: pre-refactor snapshot"
git tag v-pre-refactor
git push origin v-pre-refactor

# 2. Document current routes
php artisan route:list --columns=method,uri,name,action > routes-snapshot.txt

# 3. Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
composer dump-autoload
```

### Risks: None — read-only phase

### Estimated Effort: 30 minutes

### Testing Checklist
- [ ] App boots (`php artisan serve`)
- [ ] Login works
- [ ] Dashboard loads
- [ ] Patients load
- [ ] Appointments load
- [ ] Huddle loads
- [ ] Communication/PRM loads

---

## PHASE 1 — EMERGENCY CLEANUP (Root-Level Hazards)

### Goal
Remove dangerous/confusing artifacts at project root. Zero functional impact.

### Files to DELETE (after grep-verification they're unused)

```bash
# Verify each is not referenced before deleting
grep -r "dashboard.php" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules
grep -r "login.php" . --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules

# If no results, delete:
rm dashboard.php login.php logout.php
# Keep root index.php ONLY if it differs from public/index.php
diff index.php public/index.php   # if identical or unused → rm index.php

# Delete orphaned file artifacts
rm "name('patients.search')"
rm "now()]"
rm "prefix('hud"

# Delete stray CSS
rm module.css temp-module.css
```

### Files to ARCHIVE (not delete — investigate first)

```bash
mkdir -p .archive
mv backups/ .archive/
mv rules/ .archive/
mv services/ .archive/    # Non-Laravel service files
mv cron/ .archive/
mv workflows/ .archive/
# modules/ folder — DO NOT MOVE YET (Phase 2)
```

### Fix the JS filename bug

```bash
cd resources/js/communication/
mv "prm-board .js" "prm-board.js"
```

Then find and update any import reference:
```bash
grep -r "prm-board " resources/ --include="*.js" --include="*.blade.php"
# Update each reference to remove the space
```

### Risks
- Low — these files are outside Laravel's request lifecycle
- Watch for the JS import fix — test Vite build after

### Estimated Effort: 1–2 hours

### Testing Checklist
- [ ] `php artisan serve` — no errors
- [ ] `npm run build` — no Vite errors
- [ ] Login still works
- [ ] Route list unchanged (diff against snapshot)

---

## PHASE 2 — INVESTIGATE & NEUTRALIZE `modules/` ROOT FOLDER

### Goal
Determine if root `modules/` is active code or legacy scaffolding.

### Investigation Commands

```bash
# Check if any files have real PHP logic
find modules/ -name "*.php" -exec grep -l "function\|class\|Route::" {} \;

# Check if any Laravel file includes from this folder
grep -r "modules/" app/ routes/ bootstrap/ config/ --include="*.php"

# Check if it's referenced in composer.json autoload
cat composer.json | grep -A5 "autoload"
```

### Expected Outcomes

**If files contain no real logic (most likely):**
```bash
mv modules/ .archive/modules-legacy/
```

**If files ARE referenced:**
Map each reference file by file and create a per-file migration plan before moving anything.

### Estimated Effort: 1 hour

### Testing Checklist
- [ ] Auth still works
- [ ] All pages load
- [ ] No 500 errors in Laravel logs

---

## PHASE 3 — RESOLVE DUPLICATE CONTROLLERS

### Goal
Eliminate duplicate class names to safely enable file moves in later phases.

### Step-by-Step Process for EACH Duplicate Pair

#### 3A — DashboardController

```bash
# Find which routes use each version
php artisan route:list | grep -i dashboard
grep -r "DashboardController" routes/ --include="*.php"
```

These serve different purposes (main dashboard vs communication dashboard) — they just need correct fully-qualified namespace references in route files.

**Ensure routes reference them with full `use` statements:**
```php
// In web.php:
use App\Http\Controllers\DashboardController;

// In communication.php:
use App\Http\Controllers\Communication\DashboardController;
```

**Verify, then test dashboard. No file moves needed for this pair.**

#### 3B — PrmController

```bash
grep -r "PrmController" routes/ --include="*.php"
```

Open both files. Compare methods. Likely the root `PrmController.php` is legacy and the `Communication\PrmController.php` is active.

**If root one is redundant:**
1. Update any routes pointing to it to use the Communication version
2. Run `php artisan route:clear && php artisan route:list`
3. Test PRM
4. Delete root `app/Http/Controllers/PrmController.php`

**If root one has unique logic:**
Rename it to `PatientPrmController.php` or move to `Patients/` namespace.

#### 3C — TaskController

Same analysis as PrmController. Communication tasks vs. global tasks may be legitimately different. Follow the same process.

#### 3D — HuddleController

Do NOT resolve this in Phase 3. It requires the full Huddle module migration (Phase 5). Flag it and skip for now.

### Estimated Effort: 3–4 hours

### Testing Checklist
- [ ] `php artisan route:list` — no ambiguous route names
- [ ] Dashboard loads
- [ ] PRM/Communication loads  
- [ ] Tasks work
- [ ] No PHP class-not-found errors in logs

---

## PHASE 4 — CONSOLIDATE DUPLICATE VIEWS

### Goal
Eliminate view duplication to have one authoritative location per module's views.

### 4A — Resolve PRM View Duplication

```bash
# Find which controller returns which view path
grep -rn "view('prm" app/Http/Controllers/ --include="*.php"
grep -rn "view('communication.prm" app/Http/Controllers/ --include="*.php"
```

**Decision:** Standardize on `resources/views/communication/prm/` as canonical.

For any controller returning `view('prm.X')`:
```php
// Change from:
return view('prm.board', $data);
// Change to:
return view('communication.prm.board', $data);
```

After all references are updated, delete `resources/views/prm/`.

### 4B — Resolve lab vs labs

```bash
grep -rn "view('lab" app/Http/Controllers/ --include="*.php"
grep -rn "view('labs" app/Http/Controllers/ --include="*.php"
```
Delete whichever one is unreferenced.

### 4C — Resolve sidebar/topbar duplicate partials

```bash
grep -rn "@include('partials" resources/views/ --include="*.blade.php"
grep -rn "@include('components" resources/views/ --include="*.blade.php"
```
Goal: use `components/` as the canonical location. Update `@include('partials.sidebar')` references to `@include('components.sidebar')` where appropriate, then delete the partials copies.

### 4D — Delete stale files

```bash
rm resources/views/components/sidebar.blade.php.bak
rm resources/views/prm/lead-drawer.js
```

### Estimated Effort: 2 hours

### Testing Checklist
- [ ] PRM board loads
- [ ] Sidebar renders on all pages
- [ ] Lab page loads
- [ ] No "View not found" errors in logs

---

## PHASE 5 — MIGRATE THE HUDDLE MODULE

### Goal
Migrate `app/Modules/Huddle/` into standard Laravel structure. This establishes the pattern for all future module migrations.

### Step 1 — Create destination folders

```bash
mkdir -p app/Http/Controllers/Huddle
mkdir -p app/Http/Requests/Huddle
mkdir -p app/Http/Resources/Huddle
mkdir -p app/Services/Huddle
mkdir -p app/Repositories/Huddle
mkdir -p app/DTOs/Huddle
```

### Step 2 — COPY files (keep originals until verified)

```bash
cp app/Modules/Huddle/Controllers/HuddleController.php      app/Http/Controllers/Huddle/
cp app/Modules/Huddle/Controllers/HuddleCommentController.php app/Http/Controllers/Huddle/
cp app/Modules/Huddle/Controllers/HuddleSettingsController.php app/Http/Controllers/Huddle/
cp app/Modules/Huddle/Controllers/HuddleTaskController.php   app/Http/Controllers/Huddle/

cp app/Modules/Huddle/Services/HuddleAggregationService.php  app/Services/Huddle/
cp app/Modules/Huddle/Services/RoleBasedHuddleService.php    app/Services/Huddle/

cp app/Modules/Huddle/Repositories/*.php  app/Repositories/Huddle/
cp app/Modules/Huddle/Requests/*.php      app/Http/Requests/Huddle/
cp app/Modules/Huddle/DTOs/*.php          app/DTOs/Huddle/
cp app/Modules/Huddle/Resources/*.php     app/Http/Resources/Huddle/

# Models go to flat app/Models/
cp app/Modules/Huddle/Models/HuddleBoard.php     app/Models/
cp app/Modules/Huddle/Models/HuddleCard.php      app/Models/
cp app/Modules/Huddle/Models/HuddleComment.php   app/Models/
cp app/Modules/Huddle/Models/HuddleSetting.php   app/Models/
cp app/Modules/Huddle/Models/HuddleTaskLog.php   app/Models/

# Route file
cp app/Modules/Huddle/Routes/huddle.php  routes/huddle.php
```

### Step 3 — Update namespaces in ALL copied files

For each copied controller:
```php
// OLD
namespace App\Modules\Huddle\Controllers;
use App\Modules\Huddle\Services\HuddleAggregationService;
use App\Modules\Huddle\DTOs\HuddleBoardDTO;

// NEW
namespace App\Http\Controllers\Huddle;
use App\Services\Huddle\HuddleAggregationService;
use App\DTOs\Huddle\HuddleBoardDTO;
```

For models:
```php
// OLD
namespace App\Modules\Huddle\Models;

// NEW
namespace App\Models;
```

For services, repos, DTOs — change `App\Modules\Huddle\X` → `App\X\Huddle` pattern.

### Step 4 — Update routes/huddle.php

```php
// OLD
use App\Modules\Huddle\Controllers\HuddleController;

// NEW
use App\Http\Controllers\Huddle\HuddleController;
```

### Step 5 — Register routes/huddle.php in bootstrap/app.php

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    then: function () {
        Route::middleware('web')->group(base_path('routes/huddle.php'));
        Route::middleware('web')->group(base_path('routes/communication.php'));
    }
)
```

### Step 6 — Rebuild autoload and test

```bash
composer dump-autoload
php artisan config:clear
php artisan route:clear
```

Verify no errors:
```bash
php artisan route:list | grep huddle
```

### Step 7 — Full Huddle test

Test every feature: board loads, cards display, tasks, settings, comments.

### Step 8 — Verify no remaining references to old namespace

```bash
grep -r "App\\Modules\\Huddle" app/ routes/ --include="*.php"
# Should return zero results
```

### Step 9 — Delete originals

```bash
rm -rf app/Modules/Huddle/
```

### Step 10 — Remove HuddleController from Communication/ folder

After verifying routes/huddle.php uses the new `App\Http\Controllers\Huddle` namespace:
```bash
rm app/Http/Controllers/Communication/HuddleController.php
```

### Estimated Effort: 4–6 hours

### Testing Checklist
- [ ] Huddle board loads completely
- [ ] HuddleCards display with correct data
- [ ] Tasks work (create, assign, update status)
- [ ] Comments work
- [ ] Settings save
- [ ] No `Class not found` errors
- [ ] `php artisan route:list` shows all huddle routes

---

## PHASE 6 — NORMALIZE FLAT CONTROLLERS INTO SUBFOLDERS

### Goal
Move remaining flat controllers from `app/Http/Controllers/` root into organized subfolders, one at a time.

### Priority Order (lowest risk first)

1. `TagController.php` → `Settings/TagController.php`
2. `TreatmentCategoryController.php` → `Treatments/TreatmentCategoryController.php`
3. `TreatmentPlanController.php` → `Treatments/TreatmentPlanController.php`
4. `ReportsController.php` → `Reports/ReportsController.php`
5. `BillingController.php` → `Billing/BillingController.php`
6. `InventoryController.php` → `Inventory/InventoryController.php`
7. `LabController.php` → `Lab/LabController.php`
8. `AuthController.php` → `Auth/AuthController.php`
9. `SettingsController.php` → `Settings/SettingsController.php`
10. `CRMController.php` → `Crm/CRMController.php`
11. `DashboardController.php` → `Dashboard/DashboardController.php`
12. `PatientNoteController.php` → `Patients/PatientNoteController.php`
13. `PatientController.php` → `Patients/PatientController.php`
14. `AppointmentController.php` → `Appointments/AppointmentController.php`
15. `ConsultationController.php` → `Consultations/ConsultationController.php`

### Process for EACH Controller

```bash
# 1. Create subfolder
mkdir -p app/Http/Controllers/Settings

# 2. Copy file
cp app/Http/Controllers/TagController.php app/Http/Controllers/Settings/TagController.php

# 3. Update namespace in copied file
#    App\Http\Controllers → App\Http\Controllers\Settings

# 4. Find route file that uses this controller
grep -r "TagController" routes/ --include="*.php"

# 5. Update use statement in that route file
#    use App\Http\Controllers\TagController;
#    → use App\Http\Controllers\Settings\TagController;

# 6. Dump autoload and clear routes
composer dump-autoload && php artisan route:clear

# 7. Verify
php artisan route:list | grep tag

# 8. Test the page in browser

# 9. Only then delete the original
rm app/Http/Controllers/TagController.php
```

**Never batch multiple controllers. One at a time. Test between each.**

### Estimated Effort: 1 hour per controller × 15 = 10–15 hours (across multiple sessions)

### Testing Checklist After Each Controller
- [ ] Affected page loads
- [ ] Forms on that page submit correctly
- [ ] No PHP errors in `storage/logs/laravel.log`

---

## PHASE 7 — ROUTE STANDARDIZATION

### Goal
Make routes folder clean, consistent, fully modular.

### 7.1 — Rename inconsistent file

```bash
git mv routes/tags-routes.php routes/tags.php
# OR absorb into routes/settings.php if tags are settings-module routes
```

Update registration in bootstrap/app.php.

### 7.2 — Create module route files

```bash
touch routes/appointments.php
touch routes/patients.php
touch routes/billing.php
touch routes/inventory.php
touch routes/lab.php
touch routes/reports.php
touch routes/settings.php
touch routes/tasks.php
touch routes/consultations.php
```

### 7.3 — Extract routes from web.php into module files

Identify all routes in `web.php` and move them to appropriate module files. Leave in `web.php` only:
- Guest routes (login, register, password reset)
- Dashboard route
- Root redirect

### 7.4 — Register all module route files

In `bootstrap/app.php`:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    then: function () {
        $modules = [
            'appointments', 'patients', 'billing', 'consultations',
            'communication', 'prm', 'huddle', 'inventory', 'lab',
            'reports', 'settings', 'tags', 'tasks'
        ];
        foreach ($modules as $module) {
            Route::middleware('web')
                 ->group(base_path("routes/{$module}.php"));
        }
    }
)
```

### Standard Route File Template

```php
<?php
// routes/patients.php

use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::resource('patients', PatientController::class);
    Route::post('patients/{patient}/notes', [PatientNoteController::class, 'store'])
         ->name('patients.notes.store');
});
```

### Estimated Effort: 3–4 hours

### Testing Checklist
- [ ] `php artisan route:list` shows all routes with correct names
- [ ] No duplicate route names
- [ ] All pages still load
- [ ] Diff routes-snapshot.txt vs current — only namespace changes, no missing routes

---

# 5. FILE MOVEMENT MAP

| FROM | TO | Namespace Change | Route Update Required |
|---|---|---|---|
| `app/Modules/Huddle/Controllers/HuddleController.php` | `app/Http/Controllers/Huddle/HuddleController.php` | `App\Modules\Huddle\Controllers` → `App\Http\Controllers\Huddle` | routes/huddle.php |
| `app/Modules/Huddle/Controllers/HuddleCommentController.php` | `app/Http/Controllers/Huddle/HuddleCommentController.php` | same pattern | routes/huddle.php |
| `app/Modules/Huddle/Controllers/HuddleSettingsController.php` | `app/Http/Controllers/Huddle/HuddleSettingsController.php` | same pattern | routes/huddle.php |
| `app/Modules/Huddle/Controllers/HuddleTaskController.php` | `app/Http/Controllers/Huddle/HuddleTaskController.php` | same pattern | routes/huddle.php |
| `app/Modules/Huddle/Models/HuddleBoard.php` | `app/Models/HuddleBoard.php` | `App\Modules\Huddle\Models` → `App\Models` | None (auto-resolved) |
| `app/Modules/Huddle/Models/HuddleCard.php` | `app/Models/HuddleCard.php` | same | None |
| `app/Modules/Huddle/Models/HuddleComment.php` | `app/Models/HuddleComment.php` | same | None |
| `app/Modules/Huddle/Models/HuddleSetting.php` | `app/Models/HuddleSetting.php` | same | None |
| `app/Modules/Huddle/Models/HuddleTaskLog.php` | `app/Models/HuddleTaskLog.php` | same | None |
| `app/Modules/Huddle/Services/HuddleAggregationService.php` | `app/Services/Huddle/HuddleAggregationService.php` | `App\Modules\Huddle\Services` → `App\Services\Huddle` | None (injection) |
| `app/Modules/Huddle/Services/RoleBasedHuddleService.php` | `app/Services/Huddle/RoleBasedHuddleService.php` | same | None |
| `app/Modules/Huddle/Repositories/HuddleBoardRepository.php` | `app/Repositories/Huddle/HuddleBoardRepository.php` | `App\Modules\Huddle\Repositories` → `App\Repositories\Huddle` | None |
| `app/Modules/Huddle/Repositories/HuddleCardRepository.php` | `app/Repositories/Huddle/HuddleCardRepository.php` | same | None |
| `app/Modules/Huddle/Repositories/HuddleCommentRepository.php` | `app/Repositories/Huddle/HuddleCommentRepository.php` | same | None |
| `app/Modules/Huddle/Repositories/HuddleTaskRepository.php` | `app/Repositories/Huddle/HuddleTaskRepository.php` | same | None |
| `app/Modules/Huddle/Requests/AssignTaskRequest.php` | `app/Http/Requests/Huddle/AssignTaskRequest.php` | `App\Modules\Huddle\Requests` → `App\Http\Requests\Huddle` | None (auto-injected) |
| `app/Modules/Huddle/Requests/StoreHuddleCommentRequest.php` | `app/Http/Requests/Huddle/StoreHuddleCommentRequest.php` | same | None |
| `app/Modules/Huddle/Requests/StoreHuddleTaskRequest.php` | `app/Http/Requests/Huddle/StoreHuddleTaskRequest.php` | same | None |
| `app/Modules/Huddle/Requests/UpdateHuddleSettingsRequest.php` | `app/Http/Requests/Huddle/UpdateHuddleSettingsRequest.php` | same | None |
| `app/Modules/Huddle/Requests/UpdateTaskStatusRequest.php` | `app/Http/Requests/Huddle/UpdateTaskStatusRequest.php` | same | None |
| `app/Modules/Huddle/DTOs/HuddleBoardDTO.php` | `app/DTOs/Huddle/HuddleBoardDTO.php` | `App\Modules\Huddle\DTOs` → `App\DTOs\Huddle` | None |
| `app/Modules/Huddle/DTOs/HuddleCardDTO.php` | `app/DTOs/Huddle/HuddleCardDTO.php` | same | None |
| `app/Modules/Huddle/DTOs/HuddleStatsDTO.php` | `app/DTOs/Huddle/HuddleStatsDTO.php` | same | None |
| `app/Modules/Huddle/Resources/HuddleBoardResource.php` | `app/Http/Resources/Huddle/HuddleBoardResource.php` | `App\Modules\Huddle\Resources` → `App\Http\Resources\Huddle` | None |
| `app/Modules/Huddle/Resources/HuddleCardResource.php` | `app/Http/Resources/Huddle/HuddleCardResource.php` | same | None |
| `app/Modules/Huddle/Routes/huddle.php` | `routes/huddle.php` | N/A | Register in bootstrap/app.php |
| `app/Services/PatientProfileService.php` | `app/Services/Patients/PatientProfileService.php` | `App\Services` → `App\Services\Patients` | None (update injection sites) |
| `app/Http/Controllers/AppointmentController.php` | `app/Http/Controllers/Appointments/AppointmentController.php` | `App\Http\Controllers` → `App\Http\Controllers\Appointments` | routes/web.php → routes/appointments.php |
| `app/Http/Controllers/AuthController.php` | `app/Http/Controllers/Auth/AuthController.php` | same pattern | routes/web.php |
| `app/Http/Controllers/PatientController.php` | `app/Http/Controllers/Patients/PatientController.php` | same pattern | routes/patients.php |
| `app/Http/Controllers/PatientNoteController.php` | `app/Http/Controllers/Patients/PatientNoteController.php` | same pattern | routes/patients.php |
| `routes/tags-routes.php` | `routes/tags.php` OR absorbed into `routes/settings.php` | N/A | Update registration |

---

# 6. SAFE ORDER OF EXECUTION

## Strict Dependency Order

```
Phase 0  — Git tag + route snapshot           MUST BE FIRST
Phase 1  — Root-level cleanup                 MUST BE SECOND
Phase 2  — Investigate modules/ folder        MUST PRECEDE any module work
Phase 3  — Resolve duplicate controllers      MUST BEFORE any controller file moves
Phase 4  — View consolidation                 Safe after Phase 3
Phase 5  — Huddle module migration            Before other module migrations
Phase 6  — Flat controller reorganization     After Phase 5, one at a time
Phase 7  — Route standardization              Last — consolidates all prior work
```

## MUST Happen First
- Git tag — without this you have no safe rollback
- Route snapshot — without this you can't tell if you broke something
- Duplicate resolution (Phase 3) — file moves without this create silent routing bugs

## MUST NOT Happen Early
- Do NOT move flat controllers before duplicates are resolved
- Do NOT delete `app/Modules/` empty scaffolding before Huddle is fully migrated and verified
- Do NOT split `web.php` routes before the target module route files are created and registered
- Do NOT touch `Communication/` controllers until HuddleController duplicate is resolved (Phase 5)

## Can Wait (Safely Deferred)
- `app/Actions/` — populate only when extracting specific controller methods
- `app/Enums/` — populate as new features are built
- `app/Traits/` — populate when repeated patterns emerge
- Test coverage — add after stabilization phases are complete
- Blade component refactor — defer until all PHP moves are complete and stable

---

# 7. REUSABLE COMPONENT STRATEGY

## Philosophy
Do NOT rewrite existing Blade views. Add components alongside them. Replace incrementally.

## Step 1 — Inventory What Already Exists

```
resources/views/components/
├── appointment-modal.blade.php     ✓ exists
├── sidebar-item.blade.php          ✓ exists
├── sidebar.blade.php               ✓ exists
├── topbar.blade.php                ✓ exists
├── communication/                  ✓ 10 components
└── prm/                            ✓ 6 components
```

Use these as your design pattern reference.

## Step 2 — Build Order (highest reuse value first)

1. `<x-modal>` — base modal wrapper (buttons, overlay, close behavior)
2. `<x-page-header title subtitle>` — consistent page headers across all modules
3. `<x-status-badge status>` — appointment/task/PRM statuses
4. `<x-empty-state icon message>` — generalize the communication empty-state
5. `<x-patient-mini-card :patient>` — used in appointments, PRM, huddle
6. `<x-data-table>` — consistent table structure with header slots
7. `<x-form-section title>` — wraps groups of form fields

## Step 3 — Safe Introduction Rule

```
Create component → Use it in ONE new view → Verify → 
Then gradually replace old inline markup in existing views
```

Never refactor an existing view to use a new component and change business logic at the same time.

## Step 4 — Blade Component File Naming Convention

```
resources/views/components/
├── modal.blade.php
├── page-header.blade.php
├── status-badge.blade.php
├── empty-state.blade.php
├── data-table.blade.php
├── form-section.blade.php
└── patient/
    └── mini-card.blade.php
```

Used as: `<x-modal>`, `<x-page-header>`, `<x-patient.mini-card>`

---

# 8. SERVICE LAYER STRATEGY

## Current State

- `app/Services/PatientProfileService.php` — one active service
- `app/Modules/Huddle/Services/` — two well-built services (move in Phase 5)
- Everything else is inline in controllers

## Rule: When to Extract to a Service

Extract controller logic to a service ONLY when:
- A controller method exceeds ~30 lines of business logic
- The same logic is needed by 2+ controllers
- Logic involves multiple model writes in sequence
- Logic involves external API calls (SMS, email services, etc.)

Do NOT create services for simple CRUD operations.

## Extraction Process (Safe Pattern)

```php
// Step 1: Identify fat controller method
public function store(Request $request)
{
    // 40+ lines of logic mixing DB writes, notifications, state changes
}

// Step 2: Create service with that logic
// app/Services/Patients/PatientService.php
class PatientService
{
    public function createWithProfile(array $data): Patient
    {
        // The extracted logic here — behavior UNCHANGED
    }
}

// Step 3: Inject service — controller becomes thin
public function store(Request $request, PatientService $service)
{
    $patient = $service->createWithProfile($request->validated());
    return redirect()->route('patients.show', $patient);
}
```

**Golden rule: Extract logic AND change behavior are two separate commits.**

## Priority Candidates for Service Extraction

| Controller Method | Why Extract | Service Name |
|---|---|---|
| AppointmentController@store | Likely touches huddle, notifications | `AppointmentService` |
| ConsultationController@store | Multiple models: photos, scans, diagnoses, treatments | `ConsultationService` |
| PatientController@store/update | Tags, alerts, relationships | `PatientService` |
| Communication/PrmController | Pipeline stage management | `PrmPipelineService` |

## Service Registration

Simple services need no registration — Laravel auto-resolves type-hinted dependencies.

Use `AppServiceProvider.php` for interface bindings only. Avoid over-engineering with interfaces unless you genuinely have multiple implementations.

---

# 9. ROUTE STANDARDIZATION PLAN

## Target Convention

Every route name follows: `{module}.{resource}.{action}`

```
patients.index
patients.show
patients.store
patients.notes.store
appointments.today
appointments.index
huddle.board
communication.prm.index
communication.manager.queue
```

## Module Route File Template

```php
<?php
// routes/patients.php

use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    Route::resource('patients', PatientController::class);

    Route::prefix('patients/{patient}')->group(function () {
        Route::post('notes', [PatientNoteController::class, 'store'])
             ->name('patients.notes.store');
    });

});
```

## Migration Sequence

1. Create empty `routes/appointments.php`
2. Register it in `bootstrap/app.php`
3. Run `php artisan route:list` — verify no errors (empty file is fine)
4. Move appointment routes from `web.php` to `routes/appointments.php`
5. Run `php artisan route:list` — verify routes appear
6. Test appointment pages
7. Repeat for each module

Never move routes from web.php until the target file is registered AND verified.

---

# 10. FINAL CLEANUP PHASE

**Do not begin until ALL other phases are complete and app has been stable for at least one full working session.**

## 10.1 — Delete Empty Module Scaffolding

```bash
# Verify these are truly empty
find app/Modules/ -name "*.php" | wc -l   # Must return 0

rm -rf app/Modules/Appointment/
rm -rf app/Modules/Lab/
rm -rf app/Modules/Patient/
rm -rf app/Modules/Treatment/
rmdir app/Modules/    # Remove parent folder
```

## 10.2 — Clean Up Archive Folder

Review `.archive/` contents. Safely delete anything confirmed unused:
```bash
# Only after verifying nothing is referenced
rm -rf .archive/
```

## 10.3 — Remove Duplicate CSS from public/

The `resources/css/communication/` files should be processed by Vite and output to `public/build/`. The raw copies in `public/css/communication/` may be stale:
```bash
# Verify which CSS is actually loaded (check Blade layouts for href)
grep -r "css/communication" resources/views/ --include="*.blade.php"
```
If views load from Vite build assets (not public/css directly), remove the static copies.

## 10.4 — Final Cache Clear and Optimize

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
composer dump-autoload --optimize
php artisan optimize
```

## 10.5 — Final Route Diff

```bash
php artisan route:list --columns=method,uri,name,action > routes-final.txt
diff routes-snapshot.txt routes-final.txt
```

The diff should show only namespace path changes, never missing or added routes.

## 10.6 — Final Loose File Cleanup

```bash
find . -name "*.bak" -not -path "./vendor/*" -not -path "./node_modules/*"
find . -name "temp-*" -not -path "./vendor/*"
find . -name "*.php.php" -not -path "./vendor/*"  # Double extensions in migrations
```

---

# APPENDIX A — QUICK COMMANDS REFERENCE

```bash
# After every phase
php artisan config:clear && php artisan route:clear && php artisan view:clear
composer dump-autoload

# Check for old namespace references
grep -r "App\\Modules\\Huddle" app/ routes/ --include="*.php"

# Verify routes
php artisan route:list --columns=method,uri,name,action

# Check for class resolution errors
php artisan route:list 2>&1 | grep -i "error\|not found"

# Audit all view() calls
grep -rn "return view(" app/Http/Controllers/ --include="*.php"

# Audit all @include/@extends in Blade
grep -rn "@include\|@extends\|@component" resources/views/ --include="*.blade.php"

# Find unreferenced views
# (manual review — compare controllers view() calls vs resources/views/ contents)
```

---

# APPENDIX B — RISK REGISTRY

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Route breaks after controller namespace change | High | High | Test immediately after each move |
| Wrong `use` import in route file after copy | High | Medium | `php artisan route:list` after every change |
| Blade view path mismatch after view consolidation | Medium | Medium | Test affected pages right after view change |
| Huddle model namespace reference in old controllers | Medium | High | `grep -r App\\Modules\\Huddle` before deleting |
| JS import broken after prm-board rename | Low | Low | Check Vite build + browser console |
| Missing `composer dump-autoload` after file copy | High | High | Make it a habit after every file operation |
| Session/auth broken during Auth controller move | Low | High | Test login/logout after Phase 6 auth controller |

---

# APPENDIX C — AI-ASSISTED DEVELOPMENT GUIDELINES

To keep this architecture productive for AI-assisted development:

1. **New features go in the correct module folder** — new appointment logic → `app/Http/Controllers/Appointments/`, never back to root
2. **One controller per feature area** — never `AppointmentV2Controller.php`
3. **Services for logic over ~30 lines** — makes AI context windows efficient
4. **Route names must be descriptive** — AI can reference `patients.notes.store` unambiguously
5. **Blade components for UI repeated 3+ times** — AI can use `<x-modal>` without full markup
6. **Keep `config/` domain files current** — `config/clinic.php`, `config/permissions.php` give AI domain context
7. **Docblocks on service methods** — AI reads these in long sessions
8. **One responsibility per service method** — keeps AI-generated changes predictable

---

*Document version: 1.0*  
*Created: May 2026*  
*Architecture target: Laravel 11+ native, solo-maintainable, AI-development friendly*  
*Priority: Stability > Maintainability > Scalability > Speed > Small-team > AI-friendly*
