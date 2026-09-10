<?php

namespace App\Services\Prescription;

use App\Models\Patient;
use App\Models\Prescription\RxAllergyRule;
use App\Models\Prescription\RxDrugInteractionRule;
use App\Models\Prescription\RxWarningRule;
use Illuminate\Support\Collection;

/**
 * Point-of-selection risk assessment.
 *
 * PrescriptionAlertService answers "is this finished prescription safe?" — it runs
 * once, over a composed list of items. This service answers the earlier question:
 * "given what we know about THIS patient, which of the drugs on screen should the
 * doctor not be reaching for?" It grades every candidate drug so the typeahead can
 * grey the dangerous ones before one is ever picked.
 *
 * Deliberately advisory. Nothing here blocks a prescription — a dentist overrules
 * the software, not the other way round. The grading only decides how a row looks
 * and what reason is printed under it.
 *
 * Five signals, all from data the clinic already captures:
 *   1. Allergies      — the patient_allergies table AND the free-text fields
 *   2. Conditions     — medical_conditions / medical_alert via rx_warning_rules
 *   3. Co-medication  — current_medications via rx_drug_interaction_rules
 *   4. Pregnancy / breastfeeding — the drug's own category flags
 *   5. Age            — pediatric and geriatric safety flags
 *
 * NOTE ON MATCHING: medical_alert, current_medications and medical_conditions are
 * encrypted at rest (see App\Casts\Encrypted), so none of this can be pushed into
 * SQL. Everything is decrypted per patient and matched in PHP. That is fine — one
 * patient and a few dozen small rule rows per request — but it does mean matching
 * is keyword-based against free text. A condition typed as "BP" will not match a
 * rule keyed on "hypertension". Accuracy is bounded by intake discipline.
 */
class PrescriptionRiskService
{
    public const RISK_OK      = 'ok';
    public const RISK_CAUTION = 'caution';
    public const RISK_CONTRA  = 'contraindicated';

    /** Rule tables are tiny and re-read for every keystroke — hold them for the request. */
    private ?Collection $allergyRules     = null;
    private ?Collection $warningRules     = null;
    private ?Collection $interactionRules = null;

    /**
     * Grade every supplied drug against one patient.
     *
     * @param  Collection  $drugs  RxDrug models (generic relation eager-loaded)
     * @return array<int, array{risk: string, reasons: array<int, string>}> keyed by drug id
     */
    public function assess(Patient $patient, Collection $drugs): array
    {
        $profile = $this->profile($patient);
        $out     = [];

        foreach ($drugs as $drug) {
            $reasons = [];
            $risk    = self::RISK_OK;

            foreach ([
                $this->allergyRisk($drug, $profile),
                $this->conditionRisk($drug, $profile),
                $this->coMedicationRisk($drug, $profile),
                $this->maternalRisk($drug, $profile),
                $this->ageRisk($drug, $profile),
            ] as $finding) {
                if ($finding === null) {
                    continue;
                }
                [$findingRisk, $findingReasons] = $finding;
                $risk    = $this->escalate($risk, $findingRisk);
                $reasons = array_merge($reasons, $findingReasons);
            }

            $out[$drug->id] = [
                'risk'    => $risk,
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        return $out;
    }

    /**
     * The patient facts worth showing above the drug table, so the doctor sees the
     * context that produced the greying rather than just its effect.
     *
     * @return array{allergies: array<int,string>, conditions: array<int,string>, medications: array<int,string>, flags: array<int,string>}
     */
    public function context(Patient $patient): array
    {
        $profile = $this->profile($patient);

        return [
            'allergies'   => $profile['allergy_labels'],
            'conditions'  => $profile['condition_labels'],
            'medications' => $profile['medication_labels'],
            'flags'       => $profile['flags'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Patient profile
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Flatten everything known about the patient into lower-cased haystacks plus
     * human-readable labels. Built once per request, reused for every drug.
     */
    private function profile(Patient $patient): array
    {
        // ── Allergies: the structured table first, then the free-text fields ──
        // patient_allergies is the record a receptionist fills in properly; it was
        // previously read only by the ABDM export, never by any safety check.
        $allergyLabels = [];
        $criticalAllergy = [];

        foreach ($patient->allergyRecords()->get() as $record) {
            $label = trim((string) $record->substance);
            if ($label === '') {
                continue;
            }
            $allergyLabels[] = $record->reaction
                ? "{$label} ({$record->reaction})"
                : $label;

            if (strtolower((string) $record->criticality) === 'high') {
                $criticalAllergy[] = strtolower($label);
            }
        }

        $conditionLabels = array_values(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            is_array($patient->medical_conditions) ? $patient->medical_conditions : []
        )));

        $medicationText   = trim((string) ($patient->current_medications ?? ''));
        $medicationLabels = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,;\n]+/', $medicationText) ?: []
        )));

        $alertText = trim((string) ($patient->medical_alert ?? ''));

