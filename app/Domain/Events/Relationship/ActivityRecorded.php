<?php

namespace App\Domain\Events\Relationship;

use App\Domain\Events\AbstractDomainEvent;

/**
 * Phase 1 · Workstream B — a fact was written to the Activity ledger.
 *
 * Published by ActivityEngine after each log() commits. In Phase 1 there are
 * no subscribers (harmless no-op); later phases let projections (Timeline,
 * Insights, Analytics, Search) update incrementally by subscribing to this.
 *
 * relationshipId is nullable — some activities are system-wide (no person).
 */
final class ActivityRecorded extends AbstractDomainEvent
{
    public function __construct(
        ?int $relationshipId,
        public readonly int $activityId,
        public readonly string $event,
        public readonly string $subjectType,
        public readonly int $subjectId,
    ) {
        parent::__construct($relationshipId);
    }

    public function name(): string
    {
        return 'activity.recorded';
    }

    public function payload(): array
    {
        return [
            'activity_id'  => $this->activityId,
            'event'        => $this->event,
            'subject_type' => $this->subjectType,
            'subject_id'   => $this->subjectId,
        ];
    }
}
