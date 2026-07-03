<?php

namespace Tests\Feature\Insights;

use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Relationship\ActivityRecorded;
use App\Jobs\RecalculateInsightSignalsJob;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 6 · Slice 1 — Insights Engine, incremental recompute path.
 *
 * RecalculateInsightSignalsListener is subscribed to ActivityRecorded (the
 * one universal fact-publisher) app-wide via InsightsServiceProvider. These
 * tests prove the flag genuinely gates behaviour: while `insights.signals`
 * is off (the default), publishing the event does nothing — so this slice
 * ships with zero behaviour change until the flag is flipped.
 */
class InsightSignalEventListenerTest extends TestCase
{
    use RefreshDatabase;

    private function fire(?int $relationshipId, string $eventName): void
    {
        app(DomainEventBus::class)->publish(new ActivityRecorded(
            relationshipId: $relationshipId,
            activityId: 1,
            event: $eventName,
            subjectType: 'App\\Models\\Lead',
            subjectId: 1,
        ));
    }

    public function test_flag_off_by_default_dispatches_nothing(): void
    {
        Queue::fake();

        $this->fire(relationshipId: 42, eventName: 'recall.queued');

        Queue::assertNotPushed(RecalculateInsightSignalsJob::class);
    }

    public function test_flag_on_and_relevant_event_dispatches_recompute_for_that_relationship(): void
    {
        Feature::set('insights.signals', true);
        Queue::fake();

        $this->fire(relationshipId: 42, eventName: 'recall.queued');

        Queue::assertPushed(RecalculateInsightSignalsJob::class, function ($job) {
            return $job->relationshipId === 42;
        });
    }

    public function test_flag_on_but_irrelevant_event_dispatches_nothing(): void
    {
        Feature::set('insights.signals', true);
        Queue::fake();

        $this->fire(relationshipId: 42, eventName: 'some.unrelated_event');

        Queue::assertNotPushed(RecalculateInsightSignalsJob::class);
    }

    public function test_flag_on_but_no_relationship_dispatches_nothing(): void
    {
        Feature::set('insights.signals', true);
        Queue::fake();

        $this->fire(relationshipId: null, eventName: 'recall.queued');

        Queue::assertNotPushed(RecalculateInsightSignalsJob::class);
    }

    public function test_redelivery_of_the_same_event_is_idempotent(): void
    {
        Feature::set('insights.signals', true);
        Queue::fake();

        $event = new ActivityRecorded(
            relationshipId: 42, activityId: 1, event: 'recall.queued',
            subjectType: 'App\\Models\\Lead', subjectId: 1,
        );

        $bus = app(DomainEventBus::class);
        $bus->publish($event);
        $bus->publish($event); // same eventId — simulates re-delivery

        Queue::assertPushed(RecalculateInsightSignalsJob::class, 1);
    }
}
