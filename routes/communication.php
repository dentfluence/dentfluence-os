<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Communication\DashboardController;
use App\Http\Controllers\Communication\CommunicationController;

/*
|--------------------------------------------------------------------------
| Communication OS — Routes
| Dentfluence · Session 1: Foundation
|--------------------------------------------------------------------------
|
| All routes are prefixed /communication and named communication.*
| Middleware: auth + communication.access (defined in Session 1)
|
| Controllers for Sessions 2-8 are registered here as stubs now
| and filled out in their respective sessions.
|
*/

Route::prefix('communication')
    ->name('communication.')
    ->middleware(['web', 'auth', 'communication.access'])
    ->group(function () {

        // ── Module Home ──────────────────────────────────────────────────
        Route::get('/', [DashboardController::class, 'index'])
            ->name('index');

        // ── Communication Manager (Session 2) ───────────────────────────
        Route::prefix('manager')->name('manager.')->group(function () {
            Route::get('/',        [CommunicationController::class, 'index'])->name('index');
            Route::get('/queue',   [CommunicationController::class, 'queue'])->name('queue');
            Route::get('/overdue', [CommunicationController::class, 'overdue'])->name('overdue');
            Route::get('/log',     [CommunicationController::class, 'logForm'])->name('log.form');
            Route::post('/log',    [CommunicationController::class, 'logStore'])->name('log.store');
        });

        // ── PRM Pipeline (Session 3) ─────────────────────────────────────
        Route::prefix('prm')->name('prm.')->group(function () {
            Route::get('/',                  [\App\Http\Controllers\Communication\PrmController::class, 'index'])->name('index');
            Route::get('/board',             [\App\Http\Controllers\Communication\PrmController::class, 'board'])->name('board');
            Route::get('/add-lead',          [\App\Http\Controllers\Communication\PrmController::class, 'addLead'])->name('add-lead');
            Route::post('/add-lead',         [\App\Http\Controllers\Communication\PrmController::class, 'storeLead'])->name('store-lead');
            Route::get('/lead/{id}',         [\App\Http\Controllers\Communication\PrmController::class, 'leadDetail'])->name('lead-detail');
            Route::get('/lead/{id}/edit',    [\App\Http\Controllers\Communication\PrmController::class, 'editLead'])->name('edit-lead');
            Route::post('/lead/{id}/edit',   [\App\Http\Controllers\Communication\PrmController::class, 'updateLead'])->name('update-lead');
            Route::patch('/lead/{id}/stage', [\App\Http\Controllers\Communication\PrmController::class, 'moveStage'])->name('move_stage');
        });

        // ── Follow-up Engine (Session 4) ─────────────────────────────────
        Route::prefix('followup')->name('followup.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Communication\FollowUpController::class, 'index'])->name('index');
            Route::get('/queue',                     [\App\Http\Controllers\Communication\FollowUpController::class, 'queue'])->name('queue');
            Route::get('/overdue',                   [\App\Http\Controllers\Communication\FollowUpController::class, 'overdue'])->name('overdue');
            Route::get('/calendar',                  [\App\Http\Controllers\Communication\FollowUpController::class, 'calendar'])->name('calendar');
            Route::get('/recalls',                   [\App\Http\Controllers\Communication\FollowUpController::class, 'recalls'])->name('recalls');
            Route::post('/schedule',                 [\App\Http\Controllers\Communication\FollowUpController::class, 'schedule'])->name('schedule');
            Route::post('/{id}/complete',            [\App\Http\Controllers\Communication\FollowUpController::class, 'complete'])->name('complete');
            Route::post('/{id}/reschedule',          [\App\Http\Controllers\Communication\FollowUpController::class, 'reschedule'])->name('reschedule');
            Route::post('/{id}/note',                [\App\Http\Controllers\Communication\FollowUpController::class, 'addNote'])->name('note');
            Route::post('/{id}/change-status',       [\App\Http\Controllers\Communication\FollowUpController::class, 'changeStatus'])->name('change-status');
            Route::post('/{id}/convert',             [\App\Http\Controllers\Communication\FollowUpController::class, 'convertToPatient'])->name('convert');
            Route::post('/{id}/create-case',         [\App\Http\Controllers\Communication\FollowUpController::class, 'createCase'])->name('create-case');
        });

        // ── Communication Timeline (Session 5) ───────────────────────────
        Route::prefix('timeline')->name('timeline.')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Communication\TimelineController::class, 'index'])->name('index');
            Route::get('/patient/{id}',   [\App\Http\Controllers\Communication\TimelineController::class, 'patient'])->name('patient');
        });

        

        // ── Opportunity Engine (Session 7) ───────────────────────────────
        Route::prefix('opportunities')->name('opportunities.')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Communication\OpportunityController::class, 'index'])->name('index');
            Route::get('/board',   [\App\Http\Controllers\Communication\OpportunityController::class, 'board'])->name('board');
            Route::get('/{id}',    [\App\Http\Controllers\Communication\OpportunityController::class, 'detail'])->name('detail');
            Route::post('/',       [\App\Http\Controllers\Communication\OpportunityController::class, 'store'])->name('store');
        });

        // ── Daily Huddle Widgets (Session 8) ─────────────────────────────
        Route::prefix('huddle')->name('huddle.')->group(function () {
            Route::get('/widgets',  [\App\Http\Controllers\Communication\HuddleController::class, 'widgets'])->name('widgets');
            Route::get('/overdue',  [\App\Http\Controllers\Communication\HuddleController::class, 'overdueSummary'])->name('overdue');
            Route::get('/alerts',   [\App\Http\Controllers\Communication\HuddleController::class, 'alerts'])->name('alerts');
        });

        // ── Templates (Session 12) ────────────────────────────────────────
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/',     [\App\Http\Controllers\Communication\TemplateController::class, 'index'])->name('index');
            Route::get('/{id}', [\App\Http\Controllers\Communication\TemplateController::class, 'edit'])->name('edit');
        });
    });
