<?php

use App\Modules\Hq\Controllers\ClinicController;
use App\Modules\Hq\Controllers\DashboardController;
use App\Modules\Hq\Controllers\PlanController;
use App\Modules\Hq\Controllers\SubscriptionController;
use App\Modules\Hq\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// Loaded from bootstrap/app.php inside Route::middleware('web')->group(...),
// so 'web' is already applied — only auth + the superadmin gate here.
Route::prefix('hq')
    ->name('hq.')
    ->middleware(['auth', 'superadmin'])
    ->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('clinics', [ClinicController::class, 'index'])->name('clinics.index');
        Route::post('clinics', [ClinicController::class, 'store'])->name('clinics.store');
        Route::get('clinics/{clinic}', [ClinicController::class, 'show'])->name('clinics.show');
        Route::patch('clinics/{clinic}', [ClinicController::class, 'update'])->name('clinics.update');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');

        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::patch('plans/{plan}/toggle', [PlanController::class, 'toggle'])->name('plans.toggle');

        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    });
