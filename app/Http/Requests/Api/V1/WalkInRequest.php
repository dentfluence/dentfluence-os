<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * WalkInRequest
 * -------------
 * Validation for POST /api/v1/appointments/walk-in. Either an existing
 * patient_id, OR first/last name + phone for a brand-new walk-in patient.
 */
class WalkInRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'first_name' => ['required_without:patient_id', 'nullable', 'string', 'max:100'],
            'last_name'  => ['required_without:patient_id', 'nullable', 'string', 'max:100'],
            'phone'      => ['required_without:patient_id', 'nullable', 'string', 'max:20'],

            'doctor_id'             => ['nullable', 'integer', 'exists:users,id'],
            'appointment_date'      => ['nullable', 'date'],
            'appointment_time'      => ['nullable', 'string', 'max:8'],
            'duration_minutes'      => ['nullable', 'integer', 'min:5', 'max:480'],
            'treatment_category_id' => ['nullable', 'integer', 'exists:treatment_categories,id'],
            'treatment_id'          => ['nullable', 'integer', 'exists:treatments,id'],
            'operatory_id'          => ['nullable', 'integer', 'exists:operatories,id'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            // Explicit double-book override — same as StoreAppointmentRequest;
            // without this rule the walk-in path could never be overridden.
            'allow_overlap'         => ['nullable', 'boolean'],
        ];
    }
}
