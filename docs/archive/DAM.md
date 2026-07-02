# Dentfluence Clinical Library — Master Build Plan (DAM)

> **This file is the authoritative reference for every chat session building the Clinical Library.**
> Read this file in full at the start of every session before writing any code.

---

## What Is This Module

Dentfluence Clinical Library is the **Clinical Knowledge Engine** for the entire Dentfluence ecosystem.
It is NOT a simple file manager. It is the single source of truth for all clinical documentation.

It powers:
- Patient Documents tab (the primary integration point inside Dentfluence)
- Content Manager (marketing + education filtered views)
- Case Library (anonymised treatment showcases)
- Education Library (patient education)
- Teaching Library (training and conference use)
- Research Library (future research workflows)
- Standalone Clinical Library (sold separately)
- Dentfluence OS (includes Clinical Library by default)
- Future Mobile App and Consultant App

**One engine. Many surfaces. Zero duplication.**

---

## Finalized Architecture Decisions

### Primary Entity
Patient is always the primary entity. Every clinical file is anchored to a patient.

### Hierarchy
```
Patient
  └── Visit (treatment_visit)
        └── Clinical File (with optional procedure/treatment context)
```

A clinical file can be at three scope levels:
- **Patient scope** — `patient_id` only (historical records, general uploads)
- **Visit scope** — `patient_id + visit_id` (OPG, CBCT, visit consent, estimate)
- **Treatment scope** — `patient_id + visit_id + procedure` (working length xray, post-op photo)

### The `clinical_files` Table (future — built in Phase 7)
One table replaces: `clinical_media`, `cms_media`, `patient_documents`.

Key columns:
```
patient_id               FK → patients (always required)
visit_id                 FK → treatment_visits (nullable)
treatment_plan_item_id   FK → treatment_plan_items (nullable)
procedure                varchar, nullable (auto-filled from visit.treatment_name)
tooth_number             varchar, nullable
stage                    enum: general, before, during, after, followup
file_type                enum: photo, video, xray, opg, cbct, stl, intraoral_scan,
                               pdf, consent, estimate, invoice, lab_slip, other
title                    varchar, nullable
notes                    text, nullable
disk                     varchar (local, s3, azure)
path                     varchar (relative — NEVER absolute Windows paths)
watermarked_path         varchar, nullable
original_filename        varchar
mime_type                varchar
file_size                bigint
captured_at              datetime, nullable
uploaded_by              FK → users
source_type              varchar, nullable (prescription, invoice, lab_case)
source_id                bigint, nullable
protocol_step_id         FK → documentation_protocol_steps, nullable
sync_status              enum: local_only, sync_pending, synced, cloud_only
is_marketing_eligible    boolean default false
is_education_eligible    boolean default false
is_teaching_eligible     boolean default false
is_research_eligible     boolean default false
is_case_library_eligible boolean default false
consent_status           enum: not_given, pending, given
marketing_status         enum: pending, approved, rejected (nullable)
content_rating           tinyint nullable
tags                     json nullable
deleted_at               (soft delete)
```

### Views Are Filters, Not Storage
- **Content Manager** = `clinical_files WHERE is_marketing_eligible = true`
- **Education Library** = `clinical_files WHERE is_education_eligible = true`
- **Case Library** = `clinical_files WHERE is_case_library_eligible = true` (patient identity hidden)
- **Teaching Library** = `clinical_files WHERE is_teaching_eligible = true`

Nothing is stored twice. These are all SQL filtered views over the same data.

### Watermark Rules
- The **original file is NEVER modified**
- Watermarked copies are generated on demand or during export
- Configurable templates: Marketing / Education / Conference / Internal Review
- Clinic name, doctor name, treatment, stage, tooth number, date — all optional elements
- Settings → Clinical Library → Watermark Templates

### Storage Abstraction
- `disk` column maps to Laravel filesystem disk names
- `path` is always relative to the disk root
- Hybrid: each file independently knows its storage location
- `sync_status` tracks local ↔ cloud state per file

