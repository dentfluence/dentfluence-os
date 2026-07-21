<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Web create-patient validation. Shares the canonical rules with the API via
 * ProvidesPatientRules; response behaviour (redirect vs JSON) is the standard
 * FormRequest default, so the AJAX modal gets a 422 and full-page posts redirect.
 */
class StorePatientRequest extends FormRequest
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
        return $this->patientCreateRules();
    }
}
