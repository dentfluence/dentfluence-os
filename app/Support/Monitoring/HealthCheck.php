<?php

namespace App\Support\Monitoring;

/**
 * A single health/status check. Keep implementations tiny and DEFENSIVE —
 * a check must never throw; on error it returns a 'fail' status with detail.
 *
 * status is one of: 'ok' | 'warn' | 'fail' | 'unknown'.
 */
interface HealthCheck
{
    /** Stable machine key, e.g. 'database', 'queue', 'scheduler'. */
    public function key(): string;

    /**
     * @return array{status: string, detail?: string, meta?: array<string,mixed>}
     */
    public function run(): array;
}