### What Is NOT Being Touched
The following existing modules are untouched in all phases:
- `Patient` model and profile
- `TreatmentVisit` model and visits tab
- `TreatmentPlan` / `TreatmentPlanItem`
- `Treatment` (template entity)
- `Consultation` tab
- `Billing` tab
- `Prescriptions` tab
- `Lab Cases` tab

The **Documents tab** is the integration point. It is currently a stub (tab exists in nav, no content panel).

---

## Existing Codebase State

### Design System
- **CSS**: Tailwind CSS (CDN at `https://cdn.tailwindcss.com`) + custom `<style>` blocks
- **JS**: Alpine.js for all interactivity (no Vue/React)
- **Brand color**: `#6a0f70` (deep purple)
- **Layout wrapper**: `@extends('layouts.app')` for all pages
- **Icons**: Inline SVG (24×24 viewBox, stroke-based, no icon library)

### Existing Files to Be Aware Of
| Path | Status |
|------|--------|
| `resources/views/patients/show.blade.php` | 2952 lines. DO NOT restructure. Only add `@include` for Documents tab. |
| `resources/views/patients/partials/documents-tab.blade.php` | DOES NOT EXIST. Must be created. |
| `resources/views/content-management/index.blade.php` | Exists. Well-styled CMS page. Will be REPLACED in Phase 4. |
| `resources/views/components/sidebar.blade.php` | Exists. `Content Management` label at line 68 → rename to `Clinical Library` in Phase 0. |
| `routes/cms.php` | Exists. Two conflicting route files (cms.php + content-management.php). Consolidate in Phase 0. |
| `app/Http/Controllers/ContentManagement/ClinicalLibraryController.php` | Exists. |
| `app/Http/Controllers/Cms/ClinicalLibraryController.php` | Duplicate — remove in Phase 0. |
| `app/Models/PatientDocument.php` | Exists. Kept until Phase 8 migration. |
| `app/Models/ClinicalMedia.php` | Exists. Kept until Phase 8 migration. |
| `app/Models/CmsMedia.php` | Exists. Kept until Phase 8 migration. |

### Patient Show Tab Structure
Tabs in `show.blade.php` (line 393):
```
profile | consultation | treatment-plan | visits | lab | prescriptions | billing | wallet | documents | notes | communications
```
The `documents` tab is in the navigation but has NO content panel rendered. It is a clean canvas.

### Patient Document Routes (currently)
```
POST   /patients/{patient}/documents        → PatientDocumentController@store
DELETE /patients/{patient}/documents/{doc} → PatientDocumentController@destroy
```
These will be replaced by `ClinicalFileController` routes in Phase 7.

---

## Phase Plan

---

### Phase 0 — Navigation + Route Cleanup
**Type**: Structural cleanup (small)
**Goal**: Clean up duplicate routes and rename nav. No new UI.

**Tasks**:
1. Rename sidebar label from `Content Management` → `Clinical Library`
   - File: `resources/views/components/sidebar.blade.php` line 68
   - Update icon to something more appropriate (a folder-image or library icon)
2. Consolidate duplicate route files: `routes/cms.php` and `routes/content-management.php` are both defining `cms.` prefix routes — keep ONE, delete the other
3. Remove duplicate controller: `app/Http/Controllers/Cms/ClinicalLibraryController.php` — keep `ContentManagement\ClinicalLibraryController.php`
4. Remove duplicate: `app/Http/Controllers/Cms/EduLibraryController.php`

**Do NOT**: Write migrations, change models, touch patient profile.
**Definition of done**: `php artisan route:list | grep cms` shows clean non-duplicate routes.

---

### Phase 1 — Documents Tab (UI Shell)
**Type**: UI prototype, no backend
**Goal**: Build the complete visual Documents tab inside the Patient Profile.

**File to create**: `resources/views/patients/partials/documents-tab.blade.php`

**Add to `show.blade.php`**: One `@include` line after the existing partials (around line 1303 where other tab partials are included):
```php
@include('patients.partials.documents-tab')
```

**What to build**:

