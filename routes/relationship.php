<?php

use App\Http\Controllers\Relationship\AnalyticsController as RelationshipAnalyticsController;
use App\Http\Controllers\Relationship\DashboardController as RelationshipDashboardController;
use App\Http\Controllers\Relationship\LeadPipelineController;
use App\Http\Controllers\Relationship\OpportunityPipelineController;
use App\Http\Controllers\Relationship\RecallPipelineController;
use App\Http\Controllers\Relationship\ReceptionController;
use App\Http\Controllers\Relationship\RelationshipListController;
use App\Http\Controllers\Relationship\NotificationController as RelationshipNotificationController;
use App\Http\Controllers\Relationship\ProfileController;
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

    // Shared projection summary (JSON) — consumed by the Daily Huddle (slice E4).
    Route::get('/today/summary', [TodayController::class, 'summary'])
        ->name('today.summary');

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
    // Additive alongside the legacy PRM board (/communication/prm/board),
    // which is left completely untouched. Read-only. Static segment — must
    // stay above the /{id} wildcard below.
    Route::get('/pipeline', [LeadPipelineController::class, 'index'])
        ->name('pipeline');  // relationship.pipeline

    // ── PRE Opportunity + Recall pipelines (Phase 1 · Workstream D, slice 3) ─
    // Read-only, relationship-centric boards, additive alongside the legacy
    // Communication / Opportunity surfaces (which are left untouched).
    // Static segments — must stay above the /{id} wildcard below.
    Route::get('/opportunities', [OpportunityPipelineController::class, 'index'])
        ->name('opportunities');  // relationship.opportunities

    Route::get('/recalls', [RecallPipelineController::class, 'index'])
        ->name('recalls');  // relationship.recalls

    // ── PRE Reception dashboard (Phase 1 · Workstream E, slice E3) ──────────
    // Reads the Today's Actions projection into Today's Calls / Today's Work.
    Route::get('/reception', [ReceptionController::class, 'index'])
        ->name('reception');  // relationship.reception

    // ── Analytics (Phase 6) ────────────────────────────────────────────────
    Route::get('/analytics', [RelationshipAnalyticsController::class, 'index'])
        ->name('analytics');  // relationship.analytics

    // ── Profile + Universal Search ─────────────────────────────────────────
    // IMPORTANT: /{id} wildcard must come LAST — all static routes above.
    Route::get('/search', [ProfileController::class, 'search'])
        ->name('search');  // relationship.search — returns JSON for AJAX typeahead

    Route::get('/{id}', [ProfileController::class, 'show'])
        ->whereNumber('id')
        ->name('profile');  // relationship.profile

});
