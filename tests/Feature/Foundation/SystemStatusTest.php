<?php

namespace Tests\Feature\Foundation;

use App\Support\Monitoring\SystemStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 0 — Monitoring foundation.
 *
 * Verifies the status service aggregates the default checks and that the
 * internal status route is registered (behind auth) without touching '/up'.
 */
class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_service_reports_default_checks(): void
    {
        $result = app(SystemStatusService::class)->run();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checks', $result);

        foreach (['database', 'cache', 'queue', 'scheduler', 'communication'] as $key) {
            $this->assertArrayHasKey($key, $result['checks'], "Missing check: {$key}");
            $this->assertArrayHasKey('status', $result['checks'][$key]);
        }

        // Database is up under tests → overall must not be 'fail'.
        $this->assertNotSame('fail', $result['status']);
    }

    public function test_internal_status_route_is_registered(): void
    {
        $this->assertTrue(Route::has('system.status'));
    }

    public function test_registry_is_extensible(): void
    {
        // A later phase registers new checks the same way, without editing core.
        $service = new SystemStatusService();
        $service->register(new class implements \App\Support\Monitoring\HealthCheck {
            public function key(): string { return 'engine.sample'; }
            public function run(): array { return ['status' => 'ok']; }
        });

        $this->assertArrayHasKey('engine.sample', $service->run()['checks']);
    }
}
