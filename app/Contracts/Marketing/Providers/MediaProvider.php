<?php

namespace App\Contracts\Marketing\Providers;

/**
 * Where Marketing's post composer pulls photo/video suggestions from.
 * Standalone: the clinic's own uploaded mkt_assets library only.
 * Integrated: also surfaces consented Clinical Media (before/after cases).
 * See docs/marketing-module-reengineering-plan.md §9.
 *
 * Returns a flat array of ['id','url','type','caption'] items — deliberately
 * loose/array-shaped rather than a strict DTO, since Standalone and
 * Integrated draw from two structurally different models (MarketingAsset vs
 * CmsMedia) and forcing one shared value object would leak internals either
 * way.
 */
interface MediaProvider
{
    public function availableMedia(int $clinicId, ?int $patientId = null): array;

    public function isManual(): bool;
}
