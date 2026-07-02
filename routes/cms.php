<?php

use App\Http\Controllers\ContentManagement\ClinicalLibraryController;
use App\Http\Controllers\ContentManagement\CmsController;
use App\Http\Controllers\ContentManagement\CmsSearchController;
use App\Http\Controllers\ContentManagement\EducationContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Content Management / Clinical Library Routes
|--------------------------------------------------------------------------
| Register in bootstrap/app.php via withRouting() callback.
| NEVER add these to routes/web.php.
|
| Add this to bootstrap/app.php inside withRouting():
|   then: function () {
|       Route::middleware('web')->group(base_path('routes/cms.php'));
|   }
*/

// ── Clinical Library Dashboard (standalone workspace) ──────────────────────
Route::middleware(['auth', 'module:cms'])->prefix('clinical-library')->name('cms.')->group(function () {
    Route::get('/', [ClinicalLibraryController::class, 'dashboard'])->name('dashboard');

    // ── Upload clinical files ──────────────────────────────────────────────
    Route::post('/upload', [ClinicalLibraryController::class, 'store'])->name('files.store');

    // ── Marketing approval actions (Phase 9) ──
    // PUT /clinical-library/files/{file}/approve
    // PUT /clinical-library/files/{file}/reject
    Route::put('/files/{file}/approve', [ClinicalLibraryController::class, 'approveFile'])->name('files.approve');
    Route::put('/files/{file}/reject',  [ClinicalLibraryController::class, 'rejectFile'])->name('files.reject');
});

Route::middleware(['auth'])->prefix('content-management')->name('cms.')->group(function () {

    // Main Clinical Library index (Patient Clinical Data tab)
    Route::get('/', [ClinicalLibraryController::class, 'index'])->name('index');

    // Generic Education Library tab
    Route::get('/education', [ClinicalLibraryController::class, 'education'])->name('education');

    // AJAX: Slide-in Case Viewer panel data
    Route::get('/case-viewer', [ClinicalLibraryController::class, 'caseViewer'])->name('case-viewer');

    // AJAX: Search autocomplete suggestions
    Route::get('/search-suggest', [ClinicalLibraryController::class, 'searchSuggest'])->name('search-suggest');

    Route::get('/education/manage', [ClinicalLibraryController::class, 'educationManage'])->name('education.manage');
    Route::post('/education/category', [EducationContentController::class, 'storeCategory'])->name('education.category.store');
    Route::delete('/education/category/{category}', [EducationContentController::class, 'destroyCategory'])->name('education.category.destroy');
    Route::post('/education/treatment', [EducationContentController::class, 'storeTreatment'])->name('education.treatment.store');
    Route::delete('/education/treatment/{treatment}', [EducationContentController::class, 'destroyTreatment'])->name('education.treatment.destroy');
    Route::post('/education/treatment/{treatment}/upload', [EducationContentController::class, 'uploadMedia'])->name('education.media.upload');
    Route::delete('/education/media/{media}', [EducationContentController::class, 'destroyMedia'])->name('education.media.destroy');

    Route::put('/education/category/{category}/update', [EducationContentController::class, 'updateCategory'])->name('education.category.update');
    Route::put('/education/treatment/{treatment}/update', [EducationContentController::class, 'updateTreatment'])->name('education.treatment.update');

    // ── Routes merged from routes/content-management.php (consolidated Phase 0) ──

    // Tab views
    Route::get('/clinical',                [CmsController::class, 'clinical'])->name('clinical');
    Route::get('/marketing',               [CmsController::class, 'marketing'])->name('marketing');

    // Patient shortcut from profile
    Route::get('/patient/{patientId}',     [CmsController::class, 'patientView'])->name('patient');

    // Search + filter (AJAX)
    Route::get('/search',                  [CmsSearchController::class, 'search'])->name('search');

    // Case viewer (AJAX slide-in)
    Route::get('/case/{id}',               [CmsSearchController::class, 'caseViewer'])->name('case');

    // Marketing tagging
    Route::post('/tag-marketing',          [CmsSearchController::class, 'tagMarketing'])->name('tag.marketing');
    Route::delete('/tag-marketing/{id}',   [CmsSearchController::class, 'removeMarketingTag'])->name('tag.marketing.remove');

    // Watermark settings
    Route::post('/watermark-settings',     [CmsController::class, 'saveWatermarkSettings'])->name('watermark.save');
});
