<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Huddle\Controllers\HuddleController;
use App\Modules\Huddle\Controllers\HuddleTaskController;
use App\Modules\Huddle\Controllers\HuddleCommentController;
use App\Modules\Huddle\Controllers\HuddleSettingsController;
use App\Modules\Huddle\Controllers\HuddleCloseController;

Route::middleware(['auth', 'web', 'module:daily_huddle'])->prefix('huddle')->name('huddle.')->group(function () {

    // ── Board ────────────────────────────────────────────────────────────────
    // Existing routes — kept exactly as-is, now registered here instead of web.php
    Route::get('/', [HuddleController::class, 'index'])
        ->name('index');

    Route::get('/accountability', [HuddleController::class, 'accountability'])
        ->name('accountability');

    // Period-driven performance report (Weekly / Monthly / Quarterly / Annual tabs)
    Route::get('/report', [HuddleController::class, 'report'])
        ->name('report');

    Route::patch('/appointments/{id}/instruction', [HuddleController::class, 'updateInstruction'])
        ->name('appointments.instruction')->middleware('module:daily_huddle,edit');

    // Push selected huddle comms items to the FollowUp queue
    Route::post('/comms/push', [HuddleController::class, 'pushToCommList'])
        ->name('comms.push')->middleware('module:daily_huddle,edit');

    // Yesterday's Flow quick-action card — logs a task and/or a follow-up call
    // for a patient instead of navigating straight to their profile.
    Route::post('/yesterday-flow/log', [HuddleController::class, 'logYesterdayFollowUp'])
        ->name('yesterday-flow.log')->middleware('module:daily_huddle,edit');

    // ── "Huddle done" tick + huddle log ──────────────────────────────────────
    // The tick records that the meeting actually happened and freezes the
    // briefing; the log lists every working day and which ones were missed.
    // Only today can be ticked — see HuddleCloseService.
    Route::post('/close', [HuddleCloseController::class, 'close'])
        ->name('close')->middleware('module:daily_huddle,edit');

    Route::delete('/close', [HuddleCloseController::class, 'reopen'])
        ->name('close.reopen')->middleware('module:daily_huddle,edit');

    Route::get('/history', [HuddleCloseController::class, 'history'])
        ->name('history');

    Route::get('/history/{date}', [HuddleCloseController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('history.show');

    // Huddle notes — wins / lows / failures / concerns.
    // `failures` is the report path for equipment or process breakdowns.
    Route::post('/notes', [HuddleController::class, 'storeNote'])
        ->middleware('module:daily_huddle,edit')
        ->name('notes.store');

    // ── Tasks ────────────────────────────────────────────────────────────────
    Route::prefix('tasks')->name('tasks.')->group(function () {

        Route::get('/', [HuddleTaskController::class, 'index'])
            ->name('index');

        Route::post('/', [HuddleTaskController::class, 'store'])
            ->name('store')->middleware('module:daily_huddle,edit');

        Route::patch('/{taskId}/status', [HuddleTaskController::class, 'updateStatus'])
            ->name('status')->middleware('module:daily_huddle,edit');

        Route::patch('/{taskId}/assign', [HuddleTaskController::class, 'assign'])
            ->name('assign')->middleware('module:daily_huddle,edit');

        Route::post('/{taskId}/proof', [HuddleTaskController::class, 'uploadProof'])
            ->name('proof')->middleware('module:daily_huddle,edit');

        Route::post('/{taskId}/carry-forward', [HuddleTaskController::class, 'carryForward'])
            ->name('carry-forward')->middleware('module:daily_huddle,edit');
    });

    // ── Comments ─────────────────────────────────────────────────────────────
    Route::prefix('comments')->name('comments.')->group(function () {

        Route::get('/', [HuddleCommentController::class, 'index'])
            ->name('index');

        Route::post('/', [HuddleCommentController::class, 'store'])
            ->name('store')->middleware('module:daily_huddle,edit');

        Route::patch('/{commentId}/resolve', [HuddleCommentController::class, 'resolve'])
            ->name('resolve')->middleware('module:daily_huddle,edit');

        Route::delete('/{commentId}', [HuddleCommentController::class, 'destroy'])
            ->name('destroy')->middleware('module:daily_huddle,delete');
    });

    // ── Settings ─────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {

        // GET redirects to unified Settings module; PATCH remains for API use
        Route::get('/', fn() => redirect()->route('settings.index', ['tab' => 'huddle']))
            ->name('index');

        Route::patch('/', [HuddleSettingsController::class, 'update'])
            ->name('update')->middleware('module:daily_huddle,edit');
    });
});