<?php

namespace App\Abdm\Fhir\Builders;

use App\Models\Patient;

/**
 * Builds FHIR AllergyIntolerance resources for a patient.
 *
 * Prefers the first-class patient_allergies rows; if a patient has none yet, falls
 * back to the legacy patients.allergies JSON array so it works during the transition.
 */
class AllergyBuilder
{
    /**
     * @return array<int, array>  list of FHIR AllergyIntolerance resources
     */
    public function build(Patient $p, string $patientRef): array
    {
        $out = [];
        $n   = 0;

        $rows = $p->relationLoaded('allergyRecords') ? $p->allergyRecords : $p->allergyRecords()->get();

        foreach ($rows as $a) {
            $out[] = $this->resource(
                ++$n,
                $p->id,
                $patientRef,
                $a->substance,
                $a->snomed_code,
                $a->category,
                $a->criticality,
                $a->reaction,
            );
        }

        // Fallback to the legacy JSON list of allergy strings.
        if (empty($out) && is_array($p->allergies)) {
            foreach ($p->allergies as $substance) {
                $substance = trim((string) $substance);
                if ($substance === '') continue;
                $out[] = $this->resource(++$n, $p->id, $patientRef, $substance, null, 'medication', null, null);
            }
        }

        return $out;
    }

    private function resource(int $n, $patientId, string $patientRef, ?string $substance, ?string $snomed, ?string $category, ?string $criticality, ?string $reaction): array
    {
        $code = $snomed
            ? ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $snomed, 'display' => $substance]], 'text' => $substance]
            : ['text' => $substance];

        $resource = [
            'resourceType'       => 'AllergyIntolerance',
            'id'                 => 'allergy-' . $patientId . '-' . $n,
            'clinicalStatus'     => ['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical',
                'code'   => 'active',
            ]]],
            'verificationStatus' => ['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification',
                'code'   => 'unconfirmed',
            ]]],
            'code'               => $code,
            'patient'            => ['reference' => $patientRef],
        ];

        if ($category) {
            $resource['category'] = [$category];
        }
        if ($criticality) {
            $resource['criticality'] = $criticality;
        }
        if ($reaction) {
            $resource['reaction'] = [['description' => $reaction]];
        }

        return $resource;
    }
}
