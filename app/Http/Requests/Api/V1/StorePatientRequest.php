<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;
use App\Http\Requests\Patient\ProvidesPatientRules;

/**
 * StorePatientRequest (API) — POST /api/v1/patients.
 * Uses the SAME canonical rules as the web via ProvidesPatientRules; only the
 * failure envelope (JSON, from ApiFormRequest) differs.
 */
class StorePatientRequest extends ApiFormRequest
{
    use ProvidesPatientRules;

    protected function prepareForValidation(): void
    {
        $this->normalizePatientAliases();
    }

    public function rules(): array
    {
        return $this->patientCreateRules();
    }
}
