<?php
use App\Http\Controllers\TagController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\TreatmentPlanController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientNoteController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TreatmentCategoryController;


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
    Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');

    /* ── Dashboard ── */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ── Patients ── */
    Route::prefix('patients')->name('patients.')->group(function () {
        Route::get('/',                  [PatientController::class, 'index'])->name('index');
        Route::get('/search',            [PatientController::class, 'search'])->name('search');
        Route::get('/create',            [PatientController::class, 'create'])->name('create');
        Route::post('/',                 [PatientController::class, 'store'])->name('store');
        Route::get('/{patient}',         [PatientController::class, 'show'])->name('show');
        Route::get('/{patient}/edit',    [PatientController::class, 'edit'])->name('edit');
        Route::patch('/{patient}',       [PatientController::class, 'update'])->name('update');
        Route::delete('/{patient}',      [PatientController::class, 'destroy'])->name('destroy');

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
    Route::post(
    '/consultations/{consultation}/plans',
    [TreatmentPlanController::class, 'store']
)->name('treatment-plans.store');

Route::delete(
    '/plans/{plan}',
    [TreatmentPlanController::class, 'destroy']
)->name('treatment-plans.destroy');

    /* ── Appointments ── */
    Route::prefix('appointments')->name('appointments.')->group(function () {
        Route::get('/today',                  [AppointmentController::class, 'today'])->name('today');
        Route::get('/',                       [AppointmentController::class, 'index'])->name('index');
        Route::get('/create',                 [AppointmentController::class, 'create'])->name('create');
        Route::post('/',                      [AppointmentController::class, 'store'])->name('store');
        Route::get('/queue/today',            [AppointmentController::class, 'todayQueue'])->name('appointments.queue.today');
        Route::get('/{appointment}',          [AppointmentController::class, 'show'])->name('show');
        Route::get('/{appointment}/edit',     [AppointmentController::class, 'edit'])->name('edit');
        Route::patch('/{appointment}',        [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}',       [AppointmentController::class, 'destroy'])->name('destroy');
        Route::patch('/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('updateStatus');
    });


Route::prefix('appointments')->name('appointments.')->middleware('auth')->group(function () {

    // Core CRUD
    Route::get('/',             [AppointmentController::class, 'index'])->name('index');
    Route::get('/create',       [AppointmentController::class, 'create'])->name('create');
    Route::post('/',            [AppointmentController::class, 'store'])->name('store');
    Route::get('/today',        [AppointmentController::class, 'today'])->name('today');

    // Phase 2 — operational endpoints
    Route::get('/queue/today',      [AppointmentController::class, 'todayQueue'])->name('queue.today');
    Route::get('/status-counts',    [AppointmentController::class, 'statusCounts'])->name('status.counts');
    Route::get('/check-conflict',   [AppointmentController::class, 'checkConflict'])->name('check.conflict');

    // Per-appointment
    Route::get('/{appointment}',            [AppointmentController::class, 'show'])->name('show');
    Route::get('/{appointment}/quick',      [AppointmentController::class, 'quickView'])->name('quick');
    Route::patch('/{appointment}/status',   [AppointmentController::class, 'updateStatus'])->name('status.update');

});

    /* ── Tasks ── */
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/',                  [TaskController::class, 'index'])->name('index');
        Route::get('/create',            [TaskController::class, 'create'])->name('create');
        Route::post('/',                 [TaskController::class, 'store'])->name('store');
        Route::get('/overdue',           [TaskController::class, 'overdue'])->name('overdue');
        Route::get('/mine',              [TaskController::class, 'myTasks'])->name('mine');
        Route::patch('/{task}/done',     [TaskController::class, 'markDone'])->name('markDone');
        Route::post('/{task}/escalate',  [TaskController::class, 'escalate'])->name('escalate');
    });

    
    Route::resource('patients.consultations', ConsultationController::class)
    ->only(['create', 'store', 'show', 'edit', 'update', 'destroy'])
    ->middleware('auth');


   
    /* ── Treatment Categories ── */
    Route::get('/treatment-categories',                       [TreatmentCategoryController::class, 'index']);
    Route::get('/treatment-categories/{category}/treatments', [TreatmentCategoryController::class, 'treatments']);



    require __DIR__ . '/../app/Modules/Huddle/Routes/huddle.php';

    /* ── Settings / Tags ── */
    Route::get('/settings', fn() => redirect()->route('settings.tags.index'))->name('settings.index');
    Route::prefix('settings/tags')->name('settings.tags.')->group(function () {
        Route::get('/',         [TagController::class, 'index'])->name('index');
        Route::post('/',        [TagController::class, 'store'])->name('store');
        Route::put('/{tag}',    [TagController::class, 'update'])->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])->name('destroy');
    });

    /* ── Coming soon stubs ── */
    Route::get('/treatments',     fn() => 'Coming soon')->name('treatments.index');
    Route::get('/billing',        fn() => 'Coming soon')->name('billing.index');
    Route::get('/billing/create', fn() => 'Coming soon')->name('billing.create');
    Route::get('/inventory',      fn() => 'Coming soon')->name('inventory.index');
    Route::get('/lab',            fn() => 'Coming soon')->name('lab.index');
    Route::get('/lab/create',     fn() => 'Coming soon')->name('lab.create');
    Route::get('/crm',            fn() => 'Coming soon')->name('crm.index');
    Route::get('/reports',        fn() => 'Coming soon')->name('reports.index');
    Route::get('/analytics',      fn() => 'Coming soon')->name('analytics.index');
    Route::get('/notifications',  fn() => 'Coming soon')->name('notifications.index');
    Route::get('/profile/edit',   fn() => 'Coming soon')->name('profile.edit');
    Route::get('/help',           fn() => 'Coming soon')->name('help.index');
    /* ── Coming soon stubs ── */
    Route::get('/treatments',     fn() => 'Coming soon')->name('treatments.index');
    Route::get('/billing',        fn() => 'Coming soon')->name('billing.index');
    Route::get('/billing/create', fn() => 'Coming soon')->name('billing.create');
    Route::get('/inventory',      fn() => 'Coming soon')->name('inventory.index');
    Route::get('/lab',            fn() => 'Coming soon')->name('lab.index');
    Route::get('/lab/create',     fn() => 'Coming soon')->name('lab.create');
    Route::get('/crm',            fn() => 'Coming soon')->name('crm.index');
    Route::get('/reports',        fn() => 'Coming soon')->name('reports.index');
    Route::get('/analytics',      fn() => 'Coming soon')->name('analytics.index');
    Route::get('/notifications',  fn() => 'Coming soon')->name('notifications.index');
    Route::get('/profile/edit',   fn() => 'Coming soon')->name('profile.edit');
    Route::get('/help',           fn() => 'Coming soon')->name('help.index');
    Route::get('/cms', fn() => view('coming-soon'))->name('cms.index');
Route::get('/marketing', fn() => view('coming-soon'))->name('marketing.index');

    require __DIR__.'/communication.php';

});