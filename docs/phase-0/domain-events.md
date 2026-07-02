# Domain Events

Phase 0 ships the **event contract + a synchronous, in-process bus**. No real domain events are published into production flows yet — that begins in Phase 1. Think in *events*, never in "a bus" or a transport.

## Contracts

- `App\Domain\Events\DomainEvent` — the interface: `eventId()`, `name()`, `version()`, `occurredAt()`, `relationshipId()`, `payload()`.
- `App\Domain\Events\AbstractDomainEvent` — base class that supplies the envelope (`eventId` = UUID, `occurredAt`). Concrete events declare `name()`, `version()`, `payload()`.
- `App\Domain\Events\DomainEventBus` — `publish()`, `subscribe()`, `onceProcessed()`.

## Why it is built this way (future async safety)

The bus is synchronous now, but every event is designed so the later switch to an async transport is a change *here only*:

- **Idempotent** — each event has a stable `eventId`. Subscribers use `onceProcessed()` to dedupe re-delivery.
- **Order-tolerant** — events carry `occurredAt`; a subscriber must not assume ordering or that another subscriber has completed.
- **Versionable** — `version()` lets a payload evolve additively (add fields, never break).

## Idempotency primitive

```php
$bus->onceProcessed($event, 'subscriber.key', function ($event) {
    // ... do the work exactly once ...
});
```

The claim (into `processed_domain_events`) and the handler run in **one transaction**: if the handler throws, the claim rolls back too, so a later re-delivery safely retries. A duplicate delivery returns `false` and does nothing.

## Rules

- New event = a new `AbstractDomainEvent` subclass with a past-tense `name()` (e.g. `appointment.completed`).
- Payloads are **additive-only**; bump `version()` when you add fields.
- Subscribers must be idempotent (`onceProcessed`) and must not depend on ordering.
