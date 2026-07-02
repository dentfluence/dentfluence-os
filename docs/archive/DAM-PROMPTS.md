# Dentfluence Clinical Library — Phase Prompts

Copy the prompt for the phase you want to build. Paste it into a fresh chat.
Each prompt assumes all previous phases are complete.

---

## Phase 0 — Navigation + Route Cleanup

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. That file contains the full architecture and phase plan. Focus on the Phase 0 section.

Before writing any code:
1. Read the files you will be modifying (sidebar.blade.php, routes/cms.php, routes/content-management.php, and any duplicate controllers).
2. Estimate total lines of changes across all files.
3. Flag truncation risk if the output might be cut off mid-edit.
4. Tell me your plan and ask me to confirm before starting.

Phase 0 tasks:
- Rename "Content Management" to "Clinical Library" in the sidebar
- Update the sidebar icon for Clinical Library
- Consolidate duplicate route files (routes/cms.php and routes/content-management.php both define cms. prefix routes — keep one, remove the other)
- Remove duplicate controller: app/Http/Controllers/Cms/ClinicalLibraryController.php
- Remove duplicate: app/Http/Controllers/Cms/EduLibraryController.php

Do NOT touch any other files. Do NOT write migrations or models.
```

---

## Phase 1 — Documents Tab UI Shell

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 1 section.

Phase 0 (navigation + route cleanup) is already done.

Before writing any code:
1. Read resources/views/patients/show.blade.php (note where other tab partials are @included, around line 1303).
2. Read resources/views/patients/partials/treatment-visits-tab.blade.php to understand the design pattern for tab partials.
3. Confirm that resources/views/patients/partials/documents-tab.blade.php does NOT yet exist.
4. Estimate total lines for the new file.
5. Flag truncation risk — this file will likely be 300–400+ lines. If truncation is likely, propose splitting into sub-partials (e.g. gallery-view, timeline-view, filters) and confirm with me before starting.
6. Tell me the plan and ask me to confirm.

Phase 1 tasks:
- Create resources/views/patients/partials/documents-tab.blade.php
- Add one @include line in show.blade.php at the correct position
- Build: sub-navigation bar, toolbar, filter bar, gallery view (with file cards grouped by visit), timeline view toggle, empty states
- All data is static placeholder — no backend, no Blade variables needed
- Use Alpine.js for sub-tab switching and view toggle
- Match existing design language: Tailwind + custom CSS, brand color #6a0f70

Do NOT touch any other files. Do NOT write migrations, models, or controllers.
```

---

## Phase 2 — Upload Modal

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 2 section.

Phases 0 and 1 are already done. The Documents tab shell exists at resources/views/patients/partials/documents-tab.blade.php.

Before writing any code:
1. Read resources/views/patients/partials/documents-tab.blade.php to understand the current state and where the Upload button triggers.
2. Read resources/views/patients/show.blade.php lines 2390–2430 to understand the existing drawer/modal pattern used in the app (invoice drawer pattern).
3. Estimate total lines for the upload modal partial.
4. Flag truncation risk — this file will likely be 200–300+ lines. If at risk, propose splitting and confirm with me.
5. Tell me the plan and ask me to confirm.

Phase 2 tasks:
- Create resources/views/patients/partials/documents-upload-modal.blade.php
- Add @include at the bottom of documents-tab.blade.php
- Build: file drag-and-drop zone, file list with auto-detected type badges, metadata form (treatment, visit, tooth, stage, tags, notes), eligibility flag toggles, upload progress placeholder
- Use the same drawer/slide-in pattern already used in the app
- Alpine.js for step navigation (select files → add metadata → review)
- All static placeholder — no actual upload POST logic

