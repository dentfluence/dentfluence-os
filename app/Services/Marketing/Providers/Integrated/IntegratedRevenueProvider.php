<?php

namespace App\Services\Marketing\Providers\Integrated;

use App\Contracts\Marketing\Providers\RevenueProvider;
use App\Models\Invoice;
use DateTimeInterface;

/**
 * Real revenue, summed from actual Invoice payments instead of a typed-in
 * number. Invoice has no clinic_id column (this app is effectively
 * single-clinic in Billing today, same as the rest of Marketing before the
 * V2 CLINIC_ID fix) — $clinicId is accepted for interface parity and future
 * multi-clinic billing, but not yet filterable here.
 */
class IntegratedRevenueProvider implements RevenueProvider
{
    public function totalRevenue(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): float
    {
        $query = Invoice::query();

        if ($from) {
            $query->where('invoice_date', '>=', $from);
        }
        if ($to) {
            $query->where('invoice_date', '<=', $to);
        }

        return (float) $query->sum('paid_amount');
    }

    public function isManual(): bool
    {
        return false;
    }
}
