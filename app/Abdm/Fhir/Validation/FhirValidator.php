<?php

namespace App\Abdm\Fhir\Validation;

/**
 * Pragmatic FHIR R4 validator.
 *
 * This is NOT a full StructureDefinition validator (that needs the official
 * profile packages). It checks the things that actually matter for ABDM exchange:
 * every resource has a type and the fields ABDM/FHIR require, enums are legal, and
 * inside a document Bundle every internal reference resolves to an entry. The point
 * is a hard gate: nothing is ever marked "final" (and later, transmitted) if it has
 * errors. Full profile validation can be layered on later without changing callers.
 *
 * Returns: ['ok' => bool, 'errors' => string[], 'warnings' => string[]]
 */
class FhirValidator
{
    private const GENDERS = ['male', 'female', 'other', 'unknown'];

    private const ENCOUNTER_STATUS = ['planned', 'arrived', 'triaged', 'in-progress', 'onleave', 'finished', 'cancelled', 'unknown'];

    private const MEDREQ_STATUS = ['active', 'on-hold', 'cancelled', 'completed', 'entered-in-error', 'stopped', 'draft', 'unknown'];

    public function validate(array $resource): array
    {
        $errors   = [];
        $warnings = [];

        $type = $resource['resourceType'] ?? null;
        if (! $type) {
            return ['ok' => false, 'errors' => ['Missing resourceType'], 'warnings' => []];
        }

        if ($type === 'Bundle') {
            return $this->validateBundle($resource);
        }

        $this->validateResource($resource, $errors, $warnings);

        return ['ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    /* ── Bundle ── */

    private function validateBundle(array $bundle): array
    {
        $errors   = [];
        $warnings = [];

        if (empty($bundle['type'])) {
            $errors[] = 'Bundle.type is required';
        }

        $entries  = $bundle['entry'] ?? [];
        if (empty($entries)) {
            $errors[] = 'Bundle has no entries';
            return ['ok' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Collect fullUrls for reference-resolution.
        $fullUrls = [];
        foreach ($entries as $e) {
            if (! empty($e['fullUrl'])) $fullUrls[$e['fullUrl']] = true;
        }

        foreach ($entries as $i => $e) {
            if (empty($e['fullUrl'])) {
                $warnings[] = "entry[{$i}] missing fullUrl";
            }
            if (empty($e['resource'])) {
                $errors[] = "entry[{$i}] missing resource";
                continue;
            }
            $rType = $e['resource']['resourceType'] ?? '(unknown)';
            $sub   = ['errors' => [], 'warnings' => []];
            $this->validateResource($e['resource'], $sub['errors'], $sub['warnings']);
            foreach ($sub['errors'] as $msg)   $errors[]   = "{$rType}: {$msg}";
            foreach ($sub['warnings'] as $msg) $warnings[] = "{$rType}: {$msg}";
        }

        // A document Bundle must start with a Composition.
        if (($bundle['type'] ?? null) === 'document') {
            $first = $entries[0]['resource']['resourceType'] ?? null;
            if ($first !== 'Composition') {
                $errors[] = 'Document Bundle must have a Composition as the first entry';
            }
        }

        // Every urn:uuid reference should resolve to an entry fullUrl.
        foreach ($this->collectReferences($bundle) as $ref) {
            if (str_starts_with($ref, 'urn:uuid:') && ! isset($fullUrls[$ref])) {
                $warnings[] = "Unresolved reference: {$ref}";
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings];
    }

    /* ── Per-resource required fields ── */

    private function validateResource(array $r, array &$errors, array &$warnings): void
    {
        $type = $r['resourceType'] ?? null;

        if (empty($r['id'])) {
            $warnings[] = 'missing id';
        }

        switch ($type) {
            case 'Patient':
                if (empty($r['identifier']))  $errors[]   = 'Patient.identifier is required';
                if (empty($r['name']))        $warnings[] = 'Patient.name is recommended';
                if (isset($r['gender']) && ! in_array($r['gender'], self::GENDERS, true)) {
                    $errors[] = "Patient.gender '{$r['gender']}' is not a valid code";
                }
                break;

            case 'Practitioner':
                if (empty($r['identifier'])) $errors[] = 'Practitioner.identifier is required';
                if (empty($r['name']))       $warnings[] = 'Practitioner.name is recommended';
                break;

            case 'Organization':
                if (empty($r['name'])) $errors[] = 'Organization.name is required';
                break;

            case 'Encounter':
                if (empty($r['status']) || ! in_array($r['status'], self::ENCOUNTER_STATUS, true)) {
                    $errors[] = 'Encounter.status missing or invalid';
                }
                if (empty($r['class']))   $warnings[] = 'Encounter.class is recommended';
                if (empty($r['subject'])) $errors[]   = 'Encounter.subject is required';
                break;

            case 'Condition':
                if (empty($r['code']))    $errors[] = 'Condition.code is required';
                if (empty($r['subject'])) $errors[] = 'Condition.subject is required';
                break;

            case 'Observation':
                if (empty($r['status'])) $errors[] = 'Observation.status is required';
                if (empty($r['code']))   $errors[] = 'Observation.code is required';
                break;

            case 'MedicationRequest':
                if (empty($r['status']) || ! in_array($r['status'], self::MEDREQ_STATUS, true)) {
                    $errors[] = 'MedicationRequest.status missing or invalid';
                }
                if (empty($r['intent'])) $errors[] = 'MedicationRequest.intent is required';
                if (empty($r['medicationCodeableConcept']) && empty($r['medicationReference'])) {
                    $errors[] = 'MedicationRequest requires medication[x]';
                }
                if (empty($r['subject'])) $errors[] = 'MedicationRequest.subject is required';
                break;

            case 'AllergyIntolerance':
                if (empty($r['patient'])) $errors[]   = 'AllergyIntolerance.patient is required';
                if (empty($r['code']))    $warnings[] = 'AllergyIntolerance.code is recommended';
                break;

            case 'Composition':
                foreach (['status', 'type', 'subject', 'date', 'author'] as $f) {
                    if (empty($r[$f])) $errors[] = "Composition.{$f} is required";
                }
                break;
        }
    }

    /** Recursively collect all reference strings in a structure. */
    private function collectReferences(array $node, array &$acc = []): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'reference' && is_string($value)) {
                $acc[] = $value;
            } elseif (is_array($value)) {
                $this->collectReferences($value, $acc);
            }
        }
        return $acc;
    }
}
