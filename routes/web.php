<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Dentfluence OS — Web Routes
|--------------------------------------------------------------------------
*/

// ── Redirect root to login ──────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ── Auth routes (guests only) ───────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// ── Authenticated routes ────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::post('/logout',    [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard',  [DashboardController::class, 'index'])->name('dashboard');

    // ── Placeholder routes — real controllers built later ──
    Route::get('/patients',            fn() => 'Coming soon')->name('patients.index');
    Route::get('/patients/create',     fn() => 'Coming soon')->name('patients.create');
    Route::get('/appointments',        fn() => 'Coming soon')->name('appointments.index');
    Route::get('/appointments/create', fn() => 'Coming soon')->name('appointments.create');
    Route::get('/treatments',          fn() => 'Coming soon')->name('treatments.index');
    Route::get('/billing',             fn() => 'Coming soon')->name('billing.index');
    Route::get('/billing/create',      fn() => 'Coming soon')->name('billing.create');
    Route::get('/inventory',           fn() => 'Coming soon')->name('inventory.index');
    Route::get('/lab',                 fn() => 'Coming soon')->name('lab.index');
    Route::get('/lab/create',          fn() => 'Coming soon')->name('lab.create');
    Route::get('/tasks',               fn() => 'Coming soon')->name('tasks.index');
    Route::get('/tasks/create',        fn() => 'Coming soon')->name('tasks.create');
    Route::get('/crm',                 fn() => 'Coming soon')->name('crm.index');
    Route::get('/communication',       fn() => 'Coming soon')->name('communication.index');
    Route::get('/huddle',              fn() => 'Coming soon')->name('huddle.index');
    Route::get('/reports',             fn() => 'Coming soon')->name('reports.index');
    Route::get('/analytics',           fn() => 'Coming soon')->name('analytics.index');
    Route::get('/settings',            fn() => 'Coming soon')->name('settings.index');
    Route::get('/notifications',       fn() => 'Coming soon')->name('notifications.index');
    Route::get('/profile/edit',        fn() => 'Coming soon')->name('profile.edit');
    Route::get('/help',                fn() => 'Coming soon')->name('help.index');

});