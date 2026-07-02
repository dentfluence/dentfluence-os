<?php

namespace App\Providers;

use App\Domain\Events\DomainEventBus;
use App\Http\Controllers\System\StatusController;
use App\Support\Features\FeatureFlagService;
use App\Support\Monitoring\Checks\CacheCheck;
use App\Support\Monitoring\Checks\CommunicationCheck;
use App\Support\Monitoring\Checks\DatabaseCheck;
use App\Support\Monitoring\Checks\QueueCheck;
use App\Support\Monitoring\Checks\SchedulerCheck;
use App\Support\Monitoring\SystemStatusService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * FoundationServiceProvider — Phase 0 (Safety & Foundations).
 *
 * Wires the Phase 0 infrastructure into the container and boots:
 *   - FeatureFlagService (singleton — backs the Feature facade)
 *   - DomainEventBus     (singleton — synchronous, in-process)
 *   - SystemStatusService (singleton — with default health checks registered)
 *   - an authenticated internal '/system/status' route
 *
 * Everything here is additive and inert with respect to user-facing behaviour.
 * Removing this provider from bootstrap/providers.php fully disables Phase 0.
 */
class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Feature flags — singleton so the cache is shared per request.
        $this->app->singleton(FeatureFlagService::class, fn () => new FeatureFlagService());

        // Domain event bus over Laravel's dispatcher (synchronous in Phase 0).
        $this->app->singleton(DomainEventBus::class, function ($app) {
            return new DomainEventBus($app->make(Dispatcher::class));
        });

        // System status registry with the default Phase 0 checks.
        $this->app->singleton(SystemStatusService::class, function () {
            $service = new SystemStatusService();
            $service->register(new DatabaseCheck());
            $service->register(new CacheCheck());
            $service->register(new QueueCheck());
            $service->register(new SchedulerCheck());
            $service->register(new CommunicationCheck());
            return $service;
        });
    }

    public function boot(): void
    {
        // Internal, authenticated operator status endpoint. Laravel's own
        // '/up' health route is left untouched.
        Route::middleware(['web', 'auth'])
            ->get('/system/status', [StatusController::class, 'index'])
            ->name('system.status');
    }
}
