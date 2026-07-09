<?php

namespace App\Contracts\Marketing\Providers;

use DateTimeInterface;

/**
 * Where Marketing gets patient/lead counts from.
 * Standalone: manual count (mkt_settings). Integrated: real Patient/Lead records.
 * See docs/marketing-module-reengineering-plan.md §9.
 */
interface PatientProvider
{
    public function activePatientCount(int $clinicId): int;

    /** New leads attributable to marketing in the given range. */
    public function recentLeads(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int;

    public function isManual(): bool;
}
