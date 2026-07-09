<?php

namespace App\Services\Marketing\Providers\Integrated;

use App\Contracts\Marketing\Providers\MediaProvider;
use App\Models\CmsMedia;
use App\Services\Marketing\Providers\Standalone\StandaloneMediaProvider;

/**
 * Adds consented Clinical Media (real before/after treatment photos) on top
 * of Marketing's own uploaded asset library — "also surfaces" per the
 * MediaProvider contract, not a replacement for it.
 *
 * Only pulls from CmsMedia::marketingReady(): consent explicitly given,
 * fully tagged, and staff-approved (see App\Models\CmsMedia — this consent
 * gate already existed and is untouched here). Nothing is auto-approved by
 * this provider; it only reads what a human already cleared for marketing use.
 *
 * Note: `CmsMediaController` (dossier-flagged as dead-route code) looked like
 * an abandoned first attempt at exactly this integration — this provider is
 * the real version of that idea, built independently against the current
 * CmsMedia model rather than resurrecting that controller.
 */
class IntegratedMediaProvider implements MediaProvider
{
    public function __construct(private readonly StandaloneMediaProvider $ownLibrary) {}

    public function availableMedia(int $clinicId, ?int $patientId = null): array
    {
        $query = CmsMedia::marketingReady()->latest('upload_date')->limit(24);

        if ($patientId) {
            $query->forPatient($patientId);
        }

        $clinicalMedia = $query->get()->map(fn (CmsMedia $m) => [
            'id'      => 'cms-' . $m->id,
            'url'     => $m->display_url,
            'type'    => $m->photo_type,
            'caption' => trim(($m->treatment_type_label ?? '') . ' — ' . ($m->photo_type_label ?? '')),
        ])->toArray();

        return array_merge($this->ownLibrary->availableMedia($clinicId, $patientId), $clinicalMedia);
    }

    public function isManual(): bool
    {
        return false;
    }
}
