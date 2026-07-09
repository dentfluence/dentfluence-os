<?php

namespace App\Services\ClinicalLibrary;

use App\Models\ClinicalFile;

/**
 * MarketingEligibilityDetector
 *
 * Rule-based (no AI): when a before/after (or follow-up) pair exists for the
 * same patient + case, automatically enters both files into the marketing
 * review queue — is_marketing_eligible = true, marketing_status = 'pending'.
 * This is the producer half of the existing Marketing tab, which already
 * reads ClinicalFile::marketingEligible() and already has approve/reject
 * actions — that consumer side needed nothing new.
 *
 * Deliberately does NOT touch consent_status. Consent is a deliberate human
 * decision, never inferred from "a photo exists" — a dentist/staff member
 * still has to confirm it during the review step before anything can
 * actually be marketed (see ClinicalFile::scopeMarketingReady()).
 *
 * Never re-opens a file staff already reviewed: only rows with
 * marketing_status still null are touched, so an already-approved or
 * already-rejected file is left exactly as a human left it.
 *
 * Case grouping is deliberately coarse — patient + treatment_category (or
 * free-text procedure when no category matched) only, not tooth number. The
 * human review step in the Marketing tab is the real precision check; this
 * only decides what's worth putting in front of a reviewer.
 */
class MarketingEligibilityDetector
{
    /** Stages that count as "marketing-worthy" content. */
    private const RELEVANT_STAGES = ['before', 'after', 'followup'];

    public static function checkAndFlag(ClinicalFile $file): void
    {
        if (blank($file->patient_id) || ! in_array($file->stage, self::RELEVANT_STAGES, true)) {
            return;
        }

        $column   = $file->treatment_category ? 'treatment_category' : 'procedure';
        $groupKey = $file->treatment_category ?? $file->procedure;

        if (blank($groupKey)) {
            return; // nothing to reliably group this file with
        }

        $oppositeStages = $file->stage === 'before' ? ['after', 'followup'] : ['before'];

        $hasOpposite = ClinicalFile::where('patient_id', $file->patient_id)
            ->where($column, $groupKey)
            ->whereIn('stage', $oppositeStages)
            ->exists();

        if (! $hasOpposite) {
            return;
        }

        ClinicalFile::where('patient_id', $file->patient_id)
            ->where($column, $groupKey)
            ->whereIn('stage', self::RELEVANT_STAGES)
            ->whereNull('marketing_status')
            ->update([
                'is_marketing_eligible' => true,
                'marketing_status'      => 'pending',
            ]);
    }
}
