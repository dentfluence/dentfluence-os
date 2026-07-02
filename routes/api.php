<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientProfileController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\TreatmentPlanController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\TreatmentVisitController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HuddleController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RelationshipController;
use App\Http\Controllers\Webhooks\WebsiteLeadController;
use App\Http\Controllers\Webhooks\MetaLeadController;
use App\Http\Controllers\Webhooks\WhatsAppLeadController;
use App\Http\Controllers\Webhooks\ChatbotController;

/*
|--------------------------------------------------------------------------
| Dentfluence API — Version 1
|--------------------------------------------------------------------------
| Every mobile / Tulip / future client talks to these routes.
| Full path = "/api" (from bootstrap/app.php) + "/v1" group => /api/v1/...
|
| Rule: routes here are THIN. They point at controllers, which call
| services (the "brain"). No business logic lives in this file.
*/

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {

    /*
     | -------- Public routes (no login needed) --------
     */
    Route::get('/ping', [SystemController::class, 'ping']);
    // Brute-force protection (Phase A): 5 login attempts/min per IP (tighter
    // than the group's 120/min general limit).
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    /*
     | -------- Protected routes (require a Bearer token) --------
     | The client sends header:  Authorization: Bearer <token>
     */
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/auth/me',          [AuthController::class, 'me']);
        Route::put('/auth/me',          [AuthController::class, 'updateMe']);   // edit profile
        Route::post('/auth/logout',     [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']); // revoke all devices

        /*
         | Example RBAC route — admin only. Proves backend permission checks
         | work. (Reuses SystemController@ping just to return something.)
         */
        Route::get('/auth/admin-check', [SystemController::class, 'ping'])
            ->middleware('api.role:admin');

        /*
         | -------- Patients (Phase 1) --------
         | Same PatientService brain as the web pages. Reads are open to any
         | logged-in staff; writes are role-gated server-side. "/search" is
         | declared before "/{patient}" so it isn't swallowed as an id.
         */
        Route::get('/patients/search',    [PatientController::class, 'search']);
        Route::get('/patients',           [PatientController::class, 'index']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);

        Route::post('/patients', [PatientController::class, 'store'])
            ->middleware('api.role:admin,front_desk');

        Route::match(['put', 'patch'], '/patients/{patient}', [PatientController::class, 'update'])
            ->middleware('api.role:admin,front_desk');

        Route::post('/patients/{patient}/deactivate', [PatientController::class, 'deactivate'])
            ->middleware('api.role:admin');

        /*
         | -------- Patient Profile tabs (read-only) --------
         */
        Route::get('/patients/{patient}/consultations',   [PatientProfileController::class, 'consultations']);
        Route::get('/patients/{patient}/treatment-plans', [PatientProfileController::class, 'treatmentPlans']);
        Route::get('/patients/{patient}/visits',          [PatientProfileController::class, 'visits']);
        Route::get('/patients/{patient}/lab-cases',       [PatientProfileController::class, 'labCases']);
        Route::get('/patients/{patient}/prescriptions',   [PatientProfileController::class, 'prescriptions']);
        Route::get('/patients/{patient}/invoices',        [PatientProfileController::class, 'invoices']);
        Route::get('/patients/{patient}/wallet',          [PatientProfileController::class, 'wallet']);
        Route::get('/patients/{patient}/documents',       [PatientProfileController::class, 'documents']);
        Route::post('/patients/{patient}/documents',      [PatientProfileController::class, 'storeDocument']);
        Route::get('/patients/{patient}/notes',           [PatientProfileController::class, 'notes']);
        Route::get('/patients/{patient}/communications',  [PatientProfileController::class, 'communications']);
        Route::get('/patients/{patient}/memberships',     [MembershipController::class, 'index']);

        // Prescription + invoice + consultation detail (full record)
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show']);
        Route::get('/invoices/{invoice}',           [PatientProfileController::class, 'invoiceDetail']);
        Route::get('/consultations/{consultation}', [ConsultationController::class, 'show']);
        // Clinical write — doctors only (admin always passes). Phase A role-gating.
        Route::put('/consultations/{consultation}', [ConsultationController::class, 'update'])
            ->middleware('api.role:doctor,resident_dentist,associate_dentist,visiting_consultant');

        // Notes + communications write actions
        Route::post('/patients/{patient}/notes',           [PatientProfileController::class, 'storeNote']);
        Route::put('/patients/{patient}/notes/{note}',     [PatientProfileController::class, 'updateNote']);
        Route::delete('/patients/{patient}/notes/{note}',  [PatientProfileController::class, 'deleteNote']);
        Route::post('/patients/{patient}/communications',  [PatientProfileController::class, 'storeCommunication']);

        // Consultation create — 4 workflows (mirrors web)
        Route::get('/patients/{patient}/consultations/same-issue-context', [ConsultationController::class, 'sameIssueContext']);
        // Clinical writes — doctors only (admin always passes). Phase A role-gating.
        Route::middleware('api.role:doctor,resident_dentist,associate_dentist,visiting_consultant')->group(function () {
            Route::post('/patients/{patient}/consultations',             [ConsultationController::class, 'storeNew']);
            Route::post('/patients/{patient}/consultations/same-issue',  [ConsultationController::class, 'storeSameIssue']);
            Route::post('/patients/{patient}/consultations/minor-visit', [ConsultationController::class, 'storeMinorVisit']);
            Route::post('/patients/{patient}/consultations/emergency',   [ConsultationController::class, 'storeEmergency']);
        });

        // Treatment plans
        Route::get('/treatments',                          [TreatmentPlanController::class, 'treatments']);
        Route::get('/treatment-plans/{plan}',              [TreatmentPlanController::class, 'show']);
        Route::post('/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'store'])
            ->middleware('api.role:admin,front_desk');
        Route::put('/treatment-plans/{plan}',              [TreatmentPlanController::class, 'update'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/treatment-plans/{plan}/accept',      [TreatmentPlanController::class, 'accept'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/treatment-plans/{plan}/revert',      [TreatmentPlanController::class, 'revert'])
            ->middleware('api.role:admin,front_desk');

        /*
         | -------- Treatment visits (write) --------
         | Shared TreatmentVisitService — same side-effects as the web (billing
         | prompt, draft lab case, 6-month recall task). Reads still come from
         | the read-only "/patients/{patient}/visits" list above. "/form-options"
         | is declared before nothing ambiguous; "/visits/{visit}" is a distinct
         | path so it never clashes with the patient list route.
         */
        Route::get('/patients/{patient}/visits/form-options', [TreatmentVisitController::class, 'formOptions']);
        Route::get('/visits/{visit}',                         [TreatmentVisitController::class, 'show']);
        // Clinical writes — doctors only (admin always passes). Phase A role-gating.
        Route::middleware('api.role:doctor,resident_dentist,associate_dentist,visiting_consultant')->group(function () {
            Route::post('/patients/{patient}/visits', [TreatmentVisitController::class, 'store']);
            Route::put('/visits/{visit}',             [TreatmentVisitController::class, 'update']);
            Route::delete('/visits/{visit}',          [TreatmentVisitController::class, 'destroy']);
        });

        /*
         | -------- Prescriptions (full parity with web write-pad) --------
         | Master/CDSS/form helpers sit under "rx/" so they never collide with
         | the "/prescriptions/{prescription}" id route. Writes are open to any
         | authenticated staff, matching the web routes.
         */
        Route::get('/rx/drugs/search',  [PrescriptionController::class, 'drugSearch']);
        Route::get('/rx/form-options',  [PrescriptionController::class, 'formOptions']);
        Route::post('/rx/check-alerts', [PrescriptionController::class, 'checkAlerts']);
        Route::post('/rx/check-repeat', [PrescriptionController::class, 'checkRepeat']);

        // Prescribing is a clinical act — doctors only (admin always passes).
        // Phase A role-gating (was: open to any authenticated staff).
        Route::middleware('api.role:doctor,resident_dentist,associate_dentist,visiting_consultant')->group(function () {
            Route::post('/patients/{patient}/prescriptions',      [PrescriptionController::class, 'store']);
            Route::put('/prescriptions/{prescription}',           [PrescriptionController::class, 'update']);
            Route::post('/prescriptions/{prescription}/finalize', [PrescriptionController::class, 'finalize']);
            Route::post('/prescriptions/{prescription}/repeat',   [PrescriptionController::class, 'repeat']);
            Route::post('/prescriptions/{prescription}/cancel',   [PrescriptionController::class, 'cancel']);
        });

        /*
         | -------- Memberships (AOCP — full parity with web enrollment) --------
         | Enrollment runs the same finance chain via MembershipBenefitService,
         | so invoice/payment/receipt/transaction records match the web.
         */
        Route::get('/membership/plans',          [MembershipController::class, 'plans']);
        Route::get('/membership/active-members', [MembershipController::class, 'activeMembers']);
        Route::get('/patients/{patient}/membership-benefits', [MembershipController::class, 'benefitLogs']);
        Route::post('/patients/{patient}/membership/enroll',  [MembershipController::class, 'enroll']);

        /*
         | -------- Billing / Payments (full parity with web money-in) --------
         | Recording a payment runs the SAME chain as the web BillingController
         | via InvoicePaymentService, so InvoicePayment / Receipt / FinalBill /
         | FinanceTransaction (+ EmiSchedule) records are identical. Reads are
         | open to any logged-in staff; money-in writes are role-gated.
         | All endpoints branch-scope via the invoice's / patient's branch.
         */
        // Clinic-wide billing list + summary KPIs (mobile Billing module).
        Route::get('/billing/invoices', [BillingController::class, 'index']);
        Route::get('/billing/summary',  [BillingController::class, 'summary']);

        // Lab Work board — read + write (mobile Lab module).
        Route::get('/lab/cases',                              [LabController::class, 'index']);
        Route::get('/lab/summary',                            [LabController::class, 'summary']);
        Route::get('/lab/templates',                          [LabController::class, 'templates']);
        Route::get('/lab/cases/{id}',                         [LabController::class, 'show']);
        Route::post('/lab/cases',                             [LabController::class, 'store']);
        Route::patch('/lab/cases/{id}/status/{to}',           [LabController::class, 'transition']);
        Route::post('/lab/cases/{id}/prescription',           [LabController::class, 'prescriptionSave']);
        Route::post('/lab/cases/{id}/attachments',            [LabController::class, 'attachmentStore']);

        // Reports / analytics (mobile Reports module). Read-only.
        Route::get('/reports/overview', [ReportController::class, 'overview']);

        // ── Relationship Engine (Phase 7) ──────────────────────────────────────
        // All endpoints use the same Sanctum auth as the rest of /api/v1/.
        // Static segments (today, search) MUST come before /{id} to avoid
        // the wildcard swallowing them.
        Route::prefix('relationships')->name('api.relationships.')->group(function () {
            // Today's actions — mobile equivalent of /relationship/today
            Route::get('/today',           [RelationshipController::class, 'today'])
                ->name('today');

            // Universal search — name, phone, email
            Route::get('/search',          [RelationshipController::class, 'search'])
                ->name('search');

            // Profile summary
            Route::get('/{id}',            [RelationshipController::class, 'show'])
                ->whereNumber('id')
                ->name('show');

            // Paginated activity timeline
            Route::get('/{id}/timeline',   [RelationshipController::class, 'timeline'])
                ->whereNumber('id')
                ->name('timeline');

            // All journeys with state
            Route::get('/{id}/journeys',   [RelationshipController::class, 'journeys'])
                ->whereNumber('id')
                ->name('journeys');

            // Log an activity from mobile
            Route::post('/{id}/activity',  [RelationshipController::class, 'logActivity'])
                ->whereNumber('id')
                ->name('activity.log');
        });

        Route::get('/patients/{patient}/open-invoices',         [BillingController::class, 'openInvoices']);
        Route::post('/patients/{patient}/wallet/credit',        [BillingController::class, 'addWalletCredit']); // advance / wallet top-up
        Route::post('/invoices',                                [BillingController::class, 'createInvoice']);   // create invoice (mobile)
        Route::get('/invoices/{invoice}/payment-options',       [BillingController::class, 'paymentOptions']);
        Route::get('/invoices/{invoice}/receipts/{receipt}',    [BillingController::class, 'receipt']);

        Route::post('/invoices/{invoice}/payments', [BillingController::class, 'recordPayment'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/invoices/{invoice}/payments/{payment}/mark-provider-paid', [BillingController::class, 'markProviderPaid'])
            ->middleware('api.role:admin,front_desk');

        /*
         | -------- Dashboard (mobile home screen) --------
         */
        Route::get('/dashboard', [DashboardController::class, 'index']);

        /*
         | -------- Appointments --------
         | Shared AppointmentService. "/today" before "/{appointment}" so it
         | isn't swallowed as an id. Writes are role-gated server-side.
         */
        Route::get('/appointments/today',          [AppointmentController::class, 'today']);
        Route::get('/appointments/form-options',   [AppointmentController::class, 'formOptions']);
        Route::get('/appointments/blocked-slots',  [AppointmentController::class, 'blockedSlots']);
        Route::get('/appointments',                [AppointmentController::class, 'index']);
        Route::get('/appointments/{appointment}',  [AppointmentController::class, 'show']);

        Route::post('/appointments', [AppointmentController::class, 'store'])
            ->middleware('api.role:admin,front_desk');

        Route::post('/appointments/walk-in', [AppointmentController::class, 'walkIn'])
            ->middleware('api.role:admin,front_desk');

        Route::post('/appointments/block-slot', [AppointmentController::class, 'blockSlot'])
            ->middleware('api.role:admin,front_desk');

        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
            ->middleware('api.role:admin,front_desk');

        Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('api.role:admin,front_desk');

        /*
         | -------- Daily Huddle (mobile morning board + task layer) --------
         | Same "one brain" the web huddle uses, shaped for the phone.
         | Reads are open to any logged-in staff; task writes are role-gated.
         | "/tasks" is declared before "/{task}" so it isn't swallowed as an id.
         */
        Route::get('/huddle',       [HuddleController::class, 'index']);
        Route::get('/huddle/tasks', [HuddleController::class, 'tasks']);
        Route::get('/huddle/staff', [HuddleController::class, 'staff']);

        // Push selected reminders / follow-ups into the PRM communication queue
        Route::post('/huddle/comms/push', [HuddleController::class, 'pushComms'])
            ->middleware('api.role:admin,front_desk,doctor');

        Route::post('/huddle/tasks', [HuddleController::class, 'storeTask'])
            ->middleware('api.role:admin,front_desk,doctor');

        Route::patch('/huddle/tasks/{task}/status', [HuddleController::class, 'updateTaskStatus'])
            ->middleware('api.role:admin,front_desk,doctor');

        Route::patch('/huddle/tasks/{task}/assign', [HuddleController::class, 'assignTask'])
            ->middleware('api.role:admin,front_desk');

        /*
         | -------- Inventory (Core-6: items/stock, stock-in/out, PO+GRN,
         |          vendors, implants) --------
         | Same InventoryService "brain" as the web pages. Inventory is
         | clinic-wide (tables have no branch_id), so these are NOT branch
         | filtered — exact parity with web. Reads are open to any logged-in
         | staff; writes are role-gated. Specific paths are declared before
         | "/{id}" routes so they aren't swallowed as ids.
         */
        Route::get('/inventory/meta',     [InventoryController::class, 'meta']);
        Route::get('/inventory/alerts',   [InventoryController::class, 'alerts']);
        Route::get('/inventory/items',    [InventoryController::class, 'items']);
        Route::get('/inventory/products',  [InventoryController::class, 'products']);
        Route::post('/inventory/products', [InventoryController::class, 'storeProduct']);
        Route::get('/inventory/vendors',  [InventoryController::class, 'vendors']);

        // Implants — declare list/option routes before the "/{...}" id routes
        Route::get('/inventory/implants/catalog',      [InventoryController::class, 'implantCatalog']);
        Route::get('/inventory/implants/placements',   [InventoryController::class, 'implantPlacements']);
        Route::get('/inventory/implants/form-options', [InventoryController::class, 'implantFormOptions']);

        // Purchase orders — list + detail
        Route::get('/inventory/purchase-orders',       [InventoryController::class, 'purchaseOrders']);
        Route::get('/inventory/purchase-orders/{po}',  [InventoryController::class, 'showPurchaseOrder']);
        Route::get('/inventory/purchase-orders/{po}/whatsapp-message', [InventoryController::class, 'purchaseOrderWhatsapp']);

        // Item detail (declared after the static "/items" list above)
        Route::get('/inventory/items/{item}', [InventoryController::class, 'showItem']);

        /* ---- Writes (role-gated) ---- */

        // Item update + quick stock adjust
        Route::put('/inventory/items/{item}', [InventoryController::class, 'updateItem'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/inventory/items/{item}/adjust', [InventoryController::class, 'adjustStock'])
            ->middleware('api.role:admin,front_desk');

        // Stock movements
        Route::post('/inventory/stock-in',  [InventoryController::class, 'stockIn'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/inventory/stock-out', [InventoryController::class, 'stockOut'])
            ->middleware('api.role:admin,front_desk');

        // Purchase orders — create / mark-ordered / receive (GRN)
        Route::post('/inventory/purchase-orders', [InventoryController::class, 'storePurchaseOrder'])
            ->middleware('api.role:admin,front_desk');
        Route::patch('/inventory/purchase-orders/{po}/mark-ordered', [InventoryController::class, 'markOrdered'])
            ->middleware('api.role:admin,front_desk');
        Route::post('/inventory/purchase-orders/{po}/receive', [InventoryController::class, 'receivePurchaseOrder'])
            ->middleware('api.role:admin,front_desk');
    });
});
