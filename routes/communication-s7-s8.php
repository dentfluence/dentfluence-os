<?php

use App\Http\Controllers\Communication\OpportunityController;
use App\Http\Controllers\Communication\HuddleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session 7 — Opportunity Engine Routes
| Session 8 — Daily Huddle Integration Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('communication')->name('communication.')->group(function () {

    // ── Session 7: Opportunity Engine ────────────────────────────────────
    Route::prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/',                         [OpportunityController::class, 'index'])->name('index');
        Route::get('/{id}',                     [OpportunityController::class, 'show'])->name('show');
        Route::post('/',                        [OpportunityController::class, 'store'])->name('store');
        Route::patch('/{id}/stage',             [OpportunityController::class, 'updateStage'])->name('update-stage');
        Route::post('/{id}/convert-to-lead',    [OpportunityController::class, 'convertToLead'])->name('convert-to-lead');
    });

    // ── Session 8: Daily Huddle Integration ──────────────────────────────
    Route::prefix('huddle')->name('huddle.')->group(function () {
        Route::get('/',                         [HuddleController::class, 'widgets'])->name('index');
        Route::get('/overdue-summary',          [HuddleController::class, 'overdueSummary'])->name('overdue-summary');
        Route::get('/communication-alerts',     [HuddleController::class, 'communicationAlerts'])->name('communication-alerts');
        Route::get('/counts',                   [HuddleController::class, 'countsJson'])->name('counts');
    });

});
