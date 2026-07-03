<?php

namespace App\Services\Insights;

use App\Contracts\Insights\AppointmentReadContract;
use App\Contracts\Insights\CommunicationReadContract;
use App\Models\Relationship;
use App\Services\Insights\Support\ResolvesPatientIds;

/**
 * HealthSignalCalculator — Phase 6 · Slice 1, Insights Engine.
 *
 * Independent signal: "is this bond warming or cooling?" Reads ONLY the
 * tables this signal needs (appointments, communication_queue) — never
 * another signal's tables, never live-joins across a dozen domains.
 *
 * Pure/deterministic: given the same stored data and the same `now()`, this
 * always returns the same result. No external calls, no randomness.
 *
 * Phase 6 · Slice 4 (read-contracts): the raw queries now live behind
 * AppointmentReadContract / CommunicationReadContract, injected here, so
 * this calculator no longer touches `appointments`/`communication_queue`
 * directly — de-god-reading the Insights Engine per the target architecture.
 * The computed values are unchanged; only where the queries live moved.
 */
class HealthSignalCalculator
{
    use ResolvesPatientIds;

    public function __construct(
        private readonly AppointmentReadContract $appointments,
        private readonly CommunicationReadContract $communication,
    ) {}

    /**
     * @return array{score:int, level:string, factors:array<string,float>}
     */
    public function compute(Relationship $relationship): array
    {
        $config     = config('insights.health.factors', []);
        $patientIds = $this->resolvePatientIds($relationship);

        $visit = $this->visitRegularity($patientIds, $config['visit_regularity'] ?? []);
        $comm  = $this->communicationResponsiveness($patientIds);

        $weightVisit = (int) ($config['visit_regularity']['weight'] ?? 50);
        $weightComm  = (int) ($config['communication_responsiveness']['weight'] ?? 50);

        $score = (int) min(100, max(0, round(($visit * $weightVisit) + ($comm * $weightComm))));

        return [
            'score'   => $score,
            'level'   => $this->bandFor($score),
            'factors' => [
                'visit_regularity'             => round($visit, 4),
                'communication_responsiveness' => round($comm, 4),
            ],
        ];
    }

    /**
     * Full score if the last completed visit was within `ideal_days`, decays
     * linearly to 0 by 2x that window. 0.0 if there is no linked patient or
     * no completed visit yet.
     */
    protected function visitRegularity(array $patientIds, array $config): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        $idealDays = (int) ($config['ideal_days'] ?? 180);

        $lastVisit = $this->appointments->lastCompletedVisitDate($patientIds);

        if (! $lastVisit) {
            return 0.0;
        }

        $daysSince = now()->diffInDays($lastVisit);

        return max(0.0, 1.0 - ($daysSince / ($idealDays * 2)));
    }

    /**
     * Ratio of outbound communications with a positive outcome. Neutral (0.5)
     * if there is no outcome data yet — absence of data is not the same as a
     * cold relationship.
     */
    protected function communicationResponsiveness(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.5;
        }

        $counts = $this->communication->responsivenessCounts($patientIds);

        if ($counts['total'] === 0) {
            return 0.5;
        }

        return min(1.0, $counts['positive'] / $counts['total']);
    }

    protected function bandFor(int $score): string
    {
        foreach (config('insights.health.bands', []) as $key => $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $key;
            }
        }

        return 'steady';
    }
}