Do NOT implement file storage, controller logic, or routes.
```

---

## Phase 3 — Clinical Library Dashboard

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 3 section.

Phases 0, 1, and 2 are already done.

Before writing any code:
1. Read resources/views/layouts/app.blade.php to understand the page structure and @extends pattern.
2. Read resources/views/patients/show.blade.php lines 380–450 to understand the design language for page headers and stat cards.
3. Read routes/cms.php to understand where to add the dashboard route stub.
4. Estimate total lines for the dashboard view.
5. Flag truncation risk — this file will likely be 350–450+ lines. If at risk, propose sub-partials and confirm with me.
6. Tell me the plan and ask me to confirm.

Phase 3 tasks:
- Create resources/views/clinical-library/dashboard.blade.php
- Add a route stub GET /clinical-library → cms.dashboard in routes/cms.php pointing to ClinicalLibraryController@dashboard (method can return view with no data for now)
- Build: page header, small stat chips (not big stat cards), recent patients section (resume work), recent uploads list, needs-attention column, quick actions row
- This is a WORKSPACE not a stats page — action-oriented, not metric-heavy
- All data is static placeholder
- @extends('layouts.app'), same design language as rest of app

Do NOT implement backend queries. Do NOT pass real data. Do NOT build search.
```

---

## Phase 4 — Content Manager Rebuild

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 4 section.

Phases 0–3 are already done.

Before writing any code:
1. Read resources/views/content-management/index.blade.php in full — understand its current structure, CSS, and Alpine.js state before replacing it.
2. Read resources/views/content-management/partials/clinical/media-gallery.blade.php to understand existing card patterns.
3. Estimate total lines for the replacement view.
4. Flag truncation risk — this file will likely be 400–500+ lines. If at risk, propose splitting into sub-partials (tabs as separate @includes) and confirm with me before starting.
5. Tell me the plan and ask me to confirm.

Phase 4 tasks:
- BACK UP the existing index.blade.php by noting its content (do not delete — I will delete manually after review)
- Create the new Content Manager at the SAME PATH: resources/views/content-management/index.blade.php
- Build: top-level sub-nav (Marketing / Education / Case Library / Teaching / Research), sticky filter bar, Marketing tab with approval badges and batch select, Case Library tab with anonymised patient IDs, Education tab with two sections, placeholder tabs for Teaching and Research
- Google Photos feel: clean grid, full-bleed thumbnails, minimal chrome
- Batch selection mode using Alpine.js
- All static placeholder data

Do NOT implement approval routes, backend queries, or real data.
```

---

## Phase 5 — File Viewer + Detail Panel

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 5 section.

Phases 0–4 are already done.

Before writing any code:
1. Read resources/views/layouts/app.blade.php in full to understand where to include the global viewer partial (it goes once in the layout, available everywhere).
2. Read resources/views/patients/partials/documents-tab.blade.php to understand how file cards are currently structured and what event triggers the viewer.
3. Estimate total lines for the viewer partial.
4. Flag truncation risk — likely 200–300 lines. Propose split if needed and confirm.
5. Tell me the plan and ask me to confirm.

Phase 5 tasks:
- Create resources/views/clinical-library/partials/file-viewer.blade.php
- Add @include for it inside layouts/app.blade.php (once, globally available)
- Build: split-panel drawer (image/video/PDF display on left, metadata panel on right), navigation arrows, zoom controls placeholder, watermark toggle, patient/visit/procedure info, editable notes and tags, eligibility flag toggles, footer action buttons (download original, download watermarked, delete)
- Open via Alpine.js event: window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: 123 } }))
- Wire the click handler on file cards in documents-tab.blade.php to dispatch this event
- All static placeholder content

Do NOT implement real file serving, metadata save routes, or delete logic.
```

---

## Phase 6 — Clinical Library Settings UI

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 6 section.

Phases 0–5 are already done.

Before writing any code:
1. Read resources/views/settings/ directory listing to understand what settings pages already exist and how they are structured.
2. Read one existing settings view to understand the layout and design pattern.
3. Read the settings routes file to understand how to add a new settings section.
4. Estimate total lines for the new settings view.
5. Flag truncation risk — likely 350–500 lines across sections. Propose splitting into sub-sections if needed and confirm.
6. Tell me the plan and ask me to confirm.

