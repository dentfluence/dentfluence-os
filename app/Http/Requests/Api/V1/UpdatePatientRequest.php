<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use App\Http\Requests\Patient\ProvidesPatientRules;

/**
 * UpdatePatientRequest (API) — PUT/PATCH /api/v1/patients/{patient}.
 * Shares the canonical rules with the web via ProvidesPatientRules. Partial
 * update: PatientService writes only the fields actually supplied.
 */
class UpdatePatientRequest extends ApiFormRequest
{
    use ProvidesPatientRules;

    protected function prepareForValidation(): void
    {
        $this->normalizePatientAliases();
    }

    public function rules(): array
    {
        return $this->patientUpdateRules();
    }
}
