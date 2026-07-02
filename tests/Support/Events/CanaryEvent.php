<?php

namespace Tests\Support\Events;

use App\Domain\Events\AbstractDomainEvent;

/**
 * A trivial domain event used ONLY by the Phase 0 test-suite to exercise the
 * DomainEventBus (publish/subscribe/idempotency). It is not part of the app's
 * production event catalogue — real domain events begin in Phase 1.
 */
final class CanaryEvent extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $value = 0,
        ?int $relationshipId = null,
        ?string $eventId = null,
    ) {
        parent::__construct($relationshipId, $eventId);
    }

    public function name(): string
    {
        return 'test.canary';
    }

    public function version(): int
    {
        return 1;
    }

    public function payload(): array
    {
        return ['value' => $this->value];
    }
}
