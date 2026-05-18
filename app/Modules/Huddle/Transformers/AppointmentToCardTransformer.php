<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Transformers;

use App\Modules\Huddle\DTOs\HuddleCardDTO;

class AppointmentToCardTransformer
{
    /**
     * Transform a raw appointment row (with joined patient/doctor/treatment data)
     * into a HuddleCardDTO.
     *
     * Expected $row fields (from AggregationService query):
     *   appointment_id, patient_id, doctor_id, branch_id,
     *   appointment_date, appointment_time, duration_minutes,
     *   type, treatment_category_id, treatment_id, status,
     *   chief_complaint, notes,
     *   patient_name, patient_phone,
     *   doctor_name,
     *   treatment_name (nullable),
     *   category_name (nullable),
     *   patient_alert (nullable)
     */
    public function transform(object $row): HuddleCardDTO
    {
        return new HuddleCardDTO(
            sourceType:    'appointment',
            sourceId:      (int) $row->appointment_id,
            patientId:     (int) $row->patient_id,
            patientName:   $row->patient_name ?? 'Unknown',
            doctorName:    $row->doctor_name  ?? '—',
            time:          $row->appointment_time,
            date:          $row->appointment_date,
            duration:      (int) ($row->duration_minutes ?? 30),
            appointmentType: $row->type,
            treatmentName: $row->treatment_name  ?? null,
            categoryName:  $row->category_name   ?? null,
            status:        $this->mapStatus($row->status),
            chiefComplaint: $row->chief_complaint ?? null,
            notes:         $row->notes            ?? null,
            patientAlert:  $row->patient_alert    ?? null,
            meta:          [],
        );
    }
    private function mapStatus(string $appointmentStatus): string
    {
        return match($appointmentStatus) {
            'scheduled'         => 'pending',
            'checkin'           => 'pending',
            'in_chair'          => 'in_progress',
            'checkout'          => 'in_progress',
            'done'              => 'done',
            'cancelled'         => 'done',
            'no_show'           => 'done',
            default             => 'pending',
        };
    }
}
