<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\TreatmentProvider;
use App\Models\Marketing\MarketingSetting;

class StandaloneTreatmentProvider implements TreatmentProvider
{
    public function treatmentOptions(int $clinicId): array
    {
        // MarketingSetting::get() casts using the row's own stored `type` — pass
        // type='json' to set() when writing this key so it round-trips as an array.
        return (array) MarketingSetting::get($clinicId, 'manual_treatment_options', []);
    }

    public function isManual(): bool
    {
        return true;
    }
}
