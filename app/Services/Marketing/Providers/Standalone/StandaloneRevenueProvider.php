<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\RevenueProvider;
use App\Models\Marketing\MarketingSetting;
use DateTimeInterface;

/**
 * No Dentfluence OS connection to pull real billing from — the clinic types
 * a revenue figure in themselves (Settings), stored as a single running
 * total per clinic. No per-range breakdown yet since there's no entry UI
 * for it; this is intentionally the simplest thing that works until that's
 * asked for (docs/marketing-module-reengineering-plan.md §9).
 */
class StandaloneRevenueProvider implements RevenueProvider
{
    public function totalRevenue(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): float
    {
        return (float) MarketingSetting::get($clinicId, 'manual_revenue_total', 0);
    }

    public function isManual(): bool
    {
        return true;
    }
}
