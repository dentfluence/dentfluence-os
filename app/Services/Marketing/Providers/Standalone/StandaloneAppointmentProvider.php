<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\AppointmentProvider;
use App\Models\Marketing\MarketingSetting;
use DateTimeInterface;

class StandaloneAppointmentProvider implements AppointmentProvider
{
    public function upcomingCount(int $clinicId): int
    {
        return (int) MarketingSetting::get($clinicId, 'manual_upcoming_appointments', 0);
    }

    public function completedCount(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        return (int) MarketingSetting::get($clinicId, 'manual_completed_appointments', 0);
    }

    public function isManual(): bool
    {
        return true;
    }
}
