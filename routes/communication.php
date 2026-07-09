<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Communication\DashboardController;
use App\Http\Controllers\Communication\CommunicationController;

/*
|--------------------------------------------------------------------------
| Communication OS — Routes
| Dentfluence · Session 1: Foundation
|--------------------------------------------------------------------------
|
| All routes are prefixed /communication and named communication.*
| Middleware: auth + communication.access (defined in Session 1)
|
| Controllers for Sessions 2-8 are registered here as stubs now
| and filled out in their respective sessions.
|
*/

Route::prefix('communication')
    ->name('communication.')
    // Was 'module:prm' — stale reference. Migration
    // 2026_07_06_200002_cleanup_and_activate_role_permissions deleted the
    // 'prm' module row and created 'communication' in its place (granting
    // view+edit to every role as a zero-regression baseline), but this
    // route group was never updated to match — so every role without the
    // old ungated "role===admin && !role_id" bypass got locked out of all
    // of Communication (Reviews, WhatsApp inbox, etc). Found live 2026-07-09
    // while testing the Marketing→Reviews link.
    ->middleware(['web', 'auth', 'communication.access', 'module:communication'])
    ->group(function () {

        // ── Module Home ──────────────────────────────────────────────────
        Route::get('/', [DashboardController::class, 'index'])
            ->name('index');

        // ── Communication List / Manager (PRM Update 2026-06-13) ────────
        Route::prefix('manager')->name('manager.')->group(function () {
            // Tabbed inbox — RETIRED 2026-07-06 (Sumit's call): this list read
            // like the old PRM board and duplicated what PRE's Today's Actions
            // now covers (recall calls, missed appointments, lead follow-ups,
            // membership renewals, and manually-logged comms via the new
            // 'logged_communications' category). Redirects rather than 404s so
            // any stale bookmark/link still lands somewhere useful — same
            // pattern as the recall-settings/templates retirements below.
            // Controller method + view left in place (unreachable, not deleted).
            Route::get('/', fn () => redirect()->route('relationship.today'))->name('index');

            // Add Communication
            Route::get('/add', [CommunicationController::class, 'logForm'])->name('log.form');
            Route::post('/add',[CommunicationController::class, 'logStore'])->name('log.store');

            // AJAX patient search (before /{id} to avoid conflict)
            Route::get('/patient-search', [CommunicationController::class, 'patientSearch'])->name('patient.search');

            // Bulk action
            Route::post('/bulk', [CommunicationController::class, 'bulkAction'])->name('bulk');

            // Single record
            Route::get('/{id}',          [CommunicationController::class, 'show'])->name('show');
            Route::put('/{id}',          [CommunicationController::class, 'update'])->name('update');
            Route::post('/{id}/assign',  [CommunicationController::class, 'assign'])->name('assign');
            Route::post('/{id}/move',    [CommunicationController::class, 'move'])->name('move');
            // Phase 1: attempt logging + mandatory-outcome close
            Route::post('/{id}/attempt', [CommunicationController::class, 'logAttempt'])->name('attempt');
            Route::post('/{id}/close',   [CommunicationController::class, 'closeWithOutcome'])->name('close');
        });

        // ── PRM Pipeline ─────────────────────────────────────────────────
        // NOTE: The PRM routes now live in routes/prm.php (named `prm.*`),
        // which is the single source of truth and carries the full feature
        // set (inbox, reports, source-analytics, AI enrich/reply, etc.).
        // The old duplicate stub group that was here (named `communication.prm.*`)
        // was removed 2026-06-26 — all references were migrated to `prm.*`.

        // ── Follow-up Engine (Session 4) ─────────────────────────────────
        Route::prefix('followup')->name('followup.')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Communication\FollowUpController::class, 'index'])->name('index');
            Route::get('/queue',                     [\App\Http\Controllers\Communication\FollowUpController::class, 'queue'])->name('queue');
            Route::get('/overdue',                   [\App\Http\Controllers\Communication\FollowUpController::class, 'overdue'])->name('overdue');
            Route::get('/calendar',                  [\App\Http\Controllers\Communication\FollowUpController::class, 'calendar'])->name('calendar');
            Route::get('/recalls',                   [\App\Http\Controllers\Communication\FollowUpController::class, 'recalls'])->name('recalls');
            Route::post('/schedule',                 [\App\Http\Controllers\Communication\FollowUpController::class, 'schedule'])->name('schedule');
            Route::post('/{id}/complete',            [\App\Http\Controllers\Communication\FollowUpController::class, 'complete'])->name('complete');
            Route::post('/{id}/reschedule',          [\App\Http\Controllers\Communication\FollowUpController::class, 'reschedule'])->name('reschedule');
            Route::post('/{id}/note',                [\App\Http\Controllers\Communication\FollowUpController::class, 'addNote'])->name('note');
            Route::post('/{id}/change-status',       [\App\Http\Controllers\Communication\FollowUpController::class, 'changeStatus'])->name('change-status');
            Route::post('/{id}/convert',             [\App\Http\Controllers\Communication\FollowUpController::class, 'convertToPatient'])->name('convert');
            Route::post('/{id}/create-case',         [\App\Http\Controllers\Communication\FollowUpController::class, 'createCase'])->name('create-case');
        });

        // ── Communication Timeline ───────────────────────────────────────
        // NOTE: Timeline routes now live solely in routes/timeline.php
        // (named `communication.timeline.index` / `.show`). The duplicate
        // group that was here (an extra `.index` + an unused `.patient`) was
        // removed 2026-06-26. Views reference `.index` and `.show` only.

        // ── Recall Engine (Phase 2) ──────────────────────────────────────
        Route::prefix('recall')->name('recall.')->group(function () {
            Route::get('/',         [\App\Http\Controllers\Communication\RecallController::class, 'index'])->name('index');
            Route::post('/run-now', [\App\Http\Controllers\Communication\RecallController::class, 'runNow'])->name('run-now');
        });

        // ── Recall + Birthday/Anniversary Settings — RETIRED, redirects only ──
        // Moved to the Relationship/PRE module 2026-07-06 (Sumit's call — these
        // are PRE concerns, not Communication OS). Controller/view archived at
        // under_review/pre_consolidation_2026_07_06/. Route *names* are kept
        // identical so any old in-app links or bookmarks that still reference
        // communication.recall-settings.* don't hard-error — they land on the
        // equivalent relationship.settings.* destination instead. Any in-app
        // links have been repointed directly at relationship.* (see
        // resources/views/communication/recall/index.blade.php's Settings
        // button) — these redirects are a safety net for stale bookmarks only.
        Route::prefix('recall-settings')->name('recall-settings.')->group(function () {
            Route::get('/', fn () => redirect()->route('relationship.settings'))->name('index');
            Route::post('/general', fn () => redirect()->route('relationship.settings'))->name('general');
            Route::post('/treatment/{treatmentType}', fn () => redirect()->route('relationship.settings'))->name('treatment')->whereNumber('treatmentType');
            Route::post('/birthday', fn () => redirect()->route('relationship.settings'))->name('birthday');
            Route::post('/anniversary', fn () => redirect()->route('relationship.settings'))->name('anniversary');
        });

        // ── Opportunity Engine — RETIRED 2026-07-06 ──────────────────────
        // Fully replaced by the PRE Opportunity Pipeline (relationship.opportunities),
        // which now has the full read/write board (Add, drag-drop, Convert, popup
        // detail) that this screen used to be the only place for. These two GET
        // routes are safety nets for stale bookmarks/links; everything else in this
        // group is unreachable dead code left in place (never deleted, per project
        // convention) — nothing calls it anymore. Old view moved to under_review/.
        Route::prefix('opportunities')->name('opportunities.')->group(function () {
            Route::get('/',                    fn () => redirect()->route('relationship.opportunities'))->name('index');
            Route::get('/board',               fn () => redirect()->route('relationship.opportunities'))->name('board');
            Route::post('/',                   [\App\Http\Controllers\Communication\OpportunityController::class, 'store'])->name('store');
            // AJAX: patient autocomplete for add-opportunity modal
            Route::get('/patient-search',      [\App\Http\Controllers\Communication\OpportunityController::class, 'patientSearch'])->name('patient-search');
            // Single opportunity (must come after named static segments)
            Route::get('/{id}',                [\App\Http\Controllers\Communication\OpportunityController::class, 'detail'])->name('detail');
            // Detail popup content (AJAX) — powers the board/list click-to-open modal
            Route::get('/{id}/modal',          [\App\Http\Controllers\Communication\OpportunityController::class, 'detailModal'])->name('detail-modal');
            Route::patch('/{id}/stage',        [\App\Http\Controllers\Communication\OpportunityController::class, 'updateStage'])->name('update-stage');
            Route::post('/{id}/convert',       [\App\Http\Controllers\Communication\OpportunityController::class, 'convertToLead'])->name('convert');
        });

        // ── Daily Huddle Widgets (Session 8) ─────────────────────────────
        Route::prefix('huddle')->name('huddle.')->group(function () {
            Route::get('/widgets',  [\App\Http\Controllers\Communication\HuddleController::class, 'widgets'])->name('widgets');
            Route::get('/overdue',  [\App\Http\Controllers\Communication\HuddleController::class, 'overdueSummary'])->name('overdue');
            Route::get('/alerts',   [\App\Http\Controllers\Communication\HuddleController::class, 'alerts'])->name('alerts');
        });

        // ── Templates — RETIRED, redirects only ───────────────────────────
        // Moved to the Relationship/PRE module 2026-07-06 (Sumit's call —
        // Templates is a PRE concern: Recall/Birthday/Anniversary copy).
        // Controller/views archived at
        // under_review/pre_consolidation_2026_07_06/. Route *names* are kept
        // identical so old in-app links/bookmarks referencing
        // communication.templates.* don't hard-error. Any in-app links have
        // been repointed directly at relationship.templates.* (see the
        // Communication sidebar partial, where the "Templates" nav item was
        // removed entirely) — these redirects are a safety net only. The
        // {id}-based routes forward the id through so an old bookmarked
        // edit link still opens the right template.
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', fn () => redirect()->route('relationship.templates.index'))->name('index');
            Route::get('/create', fn () => redirect()->route('relationship.templates.create'))->name('create');
            Route::post('/', fn () => redirect()->route('relationship.templates.index'))->name('store');
            // Deep-link by type (Recall/Birthday/Anniversary Settings gear icons) — must
            // come before the `/{id}` wildcard below since "for-type" isn't numeric.
            Route::get('/for-type/{type}', fn (string $type) => redirect()->route('relationship.templates.forType', $type))->name('forType');
            Route::get('/{id}', fn (int $id) => redirect()->route('relationship.templates.edit', $id))->name('edit');
            Route::put('/{id}', fn (int $id) => redirect()->route('relationship.templates.edit', $id))->name('update');
            Route::delete('/{id}', fn () => redirect()->route('relationship.templates.index'))->name('destroy');
        });

        // ── Phase 4: B2B Comm Module ──────────────────────────────────────
        Route::prefix('b2b')->name('b2b.')->group(function () {
            Route::get('/',                       [\App\Http\Controllers\Communication\B2BController::class, 'index'])->name('index');
            Route::get('/add',                    [\App\Http\Controllers\Communication\B2BController::class, 'create'])->name('create');
            Route::post('/add',                   [\App\Http\Controllers\Communication\B2BController::class, 'store'])->name('store');
            Route::get('/{id}',                   [\App\Http\Controllers\Communication\B2BController::class, 'show'])->name('show');
            Route::post('/{id}/attempt',          [\App\Http\Controllers\Communication\B2BController::class, 'logAttempt'])->name('attempt');
            Route::post('/{id}/close',            [\App\Http\Controllers\Communication\B2BController::class, 'close'])->name('close');
            // AJAX: open lab cases for a vendor (for form dynamic dropdown)
            Route::get('/ajax/lab-cases-for-vendor', [\App\Http\Controllers\Communication\B2BController::class, 'labCasesForVendor'])->name('ajax.lab-cases');
        });

        // ── Phase 5: KPI Dashboard ────────────────────────────────────────
        Route::prefix('kpi')->name('kpi.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Communication\KpiController::class, 'index'])->name('index');
        });

        // ── Phase B 2.4: Reviews / Reputation ─────────────────────────────
        Route::prefix('reviews')->name('reviews.')->group(function () {
            Route::get('/',     [\App\Http\Controllers\Communication\ReviewController::class, 'index'])->name('index');
            Route::post('/send',[\App\Http\Controllers\Communication\ReviewController::class, 'send'])->name('send');
            Route::post('/{review}/reply', [\App\Http\Controllers\Communication\ReviewController::class, 'reply'])->name('reply');
        });

        // ── Phase B 1.2: WhatsApp two-way Inbox ───────────────────────────
        Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Communication\WhatsAppInboxController::class, 'index'])->name('index');
            Route::get('/{thread}',           [\App\Http\Controllers\Communication\WhatsAppInboxController::class, 'show'])->name('show');
            Route::post('/{thread}/reply',    [\App\Http\Controllers\Communication\WhatsAppInboxController::class, 'reply'])->name('reply');
            Route::post('/{thread}/template', [\App\Http\Controllers\Communication\WhatsAppInboxController::class, 'sendTemplate'])->name('template');
        });
    });
