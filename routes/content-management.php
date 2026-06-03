<?php

use App\Http\Controllers\ContentManagement\CmsController;
use App\Http\Controllers\ContentManagement\CmsSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('content-management')->name('cms.')->group(function () {

    // Main index — defaults to clinical tab
    Route::get('/',                        [CmsController::class, 'index'])->name('index');

    // Tab views
    Route::get('/clinical',                [CmsController::class, 'clinical'])->name('clinical');
    Route::get('/education',               [CmsController::class, 'education'])->name('education');
    Route::get('/marketing',               [CmsController::class, 'marketing'])->name('marketing');

    // Search + filter (AJAX)
    Route::get('/search',                  [CmsSearchController::class, 'search'])->name('search');

    // Case viewer (AJAX slide-in)
    Route::get('/case/{id}',               [CmsSearchController::class, 'caseViewer'])->name('case');

    // Patient shortcut from profile
    Route::get('/patient/{patientId}',     [CmsController::class, 'patientView'])->name('patient');

    // Tag as marketing
    Route::post('/tag-marketing',          [CmsSearchController::class, 'tagMarketing'])->name('tag.marketing');
    Route::delete('/tag-marketing/{id}',   [CmsSearchController::class, 'removeMarketingTag'])->name('tag.marketing.remove');

    // Watermark settings save
    Route::post('/watermark-settings',     [CmsController::class, 'saveWatermarkSettings'])->name('watermark.save');
});
