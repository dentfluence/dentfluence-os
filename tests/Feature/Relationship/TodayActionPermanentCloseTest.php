<?php

namespace Tests\Feature\Relationship;

use App\Models\ActionOptionList;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * W-10 (2026-09-10) — a close from the Today's Actions board must STICK.
 *
 * Measured on the clinic's own data: lab case #5 was closed "booked pickup"
 * on 25 Aug, 26 Aug and 9 Sep. today_action_dismissals was date-scoped by
 * design ("not today"), so "Stop chasing", "Not needed" and every
 * closes_task outcome hid a row for exactly one day on the ten
 * live-computed categories, and the engine found the same record again
 * the next morning.
 *
 * The rule now: a close is permanent for that occurrence. It lifts only
 * when the date that drives the row moves (reschedule, new follow-up date),
 * because then it IS a new occurrence.
 */
class TodayActionPermanentCloseTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function user(): User
    {
        return $this->userWithModulePerm('relationship', true, true, false);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Permanent Close Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function opportunityDueToday(Patient $patient): TreatmentOpportunity
    {
        return TreatmentOpportunity::create([
            'patient_id'     => $patient->id,
            'type'           => 'other',
            'label'          => 'Implant discussion',
            'status'         => 'prospect',
            'follow_up_date' => today()->toDateString(),
        ]);
    }

    private function appointmentOn(Patient $patient, User $doctor, string $date): Appointment
    {
        return Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'branch_id'        => 1,
            'created_by'       => $doctor->id,
            'appointment_date' => $date,
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);
    }

    private function dismissReason(string $key = 'already_handled'): void
    {
        DB::table('action_option_lists')->updateOrInsert(
            ['option_type' => 'dismiss_reason', 'key' => $key],
            [
                'label'          => 'Already handled elsewhere / not needed',
                'requires_notes' => false,
                'is_active'      => true,
                'sort_order'     => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
    }

    private function confirmedOutcome(): void
    {
        ActionOptionList::updateOrCreate(
            ['option_type' => 'call_outcome', 'action_category' => 'appointment_reminders', 'key' => 'confirmed_attendance'],
            ['label' => 'Confirmed attendance', 'requires_notes' => false, 'closes_task' => true, 'sort_order' => 0, 'is_active' => true]
        );
    }

    /** Subject ids the engine lists for a category (meta.id), in the given mode. */
    private function idsOnBoard(string $category, bool $includeDone = false): array
    {
        $rows = app(TodayActionsEngine::class)->generate(includeDone: $includeDone)[$category] ?? [];

        return array_map(fn (array $row) => $row['meta']['id'] ?? null, $rows);
    }

    // ── Stop chasing ─────────────────────────────────────────────────────

    public function test_stop_chasing_keeps_the_row_off_the_board_tomorrow(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $opp     = $this->opportunityDueToday($patient);

        $this->assertContains($opp->id, $this->idsOnBoard('opportunities'));

        $this->actingAs($user)->postJson(route('relationship.today.close'), [
            'category'   => 'opportunities',
            'subject_id' => $opp->id,
            'patient_id' => $patient->id,
            'notes'      => 'Tried four times',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'), 'still listed the same day');

        $this->travel(1)->days();
        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'), 'came back the next morning — the reported bug');

        $this->travel(6)->days();
        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'), 'came back a week later');
        $this->travelBack();

        $row = TodayActionDismissal::where('subject_id', $opp->id)->firstOrFail();
        $this->assertTrue($row->is_permanent);
        $this->assertSame('closed_manually', $row->reason_key);
        $this->assertSame(today()->toDateString(), $row->dismissed_for_date->toDateString(), 'the handled date is still recorded');
    }

    // ── Not needed ───────────────────────────────────────────────────────

    public function test_not_needed_is_permanent_too(): void
    {
        $this->dismissReason();
        $user    = $this->user();
        $patient = $this->patient();
        $opp     = $this->opportunityDueToday($patient);

        $this->actingAs($user)->postJson(route('relationship.today.dismiss'), [
            'category'   => 'opportunities',
            'subject_id' => $opp->id,
            'reason_key' => 'already_handled',
            'patient_id' => $patient->id,
        ])->assertOk();

        $this->travel(1)->days();
        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'));
        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities', includeDone: true), 'a true dismiss must hide, never render as done');
        $this->travelBack();

        // Dismiss ≠ complete — the opportunity itself is untouched.
        $this->assertSame('prospect', $opp->fresh()->status);
    }

    // ── A resolved call outcome ──────────────────────────────────────────

    public function test_confirming_tomorrows_appointment_today_does_not_relist_it_tomorrow(): void
    {
        $this->confirmedOutcome();
        $user     = $this->user();
        $patient  = $this->patient();
        $tomorrow = today()->addDay()->toDateString();
        $appt     = $this->appointmentOn($patient, $user, $tomorrow);

        $this->assertContains($appt->id, $this->idsOnBoard('appointment_reminders'), 'tomorrow-morning bucket');

        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'confirmed_attendance',
        ])->assertOk()->assertJson(['success' => true, 'closed' => true]);

        // Today: gone from the working list, still visible as done on the board.
        $this->assertNotContains($appt->id, $this->idsOnBoard('appointment_reminders'));
        $board = collect(app(TodayActionsEngine::class)->generate(includeDone: true)['appointment_reminders'])
            ->firstWhere('meta.id', $appt->id);
        $this->assertNotNull($board, 'handled today must still render, faded');
        $this->assertSame('confirmed_attendance', $board['done']['outcome'] ?? null);

        // Tomorrow, the day of the appointment: it must NOT ask for a second confirmation call.
        $this->travel(1)->days();
        $this->assertNotContains($appt->id, $this->idsOnBoard('appointment_reminders'));
        $this->assertNotContains($appt->id, $this->idsOnBoard('appointment_reminders', includeDone: true), 'yesterday\'s done row must not linger on the board');
        $this->travelBack();
    }

    // ── The lift ─────────────────────────────────────────────────────────

    public function test_moving_the_appointment_brings_the_confirmation_call_back(): void
    {
        $this->confirmedOutcome();
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentOn($patient, $user, today()->addDay()->toDateString());

        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'confirmed_attendance',
        ])->assertOk();

        $this->assertTrue(TodayActionDismissal::where('subject_id', $appt->id)->firstOrFail()->is_permanent);

        // Reception moves the slot to next week — a new occurrence.
        $appt->update(['appointment_date' => today()->addDays(7)->toDateString()]);

        $this->assertFalse(TodayActionDismissal::where('subject_id', $appt->id)->firstOrFail()->is_permanent, 'the lift');

        $this->travel(6)->days();
        $this->assertContains($appt->id, $this->idsOnBoard('appointment_reminders'), 'the rescheduled appointment needs its own confirmation call');
        $this->travelBack();
    }

    public function test_changing_the_time_alone_is_not_a_new_occurrence(): void
    {
        $this->confirmedOutcome();
        $user    = $this->user();
        $patient = $this->patient();
        $appt    = $this->appointmentOn($patient, $user, today()->addDay()->toDateString());

        $this->actingAs($user)->postJson(route('relationship.today.action'), [
            'category'   => 'appointment_reminders',
            'patient_id' => $patient->id,
            'subject_id' => $appt->id,
            'response'   => 'confirmed_attendance',
        ])->assertOk();

        $appt->update(['appointment_time' => '11:30']);

        $this->assertTrue(TodayActionDismissal::where('subject_id', $appt->id)->firstOrFail()->is_permanent);
    }

    public function test_a_new_follow_up_date_on_an_opportunity_lifts_stop_chasing(): void
    {
        $user    = $this->user();
        $patient = $this->patient();
        $opp     = $this->opportunityDueToday($patient);

        $this->actingAs($user)->postJson(route('relationship.today.close'), [
            'category'   => 'opportunities',
            'subject_id' => $opp->id,
            'patient_id' => $patient->id,
        ])->assertOk();

        $this->travel(1)->days();
        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'));

        // The doctor picks it up again with a fresh date.
        $opp->fresh()->update(['follow_up_date' => today()->addDays(2)->toDateString()]);

        $this->travel(2)->days();
        $this->assertContains($opp->id, $this->idsOnBoard('opportunities'));
        $this->travelBack();
    }

    // ── The other lifetime is untouched ──────────────────────────────────

    public function test_a_one_day_row_still_expires(): void
    {
        $patient = $this->patient();
        $opp     = $this->opportunityDueToday($patient);

        TodayActionDismissal::create([
            'category'           => 'opportunities',
            'subject_type'       => TreatmentOpportunity::class,
            'subject_id'         => $opp->id,
            'dismissed_for_date' => today()->toDateString(),
            'is_permanent'       => false,
            'reason_key'         => 'whatsapp_sent',
        ]);

        $this->assertNotContains($opp->id, $this->idsOnBoard('opportunities'));

        $this->travel(1)->days();
        $this->assertContains($opp->id, $this->idsOnBoard('opportunities'), 'a non-permanent row is "not today" and nothing more');
        $this->travelBack();
    }
}
