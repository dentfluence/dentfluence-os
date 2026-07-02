<?php

namespace App\Domain\Events;

use Illuminate\Support\Str;

/**
 * Base class for domain events. Handles the envelope (eventId, occurredAt) so
 * concrete events only declare their name, version and payload.
 *
 * Example (future phases):
 *
 *   final class AppointmentCompleted extends AbstractDomainEvent
 *   {
 *       public function __construct(public int $appointmentId, ?int $relationshipId) {
 *           parent::__construct($relationshipId);
 *       }
 *       public function name(): string { return 'appointment.completed'; }
 *       public function version(): int { return 1; }
 *       public function payload(): array { return ['appointment_id' => $this->appointmentId]; }
 *   }
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private string $eventId;
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly ?int $relationshipId = null,
        ?string $eventId = null,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->eventId    = $eventId ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function relationshipId(): ?int
    {
        return $this->relationshipId;
    }

    /** Version defaults to 1; override to evolve a payload additively. */
    public function version(): int
    {
        return 1;
    }

    /**
     * The full envelope — handy for structured logging and the future
     * async transport. Contains meta + payload, never lossy.
     *
     * @return array<string,mixed>
     */
    public function envelope(): array
    {
        return [
            'event_id'        => $this->eventId(),
            'name'            => $this->name(),
            'version'         => $this->version(),
            'occurred_at'     => $this->occurredAt()->format(DATE_ATOM),
            'relationship_id' => $this->relationshipId(),
            'payload'         => $this->payload(),
        ];
    }
}
