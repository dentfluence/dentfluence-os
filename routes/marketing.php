<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Marketing\OverviewController;
use App\Http\Controllers\Marketing\PublishController;
use App\Http\Controllers\Marketing\CalendarController;
use App\Http\Controllers\Marketing\BrainstormController;
use App\Http\Controllers\Marketing\IdeaController;
use App\Http\Controllers\Marketing\CampaignController;
use App\Http\Controllers\Marketing\LibraryController;
use App\Http\Controllers\Marketing\AssetController;
use App\Http\Controllers\Marketing\BrandKitController;
use App\Http\Controllers\Marketing\IntegrationController;
use App\Http\Controllers\Marketing\AnalyticsController;
use App\Http\Controllers\Marketing\SettingsController;

Route::middleware(['marketing.active', 'module:marketing'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        Route::get('/', [OverviewController::class, 'index'])->name('overview');

        Route::get('/publish',  [PublishController::class, 'index'])->name('publish');
        Route::post('/publish', [PublishController::class, 'store'])->name('publish.store');
        Route::post('/publish/draft', [PublishController::class, 'saveDraft'])->name('publish.draft');
        Route::put('/publish/{post}/variants/{platform}', [PublishController::class, 'updateVariant'])->name('publish.variant');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::get('/calendar/export', [CalendarController::class, 'export'])->name('calendar.export');
        Route::put('/calendar/{post}/reschedule', [CalendarController::class, 'reschedule'])->name('calendar.reschedule');

        Route::get('/brainstorm', [BrainstormController::class, 'index'])->name('brainstorm');

        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
        Route::post('/ideas/{idea}/convert-post', [IdeaController::class, 'convertToPost'])->name('ideas.convert-post');
        Route::post('/ideas/{idea}/convert-campaign', [IdeaController::class, 'convertToCampaign'])->name('ideas.convert-campaign');

        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::put('/campaigns/{campaign}/goals', [CampaignController::class, 'updateGoals'])->name('campaigns.goals');
        Route::post('/campaigns/{campaign}/team', [CampaignController::class, 'addTeamMember'])->name('campaigns.team.add');
        Route::delete('/campaigns/{campaign}/team', [CampaignController::class, 'removeTeamMember'])->name('campaigns.team.remove');

        Route::get('/library', [LibraryController::class, 'index'])->name('library');
        Route::post('/library/folders', [LibraryController::class, 'createFolder'])->name('library.folders.store');
        Route::put('/library/folders/{folder}', [LibraryController::class, 'renameFolder'])->name('library.folders.update');
        Route::delete('/library/folders/{folder}', [LibraryController::class, 'deleteFolder'])->name('library.folders.destroy');

        Route::post('/assets/upload', [AssetController::class, 'upload'])->name('assets.upload');
        Route::get('/assets/storage-usage', [AssetController::class, 'storageUsage'])->name('assets.storage-usage');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/{asset}/tags', [AssetController::class, 'addTag'])->name('assets.tags.add');
        Route::delete('/assets/{asset}/tags', [AssetController::class, 'removeTag'])->name('assets.tags.remove');

        Route::get('/brand-kit', [BrandKitController::class, 'index'])->name('brand-kit');
        Route::put('/brand-kit', [BrandKitController::class, 'update'])->name('brand-kit.update');
        Route::post('/brand-kit/logo', [BrandKitController::class, 'storeLogo'])->name('brand-kit.logo');

        // ── Integrations (Phase 5 — OAuth wiring) ────────────────────────────
        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations');

        // Static setup forms FIRST — must be before {platform} wildcard routes
        Route::get('/integrations/whatsapp/setup',  [IntegrationController::class, 'showWhatsappForm'])->name('integrations.whatsapp-setup');
        Route::post('/integrations/whatsapp/save',  [IntegrationController::class, 'saveWhatsapp'])->name('integrations.whatsapp-save');
        Route::get('/integrations/wordpress/setup', [IntegrationController::class, 'showWordpressForm'])->name('integrations.wordpress-setup');
        Route::post('/integrations/wordpress/save', [IntegrationController::class, 'saveWordpress'])->name('integrations.wordpress-save');

        // Parameterized OAuth routes (after static routes above)
        Route::get('/integrations/{platform}/connect',       [IntegrationController::class, 'connect'])->name('integrations.connect');
        Route::get('/integrations/{platform}/callback',      [IntegrationController::class, 'callback'])->name('integrations.callback');
        Route::post('/integrations/{platform}/disconnect',   [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');
        Route::post('/integrations/{platform}/health-check', [IntegrationController::class, 'healthCheck'])->name('integrations.health-check');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    });
