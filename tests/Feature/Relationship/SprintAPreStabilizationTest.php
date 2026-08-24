<?php

namespace Tests\Feature\Relationship;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Models\Task;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Services\RecallEngineService;
use App\Services\Relationship\TodayActionsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRE Sprint A (2026-08-24) — the leakage-core fixes, each guarding one
 * traced defect:
 *
 *  G-11  TreatmentVisit advances patients.last_visit_date (previously only
 *        Consultations did — patients mid-treatment were recalled as
 *        "not seen for 6 months").
 *  R-3   Recall trigger 5 (7-day follow-up): NULL visit_type/procedure rows
 *        were silently excluded (`NULL NOT LIKE` is NULL, not TRUE).
 *  A2    Web outcomes route through OutcomeAutomationService — one engine
 *        with mobile ("will call back" reschedules instead of vanishing;
 *        "wrong number" flags the contact) + idempotency on closed rows.
 *  A3    relationship:requeue-due re-surfaces waiting_for_patient rows whose
 *        day arrived (previously they vanished forever), and the due-window
 *        splits Today (due today) from Pending Calls (due earlier).
 *  G-27  RulesEngine 'system' tasks finally reach the board.
 */
class SprintAPreStabilizationTest extends TestCase
{
    use RefreshDatabase;

    // ── Shared actors/helpers (same pattern as TodayReadCutoverTest) ────

    private function preRole(): \App\Models\Role
    {
        $role = \App\Models\Role::firstOrCreate(
            ['slug' => 'pre_test_actor'],
            ['name' => 'PRE Test Actor', 'category' => \App\Models\Role::CATEGORY_STAFF, 'is_system' => false]
        );

        foreach (['relationship', 'patients', 'daily_huddle', 'tasks'] as $slug) {
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

    private function patient(array $attrs = []): Patient
    {
        return Patient::create(array_merge([
            'name'      => 'Sprint A Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ], $attrs));
    }

    private function queueRow(Patient $patient, array $attrs = []): CommunicationQueue
    {
        return CommunicationQueue::create(array_merge([
            'patient_id'      => $patient->id,
            'person_name'     => $patient->name,
            'phone'           => $patient->phone,
            'channel'         => 'call',
            'direction'       => 'outbound',
            'comm_type'       => 'existing_patient',
            'purpose'         => 'recall_due',
            'status'          => 'pending',
            'priority'        => 'medium',
            'source_engine'   => 'recall',
            'follow_up_date'  => now()->toDateString(),
        ], $attrs));
    }

    // ═══════════════════════════════════════════════════════════════════
    // G-11 — TreatmentVisit advances last_visit_date
    // ═══════════════════════════════════════════════════════════════════

    public function test_treatment_visit_advances_last_visit_date(): void
    {
        $patient = $this->patient(['last_visit_date' => now()->subMonths(8)->toDateString()]);

        TreatmentVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateString(),
            'status'     => 'completed',
        ]);

        $this->assertSame(
            now()->toDateString(),
            $patient->fresh()->last_visit_date?->toDateString(),
            'G-11: a treatment visit must advance patients.last_visit_date'
        );
    }

    public function test_backdated_visit_never_rewinds_last_visit_date(): void
    {
        $patient = $this->patient(['last_visit_date' => now()->subDays(3)->toDateString()]);

        TreatmentVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->subMonths(6)->toDateString(),
            'status'     => 'completed',
        ]);

        $this->assertSame(
            now()->subDays(3)->toDateString(),
            $patient->fresh()->last_visit_date?->toDateString(),
            'G-11: monotonic — a backdated visit must not rewind last_visit_date'
        );
    }

    public function test_future_scheduled_visit_does_not_advance_last_visit_date(): void
    {
        $patient = $this->patient(['last_visit_date' => now()->subDays(3)->toDateString()]);

        TreatmentVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->addDays(14)->toDateString(),
            'status'     => 'started',
        ]);

