<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Communication\PrmController;

Route::middleware(['web', 'auth', 'module:prm'])->prefix('communication/prm')->name('prm.')->group(function () {

    // Pages
    Route::get('/',                    [PrmController::class, 'index'])->name('index');
    Route::get('/board',               [PrmController::class, 'board'])->name('board');
    Route::get('/add-lead',            [PrmController::class, 'addLead'])->name('add-lead');
    Route::post('/add-lead',           [PrmController::class, 'storeLead'])->name('store-lead');
    Route::get('/settings',            [PrmController::class, 'settings'])->name('settings');

    // Phase 4d — Things-to-do inbox
    Route::get('/inbox', [PrmController::class, 'inbox'])->name('inbox');

    // Phase 6 — Website chatbot preview + install snippet
    Route::get('/chatbot', [PrmController::class, 'chatbotPreview'])->name('chatbot');

    // Phase 5 — Reports
    Route::get('/reports/team',        [PrmController::class, 'teamPerformance'])->name('reports.team');
    Route::get('/reports/channel-roi', [PrmController::class, 'channelRoi'])->name('reports.channel-roi');

    // Phase 3 — Lead source tracking
    Route::get('/source-analytics',    [PrmController::class, 'sourceAnalytics'])->name('source-analytics');
    Route::get('/quick-add',           [PrmController::class, 'quickAdd'])->name('quick-add');
    Route::post('/quick-add',          [PrmController::class, 'storeQuickLead'])->name('store-quick-lead');

    // Lead detail & edit
    Route::get('/lead/{id}',       [PrmController::class, 'leadDetail'])->name('lead-detail');
    Route::get('/lead/{id}/edit',  [PrmController::class, 'editLead'])->name('edit-lead');
    Route::post('/lead/{id}/edit', [PrmController::class, 'updateLead'])->name('update-lead');

    // AJAX actions
    Route::post('/lead/{id}/move',     [PrmController::class, 'moveStage'])->name('move-stage');
    Route::post('/lead/{id}/activity', [PrmController::class, 'logActivity'])->name('log-activity');
    Route::post('/lead/{id}/convert',  [PrmController::class, 'convertToPatient'])->name('convert');

    // AI enrichment (Phase 1) — re-run the local AI on a single lead.
    Route::post('/lead/{id}/enrich',   [PrmController::class, 'reEnrich'])->name('enrich');

    // AI draft replies (Phase 3)
    Route::post('/lead/{id}/draft-reply', [PrmController::class, 'draftReply'])->name('draft-reply');
    Route::post('/lead/{id}/log-reply',   [PrmController::class, 'logReply'])->name('log-reply');

});
