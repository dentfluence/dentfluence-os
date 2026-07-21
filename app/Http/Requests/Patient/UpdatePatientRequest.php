<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Web update-patient validation. Shares the canonical rules with the API via
 * ProvidesPatientRules. All fields optional (partial update).
 */
class UpdatePatientRequest extends FormRequest
{
    use ProvidesPatientRules;

    public function authorize(): bool
    {
        // Backstop for the route-level `module:patients,edit` gate.
        return (bool) $this->user()?->canAccess('patients', 'edit');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePatientAliases();
    }

    public function rules(): array
    {
        return $this->patientUpdateRules();
    }
}
