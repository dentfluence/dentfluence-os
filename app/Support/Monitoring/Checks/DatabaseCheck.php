<?php

namespace App\Support\Monitoring\Checks;

use App\Support\Monitoring\HealthCheck;
use Illuminate\Support\Facades\DB;

/** Confirms the primary database connection is reachable. */
class DatabaseCheck implements HealthCheck
{
    public function key(): string
    {
        return 'database';
    }

    public function run(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'meta' => ['connection' => config('database.default')]];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
