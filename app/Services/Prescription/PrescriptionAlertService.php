<?php

namespace App\Services\Prescription;

use App\Models\Patient;
use App\Models\Prescription\{RxDrug, RxAllergyRule, RxDrugInteractionRule, RxWarningRule};

/**
 * CDSS — Clinical Decision Support Service
 *
 * Runs four checks on a working prescription:
 *  1. Allergy check      — patient allergies vs drug molecule/class
 *  2. Duplicate molecule — same molecule appearing twice in the prescription
 *  3. Drug interaction   — known A↔B interaction pairs
 *  4. Warning rules      — dose > max, antibiotic stewardship, condition-specific cautions
 *
 * Returns an array of alert objects:
 * [
 *   {
 *     type:     'allergy' | 'duplicate' | 'interaction' | 'warning',
 *     severity: 'critical' | 'major' | 'moderate' | 'minor' | 'info',
 *     drug_id:  int|null,
 *     drug_name: string,
 *     code:     string|null,     // machine-readable code for override tracking
 *     message:  string,          // human-readable explanation
 *     blockable: bool,           // true = must override to proceed
 *   }
 * ]
 */
class PrescriptionAlertService
{
    // ─────────────────────────────────────────────────────────────────────────
    // ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  Patient  $patient
     * @param  array    $items   Raw item data from the form/request
     * @return array             Alerts array
     */
    public function check(Patient $patient, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        // Hydrate drug models for items that have a drug_id
        $drugIds   = array_filter(array_column($items, 'drug_id'));
        $drugsById = RxDrug::whereIn('id', $drugIds)->get()->keyBy('id');

        $alerts = [];

        $alerts = array_merge($alerts, $this->checkAllergies($patient, $items, $drugsById));
        $alerts = array_merge($alerts, $this->checkDuplicateMolecule($items, $drugsById));
        $alerts = array_merge($alerts, $this->checkDrugInteractions($items, $drugsById));
        $alerts = array_merge($alerts, $this->checkWarningRules($patient, $items, $drugsById));
        $alerts = array_merge($alerts, $this->checkAntibioticStewardship($items, $drugsById));

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. ALLERGY CHECK
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Matches patient allergy keywords (stored in medical_alert / medical_conditions)
     * against the allergy rule table's blocks_molecule and blocks_class columns.
     */
    private function checkAllergies(Patient $patient, array $items, $drugsById): array
    {
        $alerts = [];

        // Collect all allergy keywords from the patient record
        $allergyText = implode(' ', array_filter([
            $patient->medical_alert ?? '',
            is_array($patient->medical_conditions)
                ? implode(' ', $patient->medical_conditions)
                : ($patient->medical_conditions ?? ''),
        ]));

        if (empty(trim($allergyText))) {
            return [];
        }

        // Load all active allergy rules
        $allergyRules = RxAllergyRule::where('is_active', true)->get();

        foreach ($allergyRules as $rule) {
            // Does this patient's record mention this allergy keyword?
            if (!str_contains(strtolower($allergyText), strtolower($rule->allergy_keyword))) {
                continue;
            }

            // Check every item in the prescription
            foreach ($items as $item) {
                $drug      = $drugsById->get($item['drug_id'] ?? null);
                $drugName  = $item['drug_name'] ?? ($drug?->brand_name ?? 'Unknown');

                $moleculeMatch = $rule->blocks_molecule
                    && $drug
                    && str_contains(
                        strtolower($drug->composition ?? '') . ' ' . strtolower($drug->generic?->name ?? ''),
                        strtolower($rule->blocks_molecule)
                    );

                $classMatch = $rule->blocks_class
                    && $drug
                    && (
                        strtolower($drug->antibiotic_class ?? '') === strtolower($rule->blocks_class)
                        || str_contains(strtolower($drug->composition ?? ''), strtolower($rule->blocks_class))
                    );

                if ($moleculeMatch || $classMatch) {
                    $alerts[] = [
                        'type'      => 'allergy',
                        'severity'  => $rule->severity ?? 'critical',
                        'drug_id'   => $drug?->id,
                        'drug_name' => $drugName,
                        'code'      => 'ALLERGY_' . strtoupper(str_replace(' ', '_', $rule->allergy_keyword)),
                        'message'   => $rule->alert_message
                            ?? "⚠️ Patient has documented allergy to {$rule->allergy_keyword}. "
                               . "{$drugName} contains a contra-indicated molecule/class.",
                        'blockable' => true,
                    ];
                }
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. DUPLICATE MOLECULE CHECK
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Flags when two drugs in the same prescription share the same
     * duplicate_molecule_group (e.g. two NSAIDs, two amoxicillin variants).
     */
    private function checkDuplicateMolecule(array $items, $drugsById): array
    {
        $alerts   = [];
        $seen     = [];   // molecule_group → first drug name

        foreach ($items as $item) {
            $drug = $drugsById->get($item['drug_id'] ?? null);
            if (!$drug || empty($drug->duplicate_molecule_group)) {
                continue;
            }

            $group    = strtolower($drug->duplicate_molecule_group);
            $drugName = $item['drug_name'] ?? $drug->brand_name;

            if (isset($seen[$group])) {
                $alerts[] = [
                    'type'      => 'duplicate',
                    'severity'  => 'major',
                    'drug_id'   => $drug->id,
                    'drug_name' => $drugName,
                    'code'      => 'DUP_' . strtoupper($group),
                    'message'   => "Duplicate molecule group \"{$drug->duplicate_molecule_group}\": "
                                   . "{$seen[$group]} and {$drugName} are both in this prescription. "
                                   . "Prescribing both may cause additive toxicity.",
                    'blockable' => false,
                ];
            } else {
                $seen[$group] = $drugName;
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. DRUG–DRUG INTERACTION CHECK
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Loads all active interaction rules and checks every unique drug pair
     * in the prescription against both A→B and B→A combinations.
     */
    private function checkDrugInteractions(array $items, $drugsById): array
    {
        $alerts = [];

        $drugObjects = [];
        foreach ($items as $item) {
            $drug = $drugsById->get($item['drug_id'] ?? null);
            if ($drug) {
                $drugObjects[] = [
                    'drug'      => $drug,
                    'drug_name' => $item['drug_name'] ?? $drug->brand_name,
                ];
            }
        }

        if (count($drugObjects) < 2) {
            return [];
        }

        $rules = RxDrugInteractionRule::where('is_active', true)->get();

        // Check every unique pair
        for ($i = 0; $i < count($drugObjects); $i++) {
            for ($j = $i + 1; $j < count($drugObjects); $j++) {
                $a    = $drugObjects[$i]['drug'];
                $b    = $drugObjects[$j]['drug'];
                $nameA = $drugObjects[$i]['drug_name'];
                $nameB = $drugObjects[$j]['drug_name'];

                foreach ($rules as $rule) {
                    $matchAB = $this->moleculeOrClassMatches($a, $rule->drug_a_molecule, $rule->drug_a_class)
                            && $this->moleculeOrClassMatches($b, $rule->drug_b_molecule, $rule->drug_b_class);

                    $matchBA = $this->moleculeOrClassMatches($b, $rule->drug_a_molecule, $rule->drug_a_class)
                            && $this->moleculeOrClassMatches($a, $rule->drug_b_molecule, $rule->drug_b_class);

                    if ($matchAB || $matchBA) {
                        $alerts[] = [
                            'type'      => 'interaction',
                            'severity'  => $rule->severity ?? 'moderate',
                            'drug_id'   => $a->id,
                            'drug_name' => "{$nameA} + {$nameB}",
                            'code'      => 'INT_' . $a->id . '_' . $b->id,
                            'message'   => $rule->alert_message
                                ?? "Potential interaction between {$nameA} and {$nameB}.",
                            'blockable' => in_array($rule->severity, ['critical', 'major']),
                        ];
                        break; // one alert per pair
                    }
                }
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. WARNING RULES (condition-specific + dose cap)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Checks:
     *  a) Condition-based warnings (e.g. "patient is diabetic, avoid corticosteroids")
     *  b) Max daily dose exceeded
     */
    private function checkWarningRules(Patient $patient, array $items, $drugsById): array
    {
        $alerts = [];

        $patientConditions = strtolower(implode(' ', array_filter([
            $patient->medical_alert ?? '',
            is_array($patient->medical_conditions)
                ? implode(' ', $patient->medical_conditions)
                : ($patient->medical_conditions ?? ''),
        ])));

        $warningRules = RxWarningRule::where('is_active', true)->get();

        foreach ($items as $item) {
            $drug     = $drugsById->get($item['drug_id'] ?? null);
            $drugName = $item['drug_name'] ?? ($drug?->brand_name ?? 'Unknown');

            // a) Condition-based
            foreach ($warningRules as $rule) {
                if (empty($rule->condition_keyword)) continue;
                if (!str_contains($patientConditions, strtolower($rule->condition_keyword))) continue;

                $drugMatch    = $rule->drug_id && $drug && $drug->id === $rule->drug_id;
                $moleculeMatch = $rule->molecule_group && $drug
                    && str_contains(strtolower($drug->composition ?? ''), strtolower($rule->molecule_group));
                $classMatch   = $rule->drug_class && $drug
                    && strtolower($drug->antibiotic_class ?? '') === strtolower($rule->drug_class);

                if ($drugMatch || $moleculeMatch || $classMatch) {
                    $alerts[] = [
                        'type'      => 'warning',
                        'severity'  => $rule->severity ?? 'moderate',
                        'drug_id'   => $drug?->id,
                        'drug_name' => $drugName,
                        'code'      => 'WARN_' . strtoupper(str_replace(' ', '_', $rule->condition_keyword)),
                        'message'   => $rule->alert_message ?? "Caution: {$drugName} with condition '{$rule->condition_keyword}'.",
                        'blockable' => (bool)$rule->blockable,
                    ];
                }
            }

            // b) Max daily dose check
            if ($drug && $drug->max_daily_dose) {
                $totalDose = (float)($item['morning'] ?? 0)
                           + (float)($item['afternoon'] ?? 0)
                           + (float)($item['night'] ?? 0);

                if ($totalDose > $drug->max_daily_dose) {
                    $alerts[] = [
                        'type'      => 'warning',
                        'severity'  => 'major',
                        'drug_id'   => $drug->id,
                        'drug_name' => $drugName,
                        'code'      => 'DOSE_EXCEEDED_' . $drug->id,
                        'message'   => "{$drugName}: prescribed daily dose ({$totalDose}) exceeds the maximum "
                                       . "recommended daily dose ({$drug->max_daily_dose}). Please review.",
                        'blockable' => false,
                    ];
                }
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. ANTIBIOTIC STEWARDSHIP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Flags:
     *  - Duration < 5 days for antibiotics (too short — resistance risk)
     *  - Duration > 14 days for antibiotics (unusually long — stewardship)
     *  - Two antibiotics of the same class prescribed together
     */
    private function checkAntibioticStewardship(array $items, $drugsById): array
    {
        $alerts       = [];
        $abClassesSeen = []; // class → drug_name

        foreach ($items as $item) {
            $drug     = $drugsById->get($item['drug_id'] ?? null);
            $drugName = $item['drug_name'] ?? ($drug?->brand_name ?? 'Unknown');

            if (!$drug || empty($drug->antibiotic_class)) {
                continue;
            }

            $class    = strtolower($drug->antibiotic_class);
            $duration = (int)($item['duration'] ?? 0);
            $unit     = $item['duration_unit'] ?? 'days';

            // Normalize to days
            $daysTotal = $duration;
            if ($unit === 'weeks')  $daysTotal = $duration * 7;
            if ($unit === 'months') $daysTotal = $duration * 30;

            // Too short
            if ($daysTotal > 0 && $daysTotal < 5) {
                $alerts[] = [
                    'type'      => 'warning',
                    'severity'  => 'moderate',
                    'drug_id'   => $drug->id,
                    'drug_name' => $drugName,
                    'code'      => 'AB_SHORT_' . $drug->id,
                    'message'   => "Antibiotic stewardship: {$drugName} is prescribed for only {$daysTotal} day(s). "
                                   . "A minimum of 5 days is typically recommended to avoid sub-therapeutic dosing.",
                    'blockable' => false,
                ];
            }

            // Too long
            if ($daysTotal > 14) {
                $alerts[] = [
                    'type'      => 'warning',
                    'severity'  => 'info',
                    'drug_id'   => $drug->id,
                    'drug_name' => $drugName,
                    'code'      => 'AB_LONG_' . $drug->id,
                    'message'   => "Antibiotic stewardship: {$drugName} prescribed for {$daysTotal} days. "
                                   . "Long antibiotic courses increase resistance risk — confirm clinical need.",
                    'blockable' => false,
                ];
            }

            // Duplicate antibiotic class
            if (isset($abClassesSeen[$class])) {
                $alerts[] = [
                    'type'      => 'duplicate',
                    'severity'  => 'major',
                    'drug_id'   => $drug->id,
                    'drug_name' => $drugName,
                    'code'      => 'AB_SAME_CLASS_' . strtoupper($class),
                    'message'   => "Antibiotic stewardship: {$abClassesSeen[$class]} and {$drugName} are both "
                                   . strtoupper($class) . "-class antibiotics. Dual same-class antibiotic therapy "
                                   . "is rarely indicated.",
                    'blockable' => false,
                ];
            } else {
                $abClassesSeen[$class] = $drugName;
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITY
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if $drug matches the given molecule or class needle.
     */
    private function moleculeOrClassMatches(RxDrug $drug, ?string $molecule, ?string $class): bool
    {
        $moleculeMatch = $molecule
            && str_contains(
                strtolower($drug->composition ?? '') . ' ' . strtolower($drug->generic?->name ?? ''),
                strtolower($molecule)
            );

        $classMatch = $class
            && strtolower($drug->antibiotic_class ?? '') === strtolower($class);

        return $moleculeMatch || $classMatch;
    }
}
