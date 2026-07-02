<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 domain event — a subject (Lead or Patient) was linked to a Master
 * Relationship. The generic "identity resolved" fact.
 */
final class RelationshipLinked extends AbstractDomainEvent
{
    public function __construct(
        int $relationshipId,
        public readonly string $subjectType, // e.g. App\Models\Patient
        public readonly int $subjectId,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'relationship.linked';
    }

    public function payload(): array
    {
        return [
            'subject_type' => $this->subjectType,
            'subject_id'   => $this->subjectId,
        ];
    }
}
