<?php

namespace App\Domain\Events\Patient;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Domain event — a duplicate patient was merged into a surviving one.
 * relationshipId = the surviving patient's Master Relationship (may be null).
 * Emitted by PatientMergeService after the merge transaction commits.
 */
final class PatientMerged extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $survivingPatientId,
        public readonly int $mergedPatientId,
        public readonly ?int $mergeRecordId = null,
        ?int $relationshipId = null,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'patient.merged';
    }

    public function payload(): array
    {
        return [
            'surviving_patient_id' => $this->survivingPatientId,
            'merged_patient_id'    => $this->mergedPatientId,
            'merge_record_id'      => $this->mergeRecordId,
        ];
    }
}
