<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Transformers;

use App\Modules\Huddle\DTOs\HuddleCardDTO;

class TaskToCardTransformer
{
    /**
     * Transform a raw task row into a HuddleCardDTO.
     *
     * Expected $row fields (from AggregationService query):
     *   id, title, description, assigned_to, created_by,
     *   branch_id, patient_id, due_date, due_time,
     *   priority, category, status, done_at, escalated_at,
     *   escalation_note,
     *   assignee_name (nullable — joined from users),
     *   patient_name  (nullable — joined from patients)
     */
    public function transform(object $row): HuddleCardDTO
    {
        return new HuddleCardDTO(
            sourceType:    'task',
            sourceId:      (int) $row->id,
            patientId:     isset($row->patient_id) ? (int) $row->patient_id : null,
            patientName:   $row->patient_name  ?? null,
            doctorName:    $row->assignee_name ?? '—',
            time:          $row->due_time  ?? null,
            date:          $row->due_date,
            duration:      null,
            appointmentType: null,
            treatmentName: null,
            categoryName:  $row->category,
            status:        $row->status,
            chiefComplaint: null,
            notes:         $row->description ?? null,
            patientAlert:  null,
            meta: [
                'title'          => $row->title,
                'priority'       => $row->priority,
                'done_at'        => $row->done_at        ?? null,
                'escalated_at'   => $row->escalated_at   ?? null,
                'escalation_note'=> $row->escalation_note ?? null,
            ],
        );
    }
}
