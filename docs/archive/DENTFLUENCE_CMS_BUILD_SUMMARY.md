# Dentfluence — CMS Module Build Summary

**Module Name:** Content Management System (Clinical Library)
**Route Prefix:** `/content-management`
**Route Name Prefix:** `cms.`
**Last Updated:** May 2026

---

## Key Architectural Decisions

| Decision | Choice | Reason |
|---|---|---|
| Module name | `ContentManagement` (not `ClinicalLibrary`) | Accommodates Marketing tab + Education |
| Route prefix | `/content-management` | Broader than `/clinical-library` |
| Upload policy | **CMS never accepts uploads directly** | Uploads flow from Consultation/Visit/Follow-up only |
| CMS role | Searchable intelligence layer on top of existing workflows | Not a file manager |
| Controllers namespace | `App\Http\Controllers\ContentManagement` | Follows project architecture rules |
| Services namespace | `App\Services\ContentManagement` | Follows project architecture rules |

---

## Database Tables

### `clinical_media`
Core table. Every file uploaded from Consultation/Visit/Follow-up is registered here.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `patient_id` | foreignId | required |
| `doctor_id` | foreignId (nullable) | `users` table |
| `consultation_id` | foreignId (nullable) | links to existing consultation |
| `visit_id` | foreignId (nullable) | links to existing visit |
| `treatment_name` | string (nullable) | e.g. "Implant", "RCT" |
| `tooth_no` | string 50 (nullable) | e.g. "11, 46" |
| `treatment_stage` | string (nullable) | `before`, `during`, `after`, `followup` |
| `media_type` | string | `photo`, `xray`, `opg`, `cbct`, `scan`, `video`, `pdf` |
| `original_path` | string | always stored |
| `watermarked_path` | string (nullable) | generated async |
| `disk` | string | default `local` |
| `file_name` | string | display name |
| `file_size` | unsignedInteger (nullable) | bytes |
| `title` | string (nullable) | doctor-set label |
| `tags` | JSON (nullable) | searchable array |
| `is_generic` | boolean | `false` = patient-linked, `true` = education |
| `category` | string (nullable) | for education: `implantology`, `endodontics`, etc. |
| `sort_order` | integer | display ordering |
| `metadata` | JSON (nullable) | extensible |
| `timestamps` | — | standard |

**Indexes:** `patient_id`, `treatment_name`, `tooth_no`, `treatment_stage`, `media_type`

---

### `education_categories`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `name` | string | e.g. "Implantology" |
| `slug` | string (unique) | |
| `icon` | string (nullable) | icon identifier |
| `sort_order` | integer | |
| `is_active` | boolean | |
| `timestamps` | — | |

---

### `education_treatments`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `category_id` | foreignId | → `education_categories` |
| `title` | string | e.g. "Dental Implant Placement" |
| `description` | text (nullable) | |
| `sort_order` | integer | |
| `is_published` | boolean | |
| `timestamps` | — | |

---

### `education_media`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `treatment_id` | foreignId | → `education_treatments` |
| `media_type` | string | `photo`, `video`, `xray`, `pdf`, `scan` |
| `file_path` | string | |
| `thumbnail_path` | string (nullable) | |
| `title` | string (nullable) | |
| `tags` | JSON (nullable) | |
| `sort_order` | integer | |
| `timestamps` | — | |

---

## Models

### `app/Models/ClinicalMedia.php`
- Scopes: `patientClinical()`, `genericEducational()`, `forPatient($id)`, `forTooth($tooth)`, `forTreatment($name)`
- Accessors: `displayUrl`, `watermarkedUrl`
- Relationship: `belongsTo` Patient, User (doctor), Consultation

### `app/Models/EducationCategory.php`
- Relationships: `hasMany` EducationTreatment, `hasMany` EducationMedia
- Scope: `active()`
- Method: `activeTreatments()` → filters by `is_published = true`

### `app/Models/EducationTreatment.php`
- Relationships: `belongsTo` EducationCategory, `hasMany` EducationMedia
- `withCount('media')` used in queries

### `app/Models/EducationMedia.php`
- `belongsTo` EducationTreatment

---

## Controllers

### `app/Http/Controllers/ContentManagement/CmsController.php`
**Methods:**
- `index(Request)` — renders main CMS page (defaults to clinical tab)
- `clinical(Request)` — clinical tab
- `education(Request)` — Generic Education Library tab
- `educationManage(Request)` — delegates to `EducationContentController::manage()`
- `marketing(Request)` — Marketing tab
- `patientView(Request, $patientId)` — patient profile shortcut
- `saveWatermarkSettings(Request)` — watermark config save

Private: `sharedViewData()` — returns `$patients`, `$toothOptions`, `$treatments`, `$doctors`, `$stats` to all views via `array_merge()`.

### `app/Http/Controllers/ContentManagement/CmsSearchController.php`
**Methods:**
- `search(Request)` — AJAX multi-filter search
- `caseViewer($id)` — AJAX slide-in case panel
- `tagMarketing(Request)` — tag a media item as marketing content
- `removeMarketingTag($id)` — untag marketing

