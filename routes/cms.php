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
    Route::post('/upload', [ClinicalLibraryController::class, 'store'])->name('files.store')->middleware('module:cms,edit');

    // ── Universal search (P2) ──────────────────────────────────────────────
    // One endpoint behind both the dashboard search drawer and the Content
    // Manager filter bar. See ClinicalLibrarySearchService.
    Route::get('/search', [ClinicalLibraryController::class, 'search'])->name('library-search');

    // Re-apply the current watermark settings to files already in the library.
    Route::post('/restamp', [ClinicalLibraryController::class, 'restamp'])->name('files.restamp')->middleware('module:cms,edit');

    // ── Marketing approval actions (Phase 9) ──
    // PUT /clinical-library/files/{file}/approve
    // PUT /clinical-library/files/{file}/reject
    Route::put('/files/{file}/approve', [ClinicalLibraryController::class, 'approveFile'])->name('files.approve')->middleware('module:cms,edit');
    Route::put('/files/{file}/reject',  [ClinicalLibraryController::class, 'rejectFile'])->name('files.reject')->middleware('module:cms,edit');
});

Route::middleware(['auth', 'module:cms'])->prefix('content-management')->name('cms.')->group(function () {

    // Main Clinical Library index (Patient Clinical Data tab)
    Route::get('/', [ClinicalLibraryController::class, 'index'])->name('index');

    // Generic Education Library tab
    Route::get('/education', [ClinicalLibraryController::class, 'education'])->name('education');

    // AJAX: Slide-in Case Viewer panel data
    Route::get('/case-viewer', [ClinicalLibraryController::class, 'caseViewer'])->name('case-viewer');

    // AJAX: Search autocomplete suggestions
    Route::get('/search-suggest', [ClinicalLibraryController::class, 'searchSuggest'])->name('search-suggest');

    Route::get('/education/manage', [ClinicalLibraryController::class, 'educationManage'])->name('education.manage');
    Route::post('/education/category', [EducationContentController::class, 'storeCategory'])->name('education.category.store')->middleware('module:cms,edit');
    Route::delete('/education/category/{category}', [EducationContentController::class, 'destroyCategory'])->name('education.category.destroy')->middleware('module:cms,delete');
    Route::post('/education/treatment', [EducationContentController::class, 'storeTreatment'])->name('education.treatment.store')->middleware('module:cms,edit');
    Route::delete('/education/treatment/{treatment}', [EducationContentController::class, 'destroyTreatment'])->name('education.treatment.destroy')->middleware('module:cms,delete');
    Route::post('/education/treatment/{treatment}/upload', [EducationContentController::class, 'uploadMedia'])->name('education.media.upload')->middleware('module:cms,edit');
    Route::delete('/education/media/{media}', [EducationContentController::class, 'destroyMedia'])->name('education.media.destroy')->middleware('module:cms,delete');

    Route::put('/education/category/{category}/update', [EducationContentController::class, 'updateCategory'])->name('education.category.update')->middleware('module:cms,edit');
    Route::put('/education/treatment/{treatment}/update', [EducationContentController::class, 'updateTreatment'])->name('education.treatment.update')->middleware('module:cms,edit');

    // ── Routes merged from routes/content-management.php (consolidated Phase 0) ──

    // ── Legacy tab URLs (P4) ───────────────────────────────────────────────
    // These used to render content-management/index.blade with the pre-P3
    // variable shape and would now fail on it. Kept alive as redirects rather
    // than deleted: a bookmark or an old link should land on the right tab, not
    // on a 404 and not on an error.
    Route::get('/clinical',  fn () => redirect()->route('cms.index', ['tab' => 'case-library']))->name('clinical');
    Route::get('/marketing', fn () => redirect()->route('cms.index', ['tab' => 'marketing']))->name('marketing');

    // A patient's files live on their profile now, not on a Content Manager tab.
    Route::get('/patient/{patientId}', fn ($patientId) => redirect()->to(route('patients.show', $patientId) . '#documents'))->name('patient');

    // Search + filter (AJAX)
    Route::get('/search',                  [CmsSearchController::class, 'search'])->name('search');

    // Case viewer (AJAX slide-in)
    Route::get('/case/{id}',               [CmsSearchController::class, 'caseViewer'])->name('case');

    // Marketing tagging
    Route::post('/tag-marketing',          [CmsSearchController::class, 'tagMarketing'])->name('tag.marketing')->middleware('module:cms,edit');
    Route::delete('/tag-marketing/{id}',   [CmsSearchController::class, 'removeMarketingTag'])->name('tag.marketing.remove')->middleware('module:cms,delete');

    // Watermark settings
    Route::post('/watermark-settings',     [CmsController::class, 'saveWatermarkSettings'])->name('watermark.save')->middleware('module:cms,edit');
});
