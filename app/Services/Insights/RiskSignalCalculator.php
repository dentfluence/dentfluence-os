<?php

namespace App\Services\Insights;

use App\Contracts\Insights\AppointmentReadContract;
use App\Contracts\Insights\CommunicationReadContract;
use App\Models\Relationship;
use App\Services\Insights\Support\ResolvesPatientIds;

/**
 * RiskSignalCalculator — Phase 6 · Slice 1, Insights Engine.
 *
 * Independent signal: dormancy, no-shows, unanswered outreach. Higher score
 * = higher risk (deliberately opposite polarity to Health — they are
 * independent signals, not mirrors of one another).
 *
 * Reads ONLY appointments + communication_queue (its own tables). Pure/
 * deterministic — no external calls, no randomness.
 *
 * Phase 6 · Slice 4 (read-contracts): raw queries now live behind
 * AppointmentReadContract / CommunicationReadContract — same computed
 * values, only where the queries live moved. See HealthSignalCalculator.
 */
class RiskSignalCalculator
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
        $config     = config('insights.risk.factors', []);
        $patientIds = $this->resolvePatientIds($relationship);

        $dormancy   = $this->dormancy($patientIds, $config['dormancy'] ?? []);
        $noShow     = $this->noShowRate($patientIds, $config['no_show_rate'] ?? []);
        $unanswered = $this->unansweredOutreach($patientIds);

        $wDormancy   = (int) ($config['dormancy']['weight'] ?? 40);
        $wNoShow     = (int) ($config['no_show_rate']['weight'] ?? 30);
        $wUnanswered = (int) ($config['unanswered_outreach']['weight'] ?? 30);

        $score = (int) min(100, max(0, round(
            ($dormancy * $wDormancy) + ($noShow * $wNoShow) + ($unanswered * $wUnanswered)
        )));

        return [
            'score'   => $score,
            'level'   => $this->bandFor($score),
            'factors' => [
                'dormancy'            => round($dormancy, 4),
                'no_show_rate'        => round($noShow, 4),
                'unanswered_outreach' => round($unanswered, 4),
            ],
        ];
    }

    /**
     * 0.0 if no linked patient (not yet applicable). 1.0 (max risk) if a
     * patient exists but has never had a completed visit. Otherwise scales
     * with days since the last completed visit relative to 2x the expected
     * recall interval.
     */
    protected function dormancy(array $patientIds, array $config): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        $intervalDays = (int) ($config['recall_interval_days'] ?? 180);

        $lastVisit = $this->appointments->lastCompletedVisitDate($patientIds);

        if (! $lastVisit) {
            return 1.0;
        }

        $daysSince = now()->diffInDays($lastVisit);

        return min(1.0, $daysSince / ($intervalDays * 2));
    }

    /**
     * Proportion of the last N appointments (most recent first) that were
     * no-shows or cancellations. 0.0 if there is no appointment history yet.
     */
    protected function noShowRate(array $patientIds, array $config): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        $lookback = (int) ($config['lookback'] ?? 10);

        $recent = $this->appointments->recentAppointmentStatuses($patientIds, $lookback);

        if ($recent->isEmpty()) {
            return 0.0;
        }

        $missed = $recent->filter(fn ($status) => in_array($status, ['no_show', 'cancelled'], true))->count();

        return min(1.0, $missed / $recent->count());
    }

    /**
     * Proportion of recall outreach attempts that did NOT end in a positive
     * outcome. 0.0 if the patient has never been recalled (nothing to be
     * unanswered yet).
     */
    protected function unansweredOutreach(array $patientIds): float
    {
        if ($patientIds === []) {
            return 0.0;
        }

        $counts = $this->communication->recallOutcomeCounts($patientIds);

        if ($counts['total'] === 0) {
            return 0.0;
        }

        return min(1.0, ($counts['total'] - $counts['positive']) / $counts['total']);
    }

    protected function bandFor(int $score): string
    {
        foreach (config('insights.risk.bands', []) as $key => $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $key;
            }
        }

        return 'medium';
    }
}
