<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * BlockSlotRequest
 * ----------------
 * Validation for POST /api/v1/appointments/block-slot — blocking a doctor's
 * time (personal, leave, etc.). Either an explicit end_time or a duration.
 */
class BlockSlotRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'doctor_id'        => ['required', 'integer', 'exists:users,id'],
            'block_date'       => ['required', 'date'],
            'start_time'       => ['required', 'string', 'max:8'],
            'end_time'         => ['nullable', 'string', 'max:8'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'reason'           => ['nullable', 'string', 'max:255'],
            'block_type'       => ['nullable', 'string', 'max:50'],
        ];
    }
}