#### Sub-navigation bar (top of tab)
- Tabs: `All Files` | `Photos & Videos` | `X-rays & Scans` | `Documents` | `Consents`
- Active tab indicator (underline, brand color `#6a0f70`)
- Count badges on each tab (static placeholder numbers for prototype)

#### Toolbar
- Left: Results count ("24 files · 3 visits")
- Right: `Upload Files` button (brand primary), View toggle (grid/timeline icon buttons)

#### Filter bar (collapsible)
- Visit selector (dropdown)
- Treatment/Procedure selector (dropdown)
- Stage selector: All / Before / During / After / Follow-up / General
- File type: All / Photo / Video / X-ray / OPG / CBCT / STL / PDF / Consent
- Date range: From / To
- Tags input
- Apply + Reset buttons

#### Gallery View (default)
Files grouped by visit date. Each group:
- Group header: Visit date + procedure name + doctor name + expand/collapse
- File grid: 3–4 columns, cards with thumbnail, file type badge, stage badge, tooth number, tags

File card design:
- Thumbnail (image or icon for non-image types)
- File type colored badge (photo=blue, xray=purple, video=green, pdf=orange, consent=red)
- Stage badge (before=blue, during=amber, after=green, followup=violet)
- Tooth number chip (if set)
- Hover state: show quick action icons (view, edit metadata, download, delete)
- Click: opens file viewer (Phase 5 placeholder — no action for now)

#### Timeline View (toggle)
Chronological list, visit-anchored. Each row:
- Left: date column
- Center: visit label + procedure + file count
- Right: mini thumbnail strip (3–4 previews then "+N more")

#### Empty States
Three empty states needed:
1. **No files at all**: Illustration area + "No clinical files yet" + "Upload the first file" button
2. **Filtered — no results**: "No files match your filters" + "Clear filters" button
3. **Empty visit group**: Never show empty visit groups (filter them out server-side)

**Design rules**:
- Use Alpine.js `x-data` for sub-tab switching and view toggle
- Brand color `#6a0f70` for active states
- Match the visual density of the existing Visits tab
- Use `style="display:none"` + `x-show` pattern (already used in show.blade.php)
- Static placeholder data (no `$patient`, no Blade variables needed for prototype)
- Comment every section clearly

**Do NOT**: Write controller logic, pass real data, create routes, touch any other file.

---

### Phase 2 — Upload Modal
**Type**: UI prototype, no backend
**Goal**: Build the upload workflow and metadata form as a modal overlay.

**File to create**: `resources/views/patients/partials/documents-upload-modal.blade.php`

**Include inside documents-tab.blade.php** at the bottom.

**What to build**:

#### Upload Modal
Trigger: "Upload Files" button in Phase 1 toolbar

#### Step 1 — File Selection
- Large drag-and-drop zone ("Drag files here or click to browse")
- Accepted types listed below: JPG, PNG, MP4, PDF, DCM, STL, etc.
- Multi-file support (show selected file list with name, size, remove button)
- File type auto-detection (show detected type badge next to each file)