        // The free-text alert field is used for both allergies and conditions in
        // practice, so it feeds both haystacks rather than being forced into one.
        $allergyHaystack = strtolower(implode(' ', array_merge($allergyLabels, [$alertText])));
        $conditionHaystack = strtolower(implode(' ', array_merge($conditionLabels, [$alertText])));

        $flags = [];
        $pregnant      = $this->mentions($conditionHaystack, ['pregnan', 'gravid', 'expecting']);
        $breastfeeding = $this->mentions($conditionHaystack, ['breastfeed', 'lactat', 'nursing']);
        if ($pregnant)      { $flags[] = 'Pregnant'; }
        if ($breastfeeding) { $flags[] = 'Breastfeeding'; }

        $age = $patient->date_of_birth ? $patient->date_of_birth->age : null;
        if ($age !== null) {
            $flags[] = "Age {$age}";
        }

        return [
            'allergy_haystack'   => $allergyHaystack,
            'condition_haystack' => $conditionHaystack,
            'medication_haystack'=> strtolower($medicationText),
            'critical_allergy'   => $criticalAllergy,
            'allergy_labels'     => $allergyLabels,
            'condition_labels'   => $conditionLabels,
            'medication_labels'  => $medicationLabels,
            'flags'              => $flags,
            'pregnant'           => $pregnant,
            'breastfeeding'      => $breastfeeding,
            'age'                => $age,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Allergy
    // ─────────────────────────────────────────────────────────────────────────

    private function allergyRisk($drug, array $profile): ?array
    {
        if ($profile['allergy_haystack'] === '') {
            return null;
        }

        $reasons = [];
        $needles = $this->moleculeHaystack($drug);

        // (a) Rule-driven: "penicillin allergy blocks the penicillin class"
        foreach ($this->allergyRules() as $rule) {
            if (!str_contains($profile['allergy_haystack'], strtolower($rule->allergy_keyword))) {
                continue;
            }

            $hitsMolecule = $rule->blocks_molecule
                && str_contains($needles, strtolower($rule->blocks_molecule));
            $hitsClass = $rule->blocks_class
                && str_contains($needles, strtolower($rule->blocks_class));

            if ($hitsMolecule || $hitsClass) {
                $reasons[] = $rule->alert_message
                    ?: "Allergy on file: {$rule->allergy_keyword}";
            }
        }

        // (b) Direct: the recorded substance names this drug's own molecule.
        //     Catches allergies nobody wrote a rule for.
        foreach ($this->drugMolecules($drug) as $molecule) {
            if (strlen($molecule) >= 4 && str_contains($profile['allergy_haystack'], $molecule)) {
                $reasons[] = 'Recorded allergy names ' . $molecule;
            }
        }

        return $reasons === [] ? null : [self::RISK_CONTRA, $reasons];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Medical condition
    // ─────────────────────────────────────────────────────────────────────────

    private function conditionRisk($drug, array $profile): ?array
    {
        if ($profile['condition_haystack'] === '') {
            return null;
        }

        $reasons = [];
        $risk    = self::RISK_OK;
        $needles = $this->moleculeHaystack($drug);

        foreach ($this->warningRules() as $rule) {
            if (!str_contains($profile['condition_haystack'], strtolower($rule->condition_keyword))) {
                continue;
            }

            $matches = ($rule->drug_id && (int) $rule->drug_id === (int) $drug->id)
                || ($rule->molecule_group && str_contains($needles, strtolower($rule->molecule_group)))
                || ($rule->drug_class     && str_contains($needles, strtolower($rule->drug_class)));

            if (!$matches) {
                continue;
            }

            $reasons[] = $rule->alert_message
                . ($rule->suggestion ? " — {$rule->suggestion}" : '');

            $risk = $this->escalate(
                $risk,
                $rule->severity === 'critical' ? self::RISK_CONTRA : self::RISK_CAUTION
            );
        }

        return $reasons === [] ? null : [$risk, $reasons];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Interaction with what the patient is already taking
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The interaction check in PrescriptionAlertService only compares drugs within
     * the same prescription. This one compares the candidate against the patient's
     * existing medication list — the warfarin-plus-NSAID case, which is the one
     * that actually reaches a hospital.
     */
    private function coMedicationRisk($drug, array $profile): ?array
    {
        if ($profile['medication_haystack'] === '') {
            return null;
        }

        $reasons = [];
        $risk    = self::RISK_OK;
        $needles = $this->moleculeHaystack($drug);

        foreach ($this->interactionRules() as $rule) {
            // A rule is symmetric: either side may be the drug on screen, with the
            // other side being something the patient already takes.
            $pairs = [
                [[$rule->drug_a_molecule, $rule->drug_a_class], [$rule->drug_b_molecule, $rule->drug_b_class]],
                [[$rule->drug_b_molecule, $rule->drug_b_class], [$rule->drug_a_molecule, $rule->drug_a_class]],
            ];

            foreach ($pairs as [$candidateSide, $existingSide]) {
                $candidateHit = $this->sideMatches($candidateSide, $needles);
                $existingHit  = $this->sideMatches($existingSide, $profile['medication_haystack']);

                if ($candidateHit && $existingHit) {
                    $reasons[] = $rule->alert_message;
                    $risk = $this->escalate(
                        $risk,
                        $rule->severity === 'critical' ? self::RISK_CONTRA : self::RISK_CAUTION
                    );
                    break;
                }
            }
        }

        return $reasons === [] ? null : [$risk, $reasons];
    }

    private function sideMatches(array $side, string $haystack): bool
    {
        [$molecule, $class] = $side;

        return ($molecule && str_contains($haystack, strtolower($molecule)))
            || ($class    && str_contains($haystack, strtolower($class)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Pregnancy / breastfeeding
    // ─────────────────────────────────────────────────────────────────────────

    private function maternalRisk($drug, array $profile): ?array
    {
        $reasons = [];
        $risk    = self::RISK_OK;

        if ($profile['pregnant']) {
            $category = strtoupper((string) $drug->pregnancy_category);
            if (in_array($category, ['D', 'X'], true)) {
                $reasons[] = "Pregnancy category {$category} — avoid in pregnancy";
                $risk = self::RISK_CONTRA;
            } elseif ($category === 'C') {
                $reasons[] = 'Pregnancy category C — use only if benefit outweighs risk';
                $risk = $this->escalate($risk, self::RISK_CAUTION);
            }
        }

        if ($profile['breastfeeding']) {
            $safety = strtolower((string) $drug->breastfeeding_safety);
            if ($safety === 'avoid') {
                $reasons[] = 'Not considered safe while breastfeeding';
                $risk = self::RISK_CONTRA;
            } elseif ($safety === 'caution') {
                $reasons[] = 'Caution while breastfeeding';
                $risk = $this->escalate($risk, self::RISK_CAUTION);
            }
        }

        return $reasons === [] ? null : [$risk, $reasons];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Age
    // ─────────────────────────────────────────────────────────────────────────

    private function ageRisk($drug, array $profile): ?array
    {
        $age = $profile['age'];
        if ($age === null) {
            return null;
        }

        $reasons = [];
        $risk    = self::RISK_OK;

        if ($age < 18) {
            $safety = strtolower((string) $drug->pediatric_safety);
            if ($safety === 'avoid') {
                $reasons[] = "Not for under-18s (patient is {$age})";
                $risk = self::RISK_CONTRA;
            } elseif ($safety === 'caution') {
                $reasons[] = "Paediatric caution — check weight-based dose (patient is {$age})";
                $risk = $this->escalate($risk, self::RISK_CAUTION);
            }
        }

        if ($age >= 65) {
            $geriatric = strtolower((string) $drug->geriatric_caution);
            if ($geriatric === 'avoid') {
                $reasons[] = "Avoid in the elderly (patient is {$age})";
                $risk = self::RISK_CONTRA;
            } elseif ($geriatric === 'caution') {
                $reasons[] = "Caution in the elderly (patient is {$age})";
                $risk = $this->escalate($risk, self::RISK_CAUTION);
            }
        }

        return $reasons === [] ? null : [$risk, $reasons];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Everything about a drug worth matching a rule against, lower-cased. */
    private function moleculeHaystack($drug): string
    {
        $parts = [
            $drug->brand_name,
            $drug->composition,
            $drug->duplicate_molecule_group,
            $drug->antibiotic_class,
            $drug->generic?->name,
            $drug->category?->name,
        ];

        foreach (['allergy_tags', 'interaction_tags'] as $tagField) {
            $tags = $drug->{$tagField};
            if (is_array($tags)) {
                $parts = array_merge($parts, $tags);
            }
        }

        return strtolower(implode(' ', array_filter($parts)));
    }

    /** The individual molecules in a product — combinations are comma-joined. */
    private function drugMolecules($drug): array
    {
        $raw = $drug->duplicate_molecule_group ?: ($drug->generic?->name ?? '');

        return array_values(array_filter(array_map(
            static fn ($m) => trim(strtolower($m)),
            explode(',', (string) $raw)
        ), static fn ($m) => $m !== ''));
    }

    private function mentions(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** contraindicated beats caution beats ok. */
    private function escalate(string $current, string $incoming): string
    {
        $rank = [self::RISK_OK => 0, self::RISK_CAUTION => 1, self::RISK_CONTRA => 2];

        return $rank[$incoming] > $rank[$current] ? $incoming : $current;
    }

    private function allergyRules(): Collection
    {
        return $this->allergyRules ??= RxAllergyRule::where('is_active', true)->get();
    }

    private function warningRules(): Collection
    {
        return $this->warningRules ??= RxWarningRule::where('is_active', true)->get();
    }

    private function interactionRules(): Collection
    {
        return $this->interactionRules ??= RxDrugInteractionRule::where('is_active', true)->get();
    }
}
