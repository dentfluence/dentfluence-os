<?php

namespace Tests\Feature\Access;

use App\Models\CommunicationQueue;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 CEO verification defect (2026-07-26) — INDIVIDUAL DISMISS.
 *
 * A Follow-up Call card could not be dismissed ("missing reference id"). Two
 * independent causes, neither of them authorization:
 *   1. The board's Dismiss payload resolved the backing record with a stale
 *      two-way rule (queue-backed vs meta.id), so five of the fifteen
 *      categories sent no subject_id at all.
 *   2. `follow_up_calls` was absent from TodayController::DISMISSIBLE_MODELS,
 *      so even a correct id was refused server-side.
 *
 * Approved semantics re-asserted here: an INDIVIDUAL dismiss with a reason is
 * relationship,EDIT. relationship,DELETE is only for bulk/administrative
 * destruction and must never be required for this.
 */
class TodayActionsDismissTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function dismissReason(string $key = 'wrong_number'): void
    {
        DB::table('action_option_lists')->updateOrInsert(
            ['option_type' => 'dismiss_reason', 'key' => $key],
            [
                'label'         => 'Wrong number',
                'requires_notes'=> false,
                'is_active'     => true,
                'sort_order'    => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Dismiss Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function followUp(Patient $patient): FollowUp
    {
        return FollowUp::create([
            'patient_id' => $patient->id,
            'label'      => 'Follow-up call',
            'due_date'   => today()->toDateString(),
            'status'     => 'pending',
            'channel'    => 'call',
            'priority'   => 'medium',
        ]);
    }

    // ── The reported defect ──────────────────────────────────────────────

    public function test_edit_role_without_delete_can_dismiss_a_follow_up_call(): void
    {
        $this->dismissReason();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        // Exactly the CEO's configuration: view + edit, delete OFF.
        $user = $this->userWithModulePerm('relationship', view: true, edit: true, delete: false,
            roleName: 'Evening Desk ' . uniqid());

        $this->actingAs($user)
            ->postJson(route('relationship.today.dismiss'), [
                'category'   => 'follow_up_calls',
                'subject_id' => $fu->id,           // meta.follow_up_id
                'reason_key' => 'wrong_number',
                'patient_id' => $patient->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('today_action_dismissals', [
            'category'   => 'follow_up_calls',
            'subject_id' => $fu->id,
            'reason_key' => 'wrong_number',
        ]);
    }

    public function test_dismiss_does_not_require_the_delete_grant(): void
    {
        // Guard against "fixing" this by relaxing the approved semantics:
        // delete must NOT be what makes an individual dismiss work.
        $this->dismissReason();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $editOnly = $this->userWithModulePerm('relationship', true, true, false);

        $this->actingAs($editOnly)
            ->postJson(route('relationship.today.dismiss'), [
                'category' => 'follow_up_calls', 'subject_id' => $fu->id,
                'reason_key' => 'wrong_number', 'patient_id' => $patient->id,
            ])
            ->assertOk();
    }

    public function test_view_only_role_cannot_dismiss(): void
    {
        $this->dismissReason();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);

        $viewOnly = $this->userWithModulePerm('relationship', true, false, false);

        $this->actingAs($viewOnly)
            ->postJson(route('relationship.today.dismiss'), [
                'category' => 'follow_up_calls', 'subject_id' => $fu->id,
                'reason_key' => 'wrong_number', 'patient_id' => $patient->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('today_action_dismissals', 0);
    }

    public function test_a_dismissed_follow_up_leaves_todays_board_but_stays_pending(): void
    {
        $this->dismissReason();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);
        $user    = $this->userWithModulePerm('relationship', true, true, false);

        $before = app(TodayActionsEngine::class)->generate()['follow_up_calls'];
        $this->assertContains($fu->id, array_column(array_column($before, 'meta'), 'follow_up_id'));

        $this->actingAs($user)->postJson(route('relationship.today.dismiss'), [
            'category' => 'follow_up_calls', 'subject_id' => $fu->id,
            'reason_key' => 'wrong_number', 'patient_id' => $patient->id,
        ])->assertOk();

        $after = app(TodayActionsEngine::class)->generate()['follow_up_calls'];
        $this->assertNotContains($fu->id, array_column(array_column($after, 'meta'), 'follow_up_id'),
            'dismissed card reappeared on today\'s board');

        // Dismiss ≠ complete — the obligation itself survives.
        $this->assertSame('pending', $fu->fresh()->status);
        $this->assertNull($fu->fresh()->completed_at);
    }

    public function test_dismissal_is_auditable(): void
    {
        $this->dismissReason();
        $patient = $this->patient();
        $fu      = $this->followUp($patient);
        $user    = $this->userWithModulePerm('relationship', true, true, false);

        $this->actingAs($user)->postJson(route('relationship.today.dismiss'), [
            'category' => 'follow_up_calls', 'subject_id' => $fu->id,
            'reason_key' => 'wrong_number', 'notes' => 'patient asked us to stop calling',
            'patient_id' => $patient->id,
        ])->assertOk();

        $row = TodayActionDismissal::firstOrFail();

        $this->assertSame('follow_up_calls', $row->category);
        $this->assertSame(FollowUp::class, $row->subject_type);   // what
        $this->assertSame($fu->id, $row->subject_id);             // which record
        $this->assertSame('wrong_number', $row->reason_key);      // why
        $this->assertSame('patient asked us to stop calling', $row->notes);
        $this->assertSame($user->id, $row->dismissed_by);         // who
        $this->assertNotNull($row->created_at);                   // when

        $this->assertDatabaseHas('activities', ['event' => 'today_action.dismissed']);
    }

    // ── The other categories the payload bug silently broke ──────────────

    public function test_every_dismissible_category_accepts_its_canonical_reference(): void
    {
        $this->dismissReason();
        $patient = $this->patient();

        $lead = Lead::create([
            'name' => 'Dismiss Lead', 'phone' => '9' . random_int(100000000, 999999999), 'stage' => 'new_lead',
        ]);

        $queueRow = CommunicationQueue::create([
            'person_name' => 'Queue Person', 'phone' => '9' . random_int(100000000, 999999999),
            'channel' => 'call', 'comm_type' => 'existing_patient', 'direction' => 'inbound',
            'purpose' => 'recall', 'status' => 'pending', 'priority' => 'medium',
            'patient_id' => $patient->id, 'source_engine' => 'manual',
        ]);

        $user = $this->userWithModulePerm('relationship', true, true, false);

        // category => canonical subject id (mirrors subjectIdFor() in the board)
        $cases = [
            'new_enquiries'         => $lead->id,       // Lead-keyed
            'lead_followups'        => $lead->id,       // Lead-keyed
            'birthdays'             => $patient->id,    // Patient-keyed
            'recall_calls'          => $queueRow->id,   // queue-backed
            'logged_communications' => $queueRow->id,   // queue-backed
        ];

        foreach ($cases as $category => $subjectId) {
            $response = $this->actingAs($user)
                ->postJson(route('relationship.today.dismiss'), [
                    'category' => $category, 'subject_id' => $subjectId,
                    'reason_key' => 'wrong_number', 'patient_id' => $patient->id,
                ]);

            $this->assertSame(200, $response->getStatusCode(),
                "category [{$category}] could not be dismissed with its canonical reference: "
                . $response->getContent());
        }
    }

    public function test_a_category_with_no_backing_record_is_refused_not_faked(): void
    {
        $this->dismissReason();

        $user = $this->userWithModulePerm('relationship', true, true, false);

        $this->actingAs($user)
            ->postJson(route('relationship.today.dismiss'), [
                'category' => 'not_a_real_category', 'subject_id' => 1,
                'reason_key' => 'wrong_number',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('today_action_dismissals', 0);
    }
}
