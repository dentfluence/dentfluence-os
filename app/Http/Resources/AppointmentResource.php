<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * AppointmentResource
 * -------------------
 * The single JSON shape for an appointment handed to any API client.
 * Flattens the useful patient/doctor/treatment names so the mobile app
 * doesn't need extra round-trips.
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $date = $this->appointment_date;
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        } elseif (is_string($date) && strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        $time = $this->appointment_time ? substr($this->appointment_time, 0, 5) : null;

        return [
            'id'                    => $this->id,
            'status'                => $this->status,
            'type'                  => $this->type,

            'appointment_date'      => $date,
            'appointment_time'      => $time,
            'duration_minutes'      => $this->duration_minutes ?? 30,

            // Patient (flattened)
            'patient_id'            => $this->patient_id,
            'patient_name'          => $this->patient?->name ?? '—',
            'patient_phone'         => $this->patient?->phone,

            // Doctor (flattened)
            'doctor_id'             => $this->doctor_id,
            'doctor_name'           => $this->doctor?->name ?? '—',

            // Treatment (flattened)
            'treatment_category_id' => $this->treatment_category_id,
            'treatment_category'    => $this->treatmentCategory?->name,
            'treatment_id'          => $this->treatment_id,
            'treatment'             => $this->treatment?->name,

            // Operatory / chair
            'operatory_id'          => $this->operatory_id,
            'operatory_name'        => $this->operatory?->name,
            'chair_number'          => $this->chair_number,

            // Extras
            'is_walkin'             => (bool) $this->is_walkin,
            'notes'                 => $this->notes,
            'chief_complaint'       => $this->chief_complaint,
            'cancel_reason'         => $this->cancel_reason,

            // Lifecycle timestamps (HH:MM, today-friendly)
            'checked_in_at'         => $this->checked_in_at?->format('H:i'),
            'in_chair_at'           => $this->in_chair_at?->format('H:i'),
            'completed_at'          => $this->completed_at?->format('H:i'),

            'created_at'            => optional($this->created_at)->toIso8601String(),
        ];
    }
}
