<?php

namespace App\Domain\Events;

/**
 * DomainEvent — the contract every Dentfluence domain fact implements.
 *
 * Phase 0 ships the CONTRACT + a synchronous in-process bus only. No real
 * domain events are published into production flows yet (that begins Phase 1).
 *
 * Design constraints (Blueprint §11 + Red-Team conditions):
 *   - Idempotent:      every event has a stable eventId so re-delivery is safe.
 *   - Order-tolerant:  events carry occurredAt; subscribers must not assume
 *                      strict ordering or synchronous completion of others.
 *   - Versionable:     version() lets payloads evolve additively over time.
 *
 * These properties are what make the future in-memory → async transport switch
 * a configuration change rather than a rewrite.
 */
interface DomainEvent
{
    /** Stable unique id for this event instance (idempotency key). */
    public function eventId(): string;

    /** Past-tense fact name, e.g. 'appointment.completed'. */
    public function name(): string;

    /** Schema version of this event; increment additively. */
    public function version(): int;

    /** When the fact occurred (for ordering decisions). */
    public function occurredAt(): \DateTimeImmutable;

    /** The Master Relationship this fact concerns, if any. */
    public function relationshipId(): ?int;

    /** The event's data payload. @return array<string,mixed> */
    public function payload(): array;
}
