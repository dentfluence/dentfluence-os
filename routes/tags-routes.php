<?php

use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // Settings → Tag management
    Route::prefix('settings/tags')->name('settings.tags.')->group(function () {
        Route::get('/',                         [TagController::class, 'index'])  ->name('index');
        Route::post('/',                        [TagController::class, 'store'])  ->name('store');
        Route::put('/{tag}',                    [TagController::class, 'update']) ->name('update');
        Route::delete('/{tag}',                 [TagController::class, 'destroy'])->name('destroy');
    });

    // Patient → Tag attach/detach (called from patient profile via Alpine.js fetch)
    Route::prefix('patients/{patient}/tags')->name('patients.tags.')->group(function () {
        Route::get('/',          [TagController::class, 'forPatient'])->name('index');
        Route::post('/attach',   [TagController::class, 'attach'])   ->name('attach');
        Route::delete('/{tag}',  [TagController::class, 'detach'])   ->name('detach');
    });

});
