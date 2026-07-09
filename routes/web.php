<?php

use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\AnalyticsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordPinController;
use App\Http\Controllers\Auth\MobileOtpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientImportExportController;
use App\Http\Controllers\PatientNoteController;
use App\Http\Controllers\PatientCommunicationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\TreatmentCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryProductImportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\DataRequestController;
use App\Http\Controllers\DataBreachController;
use App\Http\Controllers\RetentionController;


/* ────────────────────────────────────────────────────────────────
   ROOT
──────────────────────────────────────────────────────────────── */

Route::get('/', fn() => redirect()->route('login'));

/* ────────────────────────────────────────────────────────────────
   GUEST
──────────────────────────────────────────────────────────────── */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    // Brute-force protection (Phase A): 5 attempts/min per IP.
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')
        ->middleware('throttle:5,1');

    /* ── Forgot Password via PIN ── */
    Route::post('/forgot-pin/send',   [ForgotPasswordPinController::class, 'sendPin'])->name('forgot-pin.send');
    Route::post('/forgot-pin/verify', [ForgotPasswordPinController::class, 'verifyPin'])->name('forgot-pin.verify');
    Route::post('/forgot-pin/reset',  [ForgotPasswordPinController::class, 'resetPassword'])->name('forgot-pin.reset');

    /* ── Mobile OTP Login ── */
    Route::post('/auth/mobile/send-otp', [MobileOtpController::class, 'sendOtp'])->name('mobile.send-otp');
    Route::post('/auth/mobile/verify',   [MobileOtpController::class, 'verify'])->name('mobile.verify');

    /* ── Two-factor login challenge (after password, before login) ── */
    Route::get('/two-factor/challenge',  [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [\App\Http\Controllers\TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,1')->name('two-factor.verify');
});

/* ────────────────────────────────────────────────────────────────
   PUBLIC — QR ATTENDANCE SCAN (no auth — staff scan on phone)
──────────────────────────────────────────────────────────────── */
Route::get('/hr/scan',  [\App\Http\Controllers\HR\HrFinanceController::class, 'scanPage'])->name('hr.scan');
Route::post('/hr/scan', [\App\Http\Controllers\HR\HrFinanceController::class, 'logScan'])->name('hr.scan.log');

/* ────────────────────────────────────────────────────────────────
   AUTHENTICATED
──────────────────────────────────────────────────────────────── */
Route::middleware('auth')->group(function () {

    /* ── Auth ── */
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    /* ── Two-factor setup (manage your own 2FA) ── */
    Route::get('/two-factor/setup',    [\App\Http\Controllers\TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/enable',  [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/two-factor/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('two-factor.disable');

    /* ── Help & Support ── */
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');

    /* ── Dashboard ── */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ── DPDP Consent ── */
    Route::prefix('consent')->name('consent.')->group(function () {
        // Admin: the catalogue of consent purposes
        Route::get('/purposes',                      [ConsentController::class, 'index'])->name('purposes');
        Route::post('/purposes',                     [ConsentController::class, 'storePurpose'])->name('purposes.store');
        Route::patch('/purposes/{purpose}',          [ConsentController::class, 'updatePurpose'])->name('purposes.update');
        Route::post('/purposes/{purpose}/toggle',    [ConsentController::class, 'togglePurpose'])->name('purposes.toggle');

        // Per-patient: capture / withdraw / view trail
        Route::get('/patient/{patient}',             [ConsentController::class, 'patient'])->name('patient');
        Route::patch('/patient/{patient}',           [ConsentController::class, 'updatePatient'])->name('patient.update');
        Route::get('/patient/{patient}/trail',       [ConsentController::class, 'trail'])->name('patient.trail');
    });

    /* ── DPDP Patient Rights (DSAR) ── */
    Route::prefix('data-rights')->name('data-rights.')->group(function () {
        Route::get('/',                      [DataRequestController::class, 'index'])->name('index');
        Route::get('/create',                [DataRequestController::class, 'create'])->name('create');
        Route::post('/',                     [DataRequestController::class, 'store'])->name('store');
        Route::get('/{dataRequest}',         [DataRequestController::class, 'show'])->name('show');
        Route::patch('/{dataRequest}',       [DataRequestController::class, 'update'])->name('update');
        Route::post('/{dataRequest}/complete', [DataRequestController::class, 'complete'])->name('complete');
        Route::post('/{dataRequest}/reject', [DataRequestController::class, 'reject'])->name('reject');
        Route::get('/{dataRequest}/download', [DataRequestController::class, 'download'])->name('download');
        Route::post('/{dataRequest}/erase',  [DataRequestController::class, 'erase'])->name('erase');
    });

    /* ── DPDP Breach Register ── */
    Route::prefix('breaches')->name('breaches.')->group(function () {
        Route::get('/',                       [DataBreachController::class, 'index'])->name('index');
        Route::get('/create',                 [DataBreachController::class, 'create'])->name('create');
        Route::post('/',                      [DataBreachController::class, 'store'])->name('store');
        Route::get('/{breach}',               [DataBreachController::class, 'show'])->name('show');
        Route::patch('/{breach}',             [DataBreachController::class, 'update'])->name('update');
        Route::post('/{breach}/report-board', [DataBreachController::class, 'reportBoard'])->name('report-board');
        Route::post('/{breach}/notify',       [DataBreachController::class, 'notifyAffected'])->name('notify');
    });

    /* ── DPDP Data Retention (dry-run) ── */
    Route::get('/retention', [RetentionController::class, 'index'])->name('retention.index');

    /* ── Patients ── */
    Route::middleware('module:patients')->prefix('patients')->name('patients.')->group(function () {
        Route::get('/',               [PatientController::class, 'index'])->name('index');
        Route::get('/search',         [PatientController::class, 'search'])->name('search');

        Route::get('/create',         [PatientController::class, 'create'])->name('create');
        Route::post('/scan-form',     [PatientController::class, 'scanForm'])->name('scan-form'); // 📷 read a paper intake form
        Route::post('/',              [PatientController::class, 'store'])->name('store');
        Route::post('/quick-store',   [PatientController::class, 'quickStore'])->name('quick-store');
        Route::get('/{patient}',      [PatientController::class, 'show'])->name('show');
        Route::get('/{patient}/edit', [PatientController::class, 'edit'])->name('edit');
        Route::patch('/{patient}',    [PatientController::class, 'update'])->name('update');
        Route::delete('/{patient}',           [PatientController::class, 'destroy'])->name('destroy');
        Route::post('/{patient}/deactivate',  [PatientController::class, 'deactivate'])->name('deactivate');
        Route::post('/{patient}/reactivate',  [PatientController::class, 'reactivate'])->name('reactivate');

        // ── ABHA / Health ID capture (local, no live ABDM) ──
        Route::get  ('/{patient}/abha', [\App\Http\Controllers\Abdm\PatientAbhaController::class, 'edit'])->name('abha.edit');
        Route::patch('/{patient}/abha', [\App\Http\Controllers\Abdm\PatientAbhaController::class, 'update'])->name('abha.update');

        // Relationship notes
        Route::post('/{patient}/relationship-notes',          [PatientController::class, 'storeRelationshipNote'])->name('relationship-notes.store');
        Route::delete('/{patient}/relationship-notes/{note}', [PatientController::class, 'destroyRelationshipNote'])->name('relationship-notes.destroy');

        // Treatment opportunities
        Route::post('/{patient}/opportunities',         [PatientController::class, 'storeOpportunity'])->name('opportunities.store');
        Route::patch('/{patient}/opportunities/{opp}',  [PatientController::class, 'updateOpportunity'])->name('opportunities.update');
        Route::delete('/{patient}/opportunities/{opp}', [PatientController::class, 'destroyOpportunity'])->name('opportunities.destroy');

        // Patient notes
        Route::post('/{patient}/notes',          [PatientNoteController::class, 'store'])->name('notes.store');
        Route::delete('/{patient}/notes/{note}', [PatientNoteController::class, 'destroy'])->name('notes.destroy');

        // Communications
        Route::get   ('/{patient}/communications',                        [PatientCommunicationController::class, 'index'])->name('communications.index');
        Route::post  ('/{patient}/communications',                        [PatientCommunicationController::class, 'store'])->name('communications.store');
        Route::delete('/{patient}/communications/{communication}',        [PatientCommunicationController::class, 'destroy'])->name('communications.destroy');

        // Patient documents
        Route::post('/{patient}/documents',                [\App\Http\Controllers\PatientDocumentController::class, 'store'])->name('documents.store');
        Route::delete('/{patient}/documents/{document}',   [\App\Http\Controllers\PatientDocumentController::class, 'destroy'])->name('documents.destroy');

        // Tags
        Route::get('/{patient}/tags',          [TagController::class, 'forPatient'])->name('tags.index');
        Route::post('/{patient}/tags/attach',  [TagController::class, 'attach'])->name('tags.attach');
        Route::delete('/{patient}/tags/{tag}', [TagController::class, 'detach'])->name('tags.detach');

        // Consultations nested under patient
        Route::get('/{patient}/consultations/create', [ConsultationController::class, 'create'])->name('consultations.create');
        Route::get('/{patient}/consultations',        [ConsultationController::class, 'forPatient'])->name('consultations.index');

        // ── Typed consultation routes (must come before resource wildcard) ────
        Route::get ('/{patient}/consultations/same-issue',  [ConsultationController::class, 'sameIssueCreate'])->name('consultations.same-issue.create');
        Route::post('/{patient}/consultations/same-issue',  [ConsultationController::class, 'sameIssueStore'])->name('consultations.same-issue.store');
        Route::get ('/{patient}/consultations/minor-visit', [ConsultationController::class, 'minorVisitCreate'])->name('consultations.minor-visit.create');
        Route::post('/{patient}/consultations/minor-visit', [ConsultationController::class, 'minorVisitStore'])->name('consultations.minor-visit.store');
        Route::get ('/{patient}/consultations/emergency',   [ConsultationController::class, 'emergencyCreate'])->name('consultations.emergency.create');
        Route::post('/{patient}/consultations/emergency',   [ConsultationController::class, 'emergencyStore'])->name('consultations.emergency.store');
    });

    // ── Patient-context clinical routes (all gated under patients module) ──────
    Route::middleware('module:patients')->group(function () {

        /* ── Consultations (standalone) ── */
        Route::prefix('consultations')->name('consultations.')->group(function () {
            Route::post('/',                   [ConsultationController::class, 'store'])->name('store');
            Route::get('/{consultation}',      [ConsultationController::class, 'show'])->name('show');
            Route::get('/{consultation}/edit', [ConsultationController::class, 'editStandalone'])->name('edit');
            Route::put('/{consultation}',      [ConsultationController::class, 'updateStandalone'])->name('update');
            Route::delete('/{consultation}',   [ConsultationController::class, 'destroy'])->name('destroy');
        });

        // Clinical Files (Documents tab) — patient scoped
        Route::post  ('/patients/{patient}/clinical-files',        [\App\Http\Controllers\ClinicalFileController::class, 'store'])->name('clinical-files.store');
        Route::get   ('/patients/{patient}/clinical-files',        [\App\Http\Controllers\ClinicalFileController::class, 'index'])->name('clinical-files.index');
        Route::get   ('/patients/{patient}/clinical-files/{file}', [\App\Http\Controllers\ClinicalFileController::class, 'show'])->name('clinical-files.show');
        Route::put   ('/patients/{patient}/clinical-files/{file}', [\App\Http\Controllers\ClinicalFileController::class, 'update'])->name('clinical-files.update');
        Route::delete('/patients/{patient}/clinical-files/{file}', [\App\Http\Controllers\ClinicalFileController::class, 'destroy'])->name('clinical-files.destroy');

        // Treatment Plans — patient scoped
        Route::post('/patients/{patient}/treatment-plans',            [TreatmentPlanController::class, 'store'])->name('treatment-plans.store');
        Route::get('/patients/{patient}/treatment-plans',             [TreatmentPlanController::class, 'index'])->name('treatment-plans.index');
        Route::post('/patients/{patient}/treatment-plans/ai-suggest', [TreatmentPlanController::class, 'aiSuggest'])->name('treatment-plans.ai-suggest');
        // P2C10c: handoff from consultation → pre-fill treatment plan form
        Route::get('/patients/{patient}/treatment-plans/from-consultation/{consultation}', [TreatmentPlanController::class, 'createFromConsultation'])->name('treatment-plans.from-consultation');

        // Treatment Plans — plan scoped
        // Note: /print must be BEFORE /{plan} routes so 'print' isn't treated as an ID
        Route::get('/treatment-plans/print',          [TreatmentPlanController::class, 'printView'])->name('treatment-plans.print');
        Route::get('/treatment-plans/{plan}/items',    [TreatmentPlanController::class, 'getItems'])->name('treatment-plans.items');
        Route::put('/treatment-plans/{plan}',          [TreatmentPlanController::class, 'update'])->name('treatment-plans.update');
        Route::post('/treatment-plans/{plan}/accept',  [TreatmentPlanController::class, 'accept'])->name('treatment-plans.accept');
        Route::post('/treatment-plans/{plan}/revert',  [TreatmentPlanController::class, 'revert'])->name('treatment-plans.revert');
        Route::delete('/treatment-plans/{plan}',       [TreatmentPlanController::class, 'destroy'])->name('treatment-plans.destroy');
        Route::delete('/treatment-plan-items/{item}',  [TreatmentPlanController::class, 'destroyItem'])->name('treatment-plan-items.destroy');

        // Treatment Visits
        Route::post('/patients/{patient}/visits', [App\Http\Controllers\TreatmentVisitController::class, 'store'])->name('visits.store');
        Route::put('/visits/{visit}',             [App\Http\Controllers\TreatmentVisitController::class, 'update'])->name('visits.update');
        Route::delete('/visits/{visit}',          [App\Http\Controllers\TreatmentVisitController::class, 'destroy'])->name('visits.destroy');
        Route::get('/visits/{visit}/print',       [App\Http\Controllers\TreatmentVisitController::class, 'print'])->name('visits.print');

        // ── Consult Assist (AJAX) ──────────────────────────────────────────────
        // Receives chief complaint text, returns matched specialties from treatment_knowledge.
        Route::post('/consult-assist/suggest', [App\Http\Controllers\ConsultAssistController::class, 'suggest'])
            ->name('consult-assist.suggest');
        Route::post('/consult-assist/section-guidance', [App\Http\Controllers\ConsultAssistController::class, 'sectionGuidance'])
            ->name('consult-assist.section-guidance');
        Route::post('/consult-assist/tooth-timeline', [App\Http\Controllers\ConsultAssistController::class, 'toothTimeline'])
            ->name('consult-assist.tooth-timeline');

        // Print routes
        Route::get('/consultations/{consultation}/print', [App\Http\Controllers\ConsultationController::class, 'print'])->name('consultations.print');
        Route::get('/patients/{patient}/print',           [App\Http\Controllers\PatientController::class, 'print'])->name('patients.print');

        // ── COHA (Comprehensive Oral Health Assessment) ───────────────────────
        // Dedicated routes — COHA is a separate workflow from standard consultations.
        Route::get('/patients/{patient}/coha/create',                      [App\Http\Controllers\ConsultationController::class, 'cohaCreate'])->name('coha.create');
        Route::post('/patients/{patient}/coha',                            [App\Http\Controllers\ConsultationController::class, 'cohaStore'])->name('coha.store');
        Route::get('/patients/{patient}/coha/{consultation}/edit',         [App\Http\Controllers\ConsultationController::class, 'cohaEdit'])->name('coha.edit');
        Route::put('/patients/{patient}/coha/{consultation}',              [App\Http\Controllers\ConsultationController::class, 'cohaUpdate'])->name('coha.update');
        Route::get('/patients/{patient}/coha/{consultation}/report',       [App\Http\Controllers\ConsultationController::class, 'cohaReport'])->name('coha.report');

    }); // end module:patients (clinical context)

    /* ── Appointments ── */
    Route::middleware('module:appointments')->prefix('appointments')->name('appointments.')->group(function () {
        Route::get('/today',                    [AppointmentController::class, 'today'])->name('today');
        Route::get('/',                         [AppointmentController::class, 'index'])->name('index');
        Route::get('/create',                   [AppointmentController::class, 'create'])->name('create');
        Route::post('/',                        [AppointmentController::class, 'store'])->name('store');
        Route::get('/queue/today',              [AppointmentController::class, 'todayQueue'])->name('queue.today');
        Route::get('/status-counts',            [AppointmentController::class, 'statusCounts'])->name('status.counts');
        Route::get('/check-conflict',           [AppointmentController::class, 'checkConflict'])->name('check.conflict');
        Route::post('/block-slot',             [AppointmentController::class, 'storeBlockedSlot'])->name('block.slot');
        Route::get('/blocked-slots',           [AppointmentController::class, 'indexBlockedSlots'])->name('blocked.slots');
        Route::get('/{appointment}',            [AppointmentController::class, 'show'])->name('show');
        Route::get('/{appointment}/quick',      [AppointmentController::class, 'quickView'])->name('quick');
        Route::get('/{appointment}/edit',       [AppointmentController::class, 'edit'])->name('edit');
        Route::patch('/{appointment}',          [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}',         [AppointmentController::class, 'destroy'])->name('destroy');
        Route::patch('/{appointment}/status',    [AppointmentController::class, 'updateStatus'])->name('updateStatus');
        Route::patch('/{appointment}/cancel',    [AppointmentController::class, 'cancelWithReason'])->name('cancel');
        Route::patch('/{appointment}/revert',    [AppointmentController::class, 'revertStatus'])->name('revert');
        Route::patch('/{appointment}/operatory', [AppointmentController::class, 'assignOperatory'])->name('assignOperatory');
        Route::patch('/{appointment}/hide',       [AppointmentController::class, 'hideFromCalendar'])->name('hide');
        Route::patch('/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('reschedule');
    });



    Route::middleware('module:patients')->group(function () {
        Route::resource('patients.consultations', ConsultationController::class)
            ->only(['create', 'store', 'show', 'edit', 'update', 'destroy']);
    });

    /* ── Treatment Categories (part of treatments module) ── */
    Route::middleware('module:treatments')->group(function () {
        Route::get('/treatment-categories',                                 [TreatmentCategoryController::class, 'index']);
        Route::get('/treatment-categories/{category}/treatments',           [TreatmentCategoryController::class, 'treatments']);
        Route::post('/treatment-categories',                                [TreatmentCategoryController::class, 'store'])->name('treatment-categories.store');
        Route::put('/treatment-categories/{treatmentCategory}',             [TreatmentCategoryController::class, 'update'])->name('treatment-categories.update');
        Route::delete('/treatment-categories/{treatmentCategory}',          [TreatmentCategoryController::class, 'destroy'])->name('treatment-categories.destroy');
    });
    // price-list is registered inside the treatments prefix group below

    require __DIR__ . '/../app/Modules/Huddle/Routes/huddle.php';

    require __DIR__ . '/../app/Modules/PracticeProtocols/Routes/practice-protocols.php';

    /* ── User Profile ── */
    Route::get('/profile',                        [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile',                       [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password',              [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar',                [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar',              [\App\Http\Controllers\ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    /* ── Settings (admin only — module:settings) ── */
    Route::middleware('module:settings')->group(function () {
        Route::get('/settings',                       [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');
        // Banking is a Finance/Settings admin page (not a Finance tab)
        Route::get('/settings/banking',               [\App\Http\Controllers\Finance\FinanceController::class, 'banking'])->name('settings.banking');
        Route::get('/settings/clinical-library',      [\App\Http\Controllers\Settings\SettingsController::class, 'clinicalLibrary'])->name('settings.clinical-library');
        Route::get('/settings/activity-log',          [\App\Http\Controllers\Settings\ActivityLogController::class, 'index'])->name('settings.activity-log');

        // ── Data: Import / Export ──
        Route::prefix('settings/data')->name('settings.data.')->group(function () {
            Route::post('/import/preview',      [PatientImportExportController::class, 'preview'])->name('import.preview');
            Route::post('/import/store',        [PatientImportExportController::class, 'import'])->name('import.store');
            Route::get('/import/template/{source}', [PatientImportExportController::class, 'downloadTemplate'])->name('import.template');
            Route::get('/export',               [PatientImportExportController::class, 'export'])->name('export');
        });
        Route::post('/settings/clinic',               [\App\Http\Controllers\Settings\SettingsController::class, 'saveClinic'])->name('settings.clinic.save');
        // HFR / Health Facility capture for the clinic (local, no live ABDM)
        Route::get  ('/settings/clinic/hfr',          [\App\Http\Controllers\Abdm\ClinicHfrController::class, 'edit'])->name('settings.clinic.hfr.edit');
        Route::patch('/settings/clinic/hfr',          [\App\Http\Controllers\Abdm\ClinicHfrController::class, 'update'])->name('settings.clinic.hfr.update');
        Route::post('/settings/inventory',            [\App\Http\Controllers\Settings\SettingsController::class, 'saveInventorySettings'])->name('settings.inventory.save');
        Route::post('/settings/patient-id',           [\App\Http\Controllers\Settings\SettingsController::class, 'savePatientId'])->name('settings.patient_id.save');
        Route::post('/settings/notifications',        [\App\Http\Controllers\Settings\SettingsController::class, 'saveNotifications'])->name('settings.notifications.save');
        Route::post('/settings/billing',              [\App\Http\Controllers\Settings\SettingsController::class, 'saveBilling'])->name('settings.billing.save');
        Route::post('/settings/print',               [\App\Http\Controllers\Settings\SettingsController::class, 'savePrint'])->name('settings.print.save');
        // PRE (Relationship Engine) feature-flag toggles — admin-only, same as everything else in this group
        Route::post('/settings/feature-flags/toggle', [\App\Http\Controllers\Settings\SettingsController::class, 'toggleFeatureFlag'])->name('settings.feature-flags.toggle');
        // EMI Providers & Schemes
        $sc = \App\Http\Controllers\Settings\SettingsController::class;
        Route::post('/settings/emi-providers',                              [$sc, 'storeEmiProvider'])->name('settings.emi.provider.store');
        Route::post('/settings/emi-providers/{emiProvider}/toggle',        [$sc, 'toggleEmiProvider'])->name('settings.emi.provider.toggle');
        Route::post('/settings/emi-providers/{emiProvider}/schemes',       [$sc, 'storeEmiScheme'])->name('settings.emi.scheme.store');
        Route::post('/settings/emi-schemes/{emiScheme}/toggle',            [$sc, 'toggleEmiScheme'])->name('settings.emi.scheme.toggle');
        Route::post('/settings/emi-schemes/{emiScheme}/cost-passthrough',  [$sc, 'toggleEmiSchemeCostPassthrough'])->name('settings.emi.scheme.passthrough');
        // AJAX: get schemes for a provider + calculate breakdown
        Route::get('/settings/emi-schemes',                                [$sc, 'emiSchemesForProvider'])->name('settings.emi.schemes.ajax');
        Route::post('/settings/staff',                [\App\Http\Controllers\Settings\SettingsController::class, 'storeStaff'])->name('settings.staff.store');
        Route::post('/settings/staff/{user}/toggle',  [\App\Http\Controllers\Settings\SettingsController::class, 'toggleStaff'])->name('settings.staff.toggle');
        Route::post('/settings/staff/{user}/role',    [\App\Http\Controllers\Settings\SettingsController::class, 'updateStaffRole'])->name('settings.staff.role');
        Route::post('/settings/staff/{user}/update',  [\App\Http\Controllers\Settings\SettingsController::class, 'updateStaff'])->name('settings.staff.update');
        Route::get('/settings/staff/activity-log',    [\App\Http\Controllers\Settings\SettingsController::class, 'activityLog'])->name('settings.staff.activity-log');

        // ── Calendar Preferences ───────────────────────────────────────────────
        Route::post('/settings/calendar', [\App\Http\Controllers\Settings\SettingsController::class, 'saveCalendarPrefs'])->name('settings.calendar.save');

        // ── Operatories ────────────────────────────────────────────────────────
        Route::prefix('settings/operatories')->name('settings.operatories.')->group(function () {
            $oc = \App\Http\Controllers\Settings\OperatoryController::class;
            Route::get   ('/',                  [$oc, 'index'])->name('index');
            Route::post  ('/',                  [$oc, 'store'])->name('store');
            Route::patch ('/{operatory}',       [$oc, 'update'])->name('update');
            Route::post  ('/{operatory}/toggle',[$oc, 'toggle'])->name('toggle');
            Route::post  ('/reorder',           [$oc, 'reorder'])->name('reorder');
            Route::delete('/{operatory}',       [$oc, 'destroy'])->name('destroy');
        });

        // Masters (treatments, complaints, etc.)
        Route::prefix('settings/masters')->name('settings.masters.')->group(function () {
            $c = \App\Http\Controllers\Settings\MastersController::class;
            Route::post('/treatments',          [$c, 'storeTreatment'])->name('treatments.store');
            Route::delete('/treatments/{id}',   [$c, 'destroyTreatment'])->name('treatments.destroy');
            Route::post('/complaints',          [$c, 'storeComplaint'])->name('complaints.store');
            Route::delete('/complaints/{id}',   [$c, 'destroyComplaint'])->name('complaints.destroy');
            Route::post('/diagnoses',           [$c, 'storeDiagnosis'])->name('diagnoses.store');
            Route::delete('/diagnoses/{id}',    [$c, 'destroyDiagnosis'])->name('diagnoses.destroy');
            Route::post('/investigations',      [$c, 'storeInvestigation'])->name('investigations.store');
            Route::delete('/investigations/{id}',[$c, 'destroyInvestigation'])->name('investigations.destroy');
            // Clinical
            Route::post('/medicines',           [$c, 'storeMedicine'])->name('medicines.store');
            Route::delete('/medicines/{id}',    [$c, 'destroyMedicine'])->name('medicines.destroy');
            // Patient defaults
            Route::post('/medical-conditions',        [$c, 'storeMedicalCondition'])->name('medical_conditions.store');
            Route::delete('/medical-conditions/{id}', [$c, 'destroyMedicalCondition'])->name('medical_conditions.destroy');
            Route::post('/dental-conditions',         [$c, 'storeDentalCondition'])->name('dental_conditions.store');
            Route::delete('/dental-conditions/{id}',  [$c, 'destroyDentalCondition'])->name('dental_conditions.destroy');
            Route::post('/patient-sources',           [$c, 'storePatientSource'])->name('patient_sources.store');
            Route::delete('/patient-sources/{id}',    [$c, 'destroyPatientSource'])->name('patient_sources.destroy');
            // Message templates
            Route::post('/message-templates',         [$c, 'storeMessageTemplate'])->name('message_templates.store');
            Route::delete('/message-templates/{id}',  [$c, 'destroyMessageTemplate'])->name('message_templates.destroy');
        });

        // Roles & Permissions API moved to hr.roles.* (admin.only-gated) —
        // see routes/web.php's `hr` group. This duplicate `/settings/roles`
        // registration was removed 2026-07-06; the roles page's own JS used
        // to call it directly instead of the hr.roles.* routes it should
        // have used after the page moved into HR.

        // Tags
        Route::prefix('settings/tags')->name('settings.tags.')->group(function () {
            Route::get('/',         [TagController::class, 'index'])->name('index');
            Route::post('/',        [TagController::class, 'store'])->name('store');
            Route::put('/{tag}',    [TagController::class, 'update'])->name('update');
            Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
        });
    }); // end module:settings

    /* ── Treatments Module (Clinic Knowledge Base) ── */
    Route::middleware('module:treatments')->prefix('treatments')->name('treatments.')->group(function () {
        $tc  = \App\Http\Controllers\TreatmentController::class;
        $tcc = \App\Http\Controllers\TreatmentCategoryController::class;

        Route::get('/',                                  [$tc, 'index'])->name('index');
        Route::get('/create',                            [$tc, 'create'])->name('create');
        Route::post('/',                                 [$tc, 'store'])->name('store');
        // Static routes — must all be before /{treatment} wildcard
        Route::get('/price-list',                        [$tcc, 'priceList'])->name('price-list');
        Route::get('/patients/search',                   [$tc, 'searchPatients'])->name('patients.search');
        Route::get('/{treatment}',                       [$tc, 'show'])->name('show');
        Route::put('/{treatment}',                       [$tc, 'update'])->name('update');
        Route::delete('/{treatment}',                    [$tc, 'destroy'])->name('destroy');

        // SOP
        Route::post('/{treatment}/sop',                  [$tc, 'saveSop'])->name('sop.save');

        // Stages
        Route::post('/{treatment}/stages',               [$tc, 'saveStages'])->name('stages.save');

        // Rules
        Route::post('/{treatment}/rules',                [$tc, 'saveRules'])->name('rules.save');

        // Media
        Route::post('/{treatment}/media',                [$tc, 'uploadMedia'])->name('media.upload');
        Route::delete('/media/{media}',                  [$tc, 'deleteMedia'])->name('media.delete');

        // Review
        Route::post('/{treatment}/review',               [$tc, 'markReviewed'])->name('review.mark');

        // Intelligence (P2C8)
        Route::post('/{treatment}/intelligence',         [$tc, 'saveIntelligence'])->name('intelligence.save');

        // Print — patient-facing instruction sheet (pre_op, post_op, consent)
        Route::get('/{treatment}/print/{type}',          [$tc, 'printView'])->name('print');

        // API — for treatment plan builder / billing auto-fill
        Route::get('/{treatment}/api',                   [$tc, 'apiDetail'])->name('api.detail');
    });

    // Billing — Phase F3a + F3b (gated under finance module)
    // ⚠ Non-resource routes must be defined BEFORE Route::resource to avoid wildcard conflicts
    Route::middleware('module:finance')->group(function () {
        Route::post('/billing/coupon/validate',
            [\App\Http\Controllers\BillingController::class, 'validateCoupon'])->name('billing.validateCoupon');
        // F4a — membership benefits AJAX (before resource to avoid {billing} catching it)
        Route::post('/billing/membership/benefits',
            [\App\Http\Controllers\BillingController::class, 'membershipBenefits'])->name('billing.membership.benefits');
        Route::post('/billing-prompt/{prompt}/dismiss',
            [\App\Http\Controllers\BillingController::class, 'dismissPrompt'])->name('billing.dismissPrompt');
        // GET: "Build Invoice" opens the editable draft form pre-filled from the prompt's visit items
        Route::get('/patients/{patient}/billing-prompt/{prompt}/create-invoice',
            [\App\Http\Controllers\BillingController::class, 'createFromPrompt'])->name('billing.createFromPrompt');
        // F4a — patient membership enrollment
        Route::post('/patients/{patient}/membership/enroll',
            [\App\Http\Controllers\BillingController::class, 'enrollMembership'])->name('billing.membership.enroll');
        // Resource uses ->parameters() so the route param is {invoice}
        Route::resource('billing', \App\Http\Controllers\BillingController::class)
             ->parameters(['billing' => 'invoice']);
        Route::post('/billing/{invoice}/cancel',      [\App\Http\Controllers\BillingController::class, 'cancel'])->name('billing.cancel');
        Route::post('/billing/{invoice}/payment',     [\App\Http\Controllers\BillingController::class, 'recordPayment'])->name('billing.payment');
        // Bill from Treatment Plan (partial multi-tooth invoicing)
        Route::get('/billing/from-plan/{plan}',  [\App\Http\Controllers\BillingController::class, 'billFromPlan'])->name('billing.fromPlan');
        Route::post('/billing/from-plan/{plan}', [\App\Http\Controllers\BillingController::class, 'storeFromPlan'])->name('billing.storeFromPlan');
        // Manual discount (permission-gated, audited)
        Route::post('/billing/{invoice}/manual-discount',        [\App\Http\Controllers\BillingController::class, 'applyManualDiscount'])->name('billing.manualDiscount.apply');
        Route::post('/billing/{invoice}/manual-discount/remove', [\App\Http\Controllers\BillingController::class, 'removeManualDiscount'])->name('billing.manualDiscount.remove');
        Route::get('/billing/{invoice}/print',        [\App\Http\Controllers\BillingController::class, 'printInvoice'])->name('billing.print');
        // Auth-gated destructive actions
        Route::post('/billing/{invoice}/delete-auth', [\App\Http\Controllers\BillingController::class, 'destroyWithAuth'])->name('billing.deleteAuth');
        Route::post('/billing/{invoice}/edit-auth',   [\App\Http\Controllers\BillingController::class, 'editWithAuth'])->name('billing.editAuth');
        // F3b — Receipt + Final Bill
        Route::get('/billing/{invoice}/panel',             [\App\Http\Controllers\BillingController::class, 'panel'])->name('billing.panel');
        Route::get('/billing/{invoice}/receipt/{receipt}', [\App\Http\Controllers\BillingController::class, 'showReceipt'])->name('billing.receipt');
        Route::get('/billing/{invoice}/final-bill',        [\App\Http\Controllers\BillingController::class, 'showFinalBill'])->name('billing.finalBill');
        // Provider EMI
        Route::post('/billing/{invoice}/payment/{payment}/mark-provider-paid', [\App\Http\Controllers\BillingController::class, 'markProviderPaid'])->name('billing.markProviderPaid');
        // Edit an already-recorded payment's date (cascades to receipt + finance transaction)
        Route::patch('/billing/{invoice}/payment/{payment}', [\App\Http\Controllers\BillingController::class, 'updatePayment'])->name('billing.payment.update');
        // Void receipt
        Route::post('/billing/{invoice}/receipt/{receipt}/void', [\App\Http\Controllers\BillingController::class, 'voidReceipt'])->name('billing.receipt.void');
        // Cancel invoice with reason + refund
        Route::post('/billing/{invoice}/cancel-with-reason', [\App\Http\Controllers\BillingController::class, 'cancelInvoice'])->name('billing.cancelWithReason');
        // Delete Final Bill with reason
        Route::delete('/billing/final-bill/{finalBill}', [\App\Http\Controllers\BillingController::class, 'deleteFinalBill'])->name('billing.finalBill.delete');
    }); // end module:finance (billing)

    // CRM — redirects to the Opportunity Pipeline (the real patient pipeline)
    Route::get('/crm', fn() => redirect()->route('relationship.opportunities'))->name('crm.index');

    // Analytics — Finance AnalyticsController (wired 2026-06-18)
    Route::middleware('module:analytics')->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/',             [AnalyticsController::class, 'index'])->name('index');
        Route::get('/vendor',       [AnalyticsController::class, 'vendorAnalytics'])->name('vendor');
        Route::get('/expense',      [AnalyticsController::class, 'expenseAnalytics'])->name('expense');
        Route::get('/lab',          [AnalyticsController::class, 'labAnalytics'])->name('lab');
        Route::get('/procurement',  [AnalyticsController::class, 'procurementAnalytics'])->name('procurement');
        Route::get('/cashflow',     [AnalyticsController::class, 'cashflow'])->name('cashflow');
        Route::get('/outstanding',  [AnalyticsController::class, 'outstanding'])->name('outstanding');
        Route::get('/business',     [AnalyticsController::class, 'businessIntelligence'])->name('business');
        Route::get('/audit',        [AnalyticsController::class, 'auditLog'])->name('audit');
    });

    // ── Main Reports (Appointments / Revenue / Patients / Treatments / Lab / Inventory) ──
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index')->middleware('module:reports');

    // ── Notifications ──
    Route::get('/notifications',               [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread',        [\App\Http\Controllers\NotificationsController::class, 'unread'])->name('notifications.unread');
    // mark-all-read must come BEFORE {id}/read to avoid wildcard conflict
    Route::post('/notifications/mark-all-read',[\App\Http\Controllers\NotificationsController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/read',    [\App\Http\Controllers\NotificationsController::class, 'markRead'])->name('notifications.read');

    // ── Marketing Module ──
    require __DIR__ . '/marketing.php';

    // ── Prescriptions Module ──
    require __DIR__ . '/prescriptions.php';
    /* ── Inventory Module ── */
    Route::middleware('module:inventory')->prefix('inventory')->name('inventory.')->group(function () {
        // Dashboard
        Route::get('/',               [InventoryController::class, 'dashboard'])->name('index');
        Route::get('/dashboard',      [InventoryController::class, 'dashboard'])->name('dashboard');

        // Stock view (current qty + quick +/- adjust)
        Route::get('/items',               [InventoryController::class, 'items'])->name('items');
        Route::post('/items/{item}/adjust',[InventoryController::class, 'adjustStock'])->name('items.adjust');
        Route::get('/items/{item}/history',[InventoryController::class, 'stockHistory'])->name('items.history');
        // Reversing a manual adjustment is Admin-only (same gate as deleting a product) —
        // it's a correction to the audit ledger, not a routine action.
        Route::post('/movements/{movement}/reverse',[InventoryController::class, 'reverseAdjustment'])->name('movements.reverse')->middleware('admin.only');

        // Stock Count — 15-day physical count cycle
        Route::prefix('stock-count')->name('stock-count.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\StockCountController::class, 'index'])->name('index');
            Route::post('/',                                    [\App\Http\Controllers\StockCountController::class, 'start'])->name('start');
            Route::get('/{session}',                            [\App\Http\Controllers\StockCountController::class, 'sheet'])->name('sheet');
            Route::post('/{session}/save',                      [\App\Http\Controllers\StockCountController::class, 'save'])->name('save');
            Route::post('/{session}/complete',                  [\App\Http\Controllers\StockCountController::class, 'complete'])->name('complete');
        });

        // Product Master — card catalogue + detail view + CRUD
        Route::get('/products',            [InventoryController::class, 'products'])->name('products');

        // Bulk Excel/CSV import (2026-07-07) — Clinical products only, MVP
        // scope. Static segments declared BEFORE /products/{item} below so
        // "import" is never mistaken for an item ID.
        Route::get('/products/import',           [InventoryProductImportController::class, 'importForm'])->name('products.import');
        Route::get('/products/import/template',  [InventoryProductImportController::class, 'downloadTemplate'])->name('products.import.template');
        Route::post('/products/import/preview',  [InventoryProductImportController::class, 'preview'])->name('products.import.preview');
        Route::post('/products/import/store',    [InventoryProductImportController::class, 'store'])->name('products.import.store');

        Route::get('/products/{item}',     [InventoryController::class, 'showProduct'])->name('products.show');
        Route::post('/products',           [InventoryController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{item}',     [InventoryController::class, 'updateProduct'])->name('products.update');
        // Delete is Admin-only regardless of what a role's Inventory permission grid says —
        // deleting a product hides its whole movement/audit history, not a routine edit.
        Route::delete('/products/{item}',  [InventoryController::class, 'destroyProduct'])->name('products.destroy')->middleware('admin.only');

        // Stock movements
        Route::get('/stock-in',       [InventoryController::class, 'stockIn'])->name('stock-in');
        Route::post('/stock-in',      [InventoryController::class, 'storeStockIn'])->name('stock-in.store');
        Route::get('/stock-out',      [InventoryController::class, 'stockOut'])->name('stock-out');
        Route::post('/stock-out',     [InventoryController::class, 'storeStockOut'])->name('stock-out.store');

        // Other sections
        Route::get('/purchase',       [InventoryController::class, 'purchase'])->name('purchase');
        Route::post('/purchase',      [InventoryController::class, 'storePurchaseOrder'])->name('purchase.store');
        Route::get('/vendors',        [InventoryController::class, 'vendors'])->name('vendors');
        Route::post('/vendors',       [InventoryController::class, 'storeVendor'])->name('vendors.store');
        Route::get('/reusable-assets',              [InventoryController::class, 'reusableAssets'])->name('reusable-assets');
        Route::post('/reusable-assets',             [InventoryController::class, 'storeAsset'])->name('reusable-assets.store');
        Route::put('/reusable-assets/{asset}',      [InventoryController::class, 'updateAsset'])->name('reusable-assets.update');
        Route::post('/reusable-assets/{asset}/status', [InventoryController::class, 'updateAssetStatus'])->name('reusable-assets.status');
        Route::get('/expiry',         [InventoryController::class, 'expiry'])->name('expiry');
        Route::get('/reports',        [InventoryController::class, 'reports'])->name('reports');

        // Settings (admin-only) — GET redirects to unified Settings module
        Route::get('/settings', fn() => redirect()->route('settings.index', ['tab' => 'inventory']))->name('settings');
        Route::post('/settings',                     [InventoryController::class, 'updateSettings'])->name('settings.update');

        // Categories CRUD (admin-only)
        Route::post('/settings/categories',          [InventoryController::class, 'storeCategory'])->name('settings.categories.store');
        Route::put('/settings/categories/{cat}',     [InventoryController::class, 'updateCategory'])->name('settings.categories.update');
        Route::delete('/settings/categories/{cat}',  [InventoryController::class, 'destroyCategory'])->name('settings.categories.destroy');

        // Locations CRUD (admin-only)
        Route::post('/settings/locations',           [InventoryController::class, 'storeLocation'])->name('settings.locations.store');
        Route::put('/settings/locations/{loc}',      [InventoryController::class, 'updateLocation'])->name('settings.locations.update');
        Route::delete('/settings/locations/{loc}',   [InventoryController::class, 'destroyLocation'])->name('settings.locations.destroy');

        // Sub-types CRUD (admin-only)
        Route::post('/settings/sub-types',           [InventoryController::class, 'storeSubType'])->name('settings.sub-types.store');
        Route::put('/settings/sub-types/{st}',       [InventoryController::class, 'updateSubType'])->name('settings.sub-types.update');
        Route::delete('/settings/sub-types/{st}',    [InventoryController::class, 'destroySubType'])->name('settings.sub-types.destroy');

        // Variants (3rd tier) — CRUD + AJAX loader + inline store
        Route::get('/ajax/variants',                 [InventoryController::class, 'ajaxVariants'])->name('ajax.variants');
        Route::post('/ajax/variants',                [InventoryController::class, 'ajaxStoreVariant'])->name('ajax.variants.store');
        Route::post('/settings/variants',            [InventoryController::class, 'storeVariant'])->name('settings.variants.store');
        Route::put('/settings/variants/{variant}',   [InventoryController::class, 'updateVariant'])->name('settings.variants.update');
        Route::delete('/settings/variants/{variant}',[InventoryController::class, 'destroyVariant'])->name('settings.variants.destroy');

        // Vendor update (edit modal)
        Route::put('/vendors/{vendor}',              [InventoryController::class, 'updateVendor'])->name('vendors.update');

        // GRN — receive against PO
        Route::post('/purchase/{po}/receive',        [InventoryController::class, 'receivePO'])->name('purchase.receive');
        Route::patch('/purchase/{po}/mark-ordered', [InventoryController::class, 'markOrdered'])->name('purchase.markOrdered');
        // PO Edit / Delete
        Route::patch('/purchase/{po}',              [InventoryController::class, 'updatePO'])->name('purchase.update');
        Route::delete('/purchase/{po}',             [InventoryController::class, 'destroyPO'])->name('purchase.destroy');
        // GRN reversal (undo last receipt within correction window)
        Route::delete('/purchase/{po}/grn/last',   [InventoryController::class, 'reverseLastGrn'])->name('purchase.grn.reverse');

        // AJAX — stock availability check
        Route::get('/stock-check',                   [InventoryController::class, 'stockCheck'])->name('stock-check');

        // Phase 1 — Vendor Invoices
        Route::prefix('vendor-invoices')->name('vendor-invoices.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\VendorInvoiceController::class, 'index'])->name('index');
            Route::get('/create',        [\App\Http\Controllers\VendorInvoiceController::class, 'create'])->name('create');
            Route::post('/',             [\App\Http\Controllers\VendorInvoiceController::class, 'store'])->name('store');
            Route::get('/{vendorInvoice}',[\App\Http\Controllers\VendorInvoiceController::class, 'show'])->name('show');
            Route::delete('/{vendorInvoice}',[\App\Http\Controllers\VendorInvoiceController::class, 'destroy'])->name('destroy');
        });

        // Alerts Hub (Phase 1 stub — full build in Phase 4)
        Route::get('/alerts',             [InventoryController::class, 'alerts'])->name('alerts');

        // Implant Registry
        Route::get('/implants',                                   [InventoryController::class, 'implants'])->name('implants');
        Route::post('/implants/catalog',                          [InventoryController::class, 'storeCatalogItem'])->name('implants.catalog.store');
        Route::put('/implants/catalog/{catalogItem}',             [InventoryController::class, 'updateCatalogItem'])->name('implants.catalog.update');
        Route::post('/implants/placements',                       [InventoryController::class, 'storePlacement'])->name('implants.placements.store');
        Route::put('/implants/placements/{placement}',            [InventoryController::class, 'updatePlacement'])->name('implants.placements.update');
    });
    /* ── Tasks Module ── */
    Route::middleware('module:tasks')->prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/',               [\App\Http\Controllers\Communication\TaskController::class, 'index'])->name('index');
        Route::post('/',              [\App\Http\Controllers\Communication\TaskController::class, 'store'])->name('store');
        Route::get('/my',             [\App\Http\Controllers\Communication\TaskController::class, 'myTasks'])->name('mine');
        Route::get('/overdue',        [\App\Http\Controllers\Communication\TaskController::class, 'overdue'])->name('overdue');
        Route::post('/{task}/done',   [\App\Http\Controllers\Communication\TaskController::class, 'markDone'])->name('done');
        Route::post('/{task}/evidence', [\App\Http\Controllers\Communication\TaskController::class, 'uploadEvidence'])->name('evidence');
        Route::post('/{task}/escalate', [\App\Http\Controllers\Communication\TaskController::class, 'escalate'])->name('escalate');
    });

    /* ── Lab Module v2 ── */
    Route::middleware('module:lab')->prefix('lab')->name('lab.')->group(function () {
        Route::get('/',                        [LabController::class, 'index'])->name('index');
        Route::get('/dashboard',               [LabController::class, 'dashboard'])->name('dashboard');
        Route::get('/create',                  [LabController::class, 'create'])->name('create');
        Route::post('/',                       [LabController::class, 'store'])->name('store');
        Route::get('/subtypes',                [LabController::class, 'subtypes'])->name('subtypes');

        // Attachments (static segment before {labCase} wildcard)
        Route::delete('/attachments/{attachment}', [LabController::class, 'attachmentDestroy'])->name('attachments.destroy');

        Route::get('/{labCase}',               [LabController::class, 'show'])->whereNumber('labCase')->name('show');
        Route::get('/{labCase}/edit',          [LabController::class, 'edit'])->name('edit');
        Route::put('/{labCase}',               [LabController::class, 'update'])->name('update');
        Route::post('/{labCase}/status/{to}',  [LabController::class, 'transition'])->name('transition');
        Route::post('/{labCase}/duplicate',    [LabController::class, 'duplicate'])->name('duplicate');
        Route::delete('/{labCase}',            [LabController::class, 'destroy'])->name('destroy');   // archive (soft delete)
        Route::post('/{labCase}/restore',      [LabController::class, 'restore'])->withTrashed()->name('restore');
        Route::post('/{labCase}/attachments',  [LabController::class, 'attachmentStore'])->name('attachments.store');
        Route::get('/{labCase}/print',         [LabController::class, 'print'])->name('print');

        // Prescription routes
        Route::post('/{labCase}/prescription',         [LabController::class, 'prescriptionStore'])->name('prescription.store');
        Route::put('/{labCase}/prescription',          [LabController::class, 'prescriptionUpdate'])->name('prescription.update');

        // Rating route
        Route::post('/{labCase}/rate',                 [LabController::class, 'ratingStore'])->name('rating.store');

        // Prescription template routes (AJAX)
        Route::get('/templates',                       [LabController::class, 'templateIndex'])->name('templates.index');
        Route::post('/templates',                      [LabController::class, 'templateStore'])->name('templates.store');
        Route::delete('/templates/{template}',         [LabController::class, 'templateDestroy'])->name('templates.destroy');
    });

    /* ── Lab Monthly Reconciliation (Phase 2) ── */
    Route::middleware('module:lab')->prefix('lab/reconciliation')->name('lab.reconciliation.')->group(function () {
        Route::get('/',                                    [\App\Http\Controllers\LabReconciliationController::class, 'index'])->name('index');
        Route::get('/create',                              [\App\Http\Controllers\LabReconciliationController::class, 'create'])->name('create');
        Route::post('/',                                   [\App\Http\Controllers\LabReconciliationController::class, 'store'])->name('store');
        Route::get('/eligible-cases',                      [\App\Http\Controllers\LabReconciliationController::class, 'eligibleCases'])->name('eligible-cases');
        Route::get('/{reconciliation}',                    [\App\Http\Controllers\LabReconciliationController::class, 'show'])->name('show');
        Route::post('/{reconciliation}/submit',            [\App\Http\Controllers\LabReconciliationController::class, 'submit'])->name('submit');
        Route::post('/{reconciliation}/approve',           [\App\Http\Controllers\LabReconciliationController::class, 'approve'])->name('approve');
        Route::post('/{reconciliation}/dispute',           [\App\Http\Controllers\LabReconciliationController::class, 'dispute'])->name('dispute');
        Route::post('/{reconciliation}/items/{item}/update', [\App\Http\Controllers\LabReconciliationController::class, 'updateItem'])->name('items.update');
        Route::delete('/{reconciliation}',                 [\App\Http\Controllers\LabReconciliationController::class, 'destroy'])->name('destroy');
    });

    /* ── Lab Vendors master (Phase 1 enhanced) ── */
    Route::middleware('module:lab')->prefix('lab-vendors')->name('lab-vendors.')->group(function () {
        Route::get('/',                 [\App\Http\Controllers\LabVendorController::class, 'index'])->name('index');
        Route::post('/',                [\App\Http\Controllers\LabVendorController::class, 'store'])->name('store');
        Route::put('/{labVendor}',      [\App\Http\Controllers\LabVendorController::class, 'update'])->name('update');
        Route::delete('/{labVendor}',   [\App\Http\Controllers\LabVendorController::class, 'destroy'])->name('destroy');

        // Phase 1 — Contacts
        Route::post('/{labVendor}/contacts',                    [\App\Http\Controllers\LabVendorController::class, 'storeContact'])->name('contacts.store');
        Route::put('/{labVendor}/contacts/{contact}',           [\App\Http\Controllers\LabVendorController::class, 'updateContact'])->name('contacts.update');
        Route::delete('/{labVendor}/contacts/{contact}',        [\App\Http\Controllers\LabVendorController::class, 'destroyContact'])->name('contacts.destroy');

        // Phase 1 — Services
        Route::post('/{labVendor}/services',                    [\App\Http\Controllers\LabVendorController::class, 'storeService'])->name('services.store');
        Route::put('/{labVendor}/services/{service}',           [\App\Http\Controllers\LabVendorController::class, 'updateService'])->name('services.update');
        Route::delete('/{labVendor}/services/{service}',        [\App\Http\Controllers\LabVendorController::class, 'destroyService'])->name('services.destroy');
    });

    /* ── Patient-nested lab cases (used by patient profile Lab tab) ── */
    Route::middleware('module:lab')->prefix('patients/{patient}/lab-cases')->name('patients.lab.')->group(function () {
        Route::get('/',  [LabController::class, 'patientCases'])->name('index');
        Route::post('/', [LabController::class, 'store'])->name('store');
    });
    /* ── Accounts & Finance Module ── */
    Route::middleware('module:finance')->prefix('finance')->name('finance.')->group(function () {

        // Dashboard
        Route::get('/',                     [FinanceController::class, 'dashboard'])->name('dashboard');

        // Income (tabs: invoices | receipts | bills | trash)
        Route::get('/income',                           [FinanceController::class, 'income'])->name('income');
        Route::get('/income/export',                    [FinanceController::class, 'incomeExport'])->name('income.export');
        Route::post('/income/trash/invoice/{id}/restore', [FinanceController::class, 'restoreInvoice'])->name('income.trash.invoice.restore');
        Route::post('/income/trash/receipt/{id}/restore', [FinanceController::class, 'restoreReceipt'])->name('income.trash.receipt.restore');
        Route::post('/income/trash/bill/{id}/restore',    [FinanceController::class, 'restoreBill'])->name('income.trash.bill.restore');

        // Expenses
        Route::get('/expenses',             [FinanceController::class, 'expenses'])->name('expenses');
        Route::get('/expenses/create',      [FinanceController::class, 'expenseCreate'])->name('expenses.create');
        Route::post('/expenses/scan',       [FinanceController::class, 'expenseScan'])->name('expenses.scan'); // 📷 read a bill photo
        Route::post('/expenses',            [FinanceController::class, 'expenseStore'])->name('expenses.store');
        Route::get('/expenses/{expense}/edit',   [FinanceController::class, 'expenseEdit'])->name('expenses.edit');
        Route::put('/expenses/{expense}',        [FinanceController::class, 'expenseUpdate'])->name('expenses.update');
        Route::post('/expenses/{expense}/mark-paid', [FinanceController::class, 'expenseMarkPaid'])->name('expenses.mark-paid');
        Route::get('/expenses/export',      [FinanceController::class, 'expenseExport'])->name('expenses.export');
        Route::delete('/expenses/{expense}', [FinanceController::class, 'expenseDestroy'])->name('expenses.destroy');

        // Expense Categories — inline quick-add from the Expense form
        Route::post('/expense-categories', [FinanceController::class, 'categoryStore'])->name('expense-categories.store');

        // Vendors
        Route::get('/vendors',              [FinanceController::class, 'vendors'])->name('vendors');
        Route::get('/vendors/create',       [FinanceController::class, 'vendorCreate'])->name('vendors.create');
        Route::post('/vendors',             [FinanceController::class, 'vendorStore'])->name('vendors.store');
        Route::get('/vendors/{vendor}/edit', [FinanceController::class, 'vendorEdit'])->name('vendors.edit');
        Route::put('/vendors/{vendor}',     [FinanceController::class, 'vendorUpdate'])->name('vendors.update');
        Route::delete('/vendors/{vendor}',  [FinanceController::class, 'vendorDestroy'])->name('vendors.destroy');

        // Payroll
        Route::get('/payroll',              [FinanceController::class, 'payroll'])->name('payroll');
        Route::post('/payroll',             [FinanceController::class, 'payrollStore'])->name('payroll.store');
        Route::delete('/payroll/{payroll}', [FinanceController::class, 'payrollDestroy'])->name('payroll.destroy');

        // Cashbook & Banking
        Route::get('/cashbook',             [FinanceController::class, 'cashbook'])->name('cashbook');
        Route::get('/banking',              [FinanceController::class, 'banking'])->name('banking');

        // CA Export
        Route::get('/ca-export',            [FinanceController::class, 'caExport'])->name('ca-export');

        // GST
        Route::get('/gst',                  [FinanceController::class, 'gst'])->name('gst');

        // Reports
        Route::get('/reports',              [\App\Http\Controllers\Finance\FinanceReportsController::class, 'index'])->name('reports');

        // Vouchers
        Route::prefix('vouchers')->name('vouchers.')->group(function () {
            Route::get('/',                 [\App\Http\Controllers\Finance\VoucherController::class, 'index'])->name('index');
            // NOTE: /export must be declared BEFORE /{voucher}, otherwise the
            // word "export" is treated as a {voucher} id and the page 404s.
            Route::get('/export',           [\App\Http\Controllers\Finance\VoucherController::class, 'export'])->name('export');
            Route::get('/{voucher}',        [\App\Http\Controllers\Finance\VoucherController::class, 'show'])->name('show');
            Route::get('/{voucher}/print',  [\App\Http\Controllers\Finance\VoucherController::class, 'printView'])->name('print');
        });

        // Wallet (global views)
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Finance\WalletController::class, 'index'])->name('index');
            Route::get('/register',        [\App\Http\Controllers\Finance\WalletController::class, 'register'])->name('register');
            Route::get('/register/export', [\App\Http\Controllers\Finance\WalletController::class, 'registerExport'])->name('register.export');
        });

        // Wallet (patient-scoped)
        Route::prefix('wallets/{patient}')->name('wallets.')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Finance\WalletController::class, 'show'])->name('show');
            Route::get('/credit',        [\App\Http\Controllers\Finance\WalletController::class, 'creditForm'])->name('credit-form');
            Route::post('/credit',       [\App\Http\Controllers\Finance\WalletController::class, 'credit'])->name('credit');
            Route::get('/credit-note/{transaction}', [\App\Http\Controllers\Finance\WalletController::class, 'creditNote'])->name('credit-note');
            // Direct wallet money movement (permission-gated, finance-mirrored, audited)
            Route::post('/receive-advance', [\App\Http\Controllers\Finance\WalletController::class, 'receiveAdvance'])->name('receive-advance');
            Route::post('/refund',          [\App\Http\Controllers\Finance\WalletController::class, 'refund'])->name('refund');
            Route::post('/adjust',          [\App\Http\Controllers\Finance\WalletController::class, 'adjust'])->name('adjust');
        });

        // Wallet Campaigns
        Route::prefix('wallet-campaigns')->name('wallet-campaigns.')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\Finance\WalletCampaignController::class, 'index'])->name('index');
            Route::get('/create',                     [\App\Http\Controllers\Finance\WalletCampaignController::class, 'create'])->name('create');
            Route::post('/',                          [\App\Http\Controllers\Finance\WalletCampaignController::class, 'store'])->name('store');
            Route::get('/{walletCampaign}',           [\App\Http\Controllers\Finance\WalletCampaignController::class, 'show'])->name('show');
            Route::post('/preview',                   [\App\Http\Controllers\Finance\WalletCampaignController::class, 'preview'])->name('preview');
            Route::post('/{walletCampaign}/apply',    [\App\Http\Controllers\Finance\WalletCampaignController::class, 'apply'])->name('apply');
            Route::post('/{walletCampaign}/cancel',   [\App\Http\Controllers\Finance\WalletCampaignController::class, 'cancel'])->name('cancel');
        });

        // Membership Plans
        Route::prefix('membership')->name('membership.')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\Finance\MembershipController::class, 'index'])->name('index');
            Route::get('/create',                     [\App\Http\Controllers\Finance\MembershipController::class, 'create'])->name('create');
            Route::post('/',                          [\App\Http\Controllers\Finance\MembershipController::class, 'store'])->name('store');
            Route::get('/members',                    [\App\Http\Controllers\Finance\MembershipController::class, 'members'])->name('members');
            Route::delete('/enrollment/{enrollment}', [\App\Http\Controllers\Finance\MembershipController::class, 'destroyEnrollment'])->name('enrollment.destroy');
            Route::get('/{membership}/edit',          [\App\Http\Controllers\Finance\MembershipController::class, 'edit'])->name('edit');
            Route::put('/{membership}',               [\App\Http\Controllers\Finance\MembershipController::class, 'update'])->name('update');
            Route::post('/{membership}/toggle',       [\App\Http\Controllers\Finance\MembershipController::class, 'toggle'])->name('toggle');
            Route::delete('/{membership}',            [\App\Http\Controllers\Finance\MembershipController::class, 'destroy'])->name('destroy');
        });

        // Coupons
        Route::prefix('coupons')->name('coupons.')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Finance\CouponController::class, 'index'])->name('index');
            Route::get('/create',              [\App\Http\Controllers\Finance\CouponController::class, 'create'])->name('create');
            Route::post('/',                   [\App\Http\Controllers\Finance\CouponController::class, 'store'])->name('store');
            Route::get('/{coupon}/edit',       [\App\Http\Controllers\Finance\CouponController::class, 'edit'])->name('edit');
            Route::put('/{coupon}',            [\App\Http\Controllers\Finance\CouponController::class, 'update'])->name('update');
            Route::post('/{coupon}/toggle',    [\App\Http\Controllers\Finance\CouponController::class, 'toggle'])->name('toggle');
            Route::delete('/{coupon}',         [\App\Http\Controllers\Finance\CouponController::class, 'destroy'])->name('destroy');
        });

        // Analytics
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/',             [AnalyticsController::class, 'index'])->name('index');
            Route::get('/vendors',      [AnalyticsController::class, 'vendorAnalytics'])->name('vendors');
            Route::get('/expenses',     [AnalyticsController::class, 'expenseAnalytics'])->name('expenses');
            Route::get('/lab',          [AnalyticsController::class, 'labAnalytics'])->name('lab');
            Route::get('/procurement',  [AnalyticsController::class, 'procurementAnalytics'])->name('procurement');
            Route::get('/cashflow',     [AnalyticsController::class, 'cashflow'])->name('cashflow');
            Route::get('/outstanding',  [AnalyticsController::class, 'outstanding'])->name('outstanding');
            Route::get('/bi',           [AnalyticsController::class, 'businessIntelligence'])->name('bi');
            Route::get('/audit',        [AnalyticsController::class, 'auditLog'])->name('audit');
        });

    }); // end finance group

    // HR Module
    Route::middleware('module:hr')->prefix('hr')->name('hr.')->group(function () {

        // Dashboard
        Route::get('/', [\App\Http\Controllers\HR\HrDashboardController::class, 'index'])
             ->name('dashboard');

        // Staff profiles (CRUD — destroy = deactivate, not delete)
        Route::resource('staff', \App\Http\Controllers\HR\HrStaffController::class)
             ->parameters(['staff' => 'user']);

        // HPR / Health ID capture for a clinician (local, no live ABDM)
        Route::get  ('staff/{user}/hpr', [\App\Http\Controllers\Abdm\DoctorHprController::class, 'edit'])->name('staff.hpr.edit');
        Route::patch('staff/{user}/hpr', [\App\Http\Controllers\Abdm\DoctorHprController::class, 'update'])->name('staff.hpr.update');

        // Staff documents
        Route::post('staff/{user}/documents',              [\App\Http\Controllers\HR\HrStaffController::class, 'storeDocument'])->name('staff.documents.store');
        Route::delete('staff/{user}/documents/{document}', [\App\Http\Controllers\HR\HrStaffController::class, 'destroyDocument'])->name('staff.documents.destroy');

        // Staff finance
        Route::post('staff/{user}/finance/salary',                    [\App\Http\Controllers\HR\HrFinanceController::class, 'saveSalary'])->name('staff.finance.salary');
        Route::post('staff/{user}/finance/incentive',                 [\App\Http\Controllers\HR\HrFinanceController::class, 'saveIncentive'])->name('staff.finance.incentive');
        Route::post('staff/{user}/finance/advances',                  [\App\Http\Controllers\HR\HrFinanceController::class, 'storeAdvance'])->name('staff.finance.advances.store');
        Route::post('staff/{user}/finance/advances/{advance}/close',  [\App\Http\Controllers\HR\HrFinanceController::class, 'closeAdvance'])->name('staff.finance.advances.close');
        Route::post('staff/{user}/finance/bonuses',                   [\App\Http\Controllers\HR\HrFinanceController::class, 'storeBonus'])->name('staff.finance.bonuses.store');
        Route::delete('staff/{user}/finance/bonuses/{bonus}',         [\App\Http\Controllers\HR\HrFinanceController::class, 'destroyBonus'])->name('staff.finance.bonuses.destroy');

        // Attendance (Part B)
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/',           [\App\Http\Controllers\HR\HrAttendanceController::class, 'index'])->name('index');
            Route::post('/mark',      [\App\Http\Controllers\HR\HrAttendanceController::class, 'mark'])->name('mark');
            Route::post('/mark-bulk', [\App\Http\Controllers\HR\HrAttendanceController::class, 'markBulk'])->name('mark-bulk');
        });

        // Roles & Permissions (moved here from Settings). Admin-only, hard
        // rule — not toggleable via the hr module permission, since whoever
        // can reach this could otherwise grant themselves Admin.
        Route::middleware('admin.only')->prefix('roles')->name('roles.')->group(function () {
            Route::get('/',                   [\App\Http\Controllers\HR\HrRoleController::class, 'index'])->name('index');
            Route::post('/',                  [\App\Http\Controllers\Settings\RolePermissionController::class, 'store'])->name('store');
            Route::get('/{role}',             [\App\Http\Controllers\Settings\RolePermissionController::class, 'show'])->name('show');
            Route::post('/{role}',            [\App\Http\Controllers\Settings\RolePermissionController::class, 'update'])->name('update');
            Route::delete('/{role}',          [\App\Http\Controllers\Settings\RolePermissionController::class, 'destroy'])->name('destroy');
            Route::get('/{role}/permissions', [\App\Http\Controllers\Settings\RolePermissionController::class, 'permissions'])->name('permissions');
        });

        // Training Sessions
        Route::prefix('training')->name('training.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\HR\HrTrainingController::class, 'index'])->name('index');
            Route::get('/create',                               [\App\Http\Controllers\HR\HrTrainingController::class, 'create'])->name('create');
            Route::post('/',                                    [\App\Http\Controllers\HR\HrTrainingController::class, 'store'])->name('store');
            Route::get('/{session}',                            [\App\Http\Controllers\HR\HrTrainingController::class, 'show'])->name('show');
            Route::get('/{session}/edit',                       [\App\Http\Controllers\HR\HrTrainingController::class, 'edit'])->name('edit');
            Route::put('/{session}',                            [\App\Http\Controllers\HR\HrTrainingController::class, 'update'])->name('update');
            Route::delete('/{session}',                         [\App\Http\Controllers\HR\HrTrainingController::class, 'destroy'])->name('destroy');
            Route::post('/{session}/enroll',                    [\App\Http\Controllers\HR\HrTrainingController::class, 'enroll'])->name('enroll');
            Route::delete('/{session}/enroll/{user}',           [\App\Http\Controllers\HR\HrTrainingController::class, 'unenroll'])->name('unenroll');
            Route::post('/{session}/attendance',                [\App\Http\Controllers\HR\HrTrainingController::class, 'markAttendance'])->name('attendance');
            Route::post('/{session}/complete',                  [\App\Http\Controllers\HR\HrTrainingController::class, 'markComplete'])->name('complete');
        });

        // Periodic Training Requirements & Compliance
        Route::prefix('periodic-training')->name('periodic.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\HR\HrTrainingController::class, 'periodicIndex'])->name('index');
            Route::post('/requirements',                        [\App\Http\Controllers\HR\HrTrainingController::class, 'storeRequirement'])->name('requirements.store');
            Route::delete('/requirements/{requirement}',        [\App\Http\Controllers\HR\HrTrainingController::class, 'destroyRequirement'])->name('requirements.destroy');
            Route::post('/records',                             [\App\Http\Controllers\HR\HrTrainingController::class, 'storeRecord'])->name('records.store');
        });

        // Staff Calendar
        Route::get('/calendar',                                 [\App\Http\Controllers\HR\HrCalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/events',                          [\App\Http\Controllers\HR\HrCalendarController::class, 'events'])->name('calendar.events');

        // Performance Memos
        Route::prefix('memos')->name('memos.')->group(function () {
            Route::get('/',                                     [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'index'])->name('index');
            Route::get('/create',                               [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'create'])->name('create');
            Route::post('/',                                    [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'store'])->name('store');
            Route::get('/{memo}',                               [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'show'])->name('show');
            Route::delete('/{memo}',                            [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'destroy'])->name('destroy');
            Route::post('/{memo}/acknowledge',                  [\App\Http\Controllers\HR\HrPerformanceMemoController::class, 'acknowledge'])->name('acknowledge');
        });

    }); // end hr group

    // QR Check-in endpoint — accessible by Android app (token-based, no login)
    Route::get('/hr/checkin/{token}', [\App\Http\Controllers\HR\HrAttendanceController::class, 'qrCheckin'])
         ->name('hr.attendance.qr-checkin')
         ->withoutMiddleware('auth');

}); // end auth middleware

