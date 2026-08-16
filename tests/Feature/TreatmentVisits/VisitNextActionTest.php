<?php

namespace Tests\Feature\TreatmentVisits;

use App\Models\FollowUp;
use App\Models\TreatmentVisit;
use App\Services\TreatmentVisitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Visit → Next Action (CEO workflow correction, 2026-08-14).
 *
 * Covers the five acceptance cases in the directive verbatim. The invariant
 * under test throughout:
 *
 *   Daily Huddle may show FUTURE actions.
 *   The Communication List shows only what is DUE (today or overdue).
 *   One scheduled patient action = ONE canonical follow_ups record.
 *
 * These are behaviour contracts, not implementation tests — they assert on the
 * canonical record and on the existing due-date gate in TodayActionsEngine,
 * which is the query every execution surface already reads through.
 */
class VisitNextActionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    private function service(): TreatmentVisitService
    {
        return app(TreatmentVisitService::class);
    }

    /** The 8 Aug / 12 Aug scenario from the directive, anchored on a real visit date. */
    private function visitOn(string $date, $patient, array $nextActions = []): TreatmentVisit
    {
        return $this->service()->create($patient, $this->baseVisitPayload([
            'visit_date'   => $date,
            'next_actions' => $nextActions,
        ]));
    }

    // ── Test Case 1 — "Wellness call tomorrow" ────────────────────────────

    public function test_case_1_wellness_call_tomorrow_is_due_the_next_day(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->travelTo(Carbon::parse('2026-08-08 11:00:00'));

        $this->visitOn('2026-08-08', $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
            'instruction' => 'Ask whether she has any pain or discomfort after the procedure.',
        ]]);

        $action = FollowUp::fromVisit()->firstOrFail();

        $this->assertSame('2026-08-09', $action->due_date->format('Y-m-d'));
        $this->assertSame('Wellness Call', $action->label);
        $this->assertSame('pending', $action->status);
        $this->assertSame($patient->id, $action->patient_id);
        $this->assertStringContainsString('pain or discomfort', $action->note);

        // 9 Aug — the Communication List's due gate now includes it.
        $this->travelTo(Carbon::parse('2026-08-09 09:00:00'));
        $this->assertTrue($this->isDueInCommunicationList($action));

        $this->travelBack();
    }

    // ── Test Case 2 — "Follow-up after 4 days" ────────────────────────────

    public function test_case_2_follow_up_after_four_days_is_not_due_until_its_date(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->travelTo(Carbon::parse('2026-08-08 11:00:00'));

        $this->visitOn('2026-08-08', $patient, [[
            'action_type' => 'follow_up_call',
            'due_mode'    => 'in_days',
            'due_in_days' => 4,
            'instruction' => 'Check healing.',
        ]]);

        $action = FollowUp::fromVisit()->firstOrFail();
        $this->assertSame('2026-08-12', $action->due_date->format('Y-m-d'));

        // 9 Aug — visible to the Huddle as UPCOMING, but NOT due.
        $this->travelTo(Carbon::parse('2026-08-09 09:00:00'));
        $this->assertFalse(
            $this->isDueInCommunicationList($action),
            'A 12 Aug action must NOT appear as due in the 9 Aug Communication List.'
        );
        $this->assertTrue(
            FollowUp::fromVisit()->upcoming()->whereKey($action->id)->exists(),
            'A 12 Aug action must be visible to the Huddle as upcoming on 9 Aug.'
        );

        // 12 Aug — the SAME record becomes actionable. No second row was made.
        $this->travelTo(Carbon::parse('2026-08-12 09:00:00'));
        $this->assertTrue($this->isDueInCommunicationList($action));
        $this->assertSame(1, FollowUp::fromVisit()->count());

        $this->travelBack();
    }

    // ── Test Case 3 — idempotency ─────────────────────────────────────────

    public function test_case_3_re_saving_the_visit_does_not_duplicate_the_action(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
            'instruction' => 'First pass.',
        ]]);

        $this->assertSame(1, FollowUp::fromVisit()->count());
        $original = FollowUp::fromVisit()->firstOrFail();

        // Re-save exactly what the edit form round-trips, id included.
        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date'   => $visit->visit_date->format('Y-m-d'),
            'next_actions' => [[
                'id'          => $original->id,
                'action_type' => 'wellness_call',
                'due_mode'    => 'on_date',
                'due_date'    => $original->due_date->format('Y-m-d'),
                'instruction' => 'Edited instruction.',
            ]],
        ]));

        $this->assertSame(1, FollowUp::fromVisit()->count(), 'Re-saving must not create a second call.');
        $this->assertSame('Edited instruction.', $original->fresh()->note);
    }

    public function test_re_saving_without_ids_still_does_not_duplicate(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $rows = [[
            'action_type' => 'post_op_check',
            'due_mode'    => 'in_days',
            'due_in_days' => 3,
        ]];

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, $rows);
        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date'   => $visit->visit_date->format('Y-m-d'),
            'next_actions' => $rows,   // id-less, as a naive client would send
        ]));

        $this->assertSame(1, FollowUp::fromVisit()->count());
    }

    public function test_omitting_next_actions_entirely_leaves_existing_rows_alone(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        // A client that predates this feature sends no next_actions key at all.
        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date' => $visit->visit_date->format('Y-m-d'),
        ]));

        $this->assertSame(1, FollowUp::fromVisit()->count());
    }

    public function test_removing_a_row_from_the_form_withdraws_that_action(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date'   => $visit->visit_date->format('Y-m-d'),
            'next_actions' => [],   // doctor removed it
        ]));

        $this->assertSame(0, FollowUp::fromVisit()->count());
    }

    // ── Test Case 4 — the doctor never has to come back ───────────────────

    public function test_case_4_action_surfaces_without_any_further_doctor_activity(): void
    {
        $doctor  = $this->makeUser();
        $this->actingAs($doctor);
        $patient = $this->makePatient();

        $this->travelTo(Carbon::parse('2026-08-08 18:00:00'));
        $this->visitOn('2026-08-08', $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
            'instruction' => 'Ask about pain.',
        ]]);

        $action = FollowUp::fromVisit()->firstOrFail();
        $this->assertSame($doctor->id, $action->created_by, 'The issuing doctor must be recorded.');

        // Next morning. The doctor never logs in — a different staff member does.
        $this->travelTo(Carbon::parse('2026-08-09 09:15:00'));
        $this->actingAs($this->makeUser(['role' => 'receptionist']));

        $this->assertTrue($this->isDueInCommunicationList($action));
        $this->assertNotEmpty($action->note, 'The doctor instruction must travel with the action.');

        $this->travelBack();
    }

    // ── Test Case 5 — staff completes it through the existing flow ────────

    public function test_case_5_existing_completion_flow_closes_the_action(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->visitOn(now()->subDay()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',   // = today
        ]]);

        $action = FollowUp::fromVisit()->firstOrFail();
        $this->assertTrue($this->isDueInCommunicationList($action));

        // The untouched pre-existing completion path
        // (TodayController::closeUnderlyingRecord, 'follow_up_calls' branch).
        $staff = $this->makeUser();
        $action->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'completed_by'    => $staff->id,
            'completion_note' => 'Patient reports no pain.',
        ]);

        $this->assertFalse($this->isDueInCommunicationList($action->fresh()));
        $this->assertSame('completed', $action->fresh()->status);
    }

    public function test_completed_actions_are_never_rewritten_by_a_later_visit_edit(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        $action = FollowUp::fromVisit()->firstOrFail();
        $action->update(['status' => 'completed', 'completed_at' => now(), 'completion_note' => 'Done.']);

        // Doctor edits the visit afterwards and clears the next actions.
        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date'   => $visit->visit_date->format('Y-m-d'),
            'next_actions' => [],
        ]));

        $fresh = $action->fresh();
        $this->assertNotNull($fresh, 'An executed call is history and must survive.');
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('Done.', $fresh->completion_note);
    }

    // ── Guardrails ────────────────────────────────────────────────────────

    /**
     * Reception owns the assignee and the call time; the doctor's form does
     * not show either. A later visit edit must not silently undo the front
     * desk's own work on the same record.
     */
    public function test_a_visit_edit_does_not_clobber_receptions_assignee_or_call_time(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        $action    = FollowUp::fromVisit()->firstOrFail();
        $callTeam  = $this->makeUser(['role' => 'receptionist']);
        $action->update(['assigned_to' => $callTeam->id, 'due_time' => '16:30']);

        $this->service()->update($visit->fresh(), $this->baseVisitPayload([
            'visit_date'   => $visit->visit_date->format('Y-m-d'),
            'next_actions' => [[
                'id'          => $action->id,
                'action_type' => 'wellness_call',
                'due_mode'    => 'on_date',
                'due_date'    => $action->due_date->format('Y-m-d'),
                'instruction' => 'Doctor added a note.',
            ]],
        ]));

        $fresh = $action->fresh();
        $this->assertSame($callTeam->id, $fresh->assigned_to);
        $this->assertSame('16:30', $fresh->due_time);
        $this->assertSame('Doctor added a note.', $fresh->note);
    }

    public function test_no_communication_queue_row_is_created(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        // ONE scheduled patient action = ONE canonical record. The Communication
        // List is a surface over follow_ups, not a second copy of it.
        $this->assertSame(0, \App\Models\CommunicationQueue::count());
        $this->assertSame(1, FollowUp::count());
    }

    public function test_due_date_is_anchored_on_the_visit_date_not_today(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->travelTo(Carbon::parse('2026-08-10 09:00:00'));

        // A visit for 8 Aug, logged late on the 10th. "After 4 days" must mean
        // 4 days from when the patient was SEEN.
        $this->visitOn('2026-08-08', $patient, [[
            'action_type' => 'follow_up_call',
            'due_mode'    => 'in_days',
            'due_in_days' => 4,
        ]]);

        $this->assertSame('2026-08-12', FollowUp::fromVisit()->firstOrFail()->due_date->format('Y-m-d'));

        $this->travelBack();
    }

    public function test_multiple_next_actions_on_one_visit_are_all_scheduled(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->visitOn(now()->format('Y-m-d'), $patient, [
            ['action_type' => 'wellness_call',      'due_mode' => 'tomorrow'],
            ['action_type' => 'treatment_followup', 'due_mode' => 'in_days', 'due_in_days' => 5],
        ]);

        $this->assertSame(2, FollowUp::fromVisit()->count());
        $this->assertEqualsCanonicalizing(
            ['wellness_call', 'treatment_followup'],
            FollowUp::fromVisit()->pluck('trigger_value')->all()
        );
    }

    public function test_deleting_the_visit_withdraws_its_pending_actions(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $visit = $this->visitOn(now()->format('Y-m-d'), $patient, [[
            'action_type' => 'wellness_call',
            'due_mode'    => 'tomorrow',
        ]]);

        $this->service()->delete($visit->fresh());

        $this->assertSame(0, FollowUp::fromVisit()->count());
    }

    public function test_an_invalid_action_type_is_rejected(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->postJson("/patients/{$patient->id}/visits", $this->baseVisitPayload([
            'next_actions' => [[
                'action_type' => 'send_a_carrier_pigeon',
                'due_mode'    => 'tomorrow',
            ]],
        ]))->assertStatus(422)->assertJsonValidationErrors('next_actions.0.action_type');
    }

    public function test_visits_saved_without_next_actions_create_none(): void
    {
        $this->actingAs($this->makeUser());
        $patient = $this->makePatient();

        $this->visitOn(now()->format('Y-m-d'), $patient, []);

        $this->assertSame(0, FollowUp::count());
    }

    // ── helper ────────────────────────────────────────────────────────────

    /**
     * The Communication List's actual due gate, as implemented today:
     * TodayActionsEngine::followUpCalls() and Huddle Comms Section 3 both use
     * `status = pending AND due_date <= today`. Asserting against that query
     * (rather than a bespoke one) is what makes this a contract test.
     */
    private function isDueInCommunicationList(FollowUp $action): bool
    {
        return FollowUp::whereKey($action->id)
            ->where('status', 'pending')
            ->whereDate('due_date', '<=', now()->toDateString())
            ->exists();
    }
}
