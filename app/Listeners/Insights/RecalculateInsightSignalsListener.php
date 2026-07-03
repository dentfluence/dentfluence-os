<?php

namespace App\Listeners\Insights;

use App\Domain\Events\DomainEventBus;
use App\Domain\Events\Relationship\ActivityRecorded;
use App\Jobs\RecalculateInsightSignalsJob;
use App\Support\Features\Feature;
use Illuminate\Support\Facades\Log;

/**
 * RecalculateInsightSignalsListener — Phase 6 · Slice 1 (Insights Engine).
 *
 * Subscribed to ActivityRecorded (the one universal fact-publisher — see
 * ActivityEngine::log()). This is the event-fed incremental path the
 * blueprint calls for: whenever a relevant fact lands in the ledger for a
 * relationship, its 3 signals are queued for recompute.
 *
 * Flag-gated by `insights.signals` (default OFF): while off, this listener
 * still hears every ActivityRecorded event (subscribing is harmless) but does
 * nothing — so behaviour is byte-for-byte unchanged until the flag is
 * flipped. This mirrors how the Guard/automation flags were introduced dark
 * before being turned on.
 *
 * Idempotent via DomainEventBus::onceProcessed — safe under re-delivery.
 */
class RecalculateInsightSignalsListener
{
    public function __construct(private readonly DomainEventBus $bus) {}

    public function handle(ActivityRecorded $event): void
    {
        if (! Feature::enabled('insights.signals')) {
            return;
        }

        if ($event->relationshipId() === null) {
            return; // system-wide activity, not tied to a person — nothing to recompute
        }

        if (! in_array($event->event, config('insights.recalculate_on_events', []), true)) {
            return;
        }

        $this->bus->onceProcessed($event, 'insights.recalculate_signals', function () use ($event) {
            try {
                RecalculateInsightSignalsJob::dispatch($event->relationshipId());
            } catch (\Throwable $e) {
                Log::warning('RecalculateInsightSignalsListener: dispatch failed', [
                    'relationship_id' => $event->relationshipId(),
                    'error'           => $e->getMessage(),
                ]);
            }
        });
    }
}