Phase 6 tasks:
- Create resources/views/settings/clinical-library.blade.php
- Add navigation link to Clinical Library settings in the settings sidebar/nav
- Build 5 sections: Treatment Documentation Protocols (with step builder UI), Media Categories (enable/disable list), Watermark Templates (per-template element toggles + position + opacity + preview panel), Content Classification Rules (placeholder), Storage Settings (placeholder with "Coming Soon" for cloud)
- Match existing Settings page design language exactly

Do NOT implement backend for any setting. Static UI only.
```

---

## Phase 7 — Backend: Migration + Model + Controller

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 7 section.

Phases 0–6 (all UI) are already done.

IMPORTANT RULES FOR THIS PHASE:
- Read every file before modifying it
- Do NOT run migrate:fresh or rollback — additive migrations only
- Do NOT drop any existing tables (clinical_media, cms_media, patient_documents stay until Phase 8)
- Ask me before running any artisan command
- This phase touches the database — proceed carefully

Before writing any code:
1. Read app/Models/Patient.php to understand existing relationships.
2. Read app/Http/Controllers/PatientDocumentController.php (the current documents controller).
3. Read routes/cms.php and note existing route names to avoid conflicts.
4. Read app/Models/ClinicalMedia.php, CmsMedia.php, PatientDocument.php to understand columns being unified.
5. Estimate total files and lines across migration + model + controller.
6. Flag truncation risk. This phase has multiple files — propose building them one at a time (7A migration, then 7B model, then 7C controller) and confirm order with me.
7. Tell me the plan and ask me to confirm before writing anything.

Phase 7 tasks (in order):
7A — Create migration: create_clinical_files_table (full schema from DAM.md)
7B — Create app/Models/ClinicalFile.php (relationships, accessors, scopes)
7C — Create app/Http/Controllers/ClinicalFileController.php (index, store, show, update, destroy — patient-scoped)
7D — Add clinicalFiles() relationship to Patient model
7E — Wire documents-tab.blade.php to accept real $clinicalFiles data (replace static placeholder with Blade loops, keeping graceful empty states)
7F — Create migrations for documentation_protocols and documentation_protocol_steps tables
7G — Create DocumentationProtocol and DocumentationProtocolStep models

I will run php artisan migrate manually after reviewing the migration files.
```

---

## Phase 8 — Data Migration

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 8 section.

Phases 0–7 are done. The clinical_files table exists and ClinicalFile model is working.

CRITICAL RULES FOR THIS PHASE:
- Do NOT run any migration or script without showing me the code first
- Do NOT drop any old tables until I explicitly confirm data counts match
- Do NOT run migrate:fresh or rollback
- All migration scripts should be dry-run capable (preview mode before actual insert)
- Ask me before every destructive step

Before writing any code:
1. Read app/Models/ClinicalMedia.php — columns, relationships.
2. Read app/Models/CmsMedia.php — columns, relationships.
3. Read app/Models/PatientDocument.php — columns, relationships.
4. Read app/Models/EducationMedia.php — columns, relationships.
5. Read the clinical_files migration to confirm all destination columns exist.
6. Estimate total rows to migrate (I will provide counts if needed).
7. Tell me the migration plan step by step and ask me to confirm each step before executing.

Phase 8 tasks (each requires my explicit confirmation before running):
8A — Write and show migration script: clinical_media → clinical_files (with needs_review flag for unresolvable treatment_name strings)
8B — Write and show merge script: cms_media → clinical_files (deduplicate by patient_id + original_path)
8C — Write and show migration: patient_documents → clinical_files
8D — Write and show migration: education_media → clinical_files (set is_education_eligible = true)
8E — After I verify row counts: drop old tables (one at a time, with my confirmation each time)
8F — Update Patient model: remove documents() → PatientDocument, keep clinicalFiles() → ClinicalFile only

