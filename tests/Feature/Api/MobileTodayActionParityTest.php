<?php

namespace Tests\Feature\Api;

use App\Models\ActionOptionList;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-17 (9 Sep 2026) — the phone and the web board must complete an action the
 * SAME way, because until today they did not.
 *
 * Api\V1\RelationshipController::todayLogAction() was a second, shorter
 * implementation: it wrote one Activity row and stopped. It never read
 * closes_task, never called closeUnderlyingRecord(), and did not even accept
 * subject_id — so a staffer logging a resolved outcome on the phone saw the
 * call appear in the web timeline while the action stayed open forever.
 * Sumit reported exactly that: notes show up on the web, nothing reaches
 * Completed.
 *
 * The API now delegates to the web controller. These tests are the tripwire
 * on that: if anyone reimplements the mobile path, the first case fails.
 */
class MobileTodayActionParityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function followUpFor(Patient $patient): FollowUp
    {
        return FollowUp::create([
            'patient_id' => $patient->id,
            'label'      => 'Post-op review',
            'due_date'   => today()->toDateString(),
            'status'     => 'pending',
        ]);
    }

    private function outcome(string $key, bool $closes): ActionOptionList
    {
        return ActionOptionList::create([
            'option_type'     => 'call_outcome',
            'action_category' => 'follow_up_calls',
            'key'             => $key,
            'label'           => ucfirst(str_replace('_', ' ', $key)),
            'closes_task'     => $closes,
            'requires_notes'  => false,
            'is_active'       => true,
        ]);
    }

    public function test_a_resolving_outcome_logged_from_the_phone_actually_completes_the_record(): void
    {
        $user    = $this->staff();
        // No PatientFactory exists in this repo (only UserFactory) — mint the
        // patient the way every other feature test does.
        $patient = Patient::create(['name' => 'Parity Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $follow  = $this->followUpFor($patient);
        $this->outcome('booked_appointment', closes: true);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/relationships/today/action', [
            'category'   => 'follow_up_calls',
            'subject_id' => $follow->id,
            'patient_id' => $patient->id,
            'response'   => 'booked_appointment',
            'direction'  => 'outbound',
        ])->assertOk();

        $this->assertSame(
            'completed',
            $follow->fresh()->status,
            'a closes_task outcome logged from the phone must complete the record, not just log a call'
        );
    }

    public function test_a_failed_attempt_from_the_phone_leaves_the_action_open(): void
    {
        $user    = $this->staff();
        // No PatientFactory exists in this repo (only UserFactory) — mint the
        // patient the way every other feature test does.
        $patient = Patient::create(['name' => 'Parity Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $follow  = $this->followUpFor($patient);
        $this->outcome('no_answer', closes: false);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/relationships/today/action', [
            'category'   => 'follow_up_calls',
            'subject_id' => $follow->id,
            'patient_id' => $patient->id,
            'response'   => 'no_answer',
        ])->assertOk();

        $this->assertSame(
            'pending',
            $follow->fresh()->status,
            '"no answer" is an attempt, not a completion — the row must stay due for another try'
        );
    }

    public function test_the_board_payload_carries_what_the_drawer_needs_to_ask_did_the_call_connect(): void
    {
        $this->outcome('booked_appointment', closes: true);
        $this->outcome('no_answer', closes: false);

        Sanctum::actingAs($this->staff(), ['*']);

        $meta = $this->getJson('/api/v1/relationships/today')
            ->assertOk()
            ->json('meta');

        // Without these two the phone cannot draw the web's form at all: it
        // falls back to one flat list of every outcome, which asks a
        // different question than "did the call connect?".
        $this->assertArrayHasKey('call_results', $meta);
        $this->assertArrayHasKey('closes_task_map', $meta);
        $this->assertArrayHasKey('contact_results', $meta);

        $this->assertSame(
            ['answered', 'no_answer', 'unable_to_connect', 'wrong_number'],
            array_keys($meta['contact_results']),
            'the four contact-result buttons and their order are shared with the web'
        );

        $buckets = $meta['call_results']['follow_up_calls'];
        $this->assertArrayHasKey('booked_appointment', $buckets['answered']);
        $this->assertArrayHasKey('no_answer', $buckets['no_answer']);

        $this->assertTrue($meta['closes_task_map']['follow_up_calls']['booked_appointment']);
        $this->assertFalse($meta['closes_task_map']['follow_up_calls']['no_answer']);
    }
}
