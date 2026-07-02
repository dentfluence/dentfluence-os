<?php

use App\Http\Controllers\ClinicalFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinical Library Routes (Phase 7C)
|--------------------------------------------------------------------------
| Patient-scoped AJAX routes for the ClinicalFile resource.
| Registered in bootstrap/app.php alongside cms.php.
|
| All routes require auth. Responses are JSON.
| The patient Documents tab VIEW is served by PatientController (not here).
*/

Route::middleware(['auth'])->group(function () {

    // ── Clinical Files (patient-scoped) ─────────────────────────────────────
    Route::prefix('patients/{patient}/clinical-files')->name('clinical-files.')->group(function () {

        // GET  — list files (JSON, supports ?file_type=&stage=&visit_id=&from=&to=)
        Route::get('/',       [ClinicalFileController::class, 'index'])  ->name('index');

        // POST — upload a new file
        Route::post('/',      [ClinicalFileController::class, 'store'])  ->name('store');

        // GET  — single file metadata (for File Viewer panel)
        Route::get('/{file}', [ClinicalFileController::class, 'show'])   ->name('show');

        // PUT  — update file metadata
        Route::put('/{file}', [ClinicalFileController::class, 'update']) ->name('update');

        // DELETE — soft-delete file
        Route::delete('/{file}', [ClinicalFileController::class, 'destroy'])->name('destroy');
    });

    // ── Phase 11: Protocol Steps AJAX ──────────────────────────────────────────
    // GET /clinical-library/protocol-steps?procedure=Root+Canal
    // Called by upload modal when a procedure is selected.
    Route::get('/clinical-library/protocol-steps',
        [ClinicalFileController::class, 'protocolSteps']
    )->name('clinical-library.protocol-steps');

});
