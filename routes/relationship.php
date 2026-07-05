<?php

use App\Http\Controllers\Relationship\AnalyticsController as RelationshipAnalyticsController;
use App\Http\Controllers\Relationship\DashboardController as RelationshipDashboardController;
use App\Http\Controllers\Relationship\LeadPipelineController;
use App\Http\Controllers\Relationship\MissedCallsController;
use App\Http\Controllers\Relationship\OpportunityPipelineController;
use App\Http\Controllers\Relationship\RecallPipelineController;
use App\Http\Controllers\Relationship\ReceptionController;
use App\Http\Controllers\Relationship\RelationshipListController;
use App\Http\Controllers\Relationship\NotificationController as RelationshipNotificationController;
use App\Http\Controllers\Relationship\ProfileController;
use App\Http\Controllers\Relationship\ReferralRewardController;
use App\Http\Controllers\Relationship\SettingsController as RelationshipSettingsController;
use App\Http\Controllers\Relationship\TemplateController as RelationshipTemplateController;
use App\Http\Controllers\Relationship\TodayController;
use Illuminate\Support\Facades\Route;

/**
 * Relationship Engine routes — Phase 2: Today's Actions
 *
 * Registered in bootstrap/app.php alongside other module route files.
 * All routes require auth. No extra module gate — this is a core feature.
 */
