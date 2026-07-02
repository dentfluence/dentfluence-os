<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiFormRequest;

/**
 * StorePatientRequest
 * -------------------
 * Validation for POST /api/v1/patients. Uses clean API field names
 * (phone, date_of_birth, chief_complaint) which PatientService also accepts
 * alongside the web form's names (mobile, dob, notes).
 */
class StorePatientRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Identity
            'title'       => ['nullable', 'string', 'max:10'],
            'first_name'  => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'gender'      => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'date_of_birth' => ['nullable', 'date'],
            'dob_unknown' => ['nullable', 'boolean'],
            'age_years'   => ['nullable', 'integer', 'min:0', 'max:150'],
            'tags'        => ['nullable', 'array'],
            // Contact
            'phone'                          => ['required', 'string', 'max:20'],
            'alternate_phone'                => ['nullable', 'string', 'max:20'],
            'email'                          => ['nullable', 'email', 'max:255'],
            'emergency_contact_name'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_number'       => ['nullable', 'string', 'max:20'],
            // Address
            'address' => ['nullable', 'string', 'max:500'],
            'area'    => ['nullable', 'string', 'max:150'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            // Clinical
            'medical_conditions'  => ['nullable', 'array'],
            'current_medications' => ['nullable', 'string'],
            'dental_conditions'   => ['nullable', 'array'],
            'chief_complaint'     => ['nullable', 'string'],
            // Habits
            'habits'          => ['nullable', 'array'],
            'habit_frequency' => ['nullable', 'array'],
            // Source & referral
            'source'               => ['nullable', 'string', 'max:100'],
            'source_referral_name' => ['nullable', 'string', 'max:150'],
            'source_camp_name'     => ['nullable', 'string', 'max:150'],
            'source_campaign'      => ['nullable', 'string', 'max:150'],
            'referral_type'        => ['nullable', 'in:existing_patient,other'],
            'referred_patient_id'  => ['nullable', 'integer', 'exists:patients,id'],
            'referrer_name'        => ['nullable', 'string', 'max:150'],
            'referrer_mobile'      => ['nullable', 'string', 'max:20'],
            'referrer_type'        => ['nullable', 'in:Doctor,Friend,Family,Staff,Corporate,Other'],
            'referrer_notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
