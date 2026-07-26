<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\PatientProfileController;
use App\Http\Controllers\Api\V1\ConsultationController;
use App\Http\Controllers\Api\V1\CohaController;
use App\Http\Controllers\Api\V1\TreatmentPlanController;
use App\Http\Controllers\Api\V1\PrescriptionController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\TreatmentVisitController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HuddleController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\StockCountController;
use App\Http\Controllers\Api\V1\ReusableAssetController;
use App\Http\Controllers\Api\V1\VendorInvoiceController;
use App\Http\Controllers\Api\V1\InventorySettingsController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RelationshipController;
use App\Http\Controllers\Api\V1\RelationshipMissedCallsController;
use App\Http\Controllers\Api\V1\RelationshipRecallSettingsController;
use App\Http\Controllers\Api\V1\TemplateController;
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
        Route::get('/patients/{patient}', [PatientController::class, 'show'])
            ->middleware('api.role:module:patients,view');

        Route::post('/patients', [PatientController::class, 'store'])
            ->middleware('api.role:module:patients,edit');

        Route::match(['put', 'patch'], '/patients/{patient}', [PatientController::class, 'update'])
            ->middleware('api.role:module:patients,edit');

        Route::post('/patients/{patient}/deactivate', [PatientController::class, 'deactivate'])
            ->middleware('api.role:module:patients,edit');

        /*
         | -------- Patient Profile tabs (read-only) --------
         | Slice 1.4: PHI reads require the owner-configured patients View flag
         | (were reachable by any authenticated token).
         */
        Route::middleware('api.role:module:patients,view')->group(function () {
        Route::get('/patients/{patient}/consultations',   [PatientProfileController::class, 'consultations']);
        Route::get('/patients/{patient}/treatment-plans', [PatientProfileController::class, 'treatmentPlans']);
        Route::get('/patients/{patient}/visits',          [PatientProfileController::class, 'visits']);
        Route::get('/patients/{patient}/lab-cases',       [PatientProfileController::class, 'labCases']);
        Route::get('/patients/{patient}/prescriptions',   [PatientProfileController::class, 'prescriptions']);
        Route::get('/patients/{patient}/invoices',        [PatientProfileController::class, 'invoices']);
        Route::get('/patients/{patient}/wallet',          [PatientProfileController::class, 'wallet']);
        Route::get('/patients/{patient}/documents',       [PatientProfileController::class, 'documents']);
        Route::get('/patients/{patient}/memberships',     [MembershipController::class, 'index']);
        Route::get('/patients/{patient}/notes',           [PatientProfileController::class, 'notes']);
        Route::get('/patients/{patient}/communications',  [PatientProfileController::class, 'communications']);
        Route::get('/consultations/{consultation}', [ConsultationController::class, 'show']);
        }); // end PHI read group (Slice 1.4)

        // Clinical-file + document writes — Slice 1.2/1.4: mirror the web gates
        // exactly (module:patients edit for writes, delete for destroy). These
        // were previously reachable by any authenticated token.
        Route::post('/patients/{patient}/documents',      [PatientProfileController::class, 'storeDocument'])
            ->middleware('api.role:module:patients,edit');
        Route::post('/patients/{patient}/clinical-files',  [PatientProfileController::class, 'storeClinicalFile'])
            ->middleware('api.role:module:patients,edit');
        Route::put('/patients/{patient}/clinical-files/{file}',    [PatientProfileController::class, 'updateClinicalFile'])
            ->middleware('api.role:module:patients,edit');
        Route::delete('/patients/{patient}/clinical-files/{file}', [PatientProfileController::class, 'deleteClinicalFile'])
            ->middleware('api.role:module:patients,delete');

        // Prescription + invoice detail — module-appropriate reads.
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])
            ->middleware('api.role:module:prescriptions,view');
        Route::get('/invoices/{invoice}',           [PatientProfileController::class, 'invoiceDetail'])
            ->middleware('api.role:module:finance,view');
        // Clinical write — Slice 1.2: owner-configured permission, not job title.
        Route::put('/consultations/{consultation}', [ConsultationController::class, 'update'])
            ->middleware('api.role:module:patients,edit');
        // Delete — mirrors web consultations.destroy (,delete, was ,view).
        Route::delete('/consultations/{consultation}', [ConsultationController::class, 'destroy'])
            ->middleware('api.role:module:patients,delete');

        // Notes + communications write actions — Slice 1.2: raised from the
        // view gate to edit/delete so they mirror web.php exactly.
        Route::middleware('api.role:module:patients,edit')->group(function () {
            Route::post('/patients/{patient}/notes',           [PatientProfileController::class, 'storeNote']);
            Route::put('/patients/{patient}/notes/{note}',     [PatientProfileController::class, 'updateNote']);
            Route::post('/patients/{patient}/communications',  [PatientProfileController::class, 'storeCommunication']);

            // Consent-gated WhatsApp send (2026-07-14 product decision) —
            // replaces the mobile deep-link that bypassed the DPDP gate.
            Route::post('/patients/{patient}/whatsapp/send',   [\App\Http\Controllers\Api\V1\WhatsappController::class, 'send']);
            // (thread read is registered below on the view gate — Slice 1.2)
            // Interim click-to-chat (wa.me) link builder — parity with the web
            // communication.whatsapp.link endpoint; app opens the URL via url_launcher.
            Route::post('/patients/{patient}/whatsapp/link',   [\App\Http\Controllers\Api\V1\WhatsappController::class, 'link']);
        });

        // Note delete — destructive, mirrors web notes.destroy (,delete).
        Route::delete('/patients/{patient}/notes/{note}', [PatientProfileController::class, 'deleteNote'])
            ->middleware('api.role:module:patients,delete');

        // Read-only WhatsApp thread stays on view (reading contact history).
        Route::get('/patients/{patient}/whatsapp/thread', [\App\Http\Controllers\Api\V1\WhatsappController::class, 'thread'])
            ->middleware('api.role:module:patients,view');

        // ── In-app notifications (mobile face of the web bell/page) ──────────
        Route::get('/notifications',                    [\App\Http\Controllers\Api\V1\NotificationsController::class, 'index']);
        Route::get('/notifications/unread',             [\App\Http\Controllers\Api\V1\NotificationsController::class, 'unread']);
        Route::patch('/notifications/{id}/read',        [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markRead']);
        Route::post('/notifications/mark-all-read',     [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markAllRead']);

        // Consultation create — 4 workflows (mirrors web)
        Route::get('/patients/{patient}/consultations/same-issue-context', [ConsultationController::class, 'sameIssueContext']);
        // COHA (Comprehensive Oral Health Assessment) — separate workflow from the
        // patient page, mirrors web's dedicated coha.* routes/controller.
        Route::get('/coha/{consultation}', [CohaController::class, 'show']);
        // Clinical writes — Slice 1.2: owner-configured patients,edit (was a
        // hardcoded dentist role-name list). Mirrors web consultation stores.
        Route::middleware('api.role:module:patients,edit')->group(function () {
            Route::post('/patients/{patient}/consultations',             [ConsultationController::class, 'storeNew']);
            Route::post('/patients/{patient}/consultations/same-issue',  [ConsultationController::class, 'storeSameIssue']);
            Route::post('/patients/{patient}/consultations/minor-visit', [ConsultationController::class, 'storeMinorVisit']);
            Route::post('/patients/{patient}/consultations/emergency',   [ConsultationController::class, 'storeEmergency']);
            Route::post('/patients/{patient}/coha', [CohaController::class, 'store']);
            Route::put('/coha/{consultation}',      [CohaController::class, 'update']);
        });

        // Treatment plans
        Route::get('/treatments',                          [TreatmentPlanController::class, 'treatments']);
        // Structured priced options (implant systems, crown materials, add-ons)
        // for one treatment. Read-only; powers the Case Journey live-cost UI.
        // See docs/plan-case-acceptance-engine.md §4.1.
        Route::get('/treatment-pricing',                   [\App\Http\Controllers\Api\V1\TreatmentPricingController::class, 'index']);
        Route::get('/treatment-plans/{plan}',              [TreatmentPlanController::class, 'show']);
        // Slice 1.2: treatment-plan writes follow the same owner-configured
        // patients,edit permission the web routes use (were role-name lists).
        Route::post('/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'store'])
            ->middleware('api.role:module:patients,edit');
        Route::put('/treatment-plans/{plan}',              [TreatmentPlanController::class, 'update'])
            ->middleware('api.role:module:patients,edit');
        Route::post('/treatment-plans/{plan}/accept',      [TreatmentPlanController::class, 'accept'])
            ->middleware('api.role:module:patients,edit');
        // Slice 2.2 — presentation is a clinical mutation, so it carries the
        // same owner-configured patients,edit gate as the web route.
        Route::post('/treatment-plans/{plan}/mark-presented', [TreatmentPlanController::class, 'markPresented'])
            ->middleware('api.role:module:patients,edit');
        Route::post('/treatment-plans/{plan}/revert',      [TreatmentPlanController::class, 'revert'])
            ->middleware('api.role:module:patients,edit');
        Route::get('/treatment-plans/{plan}/billable-teeth', [TreatmentPlanController::class, 'billableTeeth']);
        // Billing a plan creates an invoice → finance,edit as well as patients,edit
        // is conceptually right, but finance is the money module: gate on finance.
        Route::post('/treatment-plans/{plan}/bill',           [TreatmentPlanController::class, 'bill'])
            ->middleware('api.role:module:finance,edit');

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
        // Slice 1.2: visit writes on owner-configured patients,edit / ,delete
        // (mirrors web visits.store/update/destroy) instead of a dentist list.
        Route::middleware('api.role:module:patients,edit')->group(function () {
            Route::post('/patients/{patient}/visits', [TreatmentVisitController::class, 'store']);
            Route::put('/visits/{visit}',             [TreatmentVisitController::class, 'update']);
        });
        Route::delete('/visits/{visit}', [TreatmentVisitController::class, 'destroy'])
            ->middleware('api.role:module:patients,delete');

        /*
         | -------- Prescriptions (full parity with web write-pad) --------
         | Master/CDSS/form helpers sit under "rx/" so they never collide with
         | the "/prescriptions/{prescription}" id route. Writes are open to any
         | authenticated staff, matching the web routes.
         */
        // Slice 1.3/1.4: Rx helpers + reads now require the owner-configured
        // prescriptions module (view); the pad writes require edit below.
        Route::middleware('api.role:module:prescriptions,view')->group(function () {
            Route::get('/rx/drugs/search',  [PrescriptionController::class, 'drugSearch']);
            Route::get('/rx/form-options',  [PrescriptionController::class, 'formOptions']);
            Route::post('/rx/check-alerts', [PrescriptionController::class, 'checkAlerts']);
            Route::post('/rx/check-repeat', [PrescriptionController::class, 'checkRepeat']);
        });

        // Clinic-wide list w/ patient search — the mobile Rx module landing
        // (declared before "/prescriptions/{prescription}").
        Route::get('/prescriptions', [PrescriptionController::class, 'index'])
            ->middleware('api.role:module:prescriptions,view');

        // Slice 1.3/1.4: prescribing follows the owner-configured prescriptions
        // module (edit) instead of a hardcoded dentist role-name list.
        Route::middleware('api.role:module:prescriptions,edit')->group(function () {
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
        // Slice 1.4: membership reads = finance view; enrollment runs the full
        // money chain (invoice/payment/receipt) → finance edit. Was ungated.
        Route::middleware('api.role:module:finance,view')->group(function () {
            Route::get('/membership/plans',          [MembershipController::class, 'plans']);
            Route::get('/membership/active-members', [MembershipController::class, 'activeMembers']);
            Route::get('/patients/{patient}/membership-benefits', [MembershipController::class, 'benefitLogs']);
        });
        Route::post('/patients/{patient}/membership/enroll',  [MembershipController::class, 'enroll'])
            ->middleware('api.role:module:finance,edit');

        /*
         | -------- Billing / Payments (full parity with web money-in) --------
         | Recording a payment runs the SAME chain as the web BillingController
         | via InvoicePaymentService, so InvoicePayment / Receipt / FinalBill /
         | FinanceTransaction (+ EmiSchedule) records are identical. Reads are
         | open to any logged-in staff; money-in writes are role-gated.
         | All endpoints branch-scope via the invoice's / patient's branch.
         */
        // Clinic-wide billing list + summary KPIs (mobile Billing module).
        // Slice 1.4: clinic-wide money reads require finance view (was ungated).
        Route::middleware('api.role:module:finance,view')->group(function () {
            Route::get('/billing/invoices', [BillingController::class, 'index']);
            Route::get('/billing/summary',  [BillingController::class, 'summary']);
        });

        // Lab Work board — read + write (mobile Lab module).
        // Gated by the SAME permission table as web (module:lab) — reads need
        // view, writes need edit. Previously ungated entirely (2026-07-14).
        Route::middleware('api.role:module:lab,view')->group(function () {
            Route::get('/lab/cases',                          [LabController::class, 'index']);
            Route::get('/lab/summary',                        [LabController::class, 'summary']);
            Route::get('/lab/templates',                      [LabController::class, 'templates']);
            Route::get('/lab/form-options',                   [LabController::class, 'formOptions']);
            Route::get('/lab/cases/{id}',                     [LabController::class, 'show']);
        });
        // Slice 1.4: lab writes require lab EDIT (were on the view flag, so a
        // view-only lab grant could create cases and move statuses).
        Route::middleware('api.role:module:lab,edit')->group(function () {
            Route::post('/lab/cases',                         [LabController::class, 'store']);
            Route::patch('/lab/cases/{id}/status/{to}',       [LabController::class, 'transition']);
            Route::post('/lab/cases/{id}/prescription',       [LabController::class, 'prescriptionSave']);
            Route::post('/lab/cases/{id}/attachments',        [LabController::class, 'attachmentStore']);
        });

        // Reports / analytics (mobile Reports module). Read-only.
        // Same permission as web reports pages (module:reports), was ungated.
        Route::middleware('api.role:module:reports,view')->group(function () {
            Route::get('/reports/overview', [ReportController::class, 'overview']);
            Route::get('/reports/outstanding', [ReportController::class, 'outstandingByPatient']);
        });

        // ── Relationship Engine / PRE (Phase 7, extended for full mobile parity) ──
        // PRE is the only lead/relationship engine on web and mobile. The PRM
        // BOARD/routes are retired, but app/Services/Prm/* remains the LIVE
        // backend of PRE's lead pipeline (LeadObserver → routing/enrichment/
        // follow-up services) — badly named, not deleted. See
        // docs/legacy-prm-audit.md (2026-07-25). Static segments (today,
        // search, pipelines, recalls) MUST come before /{id} to avoid the
        // wildcard swallowing them.
        // Slice 1.3/1.4: the PRE API now carries the SAME owner-configured gate
        // as the web PRE routes — group = module:relationship (view), mutations
        // add ',edit'. Previously this entire group was auth:sanctum-only, so
        // any token holder could read every patient's contact data and execute
        // board mutations regardless of Settings.
        Route::prefix('relationships')->middleware('api.role:module:relationship,view')->name('api.relationships.')->group(function () {
            // Today's actions — mobile equivalent of /relationship/today
            Route::get('/today',           [RelationshipController::class, 'today'])
                ->name('today');

            // Action Board writes (2026-07-06 web parity) — call outcomes + dismiss.
            // Static segments, declared with /today above, before the generic
            // /{id} wildcard further down this group.
            Route::post('/today/action',   [RelationshipController::class, 'todayLogAction'])
                ->name('today.action')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/today/dismiss',  [RelationshipController::class, 'todayDismiss'])
                ->name('today.dismiss')
                ->middleware('api.role:module:relationship,edit');

            // Close / Notes / Add-Call (2026-07-08 web parity) — static
            // segments, declared with /today above, before the /{id} wildcard.
            Route::post('/today/close',    [RelationshipController::class, 'todayClose'])
                ->name('today.close')
                ->middleware('api.role:module:relationship,edit');
            Route::get('/today/notes',     [RelationshipController::class, 'todayNotes'])
                ->name('today.notes.index');
            Route::post('/today/notes',    [RelationshipController::class, 'todayAddNote'])
                ->name('today.notes.add')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/today/add-call', [RelationshipController::class, 'todayAddCall'])
                ->name('today.add-call')
                ->middleware('api.role:module:relationship,edit');

            // Universal search — name, phone, email
            Route::get('/search',          [RelationshipController::class, 'search'])
                ->name('search');

            // Searchable/filterable/paginated browse — mobile equiv. of /relationship/list
            Route::get('/',                [RelationshipController::class, 'list'])
                ->name('list');

            // Pipelines — mobile equivalents of /relationship/pipeline, /opportunities, /recalls
            Route::prefix('pipelines')->name('pipelines.')->group(function () {
                Route::get('/leads',          [RelationshipController::class, 'pipelineLeads'])
                    ->name('leads');
                Route::get('/opportunities',  [RelationshipController::class, 'pipelineOpportunities'])
                    ->name('opportunities');
                Route::get('/recalls',        [RelationshipController::class, 'pipelineRecalls'])
                    ->name('recalls');
            });

            // Manually add a recall for a patient
            Route::post('/recalls',        [RelationshipController::class, 'recallStore'])
                ->name('recalls.store')
                ->middleware('api.role:module:relationship,edit');

            // Opportunity lifecycle writes (2026-07-06 web parity) — static
            // segments ('opportunities', 'patient-search') before the generic
            // /{id} show route below so they aren't swallowed by the wildcard.
            Route::prefix('opportunities')->name('opportunities.')->group(function () {
                Route::post('/',              [RelationshipController::class, 'opportunityStore'])
                    ->name('store')
                ->middleware('api.role:module:relationship,edit');
                Route::get('/patient-search', [RelationshipController::class, 'opportunityPatientSearch'])
                    ->name('patient-search');
                Route::get('/{id}',           [RelationshipController::class, 'opportunityShow'])
                    ->whereNumber('id')
                    ->name('show');
                Route::patch('/{id}/stage',   [RelationshipController::class, 'opportunityUpdateStage'])
                    ->whereNumber('id')
                    ->name('update-stage')
                ->middleware('api.role:module:relationship,edit');
                Route::post('/{id}/convert',  [RelationshipController::class, 'opportunityConvert'])
                    ->whereNumber('id')
                    ->name('convert')
                ->middleware('api.role:module:relationship,edit');
            });

            // Grouped call-outcome vocabulary for the Activity Completion
            // Bottom Sheet (mobile + web). Static ref data — before /{id}.
            Route::get('/call-outcomes',   [RelationshipController::class, 'callOutcomes'])
                ->name('call-outcomes');

            // Complete a recall: outcome + notes + next follow-up -> runs
            // OutcomeAutomationService (create appointment / close recall /
            // schedule follow-up / mark invalid contact / etc.).
            Route::post('/recalls/{queueId}/complete', [RelationshipController::class, 'recallComplete'])
                ->whereNumber('queueId')
                ->name('recalls.complete')
                ->middleware('api.role:module:relationship,edit');

            // Profile summary (+ household)
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
                ->name('activity.log')
                ->middleware('api.role:module:relationship,edit');
        });

        // ── Lead lifecycle writes (mobile face of the lead pipeline board) ───────
        // Slice M1 (2026-07-26): same owner-configured gates as the web lead
        // pipeline (routes/relationship.php, Slice 1.3) — group view, writes
        // ',edit'. Conversion mints a Patient, so it carries the CEO-approved
        // AND semantic: relationship,edit AND patients,edit as two STACKED
        // api.role gates (tokens inside ONE api.role are OR — stacking is how
        // the web expresses AND, mirrored here).
        Route::prefix('leads')->middleware('api.role:module:relationship,view')->name('api.leads.')->group(function () {
            Route::post('/quick-add',      [RelationshipController::class, 'leadQuickAdd'])
                ->name('quick-add')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/{lead}/move',    [RelationshipController::class, 'leadMoveStage'])
                ->whereNumber('lead')
                ->name('move')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/{lead}/activity',[RelationshipController::class, 'leadLogActivity'])
                ->whereNumber('lead')
                ->name('activity')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/{lead}/convert', [RelationshipController::class, 'leadConvert'])
                ->whereNumber('lead')
                ->name('convert')
                ->middleware(['api.role:module:relationship,edit', 'api.role:module:patients,edit']);
            Route::get('/{lead}/detail',   [RelationshipController::class, 'leadDetail'])
                ->whereNumber('lead')
                ->name('detail');
        });

        // ── Missed Calls (mobile face of the PRE "Missed Calls" backlog page) ────
        // Same YesterdayReviewService::missedCallsQuery() source of truth + the
        // same CommunicationQueue ignore()/unignore()/dismiss() helpers and
        // OutboundMessageService::sendText() loop as the web MissedCallsController.
        // Static segments (bulk-whatsapp, bulk-dismiss) before /{id} below.
        // Slice M1 (2026-07-26): mirrors web today.missed-calls gates exactly —
        // group view; ignore/unignore = ',edit'; bulk-dismiss = ',delete'
        // (routes/relationship.php:97-109).
        Route::prefix('relationship/missed-calls')->middleware('api.role:module:relationship,view')->name('api.relationship.missed-calls.')->group(function () {
            Route::get('/',                  [RelationshipMissedCallsController::class, 'index'])
                ->name('index');
            // bulk-whatsapp route removed 2026-07-14: it was orphaned — no
            // web page equivalent (bulk WhatsApp was removed 07-06 for web
            // parity) and no mobile caller. bulk-dismiss gained select_all.
            Route::post('/bulk-dismiss',     [RelationshipMissedCallsController::class, 'bulkDismiss'])
                ->name('bulk-dismiss')
                ->middleware('api.role:module:relationship,delete');
            Route::post('/{missedCall}/ignore',   [RelationshipMissedCallsController::class, 'ignore'])
                ->whereNumber('missedCall')
                ->name('ignore')
                ->middleware('api.role:module:relationship,edit');
            Route::post('/{missedCall}/unignore', [RelationshipMissedCallsController::class, 'unignore'])
                ->whereNumber('missedCall')
                ->name('unignore')
                ->middleware('api.role:module:relationship,edit');
        });

        // ── Recall / Birthday Settings (mobile face of the PRE Settings page's
        //    Recall/Birthday section) — same AppSetting keys, same TreatmentType
        //    column as SettingsController::saveRecallGeneral()/saveTreatmentRecall()/
        //    saveBirthday(). Static segments before the /treatment/{treatmentType} route.
        // Slice M1 (2026-07-26): clinic-wide PRE configuration — mirrors web
        // settings.recall-general / recall-treatment / recall-birthday, which
        // carry module:relationship,DELETE (routes/relationship.php:345-356).
        // Read stays on the group view gate. Previously fully ungated: any
        // token could rewrite clinic-wide recall periodicity.
        Route::prefix('relationship/recall-settings')->middleware('api.role:module:relationship,view')->name('api.relationship.recall-settings.')->group(function () {
            Route::get('/',                             [RelationshipRecallSettingsController::class, 'index'])
                ->name('index');
            Route::post('/general',                     [RelationshipRecallSettingsController::class, 'saveGeneral'])
                ->name('general')
                ->middleware('api.role:module:relationship,delete');
            Route::post('/treatment/{treatmentType}',   [RelationshipRecallSettingsController::class, 'saveTreatment'])
                ->whereNumber('treatmentType')
                ->name('treatment')
                ->middleware('api.role:module:relationship,delete');
            Route::post('/birthday',                    [RelationshipRecallSettingsController::class, 'saveBirthday'])
                ->name('birthday')
                ->middleware('api.role:module:relationship,delete');
        });

        // ── Message Templates (mobile face of the PRE Template editor) ──────────
        // "for-type/{type}" must stay before "/{id}" so it isn't swallowed as an id.
        // Slice M1 (2026-07-26): mirrors web relationship.templates gates —
        // group view; store/update/DESTROY all carry ',edit' (verified against
        // routes/relationship.php:384-397 — the web deliberately keeps template
        // destroy on EDIT, not delete; copied, not guessed).
        Route::prefix('templates')->middleware('api.role:module:relationship,view')->name('api.templates.')->group(function () {
            Route::get('/',               [TemplateController::class, 'index'])
                ->name('index');
            Route::get('/for-type/{type}', [TemplateController::class, 'forType'])
                ->name('for-type');
            Route::get('/{id}',           [TemplateController::class, 'show'])
                ->whereNumber('id')
                ->name('show');
            Route::post('/',              [TemplateController::class, 'store'])
                ->name('store')
                ->middleware('api.role:module:relationship,edit');
            Route::put('/{id}',           [TemplateController::class, 'update'])
                ->whereNumber('id')
                ->name('update')
                ->middleware('api.role:module:relationship,edit');
            Route::delete('/{id}',        [TemplateController::class, 'destroy'])
                ->whereNumber('id')
                ->name('destroy')
                ->middleware('api.role:module:relationship,edit');
        });

        Route::get('/patients/{patient}/open-invoices',         [BillingController::class, 'openInvoices']);
        Route::post('/patients/{patient}/wallet/credit',        [BillingController::class, 'addWalletCredit']); // advance / wallet top-up
        Route::post('/invoices',                                [BillingController::class, 'createInvoice']);   // create invoice (mobile)
        Route::get('/invoices/{invoice}/payment-options',       [BillingController::class, 'paymentOptions']);
        Route::get('/invoices/{invoice}/receipts/{receipt}',    [BillingController::class, 'receipt']);

        Route::post('/invoices/{invoice}/payments', [BillingController::class, 'recordPayment'])
            ->middleware('api.role:module:finance,edit');
        Route::post('/invoices/{invoice}/payments/{payment}/mark-provider-paid', [BillingController::class, 'markProviderPaid'])
            ->middleware('api.role:module:finance,edit');
        Route::patch('/invoices/{invoice}/payments/{payment}/date', [BillingController::class, 'updatePaymentDate'])
            ->middleware('api.role:module:finance,edit');

        // Direct-EMI instalment schedule — read + receivables "mark paid" (no
        // invoice/finance side effects; see EmiScheduleService).
        Route::get('/invoices/{invoice}/payments/{payment}/emi-schedule', [BillingController::class, 'emiSchedule']);
        Route::post('/invoices/{invoice}/payments/{payment}/emi-schedule/{schedule}/mark-paid', [BillingController::class, 'markEmiInstallmentPaid'])
            ->middleware('api.role:module:finance,edit');

        // Wallet refund (money OUT, back to patient) — same WALLET_REFUND
        // permission gate as web, enforced inside the controller.
        Route::post('/patients/{patient}/wallet/refund', [BillingController::class, 'refundWalletCredit'])
            ->middleware('api.role:module:finance,edit');

        // Discount layers — coupon / membership preview / manual discount.
        // Slice 1.4 (1.1 finding): these two carried NO gate at all.
        Route::get('/coupons/validate',                              [BillingController::class, 'validateCoupon'])
            ->middleware('api.role:module:finance,view');
        Route::get('/patients/{patient}/membership-benefit-preview', [BillingController::class, 'membershipBenefitPreview'])
            ->middleware('api.role:module:finance,view');
        Route::post('/invoices/{invoice}/manual-discount',            [BillingController::class, 'applyManualDiscount'])
            ->middleware('api.role:module:finance,edit');
        Route::delete('/invoices/{invoice}/manual-discount',          [BillingController::class, 'removeManualDiscount'])
            ->middleware('api.role:module:finance,edit');

        // Cancel / void — admin-only (enforced again inside the controller).
        Route::post('/invoices/{invoice}/cancel',                     [BillingController::class, 'cancelInvoice'])
            ->middleware('api.role:module:finance,delete');
        Route::post('/invoices/{invoice}/receipts/{receipt}/void',    [BillingController::class, 'voidReceipt'])
            ->middleware('api.role:module:finance,delete');

        // Billing prompts — auto-raised after a treatment visit completes.
        Route::get('/patients/{patient}/billing-prompts',             [BillingController::class, 'pendingBillingPrompts']);
        Route::get('/billing-prompts/{prompt}/form-options',          [BillingController::class, 'billingPromptFormOptions']);
        Route::post('/billing-prompts/{prompt}/dismiss',              [BillingController::class, 'dismissBillingPrompt']);

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

        // Slice 3: writes gated by the appointments module EDIT permission
        // (via the existing EnsureApiRole `module:` mode → User::canAccess),
        // the same table the web middleware uses. Replaces the admin,front_desk
        // role list so a Doctor (who holds appointments edit) is no longer
        // locked out of the mobile app. Admins still pass unconditionally.
        Route::post('/appointments', [AppointmentController::class, 'store'])
            ->middleware('api.role:module:appointments,edit');

        Route::post('/appointments/walk-in', [AppointmentController::class, 'walkIn'])
            ->middleware('api.role:module:appointments,edit');

        Route::post('/appointments/block-slot', [AppointmentController::class, 'blockSlot'])
            ->middleware('api.role:module:appointments,edit');

        Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
            ->middleware('api.role:module:appointments,edit');

        Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('api.role:module:appointments,edit');

        Route::patch('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
            ->middleware('api.role:module:appointments,edit');

        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
            ->middleware('api.role:module:appointments,delete');

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
            ->middleware('api.role:module:daily_huddle,edit');

        Route::post('/huddle/tasks', [HuddleController::class, 'storeTask'])
            ->middleware('api.role:module:daily_huddle,edit');

        Route::patch('/huddle/tasks/{task}/status', [HuddleController::class, 'updateTaskStatus'])
            ->middleware('api.role:module:daily_huddle,edit');

        Route::patch('/huddle/tasks/{task}/assign', [HuddleController::class, 'assignTask'])
            ->middleware('api.role:module:daily_huddle,edit');

        // Today's Patient Flow popup (2026-07-06 web parity) — notes / amount
        // to collect / prep item / chairside assistant, one call, one screen.
        Route::patch('/huddle/appointments/{id}/instruction', [HuddleController::class, 'updateInstruction'])
            ->whereNumber('id')
            ->middleware('api.role:module:daily_huddle,edit');

        // "Yesterday's Flow" quick-action — mirrors the web huddle modal:
        // logs a task and/or books a follow-up call for a patient instead of
        // navigating straight to their profile.
        Route::post('/huddle/yesterday-flow/log', [HuddleController::class, 'logYesterdayFollowUp'])
            ->middleware('api.role:module:daily_huddle,edit');

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
        Route::get('/inventory/dashboard', [InventoryController::class, 'dashboard']);
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

        // Stock History panel (2026-07-08 web parity) — recent movements +
        // which ones this user can still reverse.
        Route::get('/inventory/items/{item}/history', [InventoryController::class, 'stockHistory']);

        /* ---- Writes (role-gated) ---- */

        // Item update + quick stock adjust
        Route::put('/inventory/items/{item}', [InventoryController::class, 'updateItem'])
            ->middleware('api.role:module:inventory,edit');
        Route::post('/inventory/items/{item}/adjust', [InventoryController::class, 'adjustStock'])
            ->middleware('api.role:module:inventory,edit');

        // Archive a product (soft-disable) — admin-only, same as web admin.only.
        Route::delete('/inventory/products/{item}', [InventoryController::class, 'destroyProduct'])
            ->middleware('api.role:module:inventory,delete');

        // Vendor create + activate/deactivate (2026-07-14 parity — mobile
        // could previously edit vendors but never add or deactivate one).
        Route::post('/inventory/vendors', [InventorySettingsController::class, 'storeVendor'])
            ->middleware('api.role:module:inventory,edit');
        Route::patch('/inventory/vendors/{vendor}/toggle', [InventorySettingsController::class, 'toggleVendor'])
            ->middleware('api.role:module:inventory,edit');

        // Reverse a manual quick-adjustment (2026-07-08 web parity) —
        // admin-only, same gate as the web 'admin.only' middleware.
        Route::post('/inventory/movements/{movement}/reverse', [InventoryController::class, 'reverseAdjustment'])
            ->middleware('api.role:module:inventory,delete');

        // Stock movements
        Route::post('/inventory/stock-in',  [InventoryController::class, 'stockIn'])
            ->middleware('api.role:module:inventory,edit');
        Route::post('/inventory/stock-out', [InventoryController::class, 'stockOut'])
            ->middleware('api.role:module:inventory,edit');

        // Purchase orders — create / mark-ordered / receive (GRN)
        Route::post('/inventory/purchase-orders', [InventoryController::class, 'storePurchaseOrder'])
            ->middleware('api.role:module:inventory,edit');
        Route::patch('/inventory/purchase-orders/{po}/mark-ordered', [InventoryController::class, 'markOrdered'])
            ->middleware('api.role:module:inventory,edit');
        Route::post('/inventory/purchase-orders/{po}/receive', [InventoryController::class, 'receivePurchaseOrder'])
            ->middleware('api.role:module:inventory,edit');
        // Undo the most recent GRN (window-gated) — admin-only like web.
        Route::delete('/inventory/purchase-orders/{po}/grn/last', [InventoryController::class, 'reverseLastGrn'])
            ->middleware('api.role:module:inventory,delete');

        // Implant catalog + placements — writes (mobile Add Component / Add
        // Placement screens already call these paths; only the routes were
        // missing). Web has no role gate here, but every other inventory
        // write in this file is admin,front_desk, so we match that for
        // consistency. PUT with a photo arrives as POST+_method=PUT
        // (Laravel method spoofing), which still matches these PUT routes.
        Route::post('/inventory/implants/catalog', [InventoryController::class, 'storeCatalogItem'])
            ->middleware('api.role:module:inventory,edit');
        Route::put('/inventory/implants/catalog/{catalogItem}', [InventoryController::class, 'updateCatalogItem'])
            ->middleware('api.role:module:inventory,edit');
        Route::post('/inventory/implants/placements', [InventoryController::class, 'storePlacement'])
            ->middleware('api.role:module:inventory,edit');
        Route::put('/inventory/implants/placements/{placement}', [InventoryController::class, 'updatePlacement'])
            ->middleware('api.role:module:inventory,edit');

        /*
         | -------- Stock Count (15-day physical count cycle) --------
         | Same StockCountSession/StockCountLine logic as the web pages.
         | Reads open to any logged-in staff; writes (start/save/complete)
         | role-gated the same as every other inventory write above.
         */
        Route::prefix('inventory/stock-count')->group(function () {
            Route::get('/',                     [StockCountController::class, 'index']);
            Route::post('/',                    [StockCountController::class, 'start'])
                ->middleware('api.role:module:inventory,edit');
            Route::get('/{session}',            [StockCountController::class, 'sheet']);
            Route::post('/{session}/save',      [StockCountController::class, 'save'])
                ->middleware('api.role:module:inventory,edit');
            Route::post('/{session}/complete',  [StockCountController::class, 'complete'])
                ->middleware('api.role:module:inventory,edit');
        });

        /*
         | -------- Reusable Assets (individual physical instrument tracking) --------
         | Same ReusableAsset "brain" the web reusable-assets page uses — usage
         | count, sterilization history, maintenance schedule, retirement
         | threshold per physical unit. Reads open to any logged-in staff;
         | writes role-gated the same as every other inventory write above.
         */
        Route::prefix('inventory/reusable-assets')->group(function () {
            Route::get('/',  [ReusableAssetController::class, 'index']);
            Route::post('/', [ReusableAssetController::class, 'store'])
                ->middleware('api.role:module:inventory,edit');
            Route::put('/{asset}', [ReusableAssetController::class, 'update'])
                ->middleware('api.role:module:inventory,edit');
            Route::post('/{asset}/status', [ReusableAssetController::class, 'updateStatus'])
                ->middleware('api.role:module:inventory,edit');
        });

        /*
         | -------- Vendor Invoices (Procurement Phase 1 — Accounts Payable) --------
         | Same VendorInvoice "brain" the web vendor-invoices page uses — creating
         | an invoice auto-creates a FinanceExpense (AP entry), updates the PO's
         | invoice_status, and bumps FinanceVendor.outstanding_amount. Finance stays
         | the single source of truth for payment status. Reads open to any logged-in
         | staff; writes role-gated the same as every other inventory write above.
         | NOTE: /form-options must stay BEFORE /{vendorInvoice} so it isn't swallowed
         | by the id route.
         */
        Route::prefix('inventory/vendor-invoices')->group(function () {
            Route::get('/',               [VendorInvoiceController::class, 'index']);
            Route::get('/form-options',   [VendorInvoiceController::class, 'formOptions']);
            Route::get('/{vendorInvoice}', [VendorInvoiceController::class, 'show']);
            Route::post('/',              [VendorInvoiceController::class, 'store'])
                ->middleware('api.role:module:finance,edit');
            Route::delete('/{vendorInvoice}', [VendorInvoiceController::class, 'destroy'])
                ->middleware('api.role:module:finance,delete');
        });

        /*
         | -------- Inventory Settings (Category/Location/Sub-type/Variant CRUD
         |          + global key/value settings) — ADMIN ONLY --------
         | Same InventoryController@settings()/updateSettings()/store.../update...
         | /destroy... "brain" the web inventory settings page uses. Whole prefix
         | is admin-gated at the group level (simpler than repeating the
         | middleware per route — every single route here is a write or an
         | admin-only read).
         */
        // Slice 1.4: inventory master/settings writes follow the owner-configured
        // inventory module (edit) instead of the admin role name.
        Route::prefix('inventory/settings')->middleware('api.role:module:inventory,edit')->group(function () {
            Route::get('/',  [InventorySettingsController::class, 'index']);
            Route::post('/', [InventorySettingsController::class, 'updateSettings']);

            Route::post('/categories',        [InventorySettingsController::class, 'storeCategory']);
            Route::put('/categories/{cat}',   [InventorySettingsController::class, 'updateCategory']);
            Route::delete('/categories/{cat}', [InventorySettingsController::class, 'destroyCategory']);

            Route::post('/sub-types',       [InventorySettingsController::class, 'storeSubType']);
            Route::put('/sub-types/{st}',   [InventorySettingsController::class, 'updateSubType']);
            Route::delete('/sub-types/{st}', [InventorySettingsController::class, 'destroySubType']);

            Route::post('/variants',            [InventorySettingsController::class, 'storeVariant']);
            Route::put('/variants/{variant}',   [InventorySettingsController::class, 'updateVariant']);
            Route::delete('/variants/{variant}', [InventorySettingsController::class, 'destroyVariant']);

            Route::post('/locations',       [InventorySettingsController::class, 'storeLocation']);
            Route::put('/locations/{loc}',  [InventorySettingsController::class, 'updateLocation']);
            Route::delete('/locations/{loc}', [InventorySettingsController::class, 'destroyLocation']);
        });

        // Vendor edit — sits outside the /inventory/settings prefix since
        // /inventory/vendors (GET, list) is already declared earlier in this
        // file with no prefix; this PUT doesn't collide with it (different
        // HTTP verb + different path shape: /inventory/vendors/{vendor}).
        Route::put('/inventory/vendors/{vendor}', [InventorySettingsController::class, 'updateVendor'])
            ->middleware('api.role:module:inventory,edit');
    });
});
