<?php

namespace Tests\Feature\Phase1;

use App\Domain\Events\Relationship\JourneyTransitioned;
use App\Domain\Events\Relationship\LeadCaptured;
use App\Domain\Events\Relationship\PatientRegistered;
use App\Domain\Events\Relationship\RelationshipLinked;
use App\Domain\Events\Relationship\RelationshipMerged;
use Tests\TestCase;

/**
 * Phase 1 · shared event catalogue — contract test.
 *
 * Locks each event's name, version, relationship id and payload shape so the
 * contracts stay stable (additive-only) as the workstreams that emit them are
 * built. No DB required.
 */
class Phase1EventCatalogTest extends TestCase
{
    public function test_patient_registered(): void
    {
        $e = new PatientRegistered(10, 20);
        $this->assertSame('patient.registered', $e->name());
        $this->assertSame(1, $e->version());
        $this->assertSame(10, $e->relationshipId());
        $this->assertSame(['patient_id' => 20], $e->payload());
        $this->assertNotEmpty($e->eventId());
    }

    public function test_lead_captured(): void
    {
        $e = new LeadCaptured(10, 30, 'website');
        $this->assertSame('lead.captured', $e->name());
        $this->assertSame(['lead_id' => 30, 'source' => 'website'], $e->payload());
    }

    public function test_relationship_linked(): void
    {
        $e = new RelationshipLinked(10, \App\Models\Patient::class, 20);
        $this->assertSame('relationship.linked', $e->name());
        $this->assertSame(\App\Models\Patient::class, $e->payload()['subject_type']);
        $this->assertSame(20, $e->payload()['subject_id']);
    }

    public function test_relationship_merged(): void
    {
        $e = new RelationshipMerged(1, 2, 5);
        $this->assertSame('relationship.merged', $e->name());
        $this->assertSame(1, $e->relationshipId());
        $this->assertSame(2, $e->payload()['merged_relationship_id']);
        $this->assertSame(1, $e->payload()['surviving_relationship_id']);
    }

    public function test_journey_transitioned(): void
    {
        $e = new JourneyTransitioned(10, 'lead', null, 'contacted');
        $this->assertSame('journey.transitioned', $e->name());
        $this->assertSame('lead', $e->payload()['journey_type']);
        $this->assertNull($e->payload()['from_state']);
        $this->assertSame('contacted', $e->payload()['to_state']);
    }
}
