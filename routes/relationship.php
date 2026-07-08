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

    // Dismiss — clear a row without logging a call outcome (requires a reason).
    // See docs/feature-specs/feature-spec-action-board-dismiss.md.
    Route::post('/today/dismiss', [TodayController::class, 'dismiss'])
        ->name('today.dismiss');

    // Close — explicit "done with this one" action, separate from Log
    // (2026-07-08: Log used to auto-close the row per outcome, which meant
    // "No answer" silently removed the row instead of leaving it open for
    // a retry). No outcome required.
    Route::post('/today/close', [TodayController::class, 'closeAction'])
        ->name('today.close');

    // Notes — same Suggestion/Patient-Response log already live on Lead &
    // Opportunity Pipeline (see OpportunityPipelineController::addNote()),
    // ported to the Action Board drawer. See
    // docs/feature-specs/feature-spec-action-board-instruction-log.md.
    Route::get('/today/notes', [TodayController::class, 'notes'])
        ->name('today.notes.index');

    Route::post('/today/notes', [TodayController::class, 'addNote'])
        ->name('today.notes.add');

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

    // Lead Detail modal (2026-07-08) — shows the logged activity history
    // (who logged what, and when) for one lead. Mirrors the Opportunity
    // Pipeline's "Opportunity Detail" modal (relationship.opportunities.detail-modal).
    Route::get('/pipeline/{id}/modal', [LeadPipelineController::class, 'detailModal'])
        ->whereNumber('id')
        ->name('pipeline.detail-modal');  // relationship.pipeline.detail-modal

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

    // ── PRE Opportunity Pipeline (Phase 1 · Workstream D, slice 3;
    // full read/write board 2026-07-06 — replaces the legacy Communication
    // "Opportunity Engine", which now redirects here.) ────────────────────
    // Static segments — must stay above the /{id} wildcard below.
    Route::get('/opportunities', [OpportunityPipelineController::class, 'index'])
        ->name('opportunities');  // relationship.opportunities

    Route::post('/opportunities', [OpportunityPipelineController::class, 'store'])
        ->name('opportunities.store');  // relationship.opportunities.store

    Route::get('/opportunities/patient-search', [OpportunityPipelineController::class, 'patientSearch'])
        ->name('opportunities.patient-search');  // relationship.opportunities.patient-search

    Route::get('/opportunities/{id}/modal', [OpportunityPipelineController::class, 'detailModal'])
        ->whereNumber('id')
        ->name('opportunities.detail-modal');  // relationship.opportunities.detail-modal

    Route::patch('/opportunities/{id}/stage', [OpportunityPipelineController::class, 'updateStage'])
        ->whereNumber('id')
        ->name('opportunities.update-stage');  // relationship.opportunities.update-stage

    Route::post('/opportunities/{id}/convert', [OpportunityPipelineController::class, 'convertToLead'])
        ->whereNumber('id')
        ->name('opportunities.convert');  // relationship.opportunities.convert

    // Stage notes (2026-07-06) — see docs/feature-specs/feature-spec-stage-notes.md
    Route::post('/opportunities/{id}/notes', [OpportunityPipelineController::class, 'addNote'])
        ->whereNumber('id')
        ->name('opportunities.notes.add');  // relationship.opportunities.notes.add

    // ── PRE Recalls (Phase 1 · Workstream D, slice 3; rebuilt 2026-07-06) ────
    // Filterable, actionable list — additive alongside the legacy
    // Communication / Recall surfaces (which are left untouched). Static
    // segments only, all before the /{id} wildcard at the bottom of this file.
    Route::get('/recalls', [RecallPipelineController::class, 'index'])
        ->name('recalls');  // relationship.recalls

    Route::post('/recalls', [RecallPipelineController::class, 'store'])
        ->name('recalls.store');  // relationship.recalls.store — manual "+ Add Recall"

    Route::post('/recalls/bulk-dismiss', [RecallPipelineController::class, 'bulkDismiss'])
        ->name('recalls.bulk-dismiss');  // relationship.recalls.bulk-dismiss

    Route::post('/recalls/bulk-assign', [RecallPipelineController::class, 'bulkAssign'])
        ->name('recalls.bulk-assign');  // relationship.recalls.bulk-assign

    Route::post('/recalls/{recall}/ignore', [RecallPipelineController::class, 'ignore'])
        ->whereNumber('recall')
        ->name('recalls.ignore');  // relationship.recalls.ignore

    Route::post('/recalls/{recall}/unignore', [RecallPipelineController::class, 'unignore'])
        ->whereNumber('recall')
        ->name('recalls.unignore');  // relationship.recalls.unignore

    Route::post('/recalls/{recall}/convert', [RecallPipelineController::class, 'convertToOpportunity'])
        ->whereNumber('recall')
        ->name('recalls.convert');  // relationship.recalls.convert

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

    // ── Call Outcomes + Dismiss Reasons (2026-07-06) ────────────────────────
    // See docs/feature-specs/feature-spec-custom-call-outcomes.md and
    // feature-spec-action-board-dismiss.md. Static segments — before /{id}.
    Route::post('/settings/call-outcomes/{category}/add', [RelationshipSettingsController::class, 'addCallOutcome'])
        ->name('settings.call-outcomes.add');  // relationship.settings.call-outcomes.add

    Route::post('/settings/call-outcomes/{option}', [RelationshipSettingsController::class, 'saveCallOutcome'])
        ->whereNumber('option')
        ->name('settings.call-outcomes.save');  // relationship.settings.call-outcomes.save

    Route::post('/settings/dismiss-reasons/add', [RelationshipSettingsController::class, 'addDismissReason'])
        ->name('settings.dismiss-reasons.add');  // relationship.settings.dismiss-reasons.add

    Route::post('/settings/dismiss-reasons/{option}', [RelationshipSettingsController::class, 'saveDismissReason'])
        ->whereNumber('option')
        ->name('settings.dismiss-reasons.save');  // relationship.settings.dismiss-reasons.save

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
