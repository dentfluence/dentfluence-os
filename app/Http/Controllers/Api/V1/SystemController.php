<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * SystemController
 * ----------------
 * Simple, PUBLIC endpoints used to confirm the API itself is alive and
 * reachable — no login required. This is what you hit first in Postman to
 * prove the whole /api/v1 layer is wired up correctly.
 */
class SystemController extends ApiController
{
    /**
     * GET /api/v1/ping
     * Returns a tiny payload in the standard envelope so you can see the
     * response shape working end to end.
     */
    public function ping(): JsonResponse
    {
        return $this->success([
            'app'    => config('app.name'),
            'api'    => 'v1',
            'status' => 'ok',
            'time'   => now()->toIso8601String(),
        ], 'Dentfluence API is alive');
    }
}
