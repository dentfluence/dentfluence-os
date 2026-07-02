<?php

namespace Tests\Feature\Relationship;

use App\Models\CommunicationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 3 — PRE Recall Pipeline.
 *
 * Read-only board of recall rows from the legacy communication_queue
 * (purpose = 'recall' OR source_engine = 'recall'), grouped by the reliable
 * legacy status. Non-recall rows must be excluded.
 */
class RecallPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    private function recall(array $attrs): CommunicationQueue
    {
        return CommunicationQueue::create(array_merge([
            'person_name' => 'Recall Person',
            'phone'       => '9200000000',
            'channel'     => 'call',
            'comm_type'   => 'existing_patient',
            'direction'   => 'inbound',
            'purpose'     => 'recall',
            'status'      => 'pending',
            'priority'    => 'medium',
        ], $attrs));
    }

    public function test_renders_and_excludes_non_recall_rows(): void
    {
        $this->recall(['person_name' => 'Recall Asha', 'status' => 'pending']);
        // A non-recall communication that must NOT appear.
        $this->recall(['person_name' => 'Just An Appointment', 'purpose' => 'appointment', 'source_engine' => 'manual']);

        $response = $this->actingAs($this->user())->get(route('relationship.recalls'));

        $response->assertOk();
        $response->assertSee('Recall Pipeline');
        $response->assertSee('Recall Asha');
        $response->assertDontSee('Just An Appointment');
    }

    public function test_includes_recall_by_source_engine(): void
    {
        // purpose not 'recall' but source_engine is → still counts as a recall.
        $this->recall(['person_name' => 'Engine Recall', 'purpose' => 'general_query', 'source_engine' => 'recall']);

        $response = $this->actingAs($this->user())->get(route('relationship.recalls'));

        $response->assertOk();
        $response->assertSee('Engine Recall');
        $this->assertSame(1, $response->viewData('total'));
    }

    public function test_groups_recalls_by_legacy_status(): void
    {
        $pending = $this->recall(['person_name' => 'Pending One', 'status' => 'pending']);
        $closed  = $this->recall(['person_name' => 'Closed One', 'status' => 'closed']);

        $response = $this->actingAs($this->user())->get(route('relationship.recalls'));
        $response->assertOk();

        $columns    = collect($response->viewData('columns'));
        $pendingCol = $columns->firstWhere('key', 'pending');
        $closedCol  = $columns->firstWhere('key', 'closed');

        $this->assertSame(1, $pendingCol['count']);
        $this->assertTrue($pendingCol['items']->contains('id', $pending->id));
        $this->assertFalse($pendingCol['items']->contains('id', $closed->id));

        $this->assertSame(1, $closedCol['count']);
        $this->assertTrue($closedCol['items']->contains('id', $closed->id));

        // openCount excludes the closed recall.
        $this->assertSame(1, $response->viewData('openCount'));
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('relationship.recalls'))->assertRedirect();
    }
}
