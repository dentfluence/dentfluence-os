<?php

namespace Tests\Feature\Relationship;

use App\Models\ActionOptionList;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\CommunicationQueue;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\TodayActionDismissal;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Http\Controllers\Relationship\TodayController;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\Relationship\TodayCallList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * W-10 finish (2026-09-11, Sumit) — ONE PATIENT, ONE ROW, ONE CALL.
 *
 * "Sumit Firke has an appointment today, lab work ready and a pending
 * estimate — he showed three times. Show him once, with everything the call
 * is for." The engine still produces one item per reason; the board folds
 * them into patient rows, carries the patient's overdue calls onto today's
 * row, and one logged outcome closes every reason on it.
 */
class OnePatientOneRowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function user(): User
    {
        return $this->userWithModulePerm('relationship', true, true, false);
    }

    private function patient(string $name = 'One Row Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
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
            'appointment_date' => today()->toDateString(),
            'appointment_time' => '10:00',
            'type'             => 'consultation',
            'status'           => 'scheduled',
        ]);
    }

    private function overdueEstimate(Patient $patient): TreatmentOpportunity
    {
        return TreatmentOpportunity::create([
            'patient_id'     => $patient->id,
            'type'           => 'other',
            'label'          => 'Implant estimate',
            'status'         => 'quoted',
            'follow_up_date' => today()->subDays(5)->toDateString(),
        ]);
    }

    private function followUpToday(Patient $patient, User $by): FollowUp
    {
        return FollowUp::create([
            'patient_id' => $patient->id,
            'label'      => 'Post-op check call',
            'due_date'   => today()->toDateString(),
            'channel'    => 'call',
            'priority'   => 'medium',
            'status'     => 'pending',
            'created_by' => $by->id,
        ]);
    }

    private function recallDueYesterday(Patient $patient): CommunicationQueue
    {
        return CommunicationQueue::create([
            'patient_id'     => $patient->id,
            'person_name'    => $patient->name,
            'phone'          => $patient->phone,
            'channel'        => 'call',
            'comm_type'      => 'existing_patient',
            'purpose'        => 'recall_no_visit',
            'direction'      => 'outbound',
            'status'         => 'pending',
            'priority'       => 'medium',
            'follow_up_date' => today()->subDay()->toDateString(),
            'source_engine'  => 'recall_engine',
        ]);
    }

    private function outcome(string $category, string $key, string $label, bool $closes): void
    {
        ActionOptionList::updateOrCreate(
            ['option_type' => 'call_outcome', 'action_category' => $category, 'key' => $key],
            ['label' => $label, 'requires_notes' => false, 'closes_task' => $closes, 'sort_order' => 0, 'is_active' => true]
        );
    }

    /** The Today board's patient rows, keyed by patient id. */
    private function rowsFor(User $user, string $route = 'relationship.today'): array
    {
        // Surface the real exception (with its trace) instead of a bare 500,
        // and report a redirect/status plainly (TestResponse's own failure
        // message builder throws "all() on array" here, hiding the cause).
        $this->withoutExceptionHandling();
        $response = $this->actingAs($user)->get(route($route));
        $this->assertSame(200, $response->status(),
            "GET {$route} returned {$response->status()}"
            . ($response->headers->get('Location') ? ' -> ' . $response->headers->get('Location') : ''));

        $rows = [];
        foreach ($response->viewData('rows') as $row) {
            $rows[$row['patient_id'] ?? $row['id']] = $row;
        }

        return $rows;
    }

    // ═══════════════════════════════════════════════════════════════════
    // 1 — three reasons, one row
    // ═══════════════════════════════════════════════════════════════════

    public function test_three_reasons_for_one_patient_are_one_row(): void
    {
        $user    = $this->user();
        $patient = $this->patient();

        $appt = $this->appointmentToday($patient, $user);   // due today
        $fu   = $this->followUpToday($patient, $user);      // due today
        $opp  = $this->overdueEstimate($patient);           // overdue → carried

        $rows = $this->rowsFor($user);

        $this->assertArrayHasKey($patient->id, $rows, 'the patient is on the board');
        $row = $rows[$patient->id];

        $this->assertSame(3, $row['reasonCount'], 'three reasons, one row');
        $this->assertSame('appointment_reminders', $row['primary']['cat'], "today's confirmation leads the row");
        $this->assertSame($appt->id, $row['primary']['item']['meta']['id']);

        $chipCats = array_column($row['chips'], 'cat');
        $this->assertContains('follow_up_calls', $chipCats);
        $this->assertContains('pending_estimates', $chipCats, 'the overdue estimate rides on today\'s row');

        $overdueChip = collect($row['chips'])->firstWhere('cat', 'pending_estimates');
        $this->assertTrue($overdueChip['over'], 'an overdue reason is marked as such');

        // Only ONE row on the whole board for this patient.
        $this->assertCount(1, array_filter(
            $this->actingAs($user)->get(route('relationship.today'))->viewData('rows'),
            fn ($r) => ($r['patient_id'] ?? null) === $patient->id
        ));

        // Tabs count patients: the estimate tab says 1, not "1 of 3 rows".
        $tabCounts = $this->actingAs($user)->get(route('relationship.today'))->viewData('tabCounts');
        $this->assertSame(1, $tabCounts['pending_estimates']);
        $this->assertSame(1, $tabCounts['appointment_reminders_today']);

        // The patient is being called today, so they are NOT pending.
        $this->assertSame(0, $this->actingAs($user)->get(route('relationship.today'))->viewData('pendingCount'));
        $this->assertArrayNotHasKey($patient->id, $this->rowsFor($user, 'relationship.today.pending'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2 — only overdue work → Pending, never Today
    // ═══════════════════════════════════════════════════════════════════

    public function test_patient_with_only_overdue_work_is_pending_not_today(): void
    {
        $user    = $this->user();
        $patient = $this->patient('Only Overdue');
        $this->overdueEstimate($patient);

        $this->assertArrayNotHasKey($patient->id, $this->rowsFor($user), 'nothing due today → not on Today');

        $pending = $this->rowsFor($user, 'relationship.today.pending');
        $this->assertArrayHasKey($patient->id, $pending);
        $this->assertSame('pending_estimates', $pending[$patient->id]['primary']['cat']);

        // Badge = what the Pending board shows.
        $this->assertSame(1, $this->actingAs($user)->get(route('relationship.today'))->viewData('pendingCount'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3 — yesterday's missed call rides on today's row, once
    // ═══════════════════════════════════════════════════════════════════

    public function test_yesterdays_missed_call_rides_on_todays_row_once(): void
    {
        $user    = $this->user();
        $patient = $this->patient('Missed Yesterday');
        $this->appointmentToday($patient, $user);
        $queue = $this->recallDueYesterday($patient);

        $rows = $this->rowsFor($user);
        $row  = $rows[$patient->id];

        // The same queue row is reachable as missed_calls_yesterday AND as an
        // overdue recall_calls item — it must be ONE reason on the row.
        $queueReasons = array_filter($row['items'], fn ($i) => ($i['item']['meta']['comm_queue_id'] ?? null) === $queue->id);
        $this->assertCount(1, $queueReasons, 'one queue record, one reason');
        $this->assertSame(2, $row['reasonCount']);

        // And no separate "Yesterday's Missed Calls" row anywhere on Today.
        foreach ($this->actingAs($user)->get(route('relationship.today'))->viewData('rows') as $r) {
            $this->assertNotSame('missed_calls_yesterday', $r['primary']['cat']);
        }

        $this->assertSame(1, $this->actingAs($user)->get(route('relationship.today'))->viewData('carriedCount'));
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4 — one outcome closes every reason
    // ═══════════════════════════════════════════════════════════════════

    public function test_one_logged_outcome_closes_every_reason_on_the_row(): void
    {
        $user    = $this->user();
        $patient = $this->patient('Closes All');
        $appt    = $this->appointmentToday($patient, $user);
        $opp     = $this->overdueEstimate($patient);
        $this->outcome('appointment_reminders', 'confirmed_attendance', 'Confirmed attendance', closes: true);

        $before = Activity::query()->where('event', 'call.logged')->count();

        $this->actingAs($user)->postJson(route('relationship.today.log-call'), [
            'patient_id' => $patient->id,
            'response'   => 'confirmed_attendance',
            'direction'  => 'outbound',
            'items'      => [
                ['category' => 'appointment_reminders', 'subject_id' => $appt->id],
                ['category' => 'pending_estimates',     'subject_id' => $opp->id],
            ],
        ])->assertOk()->assertJson(['success' => true, 'closed' => true, 'closed_items' => 2]);

        // ONE call on the timeline, naming both reasons.
        $this->assertSame($before + 1, Activity::query()->where('event', 'call.logged')->count());
        $log = Activity::query()->where('event', 'call.logged')->latest('id')->first();
        $this->assertEqualsCanonicalizing(['appointment_reminders', 'pending_estimates'], $log->metadata['categories']);

        // Both reasons closed — permanently, through their own dismissal rows.
        foreach ([['appointment_reminders', $appt->id], ['pending_estimates', $opp->id]] as [$cat, $id]) {
            $row = TodayActionDismissal::where('category', $cat)->where('subject_id', $id)->first();
            $this->assertNotNull($row, "$cat closed");
            $this->assertTrue($row->is_permanent);
            $this->assertSame('confirmed_attendance', $row->reason_key);
        }

        // The patient is off Today (as done) and not pending either.
        $this->assertArrayNotHasKey($patient->id, $this->rowsFor($user));
        $this->assertArrayNotHasKey($patient->id, $this->rowsFor($user, 'relationship.today.pending'));

        // Tomorrow — straight from the engine + list (no HTTP: the test
        // session does not survive a day of time travel), same inputs the
        // controller feeds the boards.
        $this->travel(1)->days();
        $engine = app(TodayActionsEngine::class);
        $list   = app(TodayCallList::class)->build(
            $engine->generate(includeDone: true, dueWindow: 'today'),
            $engine->generate(includeDone: false, dueWindow: 'overdue'),
            today(),
            TodayController::categoryMeta(),
        );
        $ids = array_map(fn ($r) => $r['patient_id'], array_merge($list['rows'], $list['pendingRows']));
        $this->assertNotContains($patient->id, $ids, 'nothing comes back tomorrow');
        $this->travelBack();
    }

    public function test_a_non_closing_outcome_leaves_every_reason_open(): void
    {
        $user    = $this->user();
        $patient = $this->patient('Stays Open');
        $appt    = $this->appointmentToday($patient, $user);
        $opp     = $this->overdueEstimate($patient);
        $this->outcome('appointment_reminders', 'no_answer', 'No answer', closes: false);

        $this->actingAs($user)->postJson(route('relationship.today.log-call'), [
            'patient_id' => $patient->id,
            'response'   => 'no_answer',
            'items'      => [
                ['category' => 'appointment_reminders', 'subject_id' => $appt->id],
                ['category' => 'pending_estimates',     'subject_id' => $opp->id],
            ],
        ])->assertOk()->assertJson(['success' => true, 'closed' => false, 'closed_items' => 0]);

        $this->assertSame(0, TodayActionDismissal::count(), 'an attempt closes nothing');

        $row = $this->rowsFor($user)[$patient->id];
        $this->assertSame(2, $row['reasonCount'], 'the whole row stays due');
        $this->assertSame('Attempted', $row['stTxt']);
        $this->assertTrue($row['attempted']);
        $this->assertSame('retry', $row['band'], 'an attempted row sinks into "Try again" at the bottom of the queue');
    }

    public function test_an_unticked_reason_stays_open_after_the_call(): void
    {
        $user    = $this->user();
        $patient = $this->patient('Partial');
        $appt    = $this->appointmentToday($patient, $user);
        $opp     = $this->overdueEstimate($patient);
        $this->outcome('appointment_reminders', 'confirmed_attendance', 'Confirmed attendance', closes: true);

        // Reception unticked the estimate — only the appointment is logged.
        $this->actingAs($user)->postJson(route('relationship.today.log-call'), [
            'patient_id' => $patient->id,
            'response'   => 'confirmed_attendance',
            'items'      => [['category' => 'appointment_reminders', 'subject_id' => $appt->id]],
        ])->assertOk()->assertJson(['success' => true, 'closed' => true, 'closed_items' => 1]);

        $this->assertNull(TodayActionDismissal::where('category', 'pending_estimates')->where('subject_id', $opp->id)->first());

        // The estimate is still the patient's open reason — and since the
        // appointment call is done today, it stays on the patient's Today row.
        $row = $this->rowsFor($user)[$patient->id];
        $this->assertSame(1, $row['reasonCount']);
        $this->assertSame('pending_estimates', $row['primary']['cat']);
    }
}
