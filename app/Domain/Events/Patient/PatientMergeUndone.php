<?php

namespace App\Domain\Events\Patient;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Domain event — a safety-net undo restored a previously merged patient.
 * Mirrors PatientMerged's shape. Emitted by PatientMergeService::undo() after
 * the undo transaction commits.
 */
final class PatientMergeUndone extends AbstractDomainEvent
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
        return 'patient.merge_undone';
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
