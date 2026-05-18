<?php

declare(strict_types=1);

namespace App\Modules\Huddle\DTOs;

/**
 * Typed value object that carries one card's data
 * from the aggregation service → resource → JSON.
 * No loose arrays cross service boundaries.
 */
final class HuddleCardDTO
{
    public function __construct(
        /** 'appointment' | 'task' */
        public readonly string  $sourceType,
        public readonly int     $sourceId,

        public readonly ?int    $patientId,
        public readonly ?string $patientName,
        public readonly ?string $doctorName,

        /** HH:MM:SS from DB time column */
        public readonly ?string $time,
        /** Y-m-d from DB date column */
        public readonly string  $date,
        public readonly ?int    $duration,

        /** 'consultation' | 'treatment' | null */
        public readonly ?string $appointmentType,
        public readonly ?string $treatmentName,
        public readonly ?string $categoryName,

        /**
         * For appointments: scheduled|checkin|in_chair|checkout|done|cancelled|no_show
         * For tasks:        pending|done|escalated
         */
        public readonly string  $status,

        public readonly ?string $chiefComplaint,
        public readonly ?string $notes,
        public readonly ?string $patientAlert,

        /** Arbitrary extra data (task title, priority, etc.) */
        public readonly array   $meta,
    ) {}
    public function toArray(): array
    {
        return [
            'source_type'      => $this->sourceType,
            'source_id'        => $this->sourceId,
            'patient_id'       => $this->patientId,
            'patient_name'     => $this->patientName,
            'doctor_name'      => $this->doctorName,
            'time'             => $this->time,
            'date'             => $this->date,
            'duration'         => $this->duration,
            'appointment_type' => $this->appointmentType,
            'treatment_name'   => $this->treatmentName,
            'category_name'    => $this->categoryName,
            'status'           => $this->status,
            'chief_complaint'  => $this->chiefComplaint,
            'notes'            => $this->notes,
            'patient_alert'    => $this->patientAlert,
            'meta'             => $this->meta,
        ];
    }
}
