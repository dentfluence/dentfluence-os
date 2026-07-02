<?php

namespace App\Support\Monitoring\Checks;

use App\Support\Monitoring\HealthCheck;
use Illuminate\Support\Facades\Cache;

/** Confirms the cache store can be written and read. */
class CacheCheck implements HealthCheck
{
    public function key(): string
    {
        return 'cache';
    }

    public function run(): array
    {
        try {
            $token = (string) now()->getTimestamp();
            Cache::put('system_status.probe', $token, 5);
            $ok = Cache::get('system_status.probe') === $token;

            return [
                'status' => $ok ? 'ok' : 'warn',
                'meta'   => ['store' => config('cache.default')],
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
