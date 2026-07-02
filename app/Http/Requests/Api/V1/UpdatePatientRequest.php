<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * UpdatePatientRequest
 * --------------------
 * Validation for PUT/PATCH /api/v1/patients/{patient}. Everything is optional
 * (partial update): PatientService writes only the fields actually supplied.
 */
class UpdatePatientRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title'       => ['nullable', 'string', 'max:10'],
            'first_name'  => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['nullable', 'string', 'max:100'],
            'name'        => ['nullable', 'string', 'max:200'],
            'gender'      => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'date_of_birth' => ['nullable', 'date'],
            'dob_unknown' => ['nullable', 'boolean'],
            'age_years'   => ['nullable', 'integer', 'min:0', 'max:150'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email'           => ['nullable', 'email', 'max:255'],
            'occupation'      => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'area'    => ['nullable', 'string', 'max:150'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'emergency_contact_name'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_number'       => ['nullable', 'string', 'max:20'],
            'medical_alert'       => ['nullable', 'string'],
            'medical_conditions'  => ['nullable', 'array'],
            'current_medications' => ['nullable', 'string'],
            'dental_conditions'   => ['nullable', 'array'],
            'habits'              => ['nullable', 'array'],
            'habit_frequency'     => ['nullable', 'array'],
            'allergies'           => ['nullable', 'array'],
            'family_notes'        => ['nullable', 'string', 'max:500'],
            'source'              => ['nullable', 'string', 'max:100'],
            'referred_by'         => ['nullable', 'string'],
            'source_referral_name' => ['nullable', 'string', 'max:150'],
            'source_camp_name'     => ['nullable', 'string', 'max:150'],
            'source_campaign'      => ['nullable', 'string', 'max:150'],
            'referral_type'        => ['nullable', 'in:existing_patient,other'],
            'referred_patient_id'  => ['nullable', 'integer', 'exists:patients,id'],
            'referrer_name'        => ['nullable', 'string', 'max:150'],
            'referrer_mobile'      => ['nullable', 'string', 'max:20'],
            'referrer_type'        => ['nullable', 'in:Doctor,Friend,Family,Staff,Corporate,Other'],
            'referrer_notes'       => ['nullable', 'string', 'max:500'],
            'membership_status'    => ['nullable', 'in:not_enrolled,active,expired'],
            'membership_expires_at' => ['nullable', 'date'],
            'follow_up_status'     => ['nullable', 'in:none,due,pending,completed'],
            'follow_up_date'       => ['nullable', 'date'],
        ];
    }
}
