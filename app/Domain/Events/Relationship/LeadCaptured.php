<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 domain event — a lead has been captured and linked to a Master
 * Relationship. CONTRACT ONLY in Sprint 1 (the live linkLead path is not
 * modified yet); emission is wired in a later Sprint alongside Workstream B.
 */
final class LeadCaptured extends AbstractDomainEvent
{
    public function __construct(
        int $relationshipId,
        public readonly int $leadId,
        public readonly ?string $source = null,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'lead.captured';
    }

    public function payload(): array
    {
        return ['lead_id' => $this->leadId, 'source' => $this->source];
    }
}
