<?php

namespace Tests\Feature\Relationship;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 · Workstream D, slice 3 — PRE Recalls.
 *
 * Rebuilt 2026-07-06: dropped the 4-column "Recall Pipeline" kanban (recalls
 * don't move through funnel stages) in favour of a flat, filterable,
 * actionable list — filters, ignore/unignore, bulk dismiss/assign (including
 * the "select all matching filter" path), and Convert to Opportunity.
 *
 * Recall rows still come from the legacy communication_queue
 * (purpose = 'recall' OR source_engine = 'recall'). Non-recall rows must be
 * excluded.
 */
class RecallPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1, 'is_active' => true]);
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
        $response->assertSee('Recalls');
        $response->assertSee('Recall Asha');
        $response->assertDontSee('Just An Appointment');
    }

    public function test_includes_recall_by_source_engine(): void
    {
        $this->recall(['person_name' => 'Engine Recall', 'purpose' => 'general_query', 'source_engine' => 'recall']);

        $response = $this->actingAs($this->user())->get(route('relationship.recalls'));

        $response->assertOk();
        $response->assertSee('Engine Recall');
        $this->assertSame(1, $response->viewData('total'));
    }

    public function test_filters_by_status_and_priority(): void
    {
        $this->recall(['person_name' => 'Pending High', 'status' => 'pending', 'priority' => 'high']);
        $this->recall(['person_name' => 'Closed Low', 'status' => 'closed', 'priority' => 'low']);

        $response = $this->actingAs($this->user())->get(route('relationship.recalls', ['status' => 'pending']));
        $response->assertOk();
        $response->assertSee('Pending High');
        $response->assertDontSee('Closed Low');

        $response = $this->actingAs($this->user())->get(route('relationship.recalls', ['priority' => 'low']));
        $response->assertOk();
        $response->assertSee('Closed Low');
        $response->assertDontSee('Pending High');
    }

    public function test_ignore_hides_and_unignore_restores(): void
    {
        $recall = $this->recall(['person_name' => 'Ignore Me']);
        $user   = $this->user();

        $this->actingAs($user)->post(route('relationship.recalls.ignore', $recall->id))->assertRedirect();
        $recall->refresh();
        $this->assertNotNull($recall->ignored_at);

        $response = $this->actingAs($user)->get(route('relationship.recalls'));
        $response->assertDontSee('Ignore Me');

        $response = $this->actingAs($user)->get(route('relationship.recalls', ['show_ignored' => 1]));
        $response->assertSee('Ignore Me');

        $this->actingAs($user)->post(route('relationship.recalls.unignore', $recall->id))->assertRedirect();
        $this->assertNull($recall->refresh()->ignored_at);
    }

    public function test_bulk_dismiss_by_ids(): void
    {
        $a = $this->recall(['person_name' => 'Dismiss A']);
        $b = $this->recall(['person_name' => 'Dismiss B']);

        $this->actingAs($this->user())
            ->post(route('relationship.recalls.bulk-dismiss'), ['recall_ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame('closed', $a->refresh()->status);
        $this->assertSame('closed', $b->refresh()->status);
    }

    public function test_bulk_dismiss_select_all_matching_filter(): void
    {
        $this->recall(['person_name' => 'Match A', 'priority' => 'high']);
        $this->recall(['person_name' => 'Match B', 'priority' => 'high']);
        $other = $this->recall(['person_name' => 'No Match', 'priority' => 'low']);

        $this->actingAs($this->user())
            ->post(route('relationship.recalls.bulk-dismiss'), ['select_all' => 1, 'priority' => 'high'])
            ->assertRedirect();

        $this->assertSame(2, CommunicationQueue::where('status', 'closed')->count());
        $this->assertSame('pending', $other->refresh()->status);
    }

    public function test_bulk_assign(): void
    {
        $recall = $this->recall(['person_name' => 'Assign Me']);
        $staff  = User::factory()->create(['branch_id' => 1, 'is_active' => true, 'name' => 'Front Desk Priya']);

        $this->actingAs($this->user())
            ->post(route('relationship.recalls.bulk-assign'), ['recall_ids' => [$recall->id], 'assigned_to' => $staff->id])
            ->assertRedirect();

        $this->assertSame('Front Desk Priya', $recall->refresh()->assigned_to);
    }

    public function test_convert_to_opportunity_creates_opportunity_and_closes_recall(): void
    {
        $patient = Patient::factory()->create();
        $recall  = $this->recall(['person_name' => $patient->name, 'patient_id' => $patient->id]);

        $this->actingAs($this->user())
            ->post(route('relationship.recalls.convert', $recall->id), [
                'type'            => 'implant',
                'priority'        => 'high',
                'estimated_value' => 50000,
                'follow_up_date'  => now()->toDateString(),
                'notes'           => 'Patient mentioned wanting an implant.',
            ])
            ->assertRedirect(route('relationship.recalls'));

        $this->assertSame('closed', $recall->refresh()->status);
        $this->assertDatabaseHas('treatment_opportunities', [
            'patient_id' => $patient->id,
            'type'       => 'implant',
            'status'     => 'prospect',
        ]);
    }

    public function test_convert_requires_linked_patient(): void
    {
        $recall = $this->recall(['person_name' => 'No Patient Link', 'patient_id' => null]);

        $this->actingAs($this->user())
            ->post(route('relationship.recalls.convert', $recall->id), [
                'type'           => 'implant',
                'priority'       => 'high',
                'follow_up_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('convert');

        $this->assertSame('pending', $recall->refresh()->status);
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('relationship.recalls'))->assertRedirect();
    }
}
