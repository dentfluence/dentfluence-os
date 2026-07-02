<?php

namespace App\Support\Monitoring\Checks;

use App\Support\Monitoring\HealthCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports queue health: pending + failed job counts (database queue).
 * A growing failed_jobs count is a 'warn'. Missing tables → 'unknown'
 * (the app may be on a non-database queue driver).
 */
class QueueCheck implements HealthCheck
{
    public function key(): string
    {
        return 'queue';
    }

    public function run(): array
    {
        try {
            $meta = ['connection' => config('queue.default')];

            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
            $failed  = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;

            if ($pending === null && $failed === null) {
                return ['status' => 'unknown', 'detail' => 'No database queue tables present.', 'meta' => $meta];
            }

            $meta['pending'] = $pending;
            $meta['failed']  = $failed;

            $status = ($failed ?? 0) > 0 ? 'warn' : 'ok';

            return ['status' => $status, 'meta' => $meta];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
