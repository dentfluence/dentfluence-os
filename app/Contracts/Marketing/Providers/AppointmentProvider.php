<?php

namespace App\Contracts\Marketing\Providers;

use DateTimeInterface;

/**
 * Where Marketing gets appointment counts (for ROI cards — "campaign X
 * produced Y appointments").
 * Standalone: manual count (mkt_settings). Integrated: real Appointment records.
 * See docs/marketing-module-reengineering-plan.md §9.
 */
interface AppointmentProvider
{
    public function upcomingCount(int $clinicId): int;

    public function completedCount(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int;

    public function isManual(): bool;
}