        $this->assertSame(
            now()->subDays(3)->toDateString(),
            $patient->fresh()->last_visit_date?->toDateString(),
            'G-11: a future-dated visit is a plan, not a fact — no advance'
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    // R-3 — recall trigger 5 must include NULL visit_type/procedure rows
    // ═══════════════════════════════════════════════════════════════════

    public function test_recall_7day_followup_includes_null_visit_type_rows(): void
    {
        $patient = $this->patient(['last_visit_date' => now()->toDateString()]); // keeps no-visit trigger quiet

        TreatmentVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->subDays(7)->toDateString(),
            'visit_type' => null,       // ← the silently-excluded shape (R-3)
            'procedure'  => null,
            'status'     => 'completed',
        ]);

        app(RecallEngineService::class)->runAll();

        $this->assertDatabaseHas('communication_queue', [
            'patient_id' => $patient->id,
            'purpose'    => 'recall_7day_followup',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // A2 — one outcome path (web logAction → OutcomeAutomationService)
    // ═══════════════════════════════════════════════════════════════════

    public function test_web_will_call_back_reschedules_instead_of_closing(): void
    {
        $patient = $this->patient();
        $row     = $this->queueRow($patient);

        $response = $this->actingAs($this->user())->postJson(route('relationship.today.action'), [
            'category'   => 'recall_calls',
            'patient_id' => $patient->id,
            'subject_id' => $row->id,
            'response'   => 'will_call_back',
            'notes'      => 'Asked to call day after tomorrow',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'closed' => true]);

        $fresh = $row->fresh();
        $this->assertSame('waiting_for_patient', $fresh->status, 'A2: will_call_back must reschedule, not close forever');
        $this->assertSame(
            now()->addDays(2)->toDateString(),
            $fresh->follow_up_date?->toDateString(),
            'A2: follow_up_date must advance (+2d default), same as mobile'
        );

        // Accountability: the activity row carries the actor automatically.
        $this->assertDatabaseHas('activities', ['event' => 'call.logged']);
    }

    public function test_web_wrong_number_marks_contact_invalid(): void
    {
        $patient = $this->patient();
        $row     = $this->queueRow($patient);

        $this->actingAs($this->user())->postJson(route('relationship.today.action'), [
            'category'   => 'recall_calls',
            'patient_id' => $patient->id,
            'subject_id' => $row->id,
            'response'   => 'wrong_number',
        ])->assertOk();

        $this->assertNotNull(
            $patient->fresh()->contact_invalid_at,
            'A2: wrong_number on web must flag the contact, same as mobile'
        );
        $this->assertSame('closed', $row->fresh()->status);
    }

    public function test_logging_outcome_on_closed_row_is_idempotent(): void
    {
        $patient = $this->patient();
        $row     = $this->queueRow($patient, ['status' => 'closed', 'outcome' => 'appointment_booked']);

        $response = $this->actingAs($this->user())->postJson(route('relationship.today.action'), [
            'category'   => 'recall_calls',
            'patient_id' => $patient->id,
            'subject_id' => $row->id,
            'response'   => 'will_call_back',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'closed' => true]);

        $fresh = $row->fresh();
        $this->assertSame('closed', $fresh->status, 'A2: a closed row must never be re-opened by a late submit');
        $this->assertSame('appointment_booked', $fresh->outcome, 'A2: the original outcome must survive a double-submit');
    }

    // ═══════════════════════════════════════════════════════════════════
    // A3 — requeue-due + the Today/Pending due-window
    // ═══════════════════════════════════════════════════════════════════

    public function test_requeue_due_resurfaces_waiting_rows_whose_day_arrived(): void
    {
        $patient = $this->patient();
        $due     = $this->queueRow($patient, ['status' => 'waiting_for_patient', 'follow_up_date' => now()->toDateString()]);
        $future  = $this->queueRow($patient, ['status' => 'waiting_for_patient', 'follow_up_date' => now()->addDays(5)->toDateString(), 'purpose' => 'recall_birthday']);
        $closed  = $this->queueRow($patient, ['status' => 'closed', 'follow_up_date' => now()->subDay()->toDateString(), 'purpose' => 'recall_long_term']);

        $this->artisan('relationship:requeue-due')->assertSuccessful();

        $this->assertSame('pending', $due->fresh()->status, 'A3: a rescheduled call must come back on its day');
        $this->assertSame('waiting_for_patient', $future->fresh()->status, 'A3: not-yet-due rows stay parked');
        $this->assertSame('closed', $closed->fresh()->status, 'A3: closed rows are never touched');
    }

    public function test_requeue_due_refreshes_overdue_flags(): void
    {
        $patient = $this->patient();
        $overdue = $this->queueRow($patient, ['follow_up_date' => now()->subDays(3)->toDateString(), 'is_overdue' => false]);
        $current = $this->queueRow($patient, ['follow_up_date' => now()->addDay()->toDateString(), 'is_overdue' => true, 'purpose' => 'recall_birthday']);

        $this->artisan('relationship:requeue-due')->assertSuccessful();

        $this->assertTrue((bool) $overdue->fresh()->is_overdue);
        $this->assertFalse((bool) $current->fresh()->is_overdue);
    }

    public function test_due_window_splits_today_from_pending(): void
    {
        $patient = $this->patient();
        $todayRow   = $this->queueRow($patient, ['follow_up_date' => now()->toDateString()]);
        $overdueRow = $this->queueRow($patient, ['follow_up_date' => now()->subDays(4)->toDateString(), 'purpose' => 'recall_birthday']);

        $engine = app(TodayActionsEngine::class);

        $todayIds   = array_column($engine->generate(dueWindow: 'today')['recall_calls'], 'meta');
        $todayIds   = array_column($todayIds, 'comm_queue_id');
        $overdueIds = array_column($engine->generate(dueWindow: 'overdue')['recall_calls'], 'meta');
        $overdueIds = array_column($overdueIds, 'comm_queue_id');

        $this->assertContains($todayRow->id, $todayIds, "A3: due-today row belongs on Today's board");
        $this->assertNotContains($overdueRow->id, $todayIds, 'A3: overdue row must NOT pad the Today board');
        $this->assertContains($overdueRow->id, $overdueIds, 'A3: overdue row belongs on Pending Calls');
        $this->assertNotContains($todayRow->id, $overdueIds, 'A3: no row may appear on both boards');

        $this->assertGreaterThanOrEqual(1, $engine->pendingCallsCount(), 'A3: honest pending count sees the backlog');
    }

    public function test_pending_calls_page_renders(): void
    {
        $patient = $this->patient();
        $this->queueRow($patient, ['follow_up_date' => now()->subDays(2)->toDateString()]);

        $this->actingAs($this->user())
            ->get(route('relationship.today.pending'))
            ->assertOk()
            ->assertSee('Pending Calls');
    }

    // ═══════════════════════════════════════════════════════════════════
    // G-27 — system tasks reach the board
    // ═══════════════════════════════════════════════════════════════════

    public function test_system_task_appears_on_board_and_human_task_does_not(): void
    {
        $actor   = $this->user();
        $patient = $this->patient();

        $system = Task::create([
            'title'      => 'Implant follow-up call',
            'category'   => 'call',
            'task_type'  => 'system',
            'status'     => 'pending',
            'due_date'   => now()->toDateString(),
            'patient_id' => $patient->id,
            'branch_id'  => 1,
            'created_by' => $actor->id,
        ]);

        $human = Task::create([
            'title'      => 'Manually added call',
            'category'   => 'call',
            'task_type'  => 'human',
            'status'     => 'pending',
            'due_date'   => now()->toDateString(),
            'patient_id' => $patient->id,
            'branch_id'  => 1,
            'created_by' => $actor->id,
        ]);

        $items = app(TodayActionsEngine::class)->generate(dueWindow: 'today')['tasks'];
        $ids   = array_column(array_column($items, 'meta'), 'id');

        $this->assertContains($system->id, $ids, 'G-27: RulesEngine (system) tasks must reach the board');
        $this->assertNotContains($human->id, $ids, 'G-27: human tasks already surface via their queue row — no doubles');
    }
}
