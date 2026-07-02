<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\Features\FeatureFlagService;
use App\Support\Monitoring\SystemStatusService;
use Illuminate\Http\JsonResponse;

/**
 * Internal system status endpoint — Phase 0 (Safety & Foundations).
 *
 * Read-only. Returns aggregated health checks plus the resolved feature-flag
 * state, so operators can see engine/queue/scheduler status and which flags
 * are live. Registered behind ['web','auth'] in FoundationServiceProvider —
 * it is an internal operator view, not a public endpoint. Laravel's own
 * '/up' health route is left untouched.
 */
class StatusController extends Controller
{
    public function index(SystemStatusService $status, FeatureFlagService $flags): JsonResponse
    {
        $payload = $status->run();
        $payload['feature_flags'] = $flags->all();

        $httpCode = $payload['status'] === 'fail' ? 503 : 200;

        return response()->json($payload, $httpCode);
    }
}
