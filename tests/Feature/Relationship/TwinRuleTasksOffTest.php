<?php

namespace Tests\Feature\Relationship;

use App\Models\Patient;
use App\Models\Task;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\TreatmentPlan\TreatmentPlanOpportunitySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W-10 step 2 (2026-09-10) — one event, one row.
 *
 * Six RulesEngine rules each raised a system Task that duplicated a card the
 * board already computes live, so the same patient was listed twice (lab:
 * three times) with two different close buttons. The rules are off; the
 * one thing they did that nothing else did — nudging a plan-synced
 * opportunity that was born with no follow-up date — is now the date itself.
 */
class TwinRuleTasksOffTest extends TestCase
{
    use RefreshDatabase;

    private const TWINS = [
        'membership_renewal_30d',
        'opportunity_nudge_7d',
        'missed_appointment_followup',
        'lab_ready_call',
        'payment_overdue_3d',
        'estimate_followup_3d',
    ];

    public function test_the_six_twin_rules_are_off_and_the_two_orphans_stay_on(): void
    {
        $rules = config('relationship_rules.rules');

        foreach (self::TWINS as $key) {
            $this->assertFalse($rules[$key]['enabled'], "$key must be off — its card already exists");
        }

        // These have no live card of their own; they are the only reminder.
        $this->assertTrue($rules['implant_followup']['enabled']);
        $this->assertTrue($rules['post_treatment_followup']['enabled']);
    }

    public function test_a_plan_synced_open_opportunity_reaches_the_board_on_day_seven(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => 1]));
        $patient = Patient::create(['name' => 'Plan Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $plan    = TreatmentPlan::create(['patient_id' => $patient->id, 'plan_name' => 'Implant Plan', 'status' => 'pending', 'rows' => [], 'total' => 45000]);
        TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'treatment_name' => 'Implant', 'unit_price' => 45000, 'units' => 1, 'total' => 45000]);

        $opp = app(TreatmentPlanOpportunitySync::class)->syncStage($plan->fresh('items'), 'prospect');

        $this->assertSame(today()->addDays(7)->toDateString(), $opp->follow_up_date?->toDateString());

        $ids = fn () => array_column(array_column(app(TodayActionsEngine::class)->generate()['opportunities'], 'meta'), 'id');
        $this->assertNotContains($opp->id, $ids(), 'not due yet');

        $this->travel(7)->days();
        $this->assertContains($opp->id, $ids(), 'day seven — the nudge, on the card with the call workflow');
        $this->travelBack();

        // And no twin task was raised for it.
        $this->assertSame(0, Task::where('description', '[Auto] Rule: opportunity_nudge_7d')->count());
    }

    public function test_a_presented_plan_is_chased_in_three_days_under_pending_estimates(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => 1]));
        $patient = Patient::create(['name' => 'Quoted Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $plan    = TreatmentPlan::create(['patient_id' => $patient->id, 'plan_name' => 'Crown Plan', 'status' => 'pending', 'rows' => [], 'total' => 9000]);

        $opp = app(TreatmentPlanOpportunitySync::class)->syncStage($plan->fresh('items'), 'quoted');

        $this->assertSame(today()->addDays(3)->toDateString(), $opp->follow_up_date?->toDateString());

        $this->travel(3)->days();
        $board = app(TodayActionsEngine::class)->generate();
        $this->assertContains($opp->id, array_column(array_column($board['pending_estimates'], 'meta'), 'id'));
        $this->assertNotContains($opp->id, array_column(array_column($board['opportunities'], 'meta'), 'id'), 'one card, not two');
        $this->travelBack();

        $this->assertSame(0, Task::where('description', '[Auto] Rule: estimate_followup_3d')->count());
    }

    public function test_a_card_born_closed_gets_no_follow_up_date(): void
    {
        $this->actingAs(User::factory()->create(['branch_id' => 1]));
        $patient = Patient::create(['name' => 'Closed Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $plan    = TreatmentPlan::create(['patient_id' => $patient->id, 'plan_name' => 'Done Plan', 'status' => 'pending', 'rows' => [], 'total' => 1000]);

        $opp = app(TreatmentPlanOpportunitySync::class)->syncStage($plan->fresh('items'), TreatmentOpportunity::DECLINED);

        $this->assertNull($opp->follow_up_date);
    }

    public function test_the_command_closes_only_the_twin_rule_tasks_and_only_with_apply(): void
    {
        $actor   = User::factory()->create(['branch_id' => 1]);
        $patient = Patient::create(['name' => 'Task Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);

        $make = fn (string $description, string $type = 'system') => Task::create([
            'title'       => 'Call',
            'description' => $description,
            'category'    => 'call',
            'task_type'   => $type,
            'status'      => 'pending',
            'due_date'    => now()->toDateString(),
            'priority'    => 'medium',
            'branch_id'   => 1,
            'patient_id'  => $patient->id,
            'created_by'  => $actor->id,
        ]);

        $twin   = $make('[Auto] Rule: lab_ready_call');
        $orphan = $make('[Auto] Rule: implant_followup');
        $human  = $make('[Auto] Rule: lab_ready_call', 'human');

        $this->artisan('today:close-twin-rule-tasks')->assertSuccessful();
        $this->assertSame('pending', $twin->fresh()->status, 'dry-run must not write');

        $this->artisan('today:close-twin-rule-tasks --apply')->assertSuccessful();

        $this->assertSame('done', $twin->fresh()->status);
        $this->assertNotNull($twin->fresh()->done_at);
        $this->assertSame('pending', $orphan->fresh()->status, 'implant follow-up has no twin — it stays');
        $this->assertSame('pending', $human->fresh()->status, 'a human task is never touched');
    }
}
