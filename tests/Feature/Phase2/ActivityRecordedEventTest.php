<?php

namespace Tests\Feature\Phase2;

use App\Domain\Events\Relationship\ActivityRecorded;
use App\Models\Patient;
use Tests\TestCase;

/**
 * Phase 1 · Sprint 2 (Workstream B) — ActivityRecorded event contract.
 */
class ActivityRecordedEventTest extends TestCase
{
    public function test_shape(): void
    {
        $e = new ActivityRecorded(
            relationshipId: 7,
            activityId: 100,
            event: 'appointment.completed',
            subjectType: Patient::class,
            subjectId: 55,
        );

        $this->assertSame('activity.recorded', $e->name());
        $this->assertSame(1, $e->version());
        $this->assertSame(7, $e->relationshipId());
        $this->assertSame(100, $e->payload()['activity_id']);
        $this->assertSame('appointment.completed', $e->payload()['event']);
        $this->assertSame(Patient::class, $e->payload()['subject_type']);
        $this->assertSame(55, $e->payload()['subject_id']);
    }

    public function test_relationship_id_may_be_null_for_system_events(): void
    {
        $e = new ActivityRecorded(null, 1, 'system.maintenance', Patient::class, 1);
        $this->assertNull($e->relationshipId());
    }
}
