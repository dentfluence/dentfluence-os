<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Prescription\RxDrugController;
use App\Http\Controllers\Prescription\RxSettingsController;
use App\Http\Controllers\Prescription\PrescriptionController;

// Phase 1 · Slice 1.3 (2026-07-25) — prescriptions are now owner-configurable.
// Group gate = module:prescriptions (view). Writes carry ',edit'; deletes
// carry ',delete'. Previously this whole file was auth-only, so any logged-in
// user could reach the Rx pad and its master data.
Route::middleware(['auth', 'module:prescriptions'])->group(function () {

    // ══════════════════════════════════════════════════════════════════════════
    // SETTINGS — Prescription Masters
    // Route prefix: settings/prescription
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('settings/prescription')->name('rx.settings.')->group(function () {

        // Settings index
        Route::get('/', [RxSettingsController::class, 'index'])->name('index');

        // Drug Categories
        Route::get('/categories',           [RxSettingsController::class, 'categories'])->name('categories');
        Route::post('/categories',          [RxSettingsController::class, 'categoriesStore'])->name('categories.store')->middleware('module:prescriptions,edit');
        Route::patch('/categories/{category}', [RxSettingsController::class, 'categoriesUpdate'])->name('categories.update')->middleware('module:prescriptions,edit');
        Route::delete('/categories/{category}', [RxSettingsController::class, 'categoriesDestroy'])->name('categories.destroy')->middleware('module:prescriptions,delete');

        // Generics
        Route::get('/generics',             [RxSettingsController::class, 'generics'])->name('generics');
        Route::post('/generics',            [RxSettingsController::class, 'genericsStore'])->name('generics.store')->middleware('module:prescriptions,edit');
        Route::patch('/generics/{generic}', [RxSettingsController::class, 'genericsUpdate'])->name('generics.update')->middleware('module:prescriptions,edit');
        Route::delete('/generics/{generic}',[RxSettingsController::class, 'genericsDestroy'])->name('generics.destroy')->middleware('module:prescriptions,delete');

        // Routes of Administration
        Route::get('/routes',               [RxSettingsController::class, 'routes'])->name('routes');
        Route::post('/routes',              [RxSettingsController::class, 'routesStore'])->name('routes.store')->middleware('module:prescriptions,edit');
        Route::patch('/routes/{route}',     [RxSettingsController::class, 'routesUpdate'])->name('routes.update')->middleware('module:prescriptions,edit');
        Route::delete('/routes/{route}',    [RxSettingsController::class, 'routesDestroy'])->name('routes.destroy')->middleware('module:prescriptions,delete');

        // Food Instructions
        Route::get('/food-instructions',             [RxSettingsController::class, 'foodInstructions'])->name('food-instructions');
        Route::post('/food-instructions',            [RxSettingsController::class, 'foodInstructionsStore'])->name('food-instructions.store')->middleware('module:prescriptions,edit');
        Route::patch('/food-instructions/{instruction}', [RxSettingsController::class, 'foodInstructionsUpdate'])->name('food-instructions.update')->middleware('module:prescriptions,edit');

        // Dose Templates
        Route::get('/dose-templates',                       [RxSettingsController::class, 'doseTemplates'])->name('dose-templates');
        Route::post('/dose-templates',                      [RxSettingsController::class, 'doseTemplatesStore'])->name('dose-templates.store')->middleware('module:prescriptions,edit');
        Route::patch('/dose-templates/{template}',          [RxSettingsController::class, 'doseTemplatesUpdate'])->name('dose-templates.update')->middleware('module:prescriptions,edit');
        Route::delete('/dose-templates/{template}',         [RxSettingsController::class, 'doseTemplatesDestroy'])->name('dose-templates.destroy')->middleware('module:prescriptions,delete');

        // Duration Templates
        Route::get('/duration-templates',                   [RxSettingsController::class, 'durationTemplates'])->name('duration-templates');
        Route::post('/duration-templates',                  [RxSettingsController::class, 'durationTemplatesStore'])->name('duration-templates.store')->middleware('module:prescriptions,edit');
        Route::patch('/duration-templates/{template}',      [RxSettingsController::class, 'durationTemplatesUpdate'])->name('duration-templates.update')->middleware('module:prescriptions,edit');
        Route::delete('/duration-templates/{template}',     [RxSettingsController::class, 'durationTemplatesDestroy'])->name('duration-templates.destroy')->middleware('module:prescriptions,delete');

        // Warning Rules
        Route::get('/warning-rules',        [RxSettingsController::class, 'warningRules'])->name('warning-rules');
        Route::post('/warning-rules',       [RxSettingsController::class, 'warningRulesStore'])->name('warning-rules.store')->middleware('module:prescriptions,edit');
        Route::delete('/warning-rules/{rule}', [RxSettingsController::class, 'warningRulesDestroy'])->name('warning-rules.destroy')->middleware('module:prescriptions,delete');

        // Prescription Templates
        Route::get('/prescription-templates',              [RxSettingsController::class, 'prescriptionTemplates'])->name('prescription-templates');
        Route::get('/prescription-templates/create',       [RxSettingsController::class, 'prescriptionTemplatesCreate'])->name('prescription-templates.create');
        Route::post('/prescription-templates',             [RxSettingsController::class, 'prescriptionTemplatesStore'])->name('prescription-templates.store')->middleware('module:prescriptions,edit');
        Route::delete('/prescription-templates/{template}',[RxSettingsController::class, 'prescriptionTemplatesDestroy'])->name('prescription-templates.destroy')->middleware('module:prescriptions,delete');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // DRUG MASTER  (settings/prescription/drugs)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('settings/prescription/drugs')->name('rx.drugs.')->group(function () {
        Route::get('/',           [RxDrugController::class, 'index'])->name('index');
        Route::get('/create',     [RxDrugController::class, 'create'])->name('create');
        Route::post('/',          [RxDrugController::class, 'store'])->name('store')->middleware('module:prescriptions,edit');
        Route::get('/{drug}/edit',[RxDrugController::class, 'edit'])->name('edit');
        Route::patch('/{drug}',   [RxDrugController::class, 'update'])->name('update')->middleware('module:prescriptions,edit');
        Route::delete('/{drug}',  [RxDrugController::class, 'destroy'])->name('destroy')->middleware('module:prescriptions,delete');
        Route::post('/{id}/restore', [RxDrugController::class, 'restore'])->name('restore')->middleware('module:prescriptions,edit');
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
        Route::post('/',                             [PrescriptionController::class, 'store'])->name('store')->middleware('module:prescriptions,edit');
        Route::get('/{prescription}',                [PrescriptionController::class, 'show'])->name('show');
        Route::get('/{prescription}/edit',           [PrescriptionController::class, 'edit'])->name('edit');
        Route::put('/{prescription}',                [PrescriptionController::class, 'update'])->name('update')->middleware('module:prescriptions,edit');
        Route::post('/{prescription}/repeat',        [PrescriptionController::class, 'repeat'])->name('repeat')->middleware('module:prescriptions,edit');
        Route::get('/{prescription}/print',          [PrescriptionController::class, 'printView'])->name('print');
        Route::get('/{prescription}/pdf',            [PrescriptionController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{prescription}/whatsapp-send', [PrescriptionController::class, 'sendWhatsApp'])->name('whatsapp-send')->middleware('module:prescriptions,edit');
        Route::delete('/{prescription}',             [PrescriptionController::class, 'destroy'])->name('destroy')->middleware('module:prescriptions,delete');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // PRESCRIPTION API  (JSON — called from form via fetch)
    // ══════════════════════════════════════════════════════════════════════════

    Route::prefix('api/rx')->name('api.rx.')->group(function () {
        // Live CDSS alert check
        Route::post('/check-alerts',  [PrescriptionController::class, 'checkAlerts'])->name('check-alerts')->middleware('module:prescriptions,edit');
        // Drug typeahead search (brand, generic, category)
        Route::get('/drugs/search',   [PrescriptionController::class, 'drugSearch'])->name('drugs.search');
        // Repeat medication detection
        Route::post('/check-repeat',  [PrescriptionController::class, 'checkRepeat'])->name('check-repeat')->middleware('module:prescriptions,edit');
    });

});