Route::middleware(['web', 'auth'])->prefix('relationship')->name('relationship.')->group(function () {

    // ── Profile + Universal Search (Phase 3) ──────────────────────────────

    // ── Today's Actions ────────────────────────────────────────────────────
    // Declared BEFORE /{id} so "today" is not mistaken for an ID.
    Route::get('/today', [TodayController::class, 'index'])
        ->name('today');

    Route::post('/today/action', [TodayController::class, 'logAction'])
        ->name('today.action');

    // Birthday Wishes — one-click WhatsApp send (2026-07-06), replacing the
    // Call Workflow drawer for this category only. See TodayController.
    Route::post('/today/birthday-whatsapp', [TodayController::class, 'sendBirthdayWhatsapp'])
        ->name('today.birthday-whatsapp');

    // Shared projection summary (JSON) — consumed by the Daily Huddle (slice E4).
    Route::get('/today/summary', [TodayController::class, 'summary'])
        ->name('today.summary');

    // ── Missed Calls — full paginated backlog list (2026-07-05) ────────────
    // The dashboard widget only samples ~50 rows for "yesterday"; this page
    // is the full backlog behind badges like "910". Static segments — all
    // declared before the /{id} wildcard at the bottom of this file.
    Route::get('/today/missed-calls', [MissedCallsController::class, 'index'])
        ->name('today.missed-calls');

    Route::post('/today/missed-calls/bulk-dismiss', [MissedCallsController::class, 'bulkDismiss'])
        ->name('today.missed-calls.bulk-dismiss');

    Route::post('/today/missed-calls/{missedCall}/ignore', [MissedCallsController::class, 'ignore'])
        ->whereNumber('missedCall')
        ->name('today.missed-calls.ignore');

    Route::post('/today/missed-calls/{missedCall}/unignore', [MissedCallsController::class, 'unignore'])
        ->whereNumber('missedCall')
        ->name('today.missed-calls.unignore');

    // ── In-App Notifications (Phase 6) ─────────────────────────────────────
    // Note: mark-all must come BEFORE /{id}/read to avoid wildcard conflict.
    // Also declared before /{id} to avoid static segments being caught by wildcard.
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',               [RelationshipNotificationController::class, 'index'])
            ->name('index');  // relationship.notifications.index — JSON list
        Route::post('/read-all',      [RelationshipNotificationController::class, 'markAllRead'])
            ->name('read-all');
        Route::post('/{id}/read',     [RelationshipNotificationController::class, 'markRead'])
            ->whereNumber('id')
            ->name('read');
    });

    // ── PRE Dashboard (Phase 1 · Workstream D) ─────────────────────────────
    // Static segment — declared before the /{id} wildcard below.
    Route::get('/dashboard', [RelationshipDashboardController::class, 'index'])
        ->name('dashboard');  // relationship.dashboard

    // ── PRE Relationships index (Phase 1 · Workstream D, slice 5) ───────────
    // Searchable / filterable / paginated browse over the whole base.
    Route::get('/list', [RelationshipListController::class, 'index'])
        ->name('list');  // relationship.list

    // ── PRE Lead Pipeline (Phase 1 · Workstream D, slice 2) ────────────────
    // Relationship-centric lead board grouped by the reliable legacy stage.
    // The legacy PRM board was retired in Phase 8 · Slice 5 (moved to
    // under_review/phase8_prm_retirement/, not deleted). This is now the
    // only lead-pipeline surface. Static segment — must stay above the
    // /{id} wildcard below.
    Route::get('/pipeline', [LeadPipelineController::class, 'index'])
        ->name('pipeline');  // relationship.pipeline

    // ── PRE Lead Pipeline writes (Phase 8 · Slice 1 — PRM Retirement) ───────
    // Core lifecycle writes ported onto PRE, alongside (not instead of) the
    // legacy prm.move-stage / prm.log-activity / prm.convert routes, which
    // stay untouched as a warm fallback through the soak. Static segments —
    // must stay above the /{id} wildcard below.
    Route::post('/pipeline/{id}/move', [LeadPipelineController::class, 'moveStage'])
        ->whereNumber('id')
        ->name('pipeline.move');  // relationship.pipeline.move

    Route::post('/pipeline/{id}/activity', [LeadPipelineController::class, 'logActivity'])
        ->whereNumber('id')
        ->name('pipeline.activity');  // relationship.pipeline.activity

    Route::post('/pipeline/{id}/convert', [LeadPipelineController::class, 'convertToPatient'])
        ->whereNumber('id')
        ->name('pipeline.convert');  // relationship.pipeline.convert

    // ── PRE Lead create + edit (Phase 8 · Slice 2 — PRM Retirement) ─────────
    // Ported alongside (not instead of) prm.add-lead / prm.store-lead /
    // prm.edit-lead / prm.update-lead / prm.quick-add / prm.store-quick-lead,
    // which stay untouched. Static 2-segment routes first, then the 3-segment
    // {id}/edit route — none of these collide with the trailing /{id}
    // wildcard (different segment counts), but declared here for readability.
    Route::get('/pipeline/quick-add', [LeadPipelineController::class, 'quickAdd'])
        ->name('pipeline.quick-add');  // relationship.pipeline.quick-add

    Route::post('/pipeline/quick-add', [LeadPipelineController::class, 'storeQuickLead'])
        ->name('pipeline.store-quick-lead');  // relationship.pipeline.store-quick-lead

    Route::get('/pipeline/add', [LeadPipelineController::class, 'addLead'])
        ->name('pipeline.add-lead');  // relationship.pipeline.add-lead

    Route::post('/pipeline/add', [LeadPipelineController::class, 'storeLead'])
        ->name('pipeline.store-lead');  // relationship.pipeline.store-lead

    Route::get('/pipeline/{id}/edit', [LeadPipelineController::class, 'editLead'])
        ->whereNumber('id')
        ->name('pipeline.edit-lead');  // relationship.pipeline.edit-lead

    Route::post('/pipeline/{id}/edit', [LeadPipelineController::class, 'updateLead'])
        ->whereNumber('id')
        ->name('pipeline.update-lead');  // relationship.pipeline.update-lead

    // ── PRE AI helpers (Phase 8 · Slice 3 — PRM Retirement) ─────────────────
    // Ported alongside prm.enrich / prm.draft-reply / prm.log-reply.
    Route::post('/pipeline/{id}/enrich', [LeadPipelineController::class, 'reEnrich'])
        ->whereNumber('id')
        ->name('pipeline.enrich');  // relationship.pipeline.enrich

    Route::post('/pipeline/{id}/draft-reply', [LeadPipelineController::class, 'draftReply'])
        ->whereNumber('id')
        ->name('pipeline.draft-reply');  // relationship.pipeline.draft-reply

    Route::post('/pipeline/{id}/log-reply', [LeadPipelineController::class, 'logReply'])
        ->whereNumber('id')
        ->name('pipeline.log-reply');  // relationship.pipeline.log-reply

    // ── PRE Opportunity + Recall pipelines (Phase 1 · Workstream D, slice 3) ─
    // Read-only, relationship-centric boards, additive alongside the legacy
    // Communication / Opportunity surfaces (which are left untouched).
    // Static segments — must stay above the /{id} wildcard below.
    Route::get('/opportunities', [OpportunityPipelineController::class, 'index'])
        ->name('opportunities');  // relationship.opportunities

    Route::get('/recalls', [RecallPipelineController::class, 'index'])
        ->name('recalls');  // relationship.recalls

    Route::post('/recalls', [RecallPipelineController::class, 'store'])
        ->name('recalls.store');  // relationship.recalls.store — manual "+ Add Recall"

    // Reception dashboard removed 2026-07-06 (Sumit) — it read the exact same
    // Today's Actions projection with zero interactivity (no Call drawer, no
    // date picker), so it was a strictly weaker duplicate of /today. Controller
    // + view left in place (unreferenced) rather than deleted; ask if you want
    // those files removed too.

    // ── Analytics (Phase 6) ────────────────────────────────────────────────
    Route::get('/analytics', [RelationshipAnalyticsController::class, 'index'])
        ->name('analytics');  // relationship.analytics

    // ── Module-scoped Settings (2026-07-03) ─────────────────────────────────
    // Only PRE-relevant flags — moved out of the global Settings module so
    // PRE can be sold/run standalone. Static segments — before /{id} below.
    Route::get('/settings', [RelationshipSettingsController::class, 'index'])
        ->name('settings');  // relationship.settings

    Route::post('/settings/toggle', [RelationshipSettingsController::class, 'toggleFlag'])
        ->name('settings.toggle');  // relationship.settings.toggle

    Route::post('/settings/referral', [RelationshipSettingsController::class, 'saveReferralConfig'])
        ->name('settings.referral');  // relationship.settings.referral

    Route::post('/settings/recall-effective-from', [RelationshipSettingsController::class, 'saveRecallEffectiveFrom'])
        ->name('settings.recall-effective-from');  // relationship.settings.recall-effective-from

    // ── Recall / Birthday settings (moved from Communication OS
    // 2026-07-06) — periodicities, channel toggles, and enable/window settings
    // for RecallEngineService + the Birthday trigger. Folded into
    // the same relationship.settings page (see SettingsController@index) —
    // these are POST-only handlers, not separate pages. Static segments —
    // before /{id} below.
    Route::post('/settings/recall-general', [RelationshipSettingsController::class, 'saveRecallGeneral'])
        ->name('settings.recall-general');  // relationship.settings.recall-general

    Route::post('/settings/recall-treatment/{treatmentType}', [RelationshipSettingsController::class, 'saveTreatmentRecall'])
        ->whereNumber('treatmentType')
        ->name('settings.recall-treatment');  // relationship.settings.recall-treatment

    Route::post('/settings/recall-birthday', [RelationshipSettingsController::class, 'saveBirthday'])
        ->name('settings.recall-birthday');  // relationship.settings.recall-birthday

    // ── Templates (moved from Communication OS 2026-07-06) ─────────────────
    // Generic, reusable Message Template editor — any PRE feature (Recall,
    // Birthday, ...) points its Settings "gear" icon at
    // relationship.templates.forType for a given type. Deliberately NOT a tab
    // in the PRE tab-strip — deep-link only. Static segments — before /{id}.
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/',            [RelationshipTemplateController::class, 'index'])->name('index');
        Route::get('/create',      [RelationshipTemplateController::class, 'create'])->name('create');
        Route::post('/',           [RelationshipTemplateController::class, 'store'])->name('store');
        // Deep-link by type (Settings gear icons) — must come before the
        // `/{id}` wildcard below since "for-type" isn't numeric.
        Route::get('/for-type/{type}', [RelationshipTemplateController::class, 'forType'])->name('forType');
        Route::get('/{id}',        [RelationshipTemplateController::class, 'edit'])->name('edit');
        Route::put('/{id}',        [RelationshipTemplateController::class, 'update'])->name('update');
        Route::delete('/{id}',     [RelationshipTemplateController::class, 'destroy'])->name('destroy');
    });

    // ── Referral rewards (business config lives in Settings above) ─────────
    // Two-segment static route — no conflict with the /{id} wildcard below.
    Route::post('/{id}/referral-reward', [ReferralRewardController::class, 'store'])
        ->whereNumber('id')
        ->name('referral-reward.store');  // relationship.referral-reward.store

    // ── Profile + Universal Search ─────────────────────────────────────────
    // IMPORTANT: /{id} wildcard must come LAST — all static routes above.
    Route::get('/search', [ProfileController::class, 'search'])
        ->name('search');  // relationship.search — returns JSON for AJAX typeahead

    Route::get('/{id}', [ProfileController::class, 'show'])
        ->whereNumber('id')
        ->name('profile');  // relationship.profile

});
