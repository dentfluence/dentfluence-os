<?php

namespace App\Services\Insights;

use App\Models\InsightSignal;
use App\Models\Relationship;
use InvalidArgumentException;

/**
 * InsightsEngine — Phase 6 · Slice 1 facade.
 *
 * The one place that knows which calculator answers which signal. Nothing
 * else in the app should new-up a *SignalCalculator directly — go through
 * here (or, for stored reads, through InsightsProjector) so a future 4th
 * signal is a new calculator + one line here, never a rewrite.
 *
 * AI-agnostic (§9 target architecture): this produces plain data. It never
 * decides anything and never calls an AI model itself.
 */
class InsightsEngine
{
    public function __construct(
        private readonly HealthSignalCalculator $health,
        private readonly LtvSignalCalculator $ltv,
        private readonly RiskSignalCalculator $risk,
    ) {}

    /**
     * Compute one signal for a relationship. Does NOT persist — see
     * InsightsProjector for the rebuild/store path.
     *
     * @return array<string,mixed>
     */
    public function calculate(Relationship $relationship, string $signal): array
    {
        return match ($signal) {
            InsightSignal::SIGNAL_HEALTH => $this->health->compute($relationship),
            InsightSignal::SIGNAL_LTV    => $this->ltv->compute($relationship),
            InsightSignal::SIGNAL_RISK   => $this->risk->compute($relationship),
            default => throw new InvalidArgumentException("Unknown insight signal [{$signal}]. Known: health, ltv, risk."),
        };
    }

    /**
     * Compute all three signals for a relationship.
     *
     * @return array{health:array,ltv:array,risk:array}
     */
    public function calculateAll(Relationship $relationship): array
    {
        return [
            InsightSignal::SIGNAL_HEALTH => $this->health->compute($relationship),
            InsightSignal::SIGNAL_LTV    => $this->ltv->compute($relationship),
            InsightSignal::SIGNAL_RISK   => $this->risk->compute($relationship),
        ];
    }

    /** @return array<int,string> the known signal keys, in a stable order. */
    public function knownSignals(): array
    {
        return [InsightSignal::SIGNAL_HEALTH, InsightSignal::SIGNAL_LTV, InsightSignal::SIGNAL_RISK];
    }
}
