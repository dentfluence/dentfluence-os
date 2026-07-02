<?php

namespace Tests\Feature\Relationship;

use App\Models\Lead;
use App\Models\Relationship;
use App\Models\RelationshipJourney;
use App\Models\User;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 2 — PRE Lead Pipeline.
 *
 * The board is read-only and groups leads by the reliable legacy `leads.stage`.
 * The shadow journey column is gated by the `relationship.pipeline_journey_column`
 * flag (default off).
 */
class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    /** Create a lead without firing the enrichment observer. */
    private function lead(string $name, string $stage, ?int $relationshipId = null): Lead
    {
        return Lead::withoutEvents(function () use ($name, $stage, $relationshipId) {
            $l = new Lead(['name' => $name, 'phone' => '900' . random_int(1000, 9999)]);
            $l->stage = $stage;
            $l->relationship_id = $relationshipId;
            $l->save();
            return $l;
        });
    }

    public function test_pipeline_renders_for_authenticated_user(): void
    {
        $this->lead('Asha Rao', 'new_lead');

        $response = $this->actingAs($this->user())->get(route('relationship.pipeline'));

        $response->assertOk();
        $response->assertSee('Lead Pipeline');
        $response->assertSee('Asha Rao');
        // All seven stage columns render.
        $response->assertSee('New Lead');
        $response->assertSee('Converted');
        $response->assertSee('Lost');
    }

    public function test_pipeline_groups_leads_by_legacy_stage(): void
    {
        $new  = $this->lead('New Person', 'new_lead');
        $plan = $this->lead('Plan Person', 'plan_given');

        $response = $this->actingAs($this->user())->get(route('relationship.pipeline'));
        $response->assertOk();

        $columns = collect($response->viewData('columns'));

        $newCol  = $columns->firstWhere('key', 'new_lead');
        $planCol = $columns->firstWhere('key', 'plan_given');

        $this->assertSame(1, $newCol['count']);
        $this->assertTrue($newCol['leads']->contains('id', $new->id));
        $this->assertFalse($newCol['leads']->contains('id', $plan->id));

        $this->assertSame(1, $planCol['count']);
        $this->assertTrue($planCol['leads']->contains('id', $plan->id));
    }

    public function test_pipeline_requires_authentication(): void
    {
        $this->get(route('relationship.pipeline'))->assertRedirect();
    }

    public function test_journey_column_hidden_by_default(): void
    {
        $rel  = Relationship::create([
            'name' => 'Linked Lead', 'phone' => '9001111', 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
        RelationshipJourney::create([
            'relationship_id' => $rel->id,
            'type'            => RelationshipJourney::TYPE_LEAD,
            'state'           => RelationshipJourney::LEAD_CONTACTED,
            'started_at'      => now(),
        ]);
        $this->lead('Linked Lead', 'contacted', $rel->id);

        $response = $this->actingAs($this->user())->get(route('relationship.pipeline'));

        $response->assertOk();
        $this->assertFalse($response->viewData('showJourney'));
        $response->assertDontSee('Journey (shadow)');
    }

    public function test_journey_column_shown_when_flag_enabled(): void
    {
        // Enable the flag via a global override (exercises the real mechanism).
        Feature::set('relationship.pipeline_journey_column', true);

        $rel  = Relationship::create([
            'name' => 'Linked Lead', 'phone' => '9002222', 'status' => 'active',
            'score' => 0, 'relationship_since' => now()->toDateString(),
        ]);
        RelationshipJourney::create([
            'relationship_id' => $rel->id,
            'type'            => RelationshipJourney::TYPE_LEAD,
            'state'           => RelationshipJourney::LEAD_CONTACTED,
            'started_at'      => now(),
        ]);
        $this->lead('Linked Lead', 'contacted', $rel->id);

        $response = $this->actingAs($this->user())->get(route('relationship.pipeline'));

        $response->assertOk();
        $this->assertTrue($response->viewData('showJourney'));
        $response->assertSee('Journey (shadow)');
        $response->assertSee('contacted');
    }
}
