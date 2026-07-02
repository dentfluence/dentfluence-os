<?php

namespace Tests\Feature\Relationship;

use App\Models\Lead;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\User;
use App\Services\Prm\PrmRelationshipAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream F (slice F1) — PRM → relationship spine adapter.
 *
 * Proves PRM writes now (1) shadow-sync the lead journey with the CORRECT
 * stage→state mapping and (2) record a unified-timeline Activity — while being
 * a safe no-op for leads that aren't linked to a relationship.
 */
class PrmAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function rel(): Relationship
    {
        return Relationship::create([
            'name' => 'Adapter', 'phone' => '9' . random_int(100000000, 999999999),
            'status' => 'active', 'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
    }

    private function lead(string $stage, ?int $relationshipId): Lead
    {
        return Lead::withoutEvents(function () use ($stage, $relationshipId) {
            $l = new Lead(['name' => 'L', 'phone' => '900']);
            $l->stage           = $stage;
            $l->relationship_id = $relationshipId;
            $l->save();
            return $l;
        });
    }

    public function test_stage_change_syncs_journey_with_correct_mapping_and_logs_activity(): void
    {
        $rel  = $this->rel();
        $user = User::factory()->create(['branch_id' => 1]);
        $lead = $this->lead('plan_given', $rel->id);   // already saved at new stage

        app(PrmRelationshipAdapter::class)->onStageChanged($lead, 'contacted', 'plan_given', $user);

        // Journey mapped plan_given → treatment_planned (not the raw lead stage).
        $this->assertDatabaseHas('relationship_journeys', [
            'relationship_id' => $rel->id,
            'type'            => 'lead',
            'state'           => 'treatment_planned',
        ]);

        // Unified-timeline Activity recorded, linked to the relationship.
        $this->assertDatabaseHas('activities', [
            'relationship_id' => $rel->id,
            'event'           => 'lead.stage_changed',
        ]);
    }

    public function test_activity_logged_records_relationship_activity(): void
    {
        $rel  = $this->rel();
        $lead = $this->lead('contacted', $rel->id);

        app(PrmRelationshipAdapter::class)->onActivityLogged($lead, 'call', 'Called patient', 'No answer', 'unreachable');

        $this->assertDatabaseHas('activities', [
            'relationship_id' => $rel->id,
            'event'           => 'lead.activity_logged',
            'description'     => 'No answer',
        ]);
    }

    public function test_converted_syncs_journey_and_logs(): void
    {
        $rel  = $this->rel();
        $lead = $this->lead('converted', $rel->id);

        app(PrmRelationshipAdapter::class)->onConverted($lead);

        $this->assertDatabaseHas('relationship_journeys', [
            'relationship_id' => $rel->id,
            'type'            => 'lead',
            'state'           => 'closed',
        ]);
        $this->assertDatabaseHas('activities', [
            'relationship_id' => $rel->id,
            'event'           => 'lead.converted',
        ]);
    }

    public function test_lead_without_relationship_is_a_safe_noop(): void
    {
        $lead = $this->lead('plan_given', null);

        app(PrmRelationshipAdapter::class)->onStageChanged($lead, 'contacted', 'plan_given');

        $this->assertSame(0, RelationshipJourney::count());
        $this->assertDatabaseCount('activities', 0);
    }
}
