<?php

use App\Http\Controllers\Communication\TimelineController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('communication')->name('communication.')->group(function () {

    // Communication Timeline — patient/lead list
    Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

    // Per-person communication timeline
    Route::get('/timeline/{personId}', [TimelineController::class, 'show'])->name('timeline.show');
});