Each script should output: rows processed, rows skipped (duplicates), rows flagged for review.
```

---

## Phase 9 — Wire Content Manager to Real Data

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 9 section.

Phases 0–8 are done. All data is now in clinical_files. All UI views exist.

Before writing any code:
1. Read app/Http/Controllers/ContentManagement/ClinicalLibraryController.php in full.
2. Read resources/views/content-management/index.blade.php to understand what variables the view expects.
3. Read routes/cms.php to understand existing route names.
4. Estimate total changes across controller + view updates.
5. Flag truncation risk. Propose file-by-file order and confirm.
6. Tell me the plan and ask me to confirm.

Phase 9 tasks:
- Update ClinicalLibraryController: index() queries clinical_files with eligibility flags for each tab, passes correct variables to view
- Add marketing approval routes: PUT /clinical-library/files/{file}/approve and /reject
- Case Library: anonymise patient data in controller (never expose name/ID/contact in view)
- Education Library: query is_education_eligible = true
- Update global search in CmsSearchController or new ClinicalSearchController to query clinical_files
- Wire Dashboard (Phase 3 view) to real data: recent uploads, recent patients, needs-attention queries
- All existing route names (cms.index etc.) must remain unchanged to avoid breaking sidebar links

Do NOT redesign any views. Backend wiring only.
```

---

## Phase 10 — Watermark Engine

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 10 section.

Phases 0–9 are done. Clinical files are uploading, stored, and displayed correctly.

Before writing any code:
1. Read app/Models/ClinicalFile.php — check watermarked_path column and disk column.
2. Check if GD or Imagick is available: run php -r "echo extension_loaded('gd') ? 'GD ok' : 'no GD'; echo extension_loaded('imagick') ? ' Imagick ok' : ' no Imagick';" and tell me the result.
3. Check if Laravel queues are configured: read config/queue.php.
4. Estimate total lines across service + job + settings integration.
5. Flag truncation risk. Propose building in order: service → job → settings hookup. Confirm with me.
6. Tell me the plan and ask me to confirm.

Phase 10 tasks:
- Create app/Services/ClinicalLibrary/WatermarkService.php (generates watermarked derivative, never modifies original)
- Create app/Jobs/GenerateWatermark.php (queued job dispatched after upload)
- Integrate with ClinicalFileController: dispatch GenerateWatermark after successful store
- Read watermark template config from settings (Phase 6 backend — if not built yet, read from config file)
- Support configurable elements: clinic name, doctor name, treatment, stage, tooth number, date, logo
- Store watermarked copy at watermarked_path on same disk as original
- Original file path must NEVER be modified

Do NOT modify original files under any circumstances.
```

---

## Phase 11 — Documentation Protocols Engine

```
I'm building Dentfluence, a Laravel dental clinic management app running on Laragon at C:\laragon\www\dentfluence.

Read DAM.md at C:\laragon\www\dentfluence\DAM.md before doing anything. Focus on the Phase 11 section.

Phases 0–10 are done. Clinical files, watermarking, and content manager are all working.

Before writing any code:
1. Read app/Models/DocumentationProtocol.php and DocumentationProtocolStep.php.
2. Read app/Models/TreatmentVisit.php — understand the treatment_name and current_stage fields.
3. Read resources/views/patients/partials/documents-tab.blade.php — understand where to show protocol completion status.
4. Read resources/views/patients/partials/documents-upload-modal.blade.php — understand where to inject auto-suggested steps.
5. Estimate total lines across seeder + service + view updates.
6. Flag truncation risk. Propose building in order: seeder → service → view integration. Confirm with me.
7. Tell me the plan and ask me to confirm.

Phase 11 tasks:
- Create database/seeders/DocumentationProtocolSeeder.php — seed default protocols: Root Canal (6 steps), Implant (8 steps), Crown (5 steps), Extraction (3 steps), Aligner (6 steps), Scaling (2 steps)
- Create app/Services/ClinicalLibrary/ProtocolService.php — given a procedure name, returns the matching protocol steps
- Wire upload modal: when treatment/procedure is selected, fetch suggested steps via AJAX and pre-populate the metadata form
- Wire Documents tab: show protocol completion bar per visit group ("3 of 6 steps documented")
- Add AJAX route: GET /clinical-library/protocol-steps?procedure=Root+Canal → returns steps as JSON
- Settings: connect Phase 6 protocol builder UI to real CRUD routes (create/update/delete protocols and steps)

Mark the DAM.md status table as complete for all phases when done.
```
