<?php

namespace App\Support\Monitoring\Checks;

use App\Support\Monitoring\HealthCheck;
use Illuminate\Support\Facades\Cache;

/**
 * Reports scheduler liveness via a heartbeat cache key.
 *
 * The heartbeat ('scheduler.last_run') is expected to be refreshed by a
 * lightweight scheduled task in a later phase. Until then this reports
 * 'unknown' rather than failing — Phase 0 only lays the check.
 */
class SchedulerCheck implements HealthCheck
{
    public function key(): string
    {
        return 'scheduler';
    }

    public function run(): array
    {
        $last = Cache::get('scheduler.last_run');

        if (!$last) {
            return ['status' => 'unknown', 'detail' => 'No scheduler heartbeat recorded yet.'];
        }

        $ageMinutes = now()->diffInMinutes($last);

        return [
            'status' => $ageMinutes > 15 ? 'warn' : 'ok',
            'meta'   => ['last_run' => (string) $last, 'age_minutes' => $ageMinutes],
        ];
    }
}
