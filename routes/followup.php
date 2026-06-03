<?php

use App\Http\Controllers\Communication\FollowUpController;
use Illuminate\Support\Facades\Route;

/**
 * Follow-up Engine Routes
 * Prefix: /communication/followup-engine
 * Name prefix: communication.followup.
 *
 * HOW TO REGISTER THIS FILE:
 * In bootstrap/app.php inside withRouting() callback, add:
 *   require base_path('routes/followup.php');
 *
 * OR if you have a routes/communication.php that includes sub-modules,
 * add at the bottom of routes/communication.php:
 *   require __DIR__ . '/followup.php';
 */

Route::middleware(['auth'])->prefix('communication/followup-engine')->name('communication.followup.')->group(function () {

    // Main calendar view
    Route::get('/',           [FollowUpController::class, 'index'])->name('index');

    // List views
    Route::get('/queue',      [FollowUpController::class, 'queue'])->name('queue');
    Route::get('/overdue',    [FollowUpController::class, 'overdue'])->name('overdue');

    // Follow-up actions (modal POSTs)
    Route::post('/{id}/complete',       [FollowUpController::class, 'complete'])->name('complete');
    Route::post('/{id}/reschedule',     [FollowUpController::class, 'reschedule'])->name('reschedule');
    Route::post('/{id}/note',           [FollowUpController::class, 'addNote'])->name('note');
    Route::post('/{id}/change-status',  [FollowUpController::class, 'changeStatus'])->name('change-status');
    Route::post('/{id}/convert',        [FollowUpController::class, 'convertToPatient'])->name('convert');
    Route::post('/{id}/create-case',    [FollowUpController::class, 'createCase'])->name('create-case');

    // Schedule new follow-up
    Route::post('/schedule',            [FollowUpController::class, 'schedule'])->name('schedule');

});
