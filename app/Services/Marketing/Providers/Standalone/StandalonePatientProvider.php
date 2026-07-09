<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\PatientProvider;
use App\Models\Marketing\MarketingSetting;
use DateTimeInterface;

class StandalonePatientProvider implements PatientProvider
{
    public function activePatientCount(int $clinicId): int
    {
        return (int) MarketingSetting::get($clinicId, 'manual_active_patient_count', 0);
    }

    public function recentLeads(int $clinicId, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        return (int) MarketingSetting::get($clinicId, 'manual_leads_count', 0);
    }

    public function isManual(): bool
    {
        return true;
    }
}