### `app/Http/Controllers/ContentManagement/EducationContentController.php`
**Methods:**
- `manage(Request)` — 3-column manage view (categories → treatments → media)
- `storeCategory(Request)` — create education category
- `destroyCategory(EducationCategory)` — delete category
- `storeTreatment(Request)` — create treatment under a category
- `updateTreatment(Request, EducationTreatment)` — edit treatment title/description
- `destroyTreatment(EducationTreatment)` — delete treatment
- `uploadMedia(Request, EducationTreatment)` — upload media files to a treatment
- `destroyMedia(EducationMedia)` — delete a media item

---

## Services

### `app/Services/ContentManagement/ClinicalMediaUploadService.php`
**Purpose:** Bridge between existing Consultation/Visit controllers and the CMS.
**Key method:** `store(array $data, UploadedFile $file)` — call this from Consultation/Visit save handlers.
**Does:** saves file to disk, registers metadata in `clinical_media`, triggers watermark, auto-generates tags.

### `app/Services/ContentManagement/WatermarkService.php`
**Purpose:** Auto-applies watermarks on upload.
**Watermark content:** clinic name, doctor name, date (corner placement, subtle).
**Storage:** saves original to `/storage/originals/`, watermarked to `/storage/watermarked/`.
**Fallback:** graceful no-op if Intervention Image not installed.

### `app/Services/ContentManagement/CmsSearchService.php`
**Purpose:** Powers the filter/search system.
**Filters:** patient, tooth, treatment, doctor, date range, tags, global search string.
**Returns:** grouped case records for the results table.

### `app/Services/ContentManagement/TimelineService.php`
**Purpose:** Builds the auto visit timeline in the Case Viewer.
**Logic:** groups media by upload date and treatment stage into Day 1 / Day 5 / Day 20 format.

---

## Routes (`routes/cms.php`)

```php
Route::prefix('content-management')->name('cms.')->group(function () {
    Route::get('/',                                             [CmsController::class, 'index'])             ->name('index');
    Route::get('/clinical',                                     [CmsController::class, 'clinical'])          ->name('clinical');
    Route::get('/education',                                    [CmsController::class, 'education'])         ->name('education');
    Route::get('/education/manage',                             [ClinicalLibraryController::class, 'educationManage']) ->name('education.manage');
    Route::get('/marketing',                                    [CmsController::class, 'marketing'])         ->name('marketing');
    Route::get('/search',                                       [CmsSearchController::class, 'search'])      ->name('search');
    Route::get('/case/{id}',                                    [CmsSearchController::class, 'caseViewer'])  ->name('case');
    Route::get('/patient/{patientId}',                          [CmsController::class, 'patientView'])       ->name('patient');
    Route::post('/tag-marketing',                               [CmsSearchController::class, 'tagMarketing'])->name('tag.marketing');
    Route::delete('/tag-marketing/{id}',                        [CmsSearchController::class, 'removeMarketingTag'])->name('tag.marketing.remove');
    Route::post('/watermark-settings',                          [CmsController::class, 'saveWatermarkSettings'])->name('watermark.save');

    // Education content management
    Route::post('/education/category',                          [EducationContentController::class, 'storeCategory'])  ->name('education.category.store');
    Route::delete('/education/category/{category}',             [EducationContentController::class, 'destroyCategory'])->name('education.category.destroy');
    Route::post('/education/treatment',                         [EducationContentController::class, 'storeTreatment']) ->name('education.treatment.store');
    Route::put('/education/treatment/{treatment}',              [EducationContentController::class, 'updateTreatment'])->name('education.treatment.update');
    Route::delete('/education/treatment/{treatment}',           [EducationContentController::class, 'destroyTreatment'])->name('education.treatment.destroy');
    Route::post('/education/treatment/{treatment}/upload',      [EducationContentController::class, 'uploadMedia'])    ->name('education.media.upload');
    Route::delete('/education/media/{media}',                   [EducationContentController::class, 'destroyMedia'])   ->name('education.media.destroy');
});
```

**Registration:** in `bootstrap/app.php` via `withRouting()` callback.

---

## Views

### `resources/views/content-management/index.blade.php`
Patient Clinical Data tab. Features:
- Sticky header with 3 tabs: Patient Clinical Data / Generic Education Library / Marketing Content
- Watermark Settings + Export buttons (top right)
- Filter card: Patient Name, Tooth No., Treatment, Date Range, Doctor, Tags dropdowns
- Expandable "More Filters" row
- Active filter chips with individual × clear + "Clear All"
- Sortable results table: Patient Name, Treatment, Tooth No., Start Date, Completion Date, Last Follow-up, Media count, Status, kebab menu
- **Sliding case viewer side panel** (right side) triggered on row click:
  - Tabs: Case Overview / Timeline / Visit History / Notes
  - Case Overview: start date, completion date, last follow-up, doctor, total visits, tag pills
  - Media Gallery: All / Photos / X-Rays / Scans / Videos filter tabs
  - Media grouped by stage: Before Treatment / During Treatment / After Treatment / Follow-up
  - "View Full Patient Profile" button at bottom
- Watermark footer bar

