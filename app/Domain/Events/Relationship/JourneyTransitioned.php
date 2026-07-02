<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 domain event — a relationship journey changed state.
 * CONTRACT ONLY in Sprint 1; emission is wired by Workstream C (journeys).
 */
final class JourneyTransitioned extends AbstractDomainEvent
{
    public function __construct(
        int $relationshipId,
        public readonly string $journeyType, // lead | opportunity | recall | ...
        public readonly ?string $fromState,
        public readonly string $toState,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'journey.transitioned';
    }

    public function payload(): array
    {
        return [
            'journey_type' => $this->journeyType,
            'from_state'   => $this->fromState,
            'to_state'     => $this->toState,
        ];
    }
}
