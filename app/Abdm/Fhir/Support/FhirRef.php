<?php

namespace App\Abdm\Fhir\Support;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;

/**
 * Builds consistent FHIR references for the three actor resources. The logical id
 * is deterministic (the resource's stored FHIR id, or a stable local-id fallback)
 * so that references and Bundle entry ids always line up. The Bundle assembler later
 * rewrites "Type/{id}" → "urn:uuid:{id}" uniformly.
 */
class FhirRef
{
    public static function patientId(?Patient $p): string
    {
        return $p?->fhir_resource_id ?: ('p-' . ($p?->id ?? '0'));
    }

    public static function practitionerId(?User $u): string
    {
        return (optional($u?->hrProfile)->fhir_practitioner_id) ?: ('u-' . ($u?->id ?? '0'));
    }

    public static function organizationId(?Branch $b): string
    {
        return $b?->fhir_organization_id ?: ('b-' . ($b?->id ?? '0'));
    }

    public static function patient(?Patient $p): array
    {
        return array_filter(['reference' => 'Patient/' . self::patientId($p), 'display' => $p?->name]);
    }

    public static function practitioner(?User $u): array
    {
        return array_filter(['reference' => 'Practitioner/' . self::practitionerId($u), 'display' => $u?->name]);
    }

    public static function organization(?Branch $b): array
    {
        return array_filter(['reference' => 'Organization/' . self::organizationId($b), 'display' => $b?->name]);
    }
}
