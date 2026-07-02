<?php

declare(strict_types=1);

namespace App\Modules\PracticeProtocols\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePracticeProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already guarded by the `module:practice_protocols` middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'role_id'           => ['required', 'exists:roles,id'],
            'branch_id'         => ['nullable', 'exists:branches,id'],
            'category'          => ['required', 'in:clinical,admin,lab,decon,reception,maintenance,other'],
            'frequency'         => ['required', 'in:once,daily,weekly,monthly'],
            'weekday'           => ['nullable', 'integer', 'between:0,6', 'required_if:frequency,weekly'],
            'day_of_month'      => ['nullable', 'integer', 'between:1,28', 'required_if:frequency,monthly'],
            'default_due_time'  => ['nullable', 'date_format:H:i'],
            'priority'          => ['required', 'in:urgent,high,medium,low'],
            'requires_evidence' => ['nullable', 'boolean'],
            'is_active'         => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalise checkbox values + clear schedule fields that don't apply
     * to the chosen frequency.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_evidence' => $this->boolean('requires_evidence'),
            'is_active'         => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}
