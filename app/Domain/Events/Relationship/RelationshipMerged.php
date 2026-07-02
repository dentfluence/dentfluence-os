<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 domain event — two Master Relationships were merged into one.
 * relationshipId = the SURVIVING relationship; payload carries the merged one.
 * Emitted by MergeService.
 */
final class RelationshipMerged extends AbstractDomainEvent
{
    public function __construct(
        int $survivingRelationshipId,
        public readonly int $mergedRelationshipId,
        public readonly ?int $mergeRecordId = null,
    ) {
        parent::__construct($survivingRelationshipId);
    }

    public function name(): string
    {
        return 'relationship.merged';
    }

    public function payload(): array
    {
        return [
            'surviving_relationship_id' => $this->relationshipId(),
            'merged_relationship_id'    => $this->mergedRelationshipId,
            'merge_record_id'           => $this->mergeRecordId,
        ];
    }
}
