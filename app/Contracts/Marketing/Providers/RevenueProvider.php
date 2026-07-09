<?php

namespace App\Contracts\Marketing\Providers;

use DateTimeInterface;

/**
 * Where Marketing's Analytics/Dashboard get revenue numbers from.
 *
 * Standalone: a clinic with no Dentfluence OS connection types revenue in
 * manually (stored per-clinic in mkt_settings).
 * Integrated: summed from real Invoice/InvoicePayment records.
 *
 * Marketing code should never know which one it's talking to — see
 * docs/marketing-module-reengineering-plan.md §9.
 */
interface RevenueProvider
{
    /** Total revenue attributable to marketing for the given clinic/date range. */
    public function totalRevenue(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): float;

    /** True if this number was typed in by a human rather than computed from real records. */
    public function isManual(): bool;
}
