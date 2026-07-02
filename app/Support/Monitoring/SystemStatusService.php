<?php

namespace App\Support\Monitoring;

/**
 * SystemStatusService — Phase 0 (Safety & Foundations).
 *
 * A small, EXTENSIBLE registry of health checks. Later phases register
 * additional checks (engine status, communication status, etc.) by calling
 * register() — usually from a service provider — without touching this class.
 *
 * run() aggregates every check into one payload with an overall status.
 * It never throws: a failing check is caught and reported as 'fail'.
 */
class SystemStatusService
{
    /** @var array<string, HealthCheck> */
    private array $checks = [];

    public function register(HealthCheck $check): void
    {
        $this->checks[$check->key()] = $check;
    }

    /** @return array<string, HealthCheck> */
    public function checks(): array
    {
        return $this->checks;
    }

    /**
     * @return array{
     *   status: string,
     *   generated_at: string,
     *   checks: array<string, array{status:string, detail?:string, meta?:array}>
     * }
     */
    public function run(): array
    {
        $results = [];
        $worst = 'ok';

        foreach ($this->checks as $key => $check) {
            try {
                $result = $check->run();
            } catch (\Throwable $e) {
                $result = ['status' => 'fail', 'detail' => $e->getMessage()];
            }

            $results[$key] = $result;
            $worst = $this->worse($worst, $result['status'] ?? 'unknown');
        }

        return [
            'status'       => $worst,
            'generated_at' => now()->toIso8601String(),
            'checks'       => $results,
        ];
    }

    /** Rank statuses so the overall reflects the worst individual check. */
    private function worse(string $a, string $b): string
    {
        $rank = ['ok' => 0, 'unknown' => 1, 'warn' => 2, 'fail' => 3];
        return ($rank[$b] ?? 1) > ($rank[$a] ?? 1) ? $b : $a;
    }
}
