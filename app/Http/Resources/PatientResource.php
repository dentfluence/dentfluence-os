<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PatientResource
 * ---------------
 * The single shape a Patient takes when handed to any API client. Keeping the
 * transform here (not in the controller) means the mobile app, Tulip and any
 * future client all see identical, predictable JSON — and we never accidentally
 * leak internal columns.
 *
 * Tags are only included when eager-loaded (->load('tags')), so list endpoints
 * stay lean unless tags are explicitly requested.
 */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'patient_id'    => $this->patient_id,
            'name'          => $this->name,
            'title'         => $this->title,
            'first_name'    => $this->first_name,
            'middle_name'   => $this->middle_name,
            'last_name'     => $this->last_name,
            'gender'        => $this->gender,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'dob_unknown'   => (bool) $this->dob_unknown,
            'age_years'     => $this->age_years,

            // Contact
            'phone'           => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'email'           => $this->email,
            'emergency_contact_name'         => $this->emergency_contact_name,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'emergency_contact_number'       => $this->emergency_contact_number,

            // Address
            'address' => $this->address,
            'area'    => $this->area,
            'city'    => $this->city,
            'state'   => $this->state,
            'pincode' => $this->pincode,

            // Clinical (light — full clinical history is its own endpoint later)
            'medical_alert'       => $this->medical_alert,
            'medical_conditions'  => $this->medical_conditions,
            'dental_conditions'   => $this->dental_conditions,
            'current_medications' => $this->current_medications,
            'chief_complaint'     => $this->chief_complaint,
            'occupation'          => $this->occupation,
            'habits'              => $this->habits,
            'allergies'           => $this->allergies,
            'family_notes'        => $this->family_notes,

            // Source & referral (mobile Edit screen needs these to prefill)
            'source'               => $this->source,
            'referral_type'        => $this->referral_type,
            'referred_patient_id'  => $this->referred_patient_id,
            'referrer_name'        => $this->referrer_name,
            'referrer_mobile'      => $this->referrer_mobile,
            'referrer_type'        => $this->referrer_type,
            'referrer_notes'       => $this->referrer_notes,

            // Membership & follow-up
            'membership_status'     => $this->membership_status,
            'membership_expires_at' => optional($this->membership_expires_at)->toDateString(),
            'follow_up_status'      => $this->follow_up_status,
            'follow_up_date'        => optional($this->follow_up_date)->toDateString(),

            // Activity & money (denormalized)
            'last_visit_date'     => optional($this->last_visit_date)->toDateString(),
            'outstanding_balance' => $this->outstanding_balance,

            // Status
            'is_active' => (bool) $this->is_active,

            // Relations (only when loaded)
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id'   => $t->id,
                'name' => $t->name,
            ])->values()),
            'referred_patient' => $this->whenLoaded('referredPatient', fn () => $this->referredPatient ? [
                'id'         => $this->referredPatient->id,
                'name'       => $this->referredPatient->name,
                'patient_id' => $this->referredPatient->patient_id,
                'phone'      => $this->referredPatient->phone,
            ] : null),

            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