// ── AI Assistant "Tulip" (app-wide copilot) ─────────────────────────────────
Route::middleware('auth')->prefix('assistant')->name('assistant.')->group(function () {
    Route::post('/chat', [\App\Http\Controllers\AiAssistantController::class, 'chat'])->name('chat');
    Route::get('/conversation/{conversation}', [\App\Http\Controllers\AiAssistantController::class, 'show'])->name('show');
    Route::post('/confirm/{action}', [\App\Http\Controllers\AiAssistantController::class, 'confirm'])->name('confirm');
    Route::post('/reject/{action}', [\App\Http\Controllers\AiAssistantController::class, 'reject'])->name('reject');
    Route::post('/transcribe', [\App\Http\Controllers\AiAssistantController::class, 'transcribe'])->name('transcribe');
});

// ── Secure clinical media (Phase A) ──────────────────────────────────────────
// Patient files (x-rays, scans, intake forms, photos, PDFs) are served ONLY
// through these authenticated, branch-checked routes — never via a public
// /storage URL. Downloads (?dl=1) are written to the audit log.
Route::middleware('auth')->prefix('secure-media')->name('secure.media.')->group(function () {
    Route::get('/file/{clinicalFile}',    [\App\Http\Controllers\SecureMediaController::class, 'file'])->name('file');
    Route::get('/legacy/{clinicalMedia}', [\App\Http\Controllers\SecureMediaController::class, 'legacy'])->name('legacy');
});
