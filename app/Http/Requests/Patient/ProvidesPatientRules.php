<?php

namespace App\Http\Requests\Patient;

use Illuminate\Validation\Rule;

/**
 * ProvidesPatientRules — the single canonical validation contract for a Patient,
 * shared by BOTH the web Form Requests and the API Form Requests so the two can
 * never drift again.
 *
 * Canonical field names are the storage names: `phone`, `date_of_birth`,
 * `chief_complaint`. Legacy web-form aliases (`mobile`, `dob`, `notes`) are
 * folded onto the canonical keys by normalizePatientAliases() in
 * prepareForValidation, so old front-ends keep working while validation and
 * PatientService see one vocabulary.
 */
trait ProvidesPatientRules
{
    /** Rules for creating a patient. Phone + a name are the only requirements. */
    protected function patientCreateRules(): array
    {
        return array_merge($this->patientSharedRules(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'phone'      => ['required', 'string', 'max:20'],
            // DOB is optional, but age must always be captured: either a DOB (age
            // is auto-derived) OR a manual age. So age is required only when no DOB.
            'date_of_birth' => ['nullable', 'date'],
            'age_years'     => ['nullable', 'integer', 'min:0', 'max:150', 'required_without:date_of_birth'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ]);
    }

    /** Rules for updating a patient. Everything optional (partial update). */
    protected function patientUpdateRules(): array
    {
        $patient = $this->route('patient');
        $patientId = is_object($patient) ? $patient->id : $patient;

        return array_merge($this->patientSharedRules(), [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'name'       => ['nullable', 'string', 'max:200'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'patient_id' => ['nullable', 'string', 'max:30', Rule::unique('patients', 'patient_id')->ignore($patientId)],
            // Admin/editable status fields the edit form exposes.
            'membership_status'     => ['nullable', 'in:not_enrolled,active,expired'],
            'membership_expires_at' => ['nullable', 'date'],
            'follow_up_status'      => ['nullable', 'in:none,due,pending,completed'],
            'follow_up_date'        => ['nullable', 'date'],
            'referred_by'           => ['nullable', 'string'],
        ]);
    }

    /** Fields common to create + update. */
    private function patientSharedRules(): array
    {
        return [
            'title'       => ['nullable', 'string', 'max:10'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'gender'      => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'dob_unknown' => ['nullable', 'boolean'],
            'age_years'   => ['nullable', 'integer', 'min:0', 'max:150'],
            'tags'        => ['nullable', 'array'],

            'alternate_phone'                => ['nullable', 'string', 'max:20'],
            'email'                          => ['nullable', 'email', 'max:255'],
            'emergency_contact_name'         => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_number'       => ['nullable', 'string', 'max:20'],

            'address' => ['nullable', 'string', 'max:500'],
            'area'    => ['nullable', 'string', 'max:150'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'occupation' => ['nullable', 'string', 'max:150'],

            'medical_conditions'  => ['nullable', 'array'],
            'current_medications' => ['nullable', 'string'],
            'dental_conditions'   => ['nullable', 'array'],
            'medical_alert'       => ['nullable', 'string'],
            'allergies'           => ['nullable', 'array'],
            'chief_complaint'     => ['nullable', 'string'],

            'habits'          => ['nullable', 'array'],
            'habit_frequency' => ['nullable', 'array'],

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
            'family_notes'         => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Fold legacy aliases onto canonical keys — only when the alias was actually
     * sent and the canonical key wasn't (so partial updates never blank a field).
     */
    protected function normalizePatientAliases(): void
    {
        $merge = [];
        if (! $this->has('phone') && $this->has('mobile')) {
            $merge['phone'] = $this->input('mobile');
        }
        if (! $this->has('date_of_birth') && $this->has('dob')) {
            $merge['date_of_birth'] = $this->input('dob');
        }
        if (! $this->has('chief_complaint') && $this->has('notes')) {
            $merge['chief_complaint'] = $this->input('notes');
        }
        if ($merge) {
            $this->merge($merge);
        }
    }
}
