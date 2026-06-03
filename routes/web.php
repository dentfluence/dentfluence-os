<?php

use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientNoteController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\TreatmentCategoryController;
use App\Http\Controllers\InventoryController;


/* ────────────────────────────────────────────────────────────────
   ROOT
──────────────────────────────────────────────────────────────── */

Route::get('/', fn() => redirect()->route('login'));

/* ────────────────────────────────────────────────────────────────
   GUEST
──────────────────────────────────────────────────────────────── */
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

/* ────────────────────────────────────────────────────────────────
   AUTHENTICATED
──────────────────────────────────────────────────────────────── */
Route::middleware('auth')->group(function () {

    /* ── Auth ── */
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    /* ── Dashboard ── */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ── Patients ── */
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/',               [PatientController::class, 'index'])->name('index');
        Route::get('/search',         [PatientController::class, 'search'])->name('search');
        Route::get('/create',         [PatientController::class, 'create'])->name('create');
        Route::post('/',              [PatientController::class, 'store'])->name('store');
        Route::get('/{patient}',      [PatientController::class, 'show'])->name('show');
        Route::get('/{patient}/edit', [PatientController::class, 'edit'])->name('edit');
        Route::patch('/{patient}',    [PatientController::class, 'update'])->name('update');
        Route::delete('/{patient}',   [PatientController::class, 'destroy'])->name('destroy');

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

        // Tags
        Route::get('/{patient}/tags',          [TagController::class, 'forPatient'])->name('tags.index');
        Route::post('/{patient}/tags/attach',  [TagController::class, 'attach'])->name('tags.attach');
        Route::delete('/{patient}/tags/{tag}', [TagController::class, 'detach'])->name('tags.detach');

        // Consultations nested under patient
        Route::get('/{patient}/consultations/create', [ConsultationController::class, 'create'])->name('consultations.create');
        Route::get('/{patient}/consultations',        [ConsultationController::class, 'forPatient'])->name('consultations.index');
    });

    /* ── Consultations (standalone) ── */
    Route::prefix('consultations')->name('consultations.')->group(function () {
        Route::post('/',                   [ConsultationController::class, 'store'])->name('store');
        Route::get('/{consultation}',      [ConsultationController::class, 'show'])->name('show');
        Route::get('/{consultation}/edit', [ConsultationController::class, 'edit'])->name('edit');
        Route::put('/{consultation}',      [ConsultationController::class, 'update'])->name('update');
        Route::delete('/{consultation}',   [ConsultationController::class, 'destroy'])->name('destroy');
    });

    // Treatment Plans — patient scoped
    Route::post('/patients/{patient}/treatment-plans',            [TreatmentPlanController::class, 'store'])->name('treatment-plans.store');
    Route::get('/patients/{patient}/treatment-plans',             [TreatmentPlanController::class, 'index'])->name('treatment-plans.index');
    Route::post('/patients/{patient}/treatment-plans/ai-suggest', [TreatmentPlanController::class, 'aiSuggest'])->name('treatment-plans.ai-suggest');

    // Treatment Plans — plan scoped
    Route::put('/treatment-plans/{plan}',         [TreatmentPlanController::class, 'update'])->name('treatment-plans.update');
    Route::delete('/treatment-plans/{plan}',      [TreatmentPlanController::class, 'destroy'])->name('treatment-plans.destroy');
    Route::delete('/treatment-plan-items/{item}', [TreatmentPlanController::class, 'destroyItem'])->name('treatment-plan-items.destroy');

    // Treatment Visits
    Route::post('/patients/{patient}/visits', [App\Http\Controllers\TreatmentVisitController::class, 'store'])->name('visits.store');
    Route::put('/visits/{visit}',             [App\Http\Controllers\TreatmentVisitController::class, 'update'])->name('visits.update');
    Route::delete('/visits/{visit}',          [App\Http\Controllers\TreatmentVisitController::class, 'destroy'])->name('visits.destroy');
    Route::get('/visits/{visit}/print',       [App\Http\Controllers\TreatmentVisitController::class, 'print'])->name('visits.print');

    // Print routes
    Route::get('/consultations/{consultation}/print', [App\Http\Controllers\ConsultationController::class, 'print'])->name('consultations.print');
    Route::get('/patients/{patient}/print',           [App\Http\Controllers\PatientController::class, 'print'])->name('patients.print');
    /* ── Appointments ── */
    Route::prefix('appointments')->name('appointments.')->group(function () {
        Route::get('/today',                    [AppointmentController::class, 'today'])->name('today');
        Route::get('/',                         [AppointmentController::class, 'index'])->name('index');
        Route::get('/create',                   [AppointmentController::class, 'create'])->name('create');
        Route::post('/',                        [AppointmentController::class, 'store'])->name('store');
        Route::get('/queue/today',              [AppointmentController::class, 'todayQueue'])->name('queue.today');
        Route::get('/status-counts',            [AppointmentController::class, 'statusCounts'])->name('status.counts');
        Route::get('/check-conflict',           [AppointmentController::class, 'checkConflict'])->name('check.conflict');
        Route::get('/{appointment}',            [AppointmentController::class, 'show'])->name('show');
        Route::get('/{appointment}/quick',      [AppointmentController::class, 'quickView'])->name('quick');
        Route::get('/{appointment}/edit',       [AppointmentController::class, 'edit'])->name('edit');
        Route::patch('/{appointment}',          [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}',         [AppointmentController::class, 'destroy'])->name('destroy');
        Route::patch('/{appointment}/status',   [AppointmentController::class, 'updateStatus'])->name('updateStatus');
    });



    Route::resource('patients.consultations', ConsultationController::class)
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->middleware('auth');

    /* ── Treatment Categories ── */
    Route::get('/treatment-categories',                       [TreatmentCategoryController::class, 'index']);
    Route::get('/treatment-categories/{category}/treatments', [TreatmentCategoryController::class, 'treatments']);

    require __DIR__ . '/../app/Modules/Huddle/Routes/huddle.php';

    /* ── Settings (unified) ── */
    Route::get('/settings',                       [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/clinic',               [\App\Http\Controllers\Settings\SettingsController::class, 'saveClinic'])->name('settings.clinic.save');
    Route::post('/settings/notifications',        [\App\Http\Controllers\Settings\SettingsController::class, 'saveNotifications'])->name('settings.notifications.save');
    Route::post('/settings/billing',              [\App\Http\Controllers\Settings\SettingsController::class, 'saveBilling'])->name('settings.billing.save');
    Route::post('/settings/print',               [\App\Http\Controllers\Settings\SettingsController::class, 'savePrint'])->name('settings.print.save');
    Route::post('/settings/staff',                [\App\Http\Controllers\Settings\SettingsController::class, 'storeStaff'])->name('settings.staff.store');
    Route::post('/settings/staff/{user}/toggle',  [\App\Http\Controllers\Settings\SettingsController::class, 'toggleStaff'])->name('settings.staff.toggle');
    Route::post('/settings/staff/{user}/role',    [\App\Http\Controllers\Settings\SettingsController::class, 'updateStaffRole'])->name('settings.staff.role');

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

    // Roles & Permissions API (used by the settings roles tab via fetch)
    Route::prefix('settings/roles')->name('settings.roles.')->group(function () {
        Route::post('/',             [\App\Http\Controllers\Settings\RolePermissionController::class, 'store'])->name('store');
        Route::get('/{role}',        [\App\Http\Controllers\Settings\RolePermissionController::class, 'show'])->name('show');
        Route::post('/{role}',       [\App\Http\Controllers\Settings\RolePermissionController::class, 'update'])->name('update');
        Route::delete('/{role}',     [\App\Http\Controllers\Settings\RolePermissionController::class, 'destroy'])->name('destroy');
    });

    // Tags
    Route::prefix('settings/tags')->name('settings.tags.')->group(function () {
        Route::get('/',         [TagController::class, 'index'])->name('index');
        Route::post('/',        [TagController::class, 'store'])->name('store');
        Route::put('/{tag}',    [TagController::class, 'update'])->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
    });

    /* ── Treatments Module (Clinic Knowledge Base) ── */
    Route::prefix('treatments')->name('treatments.')->group(function () {
        $tc = \App\Http\Controllers\TreatmentController::class;

        Route::get('/',                                  [$tc, 'index'])->name('index');
        Route::get('/create',                            [$tc, 'create'])->name('create');
        Route::post('/',                                 [$tc, 'store'])->name('store');
        Route::get('/{treatment}',                       [$tc, 'show'])->name('show');
        Route::put('/{treatment}',                       [$tc, 'update'])->name('update');
        Route::delete('/{treatment}',                    [$tc, 'destroy'])->name('destroy');

        // SOP
        Route::post('/{treatment}/sop',                  [$tc, 'saveSop'])->name('sop.save');

        // Rules
        Route::post('/{treatment}/rules',                [$tc, 'saveRules'])->name('rules.save');

        // Media
        Route::post('/{treatment}/media',                [$tc, 'uploadMedia'])->name('media.upload');
        Route::delete('/media/{media}',                  [$tc, 'deleteMedia'])->name('media.delete');

        // Review
        Route::post('/{treatment}/review',               [$tc, 'markReviewed'])->name('review.mark');

        // API — for treatment plan builder / billing auto-fill
        Route::get('/{treatment}/api',                   [$tc, 'apiDetail'])->name('api.detail');
    });

    // Billing — wired to BillingController (view built in Session 5)
    Route::get('/billing',        [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/create', [\App\Http\Controllers\BillingController::class, 'create'])->name('billing.create');

    // CRM — stub so sidebar doesn't crash (built in Session 6)
    Route::get('/crm',       fn() => view('crm.index'))->name('crm.index');

    // Analytics — stub so sidebar doesn't crash (built in Session 6)
    Route::get('/analytics', fn() => 'Coming soon')->name('analytics.index');

    // Marketing alias — content-management tab (cms.marketing = cms.index?tab=marketing)
    Route::get('/marketing', fn() => redirect()->route('cms.marketing'))->name('marketing.index');
    /* ── Inventory Module ── */
    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Dashboard
        Route::get('/',               [InventoryController::class, 'dashboard'])->name('index');
        Route::get('/dashboard',      [InventoryController::class, 'dashboard'])->name('dashboard');

        // Stock view (current qty + quick +/- adjust)
        Route::get('/items',               [InventoryController::class, 'items'])->name('items');
        Route::post('/items/{item}/adjust',[InventoryController::class, 'adjustStock'])->name('items.adjust');

        // Product Master — add/edit/delete products (not stock)
        Route::get('/products',            [InventoryController::class, 'products'])->name('products');
        Route::post('/products',           [InventoryController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{item}',     [InventoryController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{item}',  [InventoryController::class, 'destroyProduct'])->name('products.destroy');

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
        Route::get('/reusable-assets',[InventoryController::class, 'reusableAssets'])->name('reusable-assets');
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

        // Vendor update (edit modal)
        Route::put('/vendors/{vendor}',              [InventoryController::class, 'updateVendor'])->name('vendors.update');

        // GRN — receive against PO
        Route::post('/purchase/{po}/receive',        [InventoryController::class, 'receivePO'])->name('purchase.receive');

        // AJAX — stock availability check
        Route::get('/stock-check',                   [InventoryController::class, 'stockCheck'])->name('stock-check');

        // Implant Registry
        Route::get('/implants',                                   [InventoryController::class, 'implants'])->name('implants');
        Route::post('/implants/catalog',                          [InventoryController::class, 'storeCatalogItem'])->name('implants.catalog.store');
        Route::put('/implants/catalog/{catalogItem}',             [InventoryController::class, 'updateCatalogItem'])->name('implants.catalog.update');
        Route::post('/implants/placements',                       [InventoryController::class, 'storePlacement'])->name('implants.placements.store');
        Route::put('/implants/placements/{placement}',            [InventoryController::class, 'updatePlacement'])->name('implants.placements.update');
    });
    /* ── Tasks Module ── */
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/',               [\App\Http\Controllers\Communication\TaskController::class, 'index'])->name('index');
        Route::post('/',              [\App\Http\Controllers\Communication\TaskController::class, 'store'])->name('store');
        Route::get('/my',             [\App\Http\Controllers\Communication\TaskController::class, 'myTasks'])->name('mine');
        Route::get('/overdue',        [\App\Http\Controllers\Communication\TaskController::class, 'overdue'])->name('overdue');
        Route::post('/{task}/done',   [\App\Http\Controllers\Communication\TaskController::class, 'markDone'])->name('done');
        Route::post('/{task}/escalate', [\App\Http\Controllers\Communication\TaskController::class, 'escalate'])->name('escalate');
    });

    /* ── Lab Module (Session 7) ── */
    Route::prefix('lab')->name('lab.')->group(function () {
        Route::get('/',             [LabController::class, 'index'])->name('index');
        Route::post('/',            [LabController::class, 'store'])->name('store');
        Route::put('/{labCase}',    [LabController::class, 'update'])->name('update');
        Route::delete('/{labCase}', [LabController::class, 'destroy'])->name('destroy');
    });

    /* ── Patient-nested lab cases (used by patient profile Lab tab) ── */
    Route::prefix('patients/{patient}/lab-cases')->name('patients.lab.')->group(function () {
        Route::get('/',  [LabController::class, 'patientCases'])->name('index');
        Route::post('/', [LabController::class, 'store'])->name('store');
    });
    /* ── Accounts & Finance Module ── */
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/',          [FinanceController::class, 'dashboard'])->name('dashboard');
        Route::get('/income',    [FinanceController::class, 'income'])->name('income');
        Route::get('/expenses',  [FinanceController::class, 'expenses'])->name('expenses');
        Route::get('/vendors',   [FinanceController::class, 'vendors'])->name('vendors');
        Route::get('/payroll',   [FinanceController::class, 'payroll'])->name('payroll');
        Route::get('/cashbook',  [FinanceController::class, 'cashbook'])->name('cashbook');
        Route::get('/banking',   [FinanceController::class, 'banking'])->name('banking');
        Route::get('/ca-export', [FinanceController::class, 'caExport'])->name('ca-export');
        Route::get('/gst',       [FinanceController::class, 'gst'])->name('gst');
    });

    Route::get('/crm',           fn() => 'Coming soon')->name('crm.index');
    Route::get('/reports',       [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/analytics',     fn() => 'Coming soon')->name('analytics.index');
    Route::get('/notifications', fn() => 'Coming soon')->name('notifications.index');
    Route::get('/profile/edit',  fn() => 'Coming soon')->name('profile.edit');
    Route::get('/help',          fn() => 'Coming soon')->name('help.index');

    /* ── Communication OS Module Routes ── */
    require __DIR__ . '/communication.php';
    require __DIR__ . '/prm.php';
    require __DIR__ . '/timeline.php';
    require __DIR__ . '/content-management.php';
});
