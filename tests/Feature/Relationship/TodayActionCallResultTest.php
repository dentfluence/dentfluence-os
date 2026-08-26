<?php

namespace Tests\Feature\Relationship;

use App\Models\ActionOptionList;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TODAY'S ACTIONS — two-step call result (drawer redesign, 2026-08-26).
 *
 * The CRM principle these tests protect:
 *
 *     staff record what they DID
 *       -> the patient's response is recorded SEPARATELY
 *          -> the SYSTEM decides the task status.
 *
 * Reception never picks a status. They answer "did it connect?" and, only if
 * it did, "what did the patient say?" — and closes_task on the existing
 * ActionOptionList row does the rest.
 *
 * Nothing here is a new workflow. Every outcome key asserted below already
 * exists in the vocabulary; the drawer only regroups them into the four
 * buckets the domain has drawn since CommunicationQueue::callOutcomeGroups()
 * split Connected from Not Connected for the mobile picker.
 */
class TodayActionCallResultTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function preRole(): \App\Models\Role
    {
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => 'call_result_test_actor'],
            ['name' => 'Call Result Test Actor', 'category' => \App\Models\Role::CATEGORY_STAFF, 'is_system' => false]
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

    private function user(string $name = 'Neha'): User
    {
        return User::factory()->create(['name' => $name, 'branch_id' => 1, 'role_id' => $this->preRole()->id]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Call Result Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

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

    private function outcome(string $category, string $key, string $label, bool $closesTask, bool $requiresNotes = false): void
    {
        ActionOptionList::updateOrCreate(
            ['option_type' => 'call_outcome', 'action_category' => $category, 'key' => $key],
            [
                'label'          => $label,
                'requires_notes' => $requiresNotes,
                'closes_task'    => $closesTask,
                'sort_order'     => 0,
                'is_active'      => true,
            ]
        );
    }

    /** The full appointment-confirmation vocabulary, as the seeder writes it. */
    private function confirmationVocabulary(): void
    {
        $this->outcome('appointment_reminders', 'confirmed_attendance', 'Confirmed attendance', closesTask: true);
        $this->outcome('appointment_reminders', 'patient_called_back_confirmed', 'Patient called back — confirmed', closesTask: true);
        $this->outcome('appointment_reminders', 'asked_reschedule', 'Asked to reschedule', closesTask: true);
        $this->outcome('appointment_reminders', 'cancelled_by_patient', 'Cancelled by patient', closesTask: true, requiresNotes: true);
        $this->outcome('appointment_reminders', 'other', 'Other', closesTask: false, requiresNotes: true);
        $this->outcome('appointment_reminders', 'no_answer', 'No answer', closesTask: false);
        $this->outcome('appointment_reminders', 'busy', 'Could not connect', closesTask: false);
        $this->outcome('appointment_reminders', 'wrong_number', 'Wrong number', closesTask: true);
    }

    private function viewData(User $user, string $key): mixed
    {
        $response = $this->actingAs($user)->get(route('relationship.today'));
        $response->assertOk();

        return $response->viewData($key);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1 — The four buttons are DERIVED, never invented
    // ═══════════════════════════════════════════════════════════════════

    public function test_call_results_bucket_the_existing_vocabulary_into_four_results(): void
    {
        $user = $this->user();
        $this->confirmationVocabulary();

        $buckets = $this->viewData($user, 'callResults')['appointment_reminders'];

        // "Answered" holds the PATIENT RESPONSE choices — and only those.
        $this->assertArrayHasKey('confirmed_attendance', $buckets['answered']);
        $this->assertArrayHasKey('asked_reschedule', $buckets['answered']);
        $this->assertArrayHasKey('cancelled_by_patient', $buckets['answered']);
        $this->assertArrayHasKey('other', $buckets['answered']);

        // A call that never connected is NOT a patient response.
        $this->assertArrayNotHasKey('no_answer', $buckets['answered']);
        $this->assertArrayNotHasKey('busy', $buckets['answered']);
        $this->assertArrayNotHasKey('wrong_number', $buckets['answered']);

        $this->assertSame(['no_answer'],    array_keys($buckets['no_answer']));
        $this->assertSame(['busy'],         array_keys($buckets['unable_to_connect']));
        $this->assertSame(['wrong_number'], array_keys($buckets['wrong_number']));
    }

    /**
     * A clinic that switches an outcome off in Settings must lose the button,
     * not gain a silent outcome with no closes_task rule behind it.
     */
    public function test_a_result_button_disappears_when_its_only_outcome_is_deactivated(): void
    {
        $user = $this->user();
        $this->confirmationVocabulary();

        ActionOptionList::where('action_category', 'appointment_reminders')
            ->where('key', 'wrong_number')
            ->update(['is_active' => false]);

        $buckets = $this->viewData($user, 'callResults')['appointment_reminders'];

        $this->assertSame([], $buckets['wrong_number']);
    }

    public function test_closes_task_map_is_published_so_the_drawer_can_state_the_consequence(): void
    {
        $user = $this->user();
        $this->confirmationVocabulary();

        $map = $this->viewData($user, 'closesTaskMap')['appointment_reminders'];

        $this->assertTrue($map['confirmed_attendance']);
        $this->assertFalse($map['no_answer']);
        $this->assertFalse($map['other'], 'Other means the objective was not achieved — the action stays due.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2 — Task status is DERIVED from the result, never chosen by staff
    // ═══════════════════════════════════════════════════════════════════

    public function test_no_answer_records_an_attempt_and_keeps_the_action_due(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'no_answer',
                'direction'  => 'outbound',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => false]);

        $this->assertSame(0, TodayActionDismissal::count());

        $logged = Activity::where('event', 'call.logged')->sole();
        $this->assertSame('no_answer', $logged->metadata['response']);
        $this->assertSame('outbound',  $logged->metadata['direction']);
    }

    public function test_answered_and_confirmed_completes_the_action(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'confirmed_attendance',
                'direction'  => 'outbound',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => true]);

        $this->assertSame(1, TodayActionDismissal::count());
    }

    /**
     * "Other" is the honest escape hatch: something happened, it is written
     * down, and the objective still is not met — so the action stays due.
     */
    public function test_other_requires_a_note_and_leaves_the_action_due(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'other',
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'other',
                'notes'      => 'Husband answered, patient at work, will call at 6.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => false]);

        $this->assertSame(0, TodayActionDismissal::count());
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3 — THE CALLBACK. The original attempt is never overwritten.
    // ═══════════════════════════════════════════════════════════════════

    public function test_inbound_confirmation_resolves_the_action_and_preserves_the_first_attempt(): void
    {
        $neha      = $this->user('Neha');
        $reception = $this->user('Reception');
        $patient   = $this->patient();
        $appt      = $this->appointmentToday($patient, $neha);
        $this->confirmationVocabulary();

        // 10:14 — Neha calls out. Nobody answers.
        $this->actingAs($neha)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'no_answer',
                'direction'  => 'outbound',
            ])
            ->assertOk()
            ->assertJson(['closed' => false]);

        // 11:02 — the patient rings back. Reception opens the SAME action,
        // flips the direction toggle and records what the patient said.
        $this->actingAs($reception)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'confirmed_attendance',
                'direction'  => 'inbound',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => true]);

        $logged = Activity::where('event', 'call.logged')->orderBy('id')->get();

        // TWO interactions. The first is still exactly what Neha recorded.
        $this->assertCount(2, $logged, 'The callback must ADD an interaction, never replace the attempt.');

        $this->assertSame('no_answer', $logged[0]->metadata['response']);
        $this->assertSame('outbound',  $logged[0]->metadata['direction']);
        $this->assertSame($neha->id,   $logged[0]->actor_id);

        // The second is recorded as an inbound call, under its own outcome key
        // so the audit reads differently from an ordinary outbound confirmation.
        $this->assertSame('patient_called_back_confirmed', $logged[1]->metadata['response']);
        $this->assertSame('inbound',                       $logged[1]->metadata['direction']);
        $this->assertSame($reception->id,                  $logged[1]->actor_id);

        // And the ORIGINAL action is now complete.
        $this->assertSame(1, TodayActionDismissal::count());
    }

    /**
     * The inbound remap is scoped: it exists so the audit trail can tell a
     * callback apart from an outbound confirmation. It must not fire for
     * other categories or other responses.
     */
    public function test_inbound_remap_only_applies_to_a_confirmed_appointment_reminder(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'asked_reschedule',
                'direction'  => 'inbound',
            ])
            ->assertOk();

        $logged = Activity::where('event', 'call.logged')->sole();
        $this->assertSame('asked_reschedule', $logged->metadata['response']);
        $this->assertSame('inbound',          $logged->metadata['direction']);
    }

    /**
     * Older clients (mobile, a cached page) send no direction at all. The
     * field is optional and defaults to outbound — nothing may break.
     */
    public function test_direction_is_optional_and_defaults_to_outbound(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();

        $this->actingAs($user)
            ->postJson(route('relationship.today.action'), [
                'category'   => 'appointment_reminders',
                'patient_id' => $patient->id,
                'subject_id' => $appt->id,
                'response'   => 'confirmed_attendance',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'closed' => true]);

        $logged = Activity::where('event', 'call.logged')->sole();
        $this->assertSame('confirmed_attendance', $logged->metadata['response']);
        $this->assertSame('outbound',             $logged->metadata['direction']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4 — OWNER AUDIT: the whole story of one action, in order
    // ═══════════════════════════════════════════════════════════════════

    public function test_interaction_history_returns_both_calls_oldest_first_with_who_and_what(): void
    {
        $neha      = $this->user('Neha');
        $reception = $this->user('Reception');
        $patient   = $this->patient();
        $appt      = $this->appointmentToday($patient, $neha);
        $this->confirmationVocabulary();

        $this->actingAs($neha)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'no_answer',
            'direction'  => 'outbound',
        ])->assertOk();

        $this->actingAs($reception)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'confirmed_attendance',
            'direction'  => 'inbound',
        ])->assertOk();

        $history = $this->actingAs($reception)
            ->getJson(route('relationship.today.notes.index') . '?' . http_build_query([
                'patient_id' => $patient->id,
                'category'   => 'appointment_reminders',
            ]))
            ->assertOk()
            ->json('interactions');

        $this->assertCount(2, $history);

        // Oldest first — the attempt an owner is checking for stays on top.
        $this->assertSame('outbound',  $history[0]['direction']);
        $this->assertSame('No answer', $history[0]['label']);
        $this->assertSame('Neha',      $history[0]['actor']);

        $this->assertSame('inbound',                       $history[1]['direction']);
        $this->assertSame('Patient called back — confirmed', $history[1]['label']);
        $this->assertSame('Reception',                     $history[1]['actor']);
    }

    /** History is scoped to the action being viewed, not the whole patient. */
    public function test_interaction_history_is_scoped_to_the_category(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentToday($patient, $user);
        $this->confirmationVocabulary();
        $this->outcome('payment_reminders', 'no_answer', 'No answer', closesTask: false);

        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'no_answer',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'payment_reminders',
            'patient_id' => $patient->id,
            'response'   => 'no_answer',
        ])->assertOk();

        $history = $this->actingAs($user)
            ->getJson(route('relationship.today.notes.index') . '?' . http_build_query([
                'patient_id' => $patient->id,
                'category'   => 'appointment_reminders',
            ]))
            ->assertOk()
            ->json('interactions');

        $this->assertCount(1, $history);
    }
}
