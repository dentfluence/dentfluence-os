<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 domain event — a patient has been registered and linked to a
 * Master Relationship. Emitted (behind the identity.link_patient flag) when
 * a patient is created and the Relationship Engine links it.
 */
final class PatientRegistered extends AbstractDomainEvent
{
    public function __construct(
        int $relationshipId,
        public readonly int $patientId,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'patient.registered';
    }

    public function payload(): array
    {
        return ['patient_id' => $this->patientId];
    }
}
