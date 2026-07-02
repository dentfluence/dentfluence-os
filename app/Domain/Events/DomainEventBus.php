<?php

namespace App\Domain\Events;

use App\Models\ProcessedDomainEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * DomainEventBus — Phase 0 (Safety & Foundations).
 *
 * A thin wrapper over Laravel's event dispatcher that gives Dentfluence a
 * PRODUCT-level vocabulary: modules publish/subscribe to *domain events*,
 * never to "a bus" or a transport (Blueprint §11: "think in Events, not Bus").
 *
 * Phase 0 delivery is SYNCHRONOUS, in-process. The wrapper exists so that the
 * later switch to an async transport is a change here only — publishers and
 * subscribers do not change, because:
 *   - events carry a stable eventId (idempotency),
 *   - subscribers use onceProcessed() to dedupe re-delivery,
 *   - subscribers must not assume ordering or another subscriber's completion.
 *
 * No distributed messaging is implemented here. That is deliberate.
 */
class DomainEventBus
{
    public function __construct(private readonly Dispatcher $events)
    {
    }

    /**
     * Subscribe a listener to a domain-event class.
     *
     * @param  class-string<DomainEvent>  $eventClass
     * @param  callable|class-string      $listener
     */
    public function subscribe(string $eventClass, callable|string $listener): void
    {
        $this->events->listen($eventClass, $listener);
    }

    /**
     * Publish a domain event. Synchronous in Phase 0.
     */
    public function publish(DomainEvent $event): void
    {
        $this->events->dispatch($event);
    }

    /**
     * Run $handler exactly once for this (event, subscriber) pair.
     *
     * Returns true if the handler ran (first delivery), false if it was skipped
     * because this subscriber already processed this event.
     *
     * The idempotency claim and the handler run in ONE transaction:
     *   - insertOrIgnore atomically claims the slot (0 rows = already claimed),
     *   - if the handler throws, the whole transaction rolls back INCLUDING the
     *     claim, so a later re-delivery safely re-runs the work.
     *
     * This is the primitive that makes every subscriber idempotent and
     * re-delivery-safe under both the current sync transport and a future
     * async one.
     */
    public function onceProcessed(DomainEvent $event, string $subscriber, callable $handler): bool
    {
        return DB::transaction(function () use ($event, $subscriber, $handler): bool {
            $claimed = DB::table('processed_domain_events')->insertOrIgnore([
                'event_id'     => $event->eventId(),
                'subscriber'   => $subscriber,
                'event_name'   => $event->name(),
                'processed_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            if ($claimed === 0) {
                return false; // already processed by this subscriber → skip
            }

            $handler($event);

            return true;
        });
    }

    /** Has this subscriber already processed this event? (read-only) */
    public function alreadyProcessed(DomainEvent $event, string $subscriber): bool
    {
        return ProcessedDomainEvent::query()
            ->where('event_id', $event->eventId())
            ->where('subscriber', $subscriber)
            ->exists();
    }
}
