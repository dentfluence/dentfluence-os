<?php

namespace App\Abdm\Fhir\Builders;

use App\Models\Consultation;

/**
 * Builds FHIR Observation resources from a consultation's clinical findings.
 *
 * Reads the clinical_findings child rows (and falls back to the clinical_data JSON
 * on the consultation). Each non-empty finding becomes an Observation with a human
 * label and the finding as valueString. Standard LOINC/SNOMED codes can be layered
 * on later via terminology_maps without changing callers.
 */
class ObservationBuilder
{
    /** field => human label (dental examination findings). */
    private const FIELDS = [
        'soft_tissue'          => 'Soft tissue examination',
        'caries'               => 'Caries',
        'periodontal'          => 'Periodontal status',
        'bleeding_on_probing'  => 'Bleeding on probing',
        'plaque_index'         => 'Plaque index',
        'occlusion'            => 'Occlusion',
        'tmj'                  => 'TMJ',
        'oral_hygiene'         => 'Oral hygiene',
        'existing_condition'   => 'Existing condition',
    ];

    /**
     * @return array<int, array>  list of FHIR Observation resources
     */
    public function build(Consultation $c, string $patientRef, string $encounterRef): array
    {
        $out = [];
        $n   = 0;

        $add = function (string $label, $value) use (&$out, &$n, $patientRef, $encounterRef, $c) {
            $value = is_array($value) ? json_encode($value) : trim((string) $value);
            if ($value === '' || $value === null) return;

            $out[] = [
                'resourceType' => 'Observation',
                'id'           => 'obs-' . $c->id . '-' . (++$n),
                'status'       => 'final',
                'category'     => [['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code'   => 'exam',
                ]]]],
                'code'         => ['text' => $label],
                'subject'      => ['reference' => $patientRef],
                'encounter'    => ['reference' => $encounterRef],
                'valueString'  => $value,
            ];
        };

        // Prefer the structured clinical_findings child rows.
        $findings = $c->relationLoaded('clinicalFindings') ? $c->clinicalFindings : $c->clinicalFindings()->get();
        foreach ($findings as $f) {
            foreach (self::FIELDS as $field => $label) {
                $add($label, $f->{$field} ?? null);
            }
            if (! empty($f->notes)) {
                $add('Examination notes', $f->notes);
            }
        }

        // Fallback: the denormalized clinical_data JSON on the consultation.
        if (empty($out) && is_array($c->clinical_data)) {
            foreach (self::FIELDS as $field => $label) {
                $add($label, $c->clinical_data[$field] ?? null);
            }
        }

        return $out;
    }
}
