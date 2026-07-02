<?php

namespace Tests\Feature\Relationship;

use App\Models\Lead;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — closing the identity gap.
 *
 * Every lead — however it was created — must link to a Master Relationship.
 * Previously only the webhook ingest path called linkLead, so leads typed into
 * the PRM board (Add Lead / Quick Add) landed with relationship_id = null. The
 * LeadObserver now links on create, so all paths are covered.
 */
class LeadAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate the identity behaviour from the observer's other side-effects.
        config([
            'prm.ai.enabled'       => false,
            'prm.routing.enabled'  => false,
            'prm.followups.enabled' => false,
        ]);
    }

    public function test_a_manually_created_lead_is_linked_to_a_relationship(): void
    {
        // Lead::create fires the observer — this is the PRM board / Quick Add path.
        $lead = Lead::create(['name' => 'Walk In Patient', 'phone' => '9998887777']);

        $lead->refresh();

        $this->assertNotNull($lead->relationship_id, 'A new lead must be linked to a relationship.');

        $this->assertDatabaseHas('relationships', [
            'id'    => $lead->relationship_id,
            'phone' => '9998887777',
        ]);

        // The initial lead journey is created too (shadow).
        $this->assertDatabaseHas('relationship_journeys', [
            'relationship_id' => $lead->relationship_id,
            'type'            => 'lead',
            'state'           => 'new_enquiry',
        ]);
    }

    public function test_two_leads_sharing_a_phone_resolve_to_the_same_relationship(): void
    {
        $first  = Lead::create(['name' => 'First Enquiry',  'phone' => '9111222333']);
        $second = Lead::create(['name' => 'Second Enquiry', 'phone' => '9111222333']);

        $this->assertSame(
            $first->refresh()->relationship_id,
            $second->refresh()->relationship_id,
            'Leads with the same phone should resolve to one Master Relationship.'
        );
        $this->assertSame(1, Relationship::where('phone', '9111222333')->count());
    }
}
