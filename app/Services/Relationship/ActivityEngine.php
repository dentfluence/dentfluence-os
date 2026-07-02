<?php

namespace App\Services\Relationship;

use App\Jobs\RecalculateRelationshipScoreJob;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ActivityEngine — the universal event log service.
 *
 * Every meaningful thing that happens in the system writes here via log().
 * This replaces LeadActivity as the write target for new code, but does NOT
 * delete LeadActivity — both tables coexist through Phase 3.
 *
 * Event naming convention: '{domain}.{action}'
 * Examples:
 *   'lead.created'          — a new lead entered the system
 *   'lead.stage_changed'    — lead moved to a new stage
 *   'appointment.booked'    — appointment scheduled
 *   'appointment.cancelled' — appointment cancelled
 *   'call.logged'           — staff logged a call outcome
 *   'recall.queued'         — recall job queued for a patient
 *   'payment.received'      — payment recorded on an invoice
 *
 * Usage:
 *   app(ActivityEngine::class)->log($lead, 'lead.created', auth()->user(), ['source' => 'website_form']);
 */
class ActivityEngine
{
    /**
     * Write a single activity entry.
     *
     * @param  Model       $subject        The thing the event is about (Lead, Patient, Appointment, …)
     * @param  string      $event          Event key in dot notation: 'lead.created', 'call.logged', …
     * @param  Model|null  $actor          Who caused the event. Pass null for system/automated actions.
     * @param  array       $metadata       Any extra context (old stage, new stage, channel, values, …)
     * @param  int|null    $relationshipId Explicit relationship_id — auto-resolved from $subject if not provided.
     * @param  string|null $description    Human-readable summary shown on the Timeline.
     *
     * @return Activity|null  Returns the created Activity, or null if it failed (never throws).
     */
    public function log(
        Model   $subject,
        string  $event,
        ?Model  $actor       = null,
        array   $metadata    = [],
        ?int    $relationshipId = null,
        ?string $description = null,
    ): ?Activity {
        try {
            // Auto-resolve relationship_id from the subject if not provided
            if ($relationshipId === null && isset($subject->relationship_id)) {
                $relationshipId = $subject->relationship_id ?: null;
            }

            $activity = Activity::create([
                'relationship_id' => $relationshipId,
                'subject_type'    => get_class($subject),
                'subject_id'      => $subject->getKey(),
                'actor_type'      => $actor ? get_class($actor) : null,
                'actor_id'        => $actor?->getKey(),
                'event'           => $event,
                'description'     => $description,
                'metadata'        => $metadata ?: null,
                'occurred_at'     => now(),
            ]);

            // Phase 5 — RulesEngine hook.
            // Dispatch rules evaluation AFTER the current DB transaction commits,
            // so rule actions (task creation, etc.) are never nested inside a
            // parent transaction that might roll back.
            // Capture values for the closure — do not close over $subject directly
            // if it may be in an unsaved/dirty state.
            $capturedEvent    = $event;
            $capturedSubject  = $subject;
            $capturedMetadata = $metadata;

            // Capture relationship_id for afterCommit closures
            $capturedRelationshipId = $relationshipId;

            DB::afterCommit(function () use ($capturedEvent, $capturedSubject, $capturedMetadata) {
                try {
                    app(\App\Services\Relationship\RulesEngine::class)
                        ->evaluate($capturedEvent, $capturedSubject, $capturedMetadata);
                } catch (\Throwable $e) {
                    // RulesEngine has its own internal FailSafe; this is the outer safety net
                    Log::warning('ActivityEngine: RulesEngine::evaluate failed in afterCommit', [
                        'event' => $capturedEvent,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            // Phase 6 — RelationshipScoreEngine hook.
            // Dispatch a queued recalculation job whenever a score-relevant event fires.
            $scoreEvents = config('relationship_score.recalculate_on_events', []);
            if ($capturedRelationshipId !== null && in_array($event, $scoreEvents, true)) {
                DB::afterCommit(function () use ($capturedRelationshipId) {
                    dispatch(new RecalculateRelationshipScoreJob($capturedRelationshipId));
                });
            }

            // Phase 1 (Workstream B) — publish the ActivityRecorded domain event
            // AFTER commit so future projections/subscribers can react. There are
            // no subscribers yet → harmless no-op. Wrapped so it can never break
            // logging.
            $capturedActivityId  = $activity->getKey();
            $capturedSubjectType = get_class($subject);
            $capturedSubjectId   = (int) $subject->getKey();

            DB::afterCommit(function () use (
                $capturedActivityId, $capturedRelationshipId, $capturedEvent, $capturedSubjectType, $capturedSubjectId
            ) {
                try {
                    app(\App\Domain\Events\DomainEventBus::class)->publish(
                        new \App\Domain\Events\Relationship\ActivityRecorded(
                            relationshipId: $capturedRelationshipId,
                            activityId: (int) $capturedActivityId,
                            event: $capturedEvent,
                            subjectType: $capturedSubjectType,
                            subjectId: $capturedSubjectId,
                        )
                    );
                } catch (\Throwable $e) {
                    Log::warning('ActivityEngine: publish(ActivityRecorded) failed', ['error' => $e->getMessage()]);
                }
            });

            return $activity;

        } catch (\Throwable $e) {
            // ActivityEngine must never break the calling action
            Log::warning('ActivityEngine::log failed', [
                'event'      => $event,
                'subject'    => get_class($subject) . '#' . $subject->getKey(),
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Retrieve recent activities for a relationship (for the Timeline).
     *
     * @param int $id    Relationship ID
     * @param int $limit Max number of rows to return (default 50)
     *
     * @return Collection<Activity>
     */
    public function forRelationship(int $id, int $limit = 50): Collection
    {
        return Activity::forRelationship($id)
            ->recent()
            ->limit($limit)
            ->get();
    }
}
