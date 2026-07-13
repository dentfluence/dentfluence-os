<?php

namespace App\Services\TreatmentPlan;

use App\Models\TreatmentConsent;
use App\Models\TreatmentPlan;

/**
 * Phase 2 — Clinical Consent (see docs/gap-analysis-treatment-planning-knowledge-bank.md).
 *
 * Builds a patient/tooth/procedure-specific consent document for a treatment
 * plan by pulling the same consent education text
 * TreatmentController::printView('consent') already reads for a single
 * treatment (Treatment->activeSop->consent_notes), falling back to the
 * legacy Intelligence-tab Treatment->consent_template field when no active
 * SOP has consent text yet — and pairing it with the specific tooth and
 * procedure the patient is actually consenting to.
 *
 * Refinement (2026-07-13): not every treatment needs a consent form, and a
 * multi-tooth item ("Composite Filling" on 14, 15, 16) needs one section
 * PER TOOTH, not one grouped section — a patient signs off on each tooth
 * individually. Two things drive this:
 *   - `TreatmentPlanItem.consent_required` (per-item override, defaults from
 *     the treatment's own `consent_required` TreatmentRule when picked in
 *     the plan builder) — items where this is false are skipped entirely.
 *   - `tooth_number` is exploded the same way
 *     Services\Billing\TreatmentPlanBillingService does it (comma-separated
 *     string), NOT via TreatmentPlanItemTooth — those per-tooth rows are
 *     only created lazily at billing time, so they don't exist yet for a
 *     freshly created or just-accepted plan, which is exactly when a
 *     consent form is most likely to be generated.
 *
 * Deliberately does NOT touch the separate DPDP PatientConsent/ConsentLog
 * module — this is clinical procedure consent, not data-privacy consent.
 * No e-signature capture (see gap-analysis "skip" list) — this produces a
 * printable document for wet-ink signature.
 */
class ConsentDocumentService
{
    /**
     * Build the merge sections for a plan — one per (tooth, treatment) pair.
     * Read-only — does not persist anything. Used both for the live print
     * view and as the payload snapshotted by generateAndPersist().
     *
     * Refinement (2026-07-13, picker): the plan tab's "Consent Form" button
     * now opens a picker so staff choose exactly which treatment/tooth rows
     * to generate for, rather than the button only working off the
     * consent_required flag. $selectedKeys carries that choice as an array
     * of "{item_id}|{tooth}" strings (tooth blank for a whole-mouth item,
     * e.g. "914|" or "914|14"). When it's null (no selection passed — an old
     * bookmarked/direct link), we fall back to the original behaviour of
     * including every item flagged consent_required. When it's an array
     * (including empty), it's authoritative and the consent_required flag is
     * ignored — the picker pre-checks from that flag, but staff can still
     * opt individual rows in/out for this one generation.
     */
    public function buildSections(TreatmentPlan $plan, ?array $selectedKeys = null): array
    {
        $plan->loadMissing(['items.treatment.activeSop']);

        $selecting    = $selectedKeys !== null;
        $selectedKeys = $selecting ? array_flip($selectedKeys) : [];

        $sections = [];

        foreach ($plan->items as $item) {
            if (! $selecting && ! $item->consent_required) {
                continue;
            }

            $treatment = $item->treatment;

            $consentText = $treatment?->activeSop?->consent_notes
                ?: $treatment?->consent_template
                ?: null;

            $teeth = collect(explode(',', (string) $item->tooth_number))
                ->map(fn ($t) => trim($t))
                ->filter()
                ->values();

            if ($teeth->isEmpty()) {
                // Whole-mouth / non-tooth-specific procedure (e.g. Scaling) —
                // one section, no tooth badge.
                if ($selecting && ! isset($selectedKeys[$item->id . '|'])) {
                    continue;
                }
                $sections[] = [
                    'item_id'          => $item->id,
                    'treatment_name'   => $item->treatment_name,
                    'tooth_numbers'    => [],
                    'consent_text'     => $consentText,
                    'has_consent_text' => ! empty($consentText),
                ];
                continue;
            }

            // One section PER TOOTH — separate sign-off per tooth.
            foreach ($teeth as $tooth) {
                if ($selecting && ! isset($selectedKeys[$item->id . '|' . $tooth])) {
                    continue;
                }
                $sections[] = [
                    'item_id'          => $item->id,
                    'treatment_name'   => $item->treatment_name,
                    'tooth_numbers'    => [$tooth],
                    'consent_text'     => $consentText,
                    'has_consent_text' => ! empty($consentText),
                ];
            }
        }

        return $sections;
    }

    /**
     * Build the sections AND persist an audit snapshot of exactly what was
     * shown at this moment — the underlying SOP consent text can be edited
     * later, so this snapshot (not a live query) is the record of what this
     * particular patient actually saw and was asked to sign.
     */
    public function generateAndPersist(TreatmentPlan $plan, ?int $userId, ?array $selectedKeys = null): TreatmentConsent
    {
        return TreatmentConsent::create([
            'treatment_plan_id' => $plan->id,
            'patient_id'        => $plan->patient_id,
            'generated_by'      => $userId,
            'sections'          => $this->buildSections($plan, $selectedKeys),
        ]);
    }
}
