<?php

namespace App\Abdm\Fhir\Builders;

use App\Abdm\Fhir\Terminology\TerminologyResolver;
use App\Models\Consultation;

/**
 * Builds FHIR Condition resources from a consultation's diagnoses.
 *
 * One consultation can carry several diagnoses (primary/secondary/provisional/
 * differential + rows in the diagnoses child table), so this is a builder that
 * returns an ARRAY of resources rather than a single-resource Mapper. The primary
 * diagnosis is coded via terminology (ICD-10) when a code is present.
 */
class ConditionBuilder
{
    public function __construct(private TerminologyResolver $terminology) {}

    /**
     * @return array<int, array>  list of FHIR Condition resources
     */
    public function build(Consultation $c, string $patientRef, string $encounterRef): array
    {
        $out = [];
        $n   = 0;

        $add = function (?string $text, ?string $icd, string $verification) use (&$out, &$n, $patientRef, $encounterRef, $c) {
            $text = trim((string) $text);
            if ($text === '') return;

            $code = $this->terminology->codeableConcept('condition', $icd ?: $text, $text);

            $out[] = array_filter([
                'resourceType'       => 'Condition',
                'id'                 => 'cond-' . $c->id . '-' . (++$n),
                'clinicalStatus'     => ['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code'   => 'active',
                ]]],
                'verificationStatus' => ['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                    'code'   => $verification, // provisional | differential | confirmed
                ]]],
                'code'               => $code ?: ['text' => $text],
                'subject'            => ['reference' => $patientRef],
                'encounter'          => ['reference' => $encounterRef],
            ], fn ($v) => $v !== null && $v !== []);
        };

        // Diagnoses live on the consultation itself (the diagnoses() relation points
        // at the diagnosis_masters lookup table and is not per-consultation, so we
        // intentionally do NOT use it here).
        $add($c->primary_diagnosis, $c->diagnosis_icd_code, 'confirmed');
        $add($c->secondary_diagnosis, null, 'confirmed');
        $add($c->provisional_diagnosis, null, 'provisional');
        $add($c->differential_diagnosis, null, 'differential');

        return $out;
    }
}
