<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Communication\PrmController;

Route::prefix('communication/prm')->name('prm.')->group(function () {

    Route::get('/', [PrmController::class, 'index'])->name('index');
    Route::get('/add-lead', [PrmController::class, 'addLead'])->name('add-lead');
    Route::post('/add-lead', [PrmController::class, 'storeLead'])->name('store-lead');
    Route::get('/lead/{id}', [PrmController::class, 'leadDetail'])->name('lead-detail');
    Route::get('/lead/{id}/edit', [PrmController::class, 'editLead'])->name('edit-lead');
    Route::post('/lead/{id}/edit', [PrmController::class, 'updateLead'])->name('update-lead');

});
