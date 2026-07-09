<?php

namespace App\Contracts\Marketing\Providers;

/**
 * Where Marketing gets treatment-type options (for tagging posts/campaigns).
 * Standalone: a manually maintained list (mkt_settings). Integrated: real
 * treatment types from Consultation/Treatment Plan records.
 * See docs/marketing-module-reengineering-plan.md §9.
 */
interface TreatmentProvider
{
    /** List of treatment names/labels a post or campaign can be tagged with. */
    public function treatmentOptions(int $clinicId): array;

    public function isManual(): bool;
}
