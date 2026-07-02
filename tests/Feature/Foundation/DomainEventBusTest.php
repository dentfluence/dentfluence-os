<?php

namespace Tests\Feature\Foundation;

use App\Domain\Events\DomainEventBus;
use App\Models\ProcessedDomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Events\CanaryEvent;
use Tests\TestCase;

/**
 * Phase 0 — Domain-event foundation.
 *
 * Verifies: synchronous publish/subscribe, versioned envelope, and the
 * idempotency primitive (onceProcessed) that makes re-delivery safe.
 */
class DomainEventBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_delivers_to_subscriber_synchronously(): void
    {
        $bus = app(DomainEventBus::class);

        $received = 0;
        $bus->subscribe(CanaryEvent::class, function (CanaryEvent $e) use (&$received) {
            $received += $e->value;
        });

        $bus->publish(new CanaryEvent(value: 5));

        $this->assertSame(5, $received);
    }

    public function test_event_envelope_is_versioned_and_identified(): void
    {
        $event = new CanaryEvent(value: 1, relationshipId: 42);

        $this->assertSame('test.canary', $event->name());
        $this->assertSame(1, $event->version());
        $this->assertNotEmpty($event->eventId());
        $this->assertSame(42, $event->relationshipId());

        $envelope = $event->envelope();
        $this->assertSame($event->eventId(), $envelope['event_id']);
        $this->assertArrayHasKey('occurred_at', $envelope);
        $this->assertSame(['value' => 1], $envelope['payload']);
    }

    public function test_once_processed_runs_handler_exactly_once(): void
    {
        $bus = app(DomainEventBus::class);
        $event = new CanaryEvent(value: 1);

        $runs = 0;
        $handler = function () use (&$runs) { $runs++; };

        $first  = $bus->onceProcessed($event, 'test.subscriber', $handler);
        $second = $bus->onceProcessed($event, 'test.subscriber', $handler); // re-delivery

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(1, $runs);
        $this->assertSame(1, ProcessedDomainEvent::count());
    }

    public function test_different_subscribers_each_process_the_same_event(): void
    {
        $bus = app(DomainEventBus::class);
        $event = new CanaryEvent(value: 1);

        $this->assertTrue($bus->onceProcessed($event, 'subscriber.a', fn () => null));
        $this->assertTrue($bus->onceProcessed($event, 'subscriber.b', fn () => null));

        $this->assertSame(2, ProcessedDomainEvent::count());
    }

    public function test_handler_failure_rolls_back_the_idempotency_claim(): void
    {
        $bus = app(DomainEventBus::class);
        $event = new CanaryEvent(value: 1);

        try {
            $bus->onceProcessed($event, 'failing.subscriber', function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Expected the handler exception to propagate.');
        } catch (\RuntimeException $e) {
            // expected
        }

        // Claim must NOT persist, so a later re-delivery can retry.
        $this->assertSame(0, ProcessedDomainEvent::count());
    }
}
