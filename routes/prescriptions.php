<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Prescription\RxDrugController;
use App\Http\Controllers\Prescription\RxSettingsController;
use App\Http\Controllers\Prescription\PrescriptionController;

Route::middleware('auth')->group(function () {

    // ══════════════════════════════════════════════════════════════════════════
    // SETTINGS — Prescription Masters
    // Route prefix: settings/prescription
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('settings/prescription')->name('rx.settings.')->group(function () {

        // Settings index
        Route::get('/', [RxSettingsController::class, 'index'])->name('index');

        // Drug Categories
        Route::get('/categories',           [RxSettingsController::class, 'categories'])->name('categories');
        Route::post('/categories',          [RxSettingsController::class, 'categoriesStore'])->name('categories.store');
        Route::patch('/categories/{category}', [RxSettingsController::class, 'categoriesUpdate'])->name('categories.update');
        Route::delete('/categories/{category}', [RxSettingsController::class, 'categoriesDestroy'])->name('categories.destroy');

        // Generics
        Route::get('/generics',             [RxSettingsController::class, 'generics'])->name('generics');
        Route::post('/generics',            [RxSettingsController::class, 'genericsStore'])->name('generics.store');
        Route::patch('/generics/{generic}', [RxSettingsController::class, 'genericsUpdate'])->name('generics.update');
        Route::delete('/generics/{generic}',[RxSettingsController::class, 'genericsDestroy'])->name('generics.destroy');

        // Routes of Administration
        Route::get('/routes',               [RxSettingsController::class, 'routes'])->name('routes');
        Route::post('/routes',              [RxSettingsController::class, 'routesStore'])->name('routes.store');
        Route::patch('/routes/{route}',     [RxSettingsController::class, 'routesUpdate'])->name('routes.update');
        Route::delete('/routes/{route}',    [RxSettingsController::class, 'routesDestroy'])->name('routes.destroy');

        // Food Instructions
        Route::get('/food-instructions',             [RxSettingsController::class, 'foodInstructions'])->name('food-instructions');
        Route::post('/food-instructions',            [RxSettingsController::class, 'foodInstructionsStore'])->name('food-instructions.store');
        Route::patch('/food-instructions/{instruction}', [RxSettingsController::class, 'foodInstructionsUpdate'])->name('food-instructions.update');

        // Dose Templates
        Route::get('/dose-templates',                       [RxSettingsController::class, 'doseTemplates'])->name('dose-templates');
        Route::post('/dose-templates',                      [RxSettingsController::class, 'doseTemplatesStore'])->name('dose-templates.store');
        Route::patch('/dose-templates/{template}',          [RxSettingsController::class, 'doseTemplatesUpdate'])->name('dose-templates.update');
        Route::delete('/dose-templates/{template}',         [RxSettingsController::class, 'doseTemplatesDestroy'])->name('dose-templates.destroy');

        // Duration Templates
        Route::get('/duration-templates',                   [RxSettingsController::class, 'durationTemplates'])->name('duration-templates');
        Route::post('/duration-templates',                  [RxSettingsController::class, 'durationTemplatesStore'])->name('duration-templates.store');
        Route::patch('/duration-templates/{template}',      [RxSettingsController::class, 'durationTemplatesUpdate'])->name('duration-templates.update');
        Route::delete('/duration-templates/{template}',     [RxSettingsController::class, 'durationTemplatesDestroy'])->name('duration-templates.destroy');

        // Warning Rules
        Route::get('/warning-rules',        [RxSettingsController::class, 'warningRules'])->name('warning-rules');
        Route::post('/warning-rules',       [RxSettingsController::class, 'warningRulesStore'])->name('warning-rules.store');
        Route::delete('/warning-rules/{rule}', [RxSettingsController::class, 'warningRulesDestroy'])->name('warning-rules.destroy');

        // Prescription Templates
        Route::get('/prescription-templates',              [RxSettingsController::class, 'prescriptionTemplates'])->name('prescription-templates');
        Route::get('/prescription-templates/create',       [RxSettingsController::class, 'prescriptionTemplatesCreate'])->name('prescription-templates.create');
        Route::post('/prescription-templates',             [RxSettingsController::class, 'prescriptionTemplatesStore'])->name('prescription-templates.store');
        Route::delete('/prescription-templates/{template}',[RxSettingsController::class, 'prescriptionTemplatesDestroy'])->name('prescription-templates.destroy');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // DRUG MASTER  (settings/prescription/drugs)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('settings/prescription/drugs')->name('rx.drugs.')->group(function () {
        Route::get('/',           [RxDrugController::class, 'index'])->name('index');
        Route::get('/create',     [RxDrugController::class, 'create'])->name('create');
        Route::post('/',          [RxDrugController::class, 'store'])->name('store');
        Route::get('/{drug}/edit',[RxDrugController::class, 'edit'])->name('edit');
        Route::patch('/{drug}',   [RxDrugController::class, 'update'])->name('update');
        Route::delete('/{drug}',  [RxDrugController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restore', [RxDrugController::class, 'restore'])->name('restore');
        // AJAX search
        Route::get('/search/api', [RxDrugController::class, 'search'])->name('search');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // PRESCRIPTIONS  (global — all patients)
    // ══════════════════════════════════════════════════════════════════════════

    Route::get('/prescriptions', [PrescriptionController::class, 'globalIndex'])->name('prescriptions.index');

    // ══════════════════════════════════════════════════════════════════════════
    // PRESCRIPTIONS  (per-patient)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('patients/{patient}/prescriptions')->name('patients.prescriptions.')->group(function () {
        Route::get('/',                              [PrescriptionController::class, 'index'])->name('index');
        Route::get('/create',                        [PrescriptionController::class, 'create'])->name('create');
        Route::post('/',                             [PrescriptionController::class, 'store'])->name('store');
        Route::get('/{prescription}',                [PrescriptionController::class, 'show'])->name('show');
        Route::get('/{prescription}/edit',           [PrescriptionController::class, 'edit'])->name('edit');
        Route::put('/{prescription}',                [PrescriptionController::class, 'update'])->name('update');
        Route::post('/{prescription}/repeat',        [PrescriptionController::class, 'repeat'])->name('repeat');
        Route::get('/{prescription}/print',          [PrescriptionController::class, 'printView'])->name('print');
        Route::get('/{prescription}/pdf',            [PrescriptionController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{prescription}/whatsapp-send', [PrescriptionController::class, 'sendWhatsApp'])->name('whatsapp-send');
        Route::delete('/{prescription}',             [PrescriptionController::class, 'destroy'])->name('destroy');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // PRESCRIPTION API  (JSON — called from form via fetch)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('api/rx')->name('api.rx.')->group(function () {
        // Live CDSS alert check
        Route::post('/check-alerts',  [PrescriptionController::class, 'checkAlerts'])->name('check-alerts');
        // Drug typeahead search (brand, generic, category)
        Route::get('/drugs/search',   [PrescriptionController::class, 'drugSearch'])->name('drugs.search');
        // Repeat medication detection
        Route::post('/check-repeat',  [PrescriptionController::class, 'checkRepeat'])->name('check-repeat');
    });

});
