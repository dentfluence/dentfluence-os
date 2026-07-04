<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Modules\Huddle\Controllers\HuddleController;
use App\Modules\Huddle\Controllers\HuddleTaskController;
use App\Modules\Huddle\Controllers\HuddleCommentController;
use App\Modules\Huddle\Controllers\HuddleSettingsController;

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
        ->name('appointments.instruction');

    // Push selected huddle comms items to the FollowUp queue
    Route::post('/comms/push', [HuddleController::class, 'pushToCommList'])
        ->name('comms.push');

    // Yesterday's Flow quick-action card — logs a task and/or a follow-up call
    // for a patient instead of navigating straight to their profile.
    Route::post('/yesterday-flow/log', [HuddleController::class, 'logYesterdayFollowUp'])
        ->name('yesterday-flow.log');

    Route::post('/notes', [HuddleController::class, 'storeNote'])
        ->name('notes.store');

    // ── Tasks ────────────────────────────────────────────────────────────────
    Route::prefix('tasks')->name('tasks.')->group(function () {

        Route::get('/', [HuddleTaskController::class, 'index'])
            ->name('index');

        Route::post('/', [HuddleTaskController::class, 'store'])
            ->name('store');

        Route::patch('/{taskId}/status', [HuddleTaskController::class, 'updateStatus'])
            ->name('status');

        Route::patch('/{taskId}/assign', [HuddleTaskController::class, 'assign'])
            ->name('assign');

        Route::post('/{taskId}/proof', [HuddleTaskController::class, 'uploadProof'])
            ->name('proof');

        Route::post('/{taskId}/carry-forward', [HuddleTaskController::class, 'carryForward'])
            ->name('carry-forward');
    });

    // ── Comments ─────────────────────────────────────────────────────────────
    Route::prefix('comments')->name('comments.')->group(function () {

        Route::get('/', [HuddleCommentController::class, 'index'])
            ->name('index');

        Route::post('/', [HuddleCommentController::class, 'store'])
            ->name('store');

        Route::patch('/{commentId}/resolve', [HuddleCommentController::class, 'resolve'])
            ->name('resolve');

        Route::delete('/{commentId}', [HuddleCommentController::class, 'destroy'])
            ->name('destroy');
    });

    // ── Settings ─────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {

        // GET redirects to unified Settings module; PATCH remains for API use
        Route::get('/', fn() => redirect()->route('settings.index', ['tab' => 'huddle']))
            ->name('index');

        Route::patch('/', [HuddleSettingsController::class, 'update'])
            ->name('update');
    });
});