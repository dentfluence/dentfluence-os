<?php

namespace Tests\Feature\Relationship;

use App\Models\ActionOptionList;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\CommActivityLog;
use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Appointment-confirmation callback (2026-08-25) — live workflow defect.
 *
 * Reception calls a patient to confirm today's appointment. Nobody answers.
 * The patient rings back later. Before this fix that callback was recorded
 * through a completely different door (Communication > Add), which:
 *   - never emitted an ActivityEngine event, so nothing downstream could
 *     react (ActivityEngine is the bus RulesEngine listens on);
 *   - had no way to resolve the original confirmation action, which stayed
 *     open forever;
 *   - created a NEW pending communication_queue row that surfaced as a
 *     second, unrelated "Other Calls" action for the same patient.
 *
 * Three slices, all built on existing infrastructure:
 *   1. logStore() emits 'call.inbound' (inert — no rule declares it a
 *      trigger — but the fact is now on the bus).
 *   2. A new appointment_reminders outcome, 'patient_called_back_confirmed',
 *      closes the original action through the existing closes_task ->
 *      closeUnderlyingRecord() -> TodayActionDismissal path.
 *   3. move_to = archive (already canonical) keeps the inbound row off the
 *      board. Proven here, not newly built.
 *
 * NOT fixed here, deliberately (would change lead-creation business rules):
 * see test_characterization_existing_patient_callback_still_creates_a_lead.
 */
class AppointmentConfirmationCallbackTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function preRole(): \App\Models\Role
    {
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => 'callback_test_actor'],
            ['name' => 'Callback Test Actor', 'category' => \App\Models\Role::CATEGORY_STAFF, 'is_system' => false]
        );

        foreach (['relationship', 'patients', 'communication', 'tasks', 'daily_huddle'] as $slug) {
            $module = \App\Models\Module::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'section' => 'clinical', 'sort_order' => 90]
            );
            \App\Models\RoleModulePermission::updateOrCreate(
                ['role_id' => $role->id, 'module_id' => $module->id],
                ['can_view' => true, 'can_edit' => true, 'can_delete' => true]
            );
        }

        return $role;
    }

    private function user(): User
    {
        return User::factory()->create(['branch_id' => 1, 'role_id' => $this->preRole()->id]);
    }

    private function patient(string $name = 'Callback Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    /** An appointment for TODAY — the confirmation-call fixture. */
    private function appointmentToday(Patient $patient, User $doctor): Appointment
    {
        return Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);
    }

    /** Seeds one call-outcome row — the same shape ActionOptionListSeeder writes. */
    private function outcome(string $category, string $key, string $label, bool $closesTask): void
    {
        ActionOptionList::updateOrCreate(
            ['option_type' => 'call_outcome', 'action_category' => $category, 'key' => $key],
            [
                'label'          => $label,
                'requires_notes' => false,
                'closes_task'    => $closesTask,
                'sort_order'     => 0,
                'is_active'      => true,
            ]
        );
    }

    /** The board's groups array, exactly as the page renders it. */
    private function board(User $user): array
    {
        $response = $this->actingAs($user)->get(route('relationship.today'));
        $response->assertOk();

        return $response->viewData('groups');
    }

    /**
     * The board exactly as TodayController::index() builds it — the same
     * method the route dispatches to, without the HTTP layer. See the note
     * in the confirmed-callback test for why this matters.
     */
    private function controllerBoard(User $user): array
    {
        $this->actingAs($user);

        return app(\App\Http\Controllers\Relationship\TodayController::class)
            ->index(\Illuminate\Http\Request::create('/relationship/today', 'GET'))
            ->getData()['groups'];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1 — EXISTING BEHAVIOUR: an unanswered confirmation call stays open
    // ═══════════════════════════════════════════════════════════════════

    public function test_no_answer_leaves_the_confirmation_action_open_and_records_the_attempt(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);

        $this->outcome('appointment_reminders', 'no_answer', 'No answer', closesTask: false);

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'no_answer',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => false]);

        // A retry outcome must NOT suppress the row.
        $this->assertSame(0, TodayActionDismissal::count(), 'No answer must not dismiss the action.');

        // The attempt is recorded once, against the patient, under this category.
        $logged = Activity::where('event', 'call.logged')->get();
        $this->assertCount(1, $logged);
        $this->assertSame('appointment_reminders', $logged->first()->metadata['category']);
        $this->assertSame('no_answer', $logged->first()->metadata['response']);

        // The action is still on the board, now stamped with the attempt.
        $groups = $this->board($user);
        $this->assertSame(1, $groups['appointment_reminders_today']['count']);

        $item = $groups['appointment_reminders_today']['items'][0];
        $this->assertSame('no_answer', $item['last_call']['outcome'] ?? null);
        $this->assertSame('No answer', $item['last_call']['label'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2 — SLICE 1: an inbound call reaches the domain event bus
    // ═══════════════════════════════════════════════════════════════════

    public function test_inbound_call_for_a_patient_emits_exactly_one_call_inbound_activity(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('communication.manager.log.store'), [
                'phone'       => $patient->phone,
                'person_name' => $patient->name,
                'comm_type'   => 'ongoing_treatment',
                'patient_id'  => $patient->id,
                'channel'     => 'call',
                'direction'   => 'incoming',
                'purpose'     => 'appointment',
                'note'        => 'Patient returned our confirmation call.',
                'priority'    => 'medium',
                'move_to'     => 'stay',
            ])
            ->assertRedirect();

        $comm = CommunicationQueue::firstOrFail();

        $inbound = Activity::where('event', 'call.inbound')->get();
        $this->assertCount(1, $inbound, 'Exactly one call.inbound Activity is expected.');

        $activity = $inbound->first();
        $this->assertSame(Patient::class, $activity->subject_type);
        $this->assertSame($patient->id, $activity->subject_id);
        $this->assertSame($user->id, $activity->actor_id);
        $this->assertSame($comm->id, $activity->metadata['comm_queue_id']);
        $this->assertSame('incoming', $activity->metadata['direction']);
        $this->assertSame('appointment', $activity->metadata['purpose']);

        // The pre-existing per-row audit trail is untouched, and not doubled.
        $this->assertSame(
            1,
            CommActivityLog::where('comm_id', $comm->id)->where('action', 'created')->count(),
            'The original CommActivityLog "created" row must still be written exactly once.'
        );

        // Slice 1 must not bleed into the outgoing-call vocabulary.
        $this->assertSame(0, Activity::where('event', 'call.logged')->count());
    }

    public function test_outgoing_call_does_not_emit_call_inbound(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('communication.manager.log.store'), [
                'phone'       => $patient->phone,
                'person_name' => $patient->name,
                'comm_type'   => 'ongoing_treatment',
                'patient_id'  => $patient->id,
                'channel'     => 'call',
                'direction'   => 'outgoing',
                'priority'    => 'medium',
                'move_to'     => 'stay',
            ])
            ->assertRedirect();

        $this->assertSame(0, Activity::where('event', 'call.inbound')->count());
        $this->assertSame(1, CommActivityLog::where('action', 'created')->count());
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3 — SLICE 2: the callback resolves the ORIGINAL action
    // ═══════════════════════════════════════════════════════════════════

    public function test_patient_called_back_confirmed_dismisses_the_appointment_action(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);

        $this->outcome('appointment_reminders', 'no_answer', 'No answer', closesTask: false);
        $this->outcome('appointment_reminders', 'patient_called_back_confirmed', 'Patient called back — confirmed', closesTask: true);

        // Attempt 1 — nobody answers. Row stays.
        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'no_answer',
        ])->assertOk();

        $before = $this->controllerBoard($user);
        $this->assertSame(1, $before['appointment_reminders_today']['count']);

        // The patient rings back — reception resolves THIS row.
        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'patient_called_back_confirmed',
                'notes'      => 'Confirmed 10:00 slot.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => true]);

        // Audited suppression against the appointment itself.
        $dismissal = TodayActionDismissal::where('category', 'appointment_reminders')->first();
        $this->assertNotNull($dismissal, 'A TodayActionDismissal must be created.');
        $this->assertSame(Appointment::class, $dismissal->subject_type);
        $this->assertSame($appt->id, $dismissal->subject_id);
        $this->assertSame(now()->toDateString(), $dismissal->dismissed_for_date->toDateString());
        $this->assertSame('patient_called_back_confirmed', $dismissal->reason_key);
        $this->assertSame($user->id, $dismissal->dismissed_by);
        $this->assertSame('Confirmed 10:00 slot.', $dismissal->notes);

        // ── The row leaves reception's OPEN work.
        // The engine draws a deliberate line (annotateDone / isTrueDismissal,
        // 2026-07-14): a dismissal written by a DISMISS REASON hides the row,
        // while one written by a CALL OUTCOME means "handled" — the row stays
        // visible but faded with its outcome and stops counting as work to do.
        // So "gone from the board" = count 0 / done_count 1.
        //
        // Asserted through TodayController::index() directly, NOT $this->get().
        // A repeated in-test HTTP GET of this route returns a board whose
        // engine never ran (verified with a container-bound spy: generate()
        // was not called once), so the annotation is absent there. That is a
        // test-harness defect, not product behaviour: browser-verified on
        // 2026-08-25 against dentfluence.test, the real page renders this row
        // faded/Done and drops the open count from 5 to 4. Do NOT "fix" a
        // failure here by relaxing these assertions.
        $after = $this->controllerBoard($user);
        $group = $after['appointment_reminders_today'];

        $this->assertSame(0, $group['count'], 'Confirmed action must leave open work.');
        $this->assertSame(1, $group['done_count'], 'Confirmed action must render as done.');
        $this->assertSame('patient_called_back_confirmed', $group['items'][0]['done']['outcome']);

        // The appointment record itself is untouched — no invented status.
        $this->assertSame('scheduled', $appt->fresh()->status);

        // ── Requirement 5: the category SET is unchanged. Only the intended
        // count moved. A dismissal must never add or drop a category.
        $this->assertSame(
            array_keys($before),
            array_keys($after),
            'Resolving one action must not change the board category set.'
        );

        foreach ($before as $key => $group) {
            if ($key === 'appointment_reminders_today') {
                continue;
            }
            $this->assertSame(
                $group['count'],
                $after[$key]['count'],
                "Category {$key} count changed unexpectedly."
            );
        }

        // The appointment record itself is untouched — no status invention.
        $this->assertSame('scheduled', $appt->fresh()->status);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4 — SLICE 3: no duplicate pending action from the callback
    // ═══════════════════════════════════════════════════════════════════

    public function test_archived_inbound_callback_creates_no_open_other_calls_action(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $this->actingAs($user)
            ->post(route('communication.manager.log.store'), [
                'phone'       => $patient->phone,
                'person_name' => $patient->name,
                'comm_type'   => 'ongoing_treatment',
                'patient_id'  => $patient->id,
                'channel'     => 'call',
                'direction'   => 'incoming',
                'purpose'     => 'appointment',
                'note'        => 'Called back to confirm — handled on the call.',
                'priority'    => 'medium',
                'move_to'     => 'archive',
            ])
            ->assertRedirect();

        // Archive is the existing canonical "already handled" mechanism.
        $this->assertSame('closed', CommunicationQueue::firstOrFail()->status);

        // Nothing new to work: no OPEN generic action for this patient.
        $groups = $this->board($user);
        $this->assertSame(
            0,
            $groups['logged_communications']['count'],
            'An archived inbound callback must not become a new pending action.'
        );

        // The event still fired — the fact is recorded even though the row is closed.
        $this->assertSame(1, Activity::where('event', 'call.inbound')->count());
    }

    /**
     * A non-archived inbound callback DOES still become an open action.
     * This is the current, unchanged behaviour and the reason move_to matters
     * — pinned so the distinction stays deliberate.
     */
    public function test_inbound_callback_left_in_the_list_still_becomes_an_open_action(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $this->actingAs($user)->post(route('communication.manager.log.store'), [
            'phone'       => $patient->phone,
            'person_name' => $patient->name,
            'comm_type'   => 'ongoing_treatment',
            'patient_id'  => $patient->id,
            'channel'     => 'call',
            'direction'   => 'incoming',
            'purpose'     => 'appointment',
            'priority'    => 'medium',
            'move_to'     => 'stay',
        ]);

        $groups = $this->board($user);
        $this->assertSame(1, $groups['logged_communications']['count']);
    }

    /**
     * CHARACTERIZATION — not fixed by this change, by instruction.
     *
     * Logging the callback with comm_type = 'existing_patient' still calls
     * createLeadFromComm(), which runs before and independently of the
     * archive branch, so a Lead(stage = new_lead) appears as a New Enquiry
     * for 24h. Suppressing it would change lead-creation business rules.
     * Operational workaround: log patient callbacks as 'ongoing_treatment'
     * or 'other'. Long-term fix belongs with the V1.1 linkage work.
     */
    public function test_characterization_existing_patient_callback_still_creates_a_lead(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $this->actingAs($user)->post(route('communication.manager.log.store'), [
            'phone'       => $patient->phone,
            'person_name' => $patient->name,
            'comm_type'   => 'existing_patient',
            'patient_id'  => $patient->id,
            'channel'     => 'call',
            'direction'   => 'incoming',
            'priority'    => 'medium',
            'move_to'     => 'archive',
        ]);

        $this->assertSame(
            1,
            \App\Models\Lead::where('phone', $patient->phone)->where('stage', 'new_lead')->count(),
            'Documented current behaviour — see the docblock. Change this only with an approved rule change.'
        );
    }

}
