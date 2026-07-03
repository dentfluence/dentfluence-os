<?php

namespace App\Contracts\Insights;

/**
 * AppointmentReadContract — Phase 6 · Slice 4 (read-contracts).
 *
 * The thin seam between the Insights signal calculators and the
 * `appointments` table. Introduced so HealthSignalCalculator and
 * RiskSignalCalculator stop querying the raw table directly — per the target
 * architecture, "no signal reads another domain's tables" and Insights must
 * never become a god-reader. Each method here is exactly the query that used
 * to live inline in the calculator; nothing about what's computed changes,
 * only where the query lives.
 */
interface AppointmentReadContract
{
    /**
     * The most recent COMPLETED ("done") appointment date across the given
     * patient IDs, or null if none exists (no linked patient, or no
     * completed visit yet).
     *
     * @param  array<int,int>  $patientIds
     */
    public function lastCompletedVisitDate(array $patientIds): ?string;

    /**
     * The status of the last N appointments (most recent first) across the
     * given patient IDs, regardless of status.
     *
     * @param  array<int,int>  $patientIds
     * @return \Illuminate\Support\Collection<int,string>
     */
    public function recentAppointmentStatuses(array $patientIds, int $limit);
}
