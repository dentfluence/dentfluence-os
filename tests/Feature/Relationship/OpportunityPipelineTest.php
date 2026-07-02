<?php

namespace Tests\Feature\Relationship;

use App\Models\Patient;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 3 — PRE Opportunity Pipeline.
 *
 * Read-only board grouped by the reliable legacy treatment_opportunities.status.
 * The shadow opportunity-journey column is gated by
 * `relationship.opportunity_journey_column` (default off).
 */
class OpportunityPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    private function opportunity(string $relName, string $status, string $phone): TreatmentOpportunity
    {
        $rel     = Relationship::create([
            'name' => $relName, 'phone' => $phone, 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
        $patient = Patient::create(['name' => 'P', 'phone' => $phone]);

        $opp = new TreatmentOpportunity([
            'type' => 'implant', 'status' => $status, 'estimated_value' => 50000, 'priority' => 'high',
        ]);
        $opp->patient_id      = $patient->id;
        $opp->relationship_id = $rel->id;
        $opp->save();

        return $opp;
    }

    public function test_renders_for_authenticated_user(): void
    {
        $this->opportunity('Implant Person', 'quoted', '9101');

        $response = $this->actingAs($this->user())->get(route('relationship.opportunities'));

        $response->assertOk();
        $response->assertSee('Opportunity Pipeline');
        $response->assertSee('Implant Person');
        $response->assertSee('Identified');    // prospect column label
        $response->assertSee('Estimate Given'); // quoted column label
        $response->assertSee('Declined');
    }

    public function test_groups_opportunities_by_legacy_status(): void
    {
        $quoted   = $this->opportunity('Quoted Person', 'quoted', '9102');
        $declined = $this->opportunity('Declined Person', 'declined', '9103');

        $response = $this->actingAs($this->user())->get(route('relationship.opportunities'));
        $response->assertOk();

        $columns     = collect($response->viewData('columns'));
        $quotedCol   = $columns->firstWhere('key', 'quoted');
        $declinedCol = $columns->firstWhere('key', 'declined');

        $this->assertSame(1, $quotedCol['count']);
        $this->assertTrue($quotedCol['items']->contains('id', $quoted->id));
        $this->assertFalse($quotedCol['items']->contains('id', $declined->id));

        $this->assertSame(1, $declinedCol['count']);
        $this->assertTrue($declinedCol['items']->contains('id', $declined->id));
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('relationship.opportunities'))->assertRedirect();
    }

    public function test_journey_column_hidden_by_default(): void
    {
        $opp = $this->opportunity('Journey Person', 'quoted', '9104');
        RelationshipJourney::create([
            'relationship_id' => $opp->relationship_id,
            'type'            => RelationshipJourney::TYPE_OPPORTUNITY,
            'state'           => 'quoted',
            'metadata'        => ['opportunity_id' => $opp->id],
            'started_at'      => now(),
        ]);

        $response = $this->actingAs($this->user())->get(route('relationship.opportunities'));

        $response->assertOk();
        $this->assertFalse($response->viewData('showJourney'));
        $response->assertDontSee('Journey (shadow)');
    }

    public function test_journey_column_shown_when_flag_enabled(): void
    {
        Feature::set('relationship.opportunity_journey_column', true);

        $opp = $this->opportunity('Journey Person', 'quoted', '9105');
        RelationshipJourney::create([
            'relationship_id' => $opp->relationship_id,
            'type'            => RelationshipJourney::TYPE_OPPORTUNITY,
            'state'           => 'quoted',
            'metadata'        => ['opportunity_id' => $opp->id],
            'started_at'      => now(),
        ]);

        $response = $this->actingAs($this->user())->get(route('relationship.opportunities'));

        $response->assertOk();
        $this->assertTrue($response->viewData('showJourney'));
        $response->assertSee('Journey (shadow)');
    }
}
