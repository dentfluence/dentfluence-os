<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientNoteController;
use App\Http\Controllers\AppointmentController;

Route::get('/', fn() => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth')->group(function () {

    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Patients
    Route::get('patients/search', [PatientController::class, 'search'])->name('patients.search');
    Route::resource('patients', PatientController::class);
    Route::post('patients/{patient}/notes',              [PatientNoteController::class, 'store'])->name('patient-notes.store');
    Route::delete('patients/{patient}/notes/{note}',     [PatientNoteController::class, 'destroy'])->name('patient-notes.destroy');

    // Appointments
    Route::get('appointments/today',                     [AppointmentController::class, 'today'])->name('appointments.today');
    Route::patch('appointments/{appointment}/status',    [AppointmentController::class, 'updateStatus'])->name('appointments.updateStatus');
    Route::resource('appointments', AppointmentController::class);
    // routes/web.php
Route::get('/treatment-categories', [TreatmentCategoryController::class, 'index']);
Route::get('/treatment-categories/{category}/treatments', [TreatmentCategoryController::class, 'treatments']);
Route::post('/appointments', [AppointmentController::class, 'store']);

    // Coming soon stubs
    Route::get('/treatments',     fn() => 'Coming soon')->name('treatments.index');
    Route::get('/billing',        fn() => 'Coming soon')->name('billing.index');
    Route::get('/billing/create', fn() => 'Coming soon')->name('billing.create');
    Route::get('/inventory',      fn() => 'Coming soon')->name('inventory.index');
    Route::get('/lab',            fn() => 'Coming soon')->name('lab.index');
    Route::get('/lab/create',     fn() => 'Coming soon')->name('lab.create');
    Route::get('/tasks',          fn() => 'Coming soon')->name('tasks.index');
    Route::get('/tasks/create',   fn() => 'Coming soon')->name('tasks.create');
    Route::get('/crm',            fn() => 'Coming soon')->name('crm.index');
    Route::get('/communication',  fn() => 'Coming soon')->name('communication.index');
    Route::get('/huddle',         fn() => 'Coming soon')->name('huddle.index');
    Route::get('/reports',        fn() => 'Coming soon')->name('reports.index');
    Route::get('/analytics',      fn() => 'Coming soon')->name('analytics.index');
    Route::get('/settings',       fn() => 'Coming soon')->name('settings.index');
    Route::get('/notifications',  fn() => 'Coming soon')->name('notifications.index');
    Route::get('/profile/edit',   fn() => 'Coming soon')->name('profile.edit');
    Route::get('/help',           fn() => 'Coming soon')->name('help.index');

});