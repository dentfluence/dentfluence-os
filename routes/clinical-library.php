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

    // Clinical Files (patient-scoped) are registered ONLY in routes/web.php,
    // inside the module:patients group. This file loads after web.php, so a
    // duplicate method+URI here silently replaced those gated routes with
    // auth-only ones and let any login list every patient's files (audit TEN-01).

    // ── Phase 11: Protocol Steps AJAX ──────────────────────────────────────────
    // GET /clinical-library/protocol-steps?procedure=Root+Canal
    // Called by upload modal when a procedure is selected.
    Route::get('/clinical-library/protocol-steps',
        [ClinicalFileController::class, 'protocolSteps']
    )->name('clinical-library.protocol-steps');

});
