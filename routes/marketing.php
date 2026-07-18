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
use App\Http\Controllers\Marketing\ReviewsController;
use App\Http\Controllers\Marketing\BlogController;

Route::middleware(['marketing.active', 'module:marketing'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        Route::get('/', [OverviewController::class, 'index'])->name('overview');

        Route::get('/publish',  [PublishController::class, 'index'])->name('publish');
        Route::post('/publish', [PublishController::class, 'store'])->name('publish.store');
        Route::post('/publish/draft', [PublishController::class, 'saveDraft'])->name('publish.draft');
        Route::put('/publish/{post}/variants/{platform}', [PublishController::class, 'updateVariant'])->name('publish.variant');

        // ── Blog Marketing Hub (Wave 1) ──────────────────────────────────────
        // Gated by Feature 'blog.hub' inside BlogController (404 when off).
        // All {blog} params bind on the post's permanent uuid, never the slug
        // or sequential id (BlogPost::getRouteKeyName). Slice 2 = CRUD + save/
        // autosave/versioning; Slice 3 = editor; Slice 4 = SEO panel; Slice 5 =
        // CMS list + calendar; publishing adapter lands in Slice 6
        // (docs/blog-marketing-hub-masterplan.md §8).
        Route::get('/blog',                 [BlogController::class, 'index'])->name('blog.index');
        Route::post('/blog',                [BlogController::class, 'store'])->name('blog.store');

        // Editor pages (Slice 3). `create` MUST precede the /blog/{blog} show
        // route below — {blog} binds on uuid, so the literal "create" segment
        // would otherwise be swallowed and 404 on uuid resolution.
        Route::get('/blog/create',          [BlogController::class, 'create'])->name('blog.create');

        // NOTE: the blog no longer has its own calendar view — scheduled/
        // published posts surface in the single Marketing calendar
        // (marketing.calendar / CalendarController::index) instead.

        Route::get('/blog/{blog}/edit',     [BlogController::class, 'edit'])->name('blog.edit');

        // Editor support endpoints (JSON): DAM image list + inline category/tag
        // create. Namespaced under distinct prefixes so they never collide with
        // the uuid-bound /blog/{blog} routes.
        Route::get('/blog-media/assets',        [BlogController::class, 'assetsIndex'])->name('blog.assets');
        Route::post('/blog-taxonomy/categories',[BlogController::class, 'categoriesStore'])->name('blog.categories.store');
        Route::post('/blog-taxonomy/tags',      [BlogController::class, 'tagsStore'])->name('blog.tags.store');

        Route::get('/blog/{blog}',          [BlogController::class, 'show'])->name('blog.show');
        Route::put('/blog/{blog}',          [BlogController::class, 'update'])->name('blog.update');
        Route::post('/blog/{blog}/draft',   [BlogController::class, 'draft'])->name('blog.draft');
        Route::post('/blog/{blog}/autosave',[BlogController::class, 'autosave'])->name('blog.autosave');
        Route::post('/blog/{blog}/duplicate',[BlogController::class, 'duplicate'])->name('blog.duplicate');
        Route::post('/blog/{blog}/archive',  [BlogController::class, 'archive'])->name('blog.archive');
        Route::post('/blog/{blog}/unarchive',[BlogController::class, 'unarchive'])->name('blog.unarchive');
        Route::delete('/blog/{blog}',        [BlogController::class, 'destroy'])->name('blog.destroy');

        // Version history (autosave/manual/scheduled/publish snapshots + restore)
        Route::get('/blog/{blog}/versions',                    [BlogController::class, 'versionsIndex'])->name('blog.versions.index');
        Route::post('/blog/{blog}/versions/{version}/restore', [BlogController::class, 'versionsRestore'])->name('blog.versions.restore');
        Route::delete('/blog/{blog}/versions/{version}',       [BlogController::class, 'versionsDestroy'])->name('blog.versions.destroy');

        // Website publishing (Slice 6) — adapter-driven publish/sync + status,
        // retry-on-failure and delete-from-site, all via the blog_publications
        // ledger (WordPress → queued job; standalone → inline). No live site
        // connected resolves to the standalone adapter — never a faked success.
        Route::get('/blog/{blog}/publications',                      [BlogController::class, 'publicationsIndex'])->name('blog.publications');
        Route::post('/blog/{blog}/publish',                          [BlogController::class, 'publish'])->name('blog.publish');
        Route::post('/blog/{blog}/publications/{publication}/retry', [BlogController::class, 'publicationRetry'])->name('blog.publication.retry');
        Route::delete('/blog/{blog}/publications/{publication}',     [BlogController::class, 'publicationDestroy'])->name('blog.publication.destroy');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::get('/calendar/export', [CalendarController::class, 'export'])->name('calendar.export');
        Route::put('/calendar/{post}/reschedule', [CalendarController::class, 'reschedule'])->name('calendar.reschedule');

        Route::get('/brainstorm', [BrainstormController::class, 'index'])->name('brainstorm');

        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
        Route::post('/ideas/{idea}/convert-post', [IdeaController::class, 'convertToPost'])->name('ideas.convert-post');
        Route::post('/ideas/{idea}/convert-campaign', [IdeaController::class, 'convertToCampaign'])->name('ideas.convert-campaign');
        Route::post('/ideas/from-review/{review}', [IdeaController::class, 'createFromReview'])->name('ideas.from-review');

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
        // Native Reviews board (2026-07-09) — reuses Communication's ReviewService/
        // Review model, just rendered under Marketing's own URL/layout instead of
        // linking out to /communication/reviews. Send/reply actions still POST to
        // the communication.reviews.* routes (see resources/views/communication/reviews/_content.blade.php).
        Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/integrated-mode', [SettingsController::class, 'toggleIntegratedMode'])->name('settings.integrated-mode');
    });
