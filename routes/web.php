<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\CRMController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;

Route::get('/', function () {
    return view('dashboard.index');
});

Route::get('/dashboard', function () {
    return view('dashboard.index');
});

Route::get('/login', function () {
    return view('auth.login');
});

Route::get('/patients/create', function () {
    return view('patients.create');
});

Route::get('/appointments', [AppointmentController::class, 'index']);
Route::get('/billing', [BillingController::class, 'index']);
Route::get('/inventory', [InventoryController::class, 'index']);
Route::get('/lab', [LabController::class, 'index']);
Route::get('/crm', [CRMController::class, 'index']);
Route::get('/reports', [ReportsController::class, 'index']);
Route::get('/settings', [SettingsController::class, 'index']);