### `resources/views/content-management/education.blade.php`
Generic Education Library tab. Features:
- Category strip carousel: All Categories, Restorative, Implantology, Endodontics, Periodontics, Orthodontics, Oral Surgery, Preventive
- Treatment card grid (4 columns)
- Each card: preview image/video thumbnail, badge overlay (Video / Photos / X-Ray), media count stats (Photos, X-Rays, Videos), title, description, View button
- Empty state when no content
- Footer note: "This library contains educational content not linked to individual patients"

### `resources/views/content-management/education-manage.blade.php`
3-column content management UI. Features:
- Column 1: Category list with item counts, edit (✎) button, delete (×) button
- Column 2: Treatment list per selected category, media count, edit + delete
- Column 3: Media grid for selected treatment with upload zone
- Upload widget: drag-and-drop, media type selector, title + tags, progress indicators
- Edit modals for categories and treatments (pre-filled)
- Thumbnail previews for photos/xrays; icons for video/pdf/scan
- Toast notifications
- All tabs visible in header (including "Manage Content")

---

## Config (`config/cms.php`)
- Watermark defaults: clinic name, doctor name, date, corner position
- Storage paths: `/storage/originals/`, `/storage/watermarked/`
- Stage labels: `before`, `during`, `after`, `followup`
- Default education categories list

---

## Seeders

### `EducationCategorySeeder`
Seeds default categories: Restorative, Implantology, Endodontics, Periodontics, Orthodontics, Oral Surgery, Preventive Dentistry.

---

## Integration Points (existing modules)

### How to wire into Consultation controller
Add 2 lines inside `ConsultationController::store()` after saving photos:
```php
use App\Services\ContentManagement\ClinicalMediaUploadService;

// Inside store(), after $consultation->save():
$uploadService = app(ClinicalMediaUploadService::class);
$uploadService->store([
    'patient_id'       => $consultation->patient_id,
    'consultation_id'  => $consultation->id,
    'treatment_name'   => $request->input('treatment_name'),
    'tooth_no'         => $request->input('tooth_area'),
    'treatment_stage'  => 'before',
    'media_type'       => 'photo',
], $uploadedFile);
```

### How to wire into Visit controller
Same pattern — `visit_id` instead of `consultation_id`.

---

## Completed Sessions

| Session | Status | What was built |
|---|---|---|
| Session 1 | ✅ Done, in app | Routes, CmsController, index.blade.php, migrations, sidebar link |
| Session 2 | ✅ Done, in app | `sharedViewData()` refactor, route nesting fix, `$doctors`/`$patients`/`$stats` all passed |
| Session 3 | ✅ Done, in app | Education tab, EducationContentController, education.blade.php, education-manage.blade.php, EducationCategory/Treatment/Media models, upload system |
| Lightbox fix | ✅ Done | Image thumbnail previews + lightbox modal for education-manage |
| Edit fix | ✅ Done | Edit category + treatment modals working |
| Manage tab | ✅ Done | "Manage Content" tab visible on both index and education views |

---

## What Is Still Pending / Known Issues

| Item | Status | Notes |
|---|---|---|
| Real data from consultations | ⏳ Not wired | Integration guide exists — 2 lines per controller |
| ClinicalMediaUploadService actual file processing | ⏳ Stub only | Needs full implementation |
| WatermarkService actual image processing | ⏳ Stub / graceful fallback | Requires `intervention/image` package |
| Marketing tab | ⏳ Visual only | Tag-as-marketing routes exist, blade needs wiring |
| Case viewer AJAX data | ⏳ Visual only | `caseViewer()` route exists, response needs real query |
| Search AJAX results | ⏳ Visual only | `search()` route exists, query logic needs implementation |
| Export modal | ⏳ Not built | Route registered, controller method missing |
| Patient profile shortcut | ⏳ Not wired | Route exists at `/content-management/patient/{id}` |
| `php artisan storage:link` | ✅ Required once | For public disk access |
| `intervention/image` install | Optional | `composer require intervention/image` for watermarking |

---

## Deployment Checklist

```bash
# 1. Run migrations
php artisan migrate

# 2. Seed education categories
php artisan db:seed --class=EducationCategorySeeder

# 3. Link storage
php artisan storage:link

# 4. (Optional) Install watermark library
composer require intervention/image

# 5. Register route in bootstrap/app.php
# Already done in Session 1

# 6. Sidebar link
# Already added in Session 1
```

---

## File Locations Reference

```
routes/
  cms.php

app/Http/Controllers/ContentManagement/
  CmsController.php
  CmsSearchController.php
  EducationContentController.php

app/Models/
  ClinicalMedia.php
  EducationCategory.php
  EducationTreatment.php
  EducationMedia.php

app/Services/ContentManagement/
  ClinicalMediaUploadService.php
  WatermarkService.php
  CmsSearchService.php
  TimelineService.php

database/migrations/
  xxxx_create_clinical_media_table.php
  xxxx_create_education_categories_table.php
  xxxx_create_education_treatments_table.php
  xxxx_create_education_media_table.php

database/seeders/
  EducationCategorySeeder.php

resources/views/content-management/
  index.blade.php
  education.blade.php
  education-manage.blade.php

config/
  cms.php
```
