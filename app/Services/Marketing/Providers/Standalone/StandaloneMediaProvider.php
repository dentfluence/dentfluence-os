<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\MediaProvider;
use App\Models\Marketing\MarketingAsset;

/**
 * Unlike the other Standalone providers, this one is NOT manual — mkt_assets
 * (the composer's media library) belongs to Marketing itself, not to a
 * Dentfluence-only module, so it's real data in both modes. The Integrated
 * version's job is to ALSO surface Clinical Media; this one only has its
 * own library to offer.
 */
class StandaloneMediaProvider implements MediaProvider
{
    public function availableMedia(int $clinicId, ?int $patientId = null): array
    {
        return MarketingAsset::where('clinic_id', $clinicId)
            ->whereIn('asset_type', ['image', 'video'])
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (MarketingAsset $a) => [
                'id'      => $a->id,
                'url'     => $a->file_path,
                'type'    => $a->asset_type,
                'caption' => $a->name,
            ])
            ->toArray();
    }

    public function isManual(): bool
    {
        return false;
    }
}
