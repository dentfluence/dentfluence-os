{{-- Edit Patient Drawer — inside x-data patientProfile() scope --}}
{{-- Edit Patient — same 5-tab form as New Patient, opened pre-filled via
     the 'open-edit-patient' window event (replaces the old edit drawer). --}}
@include('partials.add-patient-modal')

@php
$editPatientPrefill = [
    'id'          => $patient->id,
    'name'        => $patient->name,
    'title'       => $patient->title,
    'first_name'  => $patient->first_name,
    'middle_name' => $patient->middle_name,
    'last_name'   => $patient->last_name,
    'gender'      => $patient->gender,
    'patient_id'  => $patient->patient_id,
    'occupation'  => $patient->occupation,
    'dob'         => $patient->date_of_birth?->format('Y-m-d'),
    'dob_unknown' => (bool) $patient->dob_unknown,
    'age_years'   => $patient->age_years,
    'tags'        => $patient->tags->pluck('name')->values(),
    'phone'           => $patient->phone,
    'alternate_phone' => $patient->alternate_phone,
    'email'           => $patient->email,
    'emergency_contact_name'         => $patient->emergency_contact_name,
    'emergency_contact_relationship' => $patient->emergency_contact_relationship,
    'emergency_contact_number'       => $patient->emergency_contact_number,
    'address' => $patient->address,
    'area'    => $patient->area,
    'city'    => $patient->city,
    'pincode' => $patient->pincode,
    'medical_conditions'  => $patient->medical_conditions ?? [],
    'current_medications' => $patient->current_medications,
    'dental_conditions'   => $patient->dental_conditions ?? [],
    'habits'              => $patient->habits ?? [],
    'habit_frequency'     => $patient->habit_frequency ?: new \stdClass,
    'medical_alert'       => $patient->medical_alert,
    'allergies'           => $patient->allergies ?? [],
    'family_notes'        => $patient->family_notes,
    'source'              => $patient->source,
    'source_camp_name'    => $patient->source_camp_name,
    'source_campaign'     => $patient->source_campaign,
    'referral_type'       => $patient->referral_type,
    'referred_patient_id' => $patient->referred_patient_id,
    'referred_patient'    => $patient->referredPatient ? [
        'id'         => $patient->referredPatient->id,
        'name'       => $patient->referredPatient->name,
        'patient_id' => $patient->referredPatient->patient_id,
        'phone'      => $patient->referredPatient->phone,
    ] : null,
    'referrer_name'   => $patient->referrer_name,
    'referrer_mobile' => $patient->referrer_mobile,
    'referrer_type'   => $patient->referrer_type,
    'referrer_notes'  => $patient->referrer_notes,
    'updated_at'      => $patient->updated_at?->toIso8601String(),
];
@endphp
<script>
    window.__editPatientPrefill = {{ \Illuminate\Support\Js::from($editPatientPrefill) }};
</script>
