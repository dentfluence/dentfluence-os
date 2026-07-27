<?php

namespace Tests\Feature\Clinical;

use App\Models\Activity;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.3c — C-1 FIX: ACCEPTED = COMMITTED, NOT CONVERTED.
 *
 * Acceptance used to move the opportunity to 'completed', which the board
 * renders as "Converted" — collapsing "the patient said yes" into "treatment
 * started". It now writes 'accepted' (Committed), and Committed leaves the
 * sales-chase pool while remaining visible on the board.
 */
class PlanCommittedProjectionTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name' => 'Committed Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
    }

    private function acceptedPlan(): TreatmentPlan
    {
        $plan = TreatmentPlan::create([
            'patient_id' => $this->patient()->id, 'plan_name' => 'Implant Plan',
            'status' => 'pending', 'rows' => [], 'total' => 45000,
        ]);
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id, 'treatment_name' => 'Implant',
            'unit_price' => 45000, 'units' => 1, 'total' => 45000,
        ]);

        $this->actingAs($this->admin());
        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');
        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh(), $this->admin(), 'clinic');

        return $plan->fresh();
    }

    // ── The fix itself ───────────────────────────────────────────────────────

    public function test_acceptance_projects_committed_and_never_converted(): void
    {
        $plan = $this->acceptedPlan();

        $opp = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->firstOrFail();

        $this->assertSame(TreatmentOpportunity::COMMITTED, $opp->status);
        $this->assertSame('Committed', $opp->stage_label);
        $this->assertNotSame(TreatmentOpportunity::CONVERTED, $opp->status,
            'accepting a plan must never mean treatment started');
    }

    public function test_nothing_in_the_system_writes_converted_on_acceptance(): void
    {
        $this->acceptedPlan();

        $this->assertSame(0, TreatmentOpportunity::where('status', TreatmentOpportunity::CONVERTED)->count(),
            'Converted is reserved for actual treatment start and is not implemented yet');
    }

    // ── Committed exits the chase pool ───────────────────────────────────────

    public function test_a_committed_opportunity_is_no_longer_sales_work(): void
    {
        $plan = $this->acceptedPlan();

        $this->assertSame(0, TreatmentOpportunity::open()->count(),
            'a patient who already said yes must not be chased to say yes');

        $opp = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->firstOrFail();
        $opp->forceFill(['follow_up_date' => now()->subWeek()])->save();

        $this->assertFalse($opp->fresh()->is_overdue, 'Committed cannot be an overdue chase');
        $this->assertSame(0, TreatmentOpportunity::overdue()->count());
    }

    /**
     * The chase is armed at PRESENTATION — "you gave an estimate, follow it up"
     * — which is correct and must keep working. What acceptance must do is
     * DISARM it: raise no second opportunity.created, and drop the card out of
     * every open/chase query.
     */
    public function test_the_estimate_chase_is_armed_by_presentation_and_disarmed_by_acceptance(): void
    {
        $patient = $this->patient();
        $plan    = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Implant Plan',
            'status' => 'pending', 'rows' => [], 'total' => 45000,
        ]);
        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id, 'treatment_name' => 'Implant',
            'unit_price' => 45000, 'units' => 1, 'total' => 45000,
        ]);

        $this->actingAs($this->admin());
        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        // Armed: an estimate is out and someone should chase it.
        $this->assertSame(1, Activity::where('event', 'opportunity.created')->count());
        $this->assertSame(1, TreatmentOpportunity::open()->count());

        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh(), $this->admin(), 'clinic');

        // Disarmed: no second arming event, and nothing left to chase.
        $this->assertSame(1, Activity::where('event', 'opportunity.created')->count(),
            'acceptance must not re-arm the chase');
        $this->assertSame(0, TreatmentOpportunity::open()->count(),
            'the patient already said yes — there is nothing left to chase them for');
        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.accepted']);
    }

    public function test_committed_leaves_todays_actions_opportunities(): void
    {
        $plan = $this->acceptedPlan();

        TreatmentOpportunity::where('treatment_plan_id', $plan->id)
            ->update(['follow_up_date' => now()->subWeek()]);

        $actions = app(TodayActionsEngine::class)->generate();

        $this->assertEmpty($actions['opportunities'] ?? [],
            'an accepted plan is not an open opportunity needing a chase call');

        // …and the board contract is still fourteen categories, not fifteen.
        $this->assertCount(14, $actions);
    }

    // ── …but stays visible as awaiting scheduling ────────────────────────────

    public function test_a_committed_card_remains_visible_on_the_pipeline_board(): void
    {
        $plan = $this->acceptedPlan();

        $this->actingAs($this->admin())
            ->get(route('relationship.opportunities'))
            ->assertOk()
            ->assertSee('Committed');

        $this->assertSame(1, TreatmentOpportunity::committed()->count());
        $this->assertSame(1, TreatmentOpportunity::where('treatment_plan_id', $plan->id)->count(),
            'still exactly one opportunity per plan');
    }

    // ── No backfill ──────────────────────────────────────────────────────────

    public function test_historical_converted_rows_are_left_exactly_as_they_are(): void
    {
        $patient = $this->patient();

        // A pre-2.3c row: accepted long ago, stored as 'completed'.
        $legacy = TreatmentOpportunity::create([
            'patient_id' => $patient->id, 'type' => 'other',
            'label' => 'Old implant case', 'status' => TreatmentOpportunity::CONVERTED,
        ]);

        $this->acceptedPlan();   // new acceptance happens alongside it

        $this->assertSame(TreatmentOpportunity::CONVERTED, $legacy->fresh()->status,
            'history is not rewritten — old rows keep the value they were given');
        $this->assertSame(0, TreatmentOpportunity::open()->count());
    }

    // ── The one definition ───────────────────────────────────────────────────

    public function test_closed_is_defined_once_and_covers_the_three_decided_states(): void
    {
        $this->assertSame(
            ['accepted', 'completed', 'declined'],
            TreatmentOpportunity::CLOSED_STATUSES,
        );

        // Every stage that is NOT closed is still genuine sales work.
        $open = array_diff(array_keys(TreatmentOpportunity::STAGES), TreatmentOpportunity::CLOSED_STATUSES);
        $this->assertSame(['prospect', 'discussed', 'quoted'], array_values($open));
    }
}
