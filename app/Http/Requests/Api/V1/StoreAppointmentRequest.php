<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * StoreAppointmentRequest
 * -----------------------
 * Validation for POST /api/v1/appointments — booking a scheduled appointment
 * for an EXISTING patient. Fails in the standard API envelope (422).
 */
class StoreAppointmentRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'patient_id'            => ['required', 'integer', 'exists:patients,id'],
            'doctor_id'             => ['required', 'integer', 'exists:users,id'],
            'appointment_date'      => ['required', 'date'],
            'appointment_time'      => ['required', 'string', 'max:8'],
            'duration_minutes'      => ['nullable', 'integer', 'min:5', 'max:480'],
            'type'                  => ['nullable', 'in:consultation,treatment'],
            'treatment_category_id' => ['nullable', 'integer', 'exists:treatment_categories,id'],
            'treatment_id'          => ['nullable', 'integer', 'exists:treatments,id'],
            'operatory_id'          => ['nullable', 'integer', 'exists:operatories,id'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'chief_complaint'       => ['nullable', 'string', 'max:1000'],
        ];
    }
}
