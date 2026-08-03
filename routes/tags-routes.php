<?php

use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // Settings → Tag management — gated like every other settings surface.
    // (Variants hardening 2026-08-03: was auth-only, letting any logged-in
    // user create/rename/delete the clinic's tag vocabulary.)
    Route::prefix('settings/tags')->name('settings.tags.')->middleware('module:settings')->group(function () {
        Route::get('/',                         [TagController::class, 'index'])  ->name('index');
        Route::post('/',                        [TagController::class, 'store'])  ->name('store');
        Route::put('/{tag}',                    [TagController::class, 'update']) ->name('update');
        Route::delete('/{tag}',                 [TagController::class, 'destroy'])->name('destroy');
    });

    // Patient → Tag attach/detach routes REMOVED from this file
    // (Variants hardening 2026-08-03). They duplicated the properly-gated
    // patients.tags.* routes in routes/web.php (module:patients / ,edit).
    // Because this file loads AFTER web.php (bootstrap/app.php `then:`),
    // Laravel's RouteCollection replaced the gated registrations with these
    // auth-only ones — an authorization bypass. The canonical, gated routes
    // in routes/web.php (lines ~178-180) are the single implementation.

});
