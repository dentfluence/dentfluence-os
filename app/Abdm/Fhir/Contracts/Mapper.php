<?php

namespace App\Abdm\Fhir\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Every entity→FHIR mapper implements this. Mappers are PURE: model in, FHIR
 * resource array out. No database writes, no network — which makes them trivial
 * to unit-test against the official FHIR R4 / ABDM example resources.
 */
interface Mapper
{
    /** The Eloquent model class this mapper handles, e.g. App\Models\Patient::class. */
    public function supports(): string;

    /** The FHIR resourceType this mapper produces, e.g. 'Patient'. */
    public function resourceType(): string;

    /** Convert the model into a FHIR R4 resource (associative array). */
    public function toFhir(Model $model): array;
}