#### Step 2 — Metadata (shown after files are selected)
Form fields per file (or applied to all if batch upload):
- **Treatment/Procedure** — dropdown (will be populated from patient's visits in Phase 7)
- **Visit** — dropdown (populated from patient's visits in Phase 7)
- **Tooth Number(s)** — text input (supports comma-separated, e.g. "16, 17")
- **Stage** — radio buttons: General / Before / During / After / Follow-up
- **File Type Override** — only show if auto-detection seems wrong
- **Tags** — tag input with chip UI
- **Notes** — small textarea

#### Eligibility Flags (collapsed section "Content Settings")
Toggle switches (not checkboxes — cleaner UX):
- Marketing Eligible
- Education Eligible
- Teaching Eligible
- Research Eligible
- Case Library Eligible

Tooltip on each explaining what it means.

#### Step 3 — Review & Upload
- Summary of files + metadata
- Upload progress bars per file
- Error states

**Design rules**:
- Modal is full-height slide-in from right (drawer pattern, like existing invoice drawer in show.blade.php)
- OR centered modal with backdrop — match existing modal pattern in the app
- Alpine.js for step navigation and toggle states
- Batch mode: same metadata applied to all files (with per-file override option)
- Zero friction: most fields optional, only procedure + stage truly important

**Do NOT**: Write the actual upload POST logic, file storage, or controller.

---

### Phase 3 — Clinical Library Dashboard
**Type**: UI prototype, no backend
**Goal**: Build the standalone Clinical Library workspace dashboard.

**File to create**: `resources/views/clinical-library/dashboard.blade.php`
**Route**: `GET /clinical-library` → `cms.dashboard` (add to `routes/cms.php`)

**This is NOT a statistics page. It is a workspace.**

**What to build**:

#### Page Header
- Title: "Clinical Library"
- Subtitle: "Your clinical documentation workspace"
- Actions: `Upload Files` button, `Search` button (triggers global search drawer)

#### Row 1 — Quick Stats (small, not dominant)
4 small stat chips (not big cards):
- Total Files · Total Patients · Files This Month · Pending Review
- Small numbers, muted style — supporting info only

#### Row 2 — Resume Work (most prominent section)
"Continue where you left off"
- 6 recent patient cards: Patient name, last upload date, file count, last file thumbnail
- Each card links to that patient's Documents tab
- If no recent activity: empty state with "Start by uploading your first clinical file"

#### Row 3 — Two-column layout
**Left column**: Recent Uploads (last 12 files uploaded across all patients)
- Compact list: thumbnail + filename + patient name + procedure + upload time
- "View all" link

**Right column**: Needs Attention
- Incomplete documentation (visits with 0 files): patient + visit date + procedure
- Pending marketing approval: files tagged marketing_eligible but not yet approved
- "View all" links

#### Row 4 — Quick Actions
- Upload for a patient (patient search → then open upload modal)
- Browse all files (link to full Clinical Library search/browse)
- Content Manager (link to Phase 4 view)

**Design rules**:
- Workspace feel, not dashboard feel — action-oriented
- All data static placeholder for prototype
- Responsive: stack on smaller screens
- Match existing app page style (white cards, border-gray-200, rounded-lg)

**Do NOT**: Write backend, pass real data, build search functionality.

---

### Phase 4 — Content Manager
**Type**: UI rebuild (replaces existing CMS index view)
**Goal**: Rebuild the Content Manager as a Google Photos-style filtered view.

**File to REPLACE**: `resources/views/content-management/index.blade.php`
> Back up the existing file first. New version replaces it at the same path so existing route `cms.index` continues to work.

**Philosophy**: "Filter. Select. Use." — No storage here. Everything shown comes from Clinical Files.

**What to build**:

#### Top-level Sub-navigation
Tabs (these are filtered views):
- `Marketing` — is_marketing_eligible = true
- `Education` — is_education_eligible = true
- `Case Library` — is_case_library_eligible = true
- `Teaching` — is_teaching_eligible = true
- `Research` — is_research_eligible = true

Each tab shows: count badge of eligible files.

#### Global Filter Bar (above content, sticky)
- Treatment type filter
- Stage filter (Before / After etc.)
- Approval status filter (Pending / Approved / Rejected) — only on Marketing tab
- Patient search (anonymised for Case Library tab)
- Date range
- Tags
- Sort: Newest / Oldest / Rating / Treatment

#### Marketing Tab
Google Photos style grid:
- Masonry or uniform grid of file thumbnails
- Each card: thumbnail, treatment name, stage badge, consent badge, approval status badge
- Approval status chip: Pending (amber) / Approved (green) / Rejected (red)
- Hover actions: Approve, Reject, View, Download with watermark
- Batch select mode: checkbox on hover, "Approve selected" action bar

#### Case Library Tab
Grid of treatment cases (grouped by procedure type):
- Patient identity HIDDEN — show "Patient #A142" or similar anonymised ID
- Show: treatment name, before/after thumbnail pair, tooth number, doctor, date range
- "View Case" → opens case detail panel (slide-in)

Case detail panel:
- Timeline of all case files (before → during → after → followup)
- Full metadata visible EXCEPT patient name/ID/contact
- "Use for Education" flag toggle

#### Education Tab
Two sections:
1. **From Clinical Files** (is_education_eligible = true) — grid view
2. **Library Resources** (generic educational content not tied to patients — placeholder for future)

#### Teaching + Research Tabs
Placeholder UI: grid view + empty state message "Coming soon — tag files as Teaching/Research eligible to populate this view."

**Design rules**:
- Google Photos energy: clean grid, full-bleed thumbnails, minimal chrome
- Batch selection mode (click to select, action bar appears at bottom)
- Approval workflow for marketing: visual badge system, keyboard-accessible
- The word "Patient" never appears on Case Library tab — only anonymised IDs

**Do NOT**: Implement actual approval routes, write backend queries, modify existing routes.

---

### Phase 5 — File Viewer + Detail Panel
**Type**: UI prototype
**Goal**: The modal or drawer that opens when any clinical file is clicked anywhere in the app.

**File to create**: `resources/views/clinical-library/partials/file-viewer.blade.php`
This partial will be included once in `layouts/app.blade.php` so it's available globally.

**What to build**:

#### Viewer Drawer (slide-in from right, ~60% width)
Left pane (60%):
- Full-size file display: image viewer / video player / PDF embed / STL viewer placeholder
- Navigation arrows (previous / next file in current context)
- Zoom controls for images
- Watermark toggle: "View original" / "View watermarked"

Right pane (40%):
- Patient name + ID (linked to patient profile)
- Visit date + visit link
- Procedure + stage badge + tooth number
- File type + upload date + uploaded by
- Notes (editable inline)
- Tags (editable inline chips)
- Eligibility flags (toggle switches)
- Consent status indicator
- Marketing approval status

Footer actions:
- Download (original)
- Download (watermarked)
- Edit metadata
- Add to Case Library (toggle)
- Delete (with confirmation)

**Design rules**:
- Reusable across Documents tab, Content Manager, Dashboard, and any future surface
- Open via global JS event: `window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: 123 } }))`
- Alpine.js manages open/close state
- Static placeholder content for prototype

**Do NOT**: Implement actual file serving, real metadata editing, or delete routes.

---

### Phase 6 — Clinical Library Settings
**Type**: UI prototype
**Goal**: Add a "Clinical Library" section to the existing Settings module.

**File to create**: `resources/views/settings/clinical-library.blade.php`
Add link in existing Settings navigation.

**Sections to build**:

#### 1. Treatment Documentation Protocols
- List of protocols (Root Canal Protocol, Implant Protocol, etc.)
- Each protocol: procedure type + list of required steps
- Step: name + file type + stage + required/optional + sort order
- Add/Edit/Delete protocol UI
- "Apply to new visits" toggle

#### 2. Media Categories
- Configurable list of file types available in upload form
- Enable/disable per category
- Display order

#### 3. Watermark Templates
- Template list (Marketing, Education, Conference, Internal Review)
- Per template: enable/disable elements (clinic name, doctor, treatment, stage, tooth, date)
- Position: top-left / top-right / bottom-left / bottom-right / center
- Opacity slider (visual)
- Preview panel showing a sample watermark layout

#### 4. Content Classification Rules
- Configure what "Marketing Eligible" means (auto-suggest based on stage, treatment type)
- Consent requirements before marketing approval
- Auto-tagging rules (future AI placeholder)

#### 5. Storage Settings (placeholder)
- Current storage: Local
- Cloud storage: Disabled (enable button grayed out with "Coming Soon")
- Storage usage bar

**Do NOT**: Build backend for any of these settings. Static UI only.

---

### Phase 7 — Backend: Migration + Model + Controller
**Type**: Backend implementation (the first code that touches the database)

> ⚠️ Do NOT run `migrate:fresh` or `rollback`. Add only new migrations.
> ⚠️ Read existing models before writing anything.
> ⚠️ This phase has the highest risk of breaking existing functionality. Proceed carefully.

**Tasks**:

#### 7A — New `clinical_files` Table
Create migration: `create_clinical_files_table`
All columns per the architecture above.
Indexes: `patient_id`, `visit_id`, `(patient_id, file_type)`, `(is_case_library_eligible)`, `(is_marketing_eligible, consent_status, marketing_status)`

#### 7B — ClinicalFile Model
Create `app/Models/ClinicalFile.php`
- Relationships: `patient()`, `visit()`, `treatmentPlanItem()`, `uploadedBy()`
- Accessors: `getDisplayUrlAttribute()`, `getOriginalUrlAttribute()`, `getThumbnailUrlAttribute()`
- Scopes: `forPatient()`, `forVisit()`, `marketingEligible()`, `educationEligible()`, `caseLibraryEligible()`
- Cast `tags` as array

#### 7C — ClinicalFileController
Replace `PatientDocumentController` with `app/Http/Controllers/ClinicalFileController.php`

Routes (add to `routes/cms.php` or new `routes/clinical-library.php`):
```
GET    /patients/{patient}/clinical-files
POST   /patients/{patient}/clinical-files
GET    /patients/{patient}/clinical-files/{file}
PUT    /patients/{patient}/clinical-files/{file}
DELETE /patients/{patient}/clinical-files/{file}
```

#### 7D — Update Patient Model
Add relationship:
```php
public function clinicalFiles(): HasMany
{
    return $this->hasMany(ClinicalFile::class)->latest('captured_at');
}
```

#### 7E — Wire Documents Tab to Real Data
Update `documents-tab.blade.php` to use real `$clinicalFiles` data passed from patient controller.
Replace static placeholder HTML with Blade loops.

#### 7F — Documentation Protocols Tables
Two migrations:
- `create_documentation_protocols_table`
- `create_documentation_protocol_steps_table`
Models: `DocumentationProtocol`, `DocumentationProtocolStep`

**Do NOT**: Migrate existing data yet (that is Phase 8).

---

### Phase 8 — Data Migration
**Type**: Data migration from old tables to `clinical_files`

> ⚠️ This phase is destructive in the sense that it moves data. Run on a backup first.
> ⚠️ Ask before running any migration script.

**Tasks**:

#### 8A — Migrate `clinical_media` → `clinical_files`
Map columns. Resolve `treatment_name` string → try to match to `TreatmentVisit.treatment_name WHERE patient_id = X`. Flag unresolvable rows with `needs_review = true` (add temp column).

#### 8B — Merge `cms_media` → `clinical_files`
Detect duplicates by `patient_id + original_path` before inserting. Transfer marketing flags, consent_status, marketing_status.

#### 8C — Migrate `patient_documents` → `clinical_files`
Map `category` → `file_type`. Title → `title`. All at `patient_id` scope (no visit).

#### 8D — Migrate `education_media` → `clinical_files`
Set `is_education_eligible = true`. These are not patient-scoped — flag with `source_type = 'education_library'`.

#### 8E — Drop Old Tables (after verification)
Only after verifying file counts match:
- `clinical_media`
- `cms_media`
- `patient_documents`
- `education_media`
- `cms_treatment_cases` (replaced by dynamic query)

#### 8F — Update Patient Model
Remove `documents()` → `PatientDocument` relationship.
Keep `clinicalFiles()` → `ClinicalFile` as the only relationship.

---

### Phase 9 — Wire Content Manager ✅ Done (2026-06-14)
**Type**: Backend integration
**Goal**: Connect Phase 4 Content Manager UI to real `clinical_files` data.

**Tasks**:
- ✅ Updated `ContentManagement\ClinicalLibraryController::index()` to query `clinical_files` with eligibility flags per tab
- ✅ Marketing approval routes: `PUT /clinical-library/files/{file}/approve` and `reject`
- ✅ Case Library: anonymised patient data in controller (deterministic anon ID — real patient_id/name never reaches view)
- ✅ Education Library: queries `is_education_eligible = true`
- ✅ Connected global search (`CmsSearchController`) to `clinical_files` table
- ✅ Dashboard (`dashboard.blade.php`) wired: stat chips, resume work, recent uploads, incomplete docs, pending approval
- ✅ Tab count badges wired via `json_encode($tabCounts)` in `index.blade.php`
- ✅ `TreatmentVisit::clinicalFiles()` HasMany relationship added (required for `whereDoesntHave` query)

---

### Phase 10 — Watermark Engine ✅ Done (2026-06-14)
**Type**: Backend service
**Goal**: Implement watermark generation as a background service.

**Tasks**:
- ✅ `app/Services/ClinicalLibrary/WatermarkService.php` — GD/Imagick, reads WatermarkSetting, overlays text + optional logo, writes wm_ copy on same disk as original
- ✅ `app/Jobs/GenerateWatermark.php` — queued job, 3 retries, 120s timeout, routes to 'watermarks' queue, updates watermarked_path on success
- ✅ `ClinicalFileController::store()` — dispatches GenerateWatermark after create() for image types only
- ✅ Original file path (`ClinicalFile::$path`) is NEVER modified

**To activate**: Run `php artisan queue:work --queue=watermarks,default` in Laragon terminal.

---

### Phase 11 — Documentation Protocols Engine
**Type**: Backend service
**Goal**: Auto-suggest documentation steps on visit/treatment selection.

**Tasks**:
- Seed default protocols (Root Canal, Implant, Crown, Extraction, Aligner, Scaling)
- On visit selection in upload modal, auto-suggest which steps are needed
- Show completion status: "3 of 6 steps complete" in Documents tab
- Settings UI from Phase 6 connected to backend

---

## Cross-Phase Design Rules (Apply in Every Session)

1. **Read before writing** — Always read the file you are modifying before making changes.
2. **Never touch existing working modules** — Patient profile, Visits, Billing, Prescriptions are off-limits unless the phase explicitly requires integration.
3. **Never use absolute paths** — All file paths in `clinical_files.path` must be relative to the disk root.
4. **Never duplicate media** — One physical file, one database record.
5. **Patient is always the anchor** — Every clinical file must have `patient_id`.
6. **Alpine.js only** — No Vue, no React, no jQuery. Alpine.js for all interactivity.
7. **Tailwind + custom CSS** — Follow the existing pattern. No new CSS frameworks.
8. **Static first** — All UI phases (1–6) use static placeholder data. No backend needed.
9. **Blade partials** — New tab content goes in `patients/partials/`, never inline in `show.blade.php`.
10. **Comment everything** — Every section of every Blade file should have a clear comment header.

---

## How to Use This File in a New Chat

Start every new chat session with:

> "I'm building Dentfluence, a Laravel dental clinic management app. Read `DAM.md` at `C:\laragon\www\dentfluence\DAM.md` for the full architecture. Today we are working on **[Phase X — Name]**. Read the relevant sections and then proceed."

Then reference the specific phase section above for the exact deliverables.

---

## Current Status

| Phase | Name | Status |
|-------|------|--------|
| 0 | Navigation + Route Cleanup | ✅ Done |
| 1 | Documents Tab UI Shell | ✅ Done |
| 2 | Upload Modal | ✅ Done |
| 3 | Clinical Library Dashboard | ✅ Done |
| 4 | Content Manager Rebuild | ✅ Done |
| 5 | File Viewer + Detail Panel | ✅ Done — 2026-06-13 |
| 6 | Clinical Library Settings UI | 🔧 Partial — CRUD wiring deferred to next session |
| 7 | Backend: Migration + Model + Controller | ✅ Done |
| 8 | Data Migration | ✅ Done — 2026-06-14 |
| 9 | Wire Content Manager | ✅ Done — 2026-06-14 |
| 10 | Watermark Engine | ✅ Done — 2026-06-14 |
| 11 | Documentation Protocols Engine | ✅ Done — 2026-06-14 |

Update this table as phases complete.
