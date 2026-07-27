<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\PlanDecision;
use App\Models\PlanDecisionItem;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Patient\PatientJourneyService;
use App\Services\Relationship\TodayActionsEngine;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.3d — REJECT / DEFER / PARTIAL ACCEPTANCE.
 *
 * The four patient decisions now all have verbs, and each projects onto PRE
 * differently:
 *   Accepted / Partially accepted → Committed (decided, needs scheduling)
 *   Deferred                      → still open, but snoozed if a date was given
 *   Rejected                      → Declined (closed)
 */
class PlanDecisionVerbsTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    private function presentedPlan(): TreatmentPlan
    {
        $patient = Patient::create([
            'name' => 'Decision Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Implant Plan',
            'status' => 'pending', 'rows' => [], 'total' => 57000,
        ]);

        foreach ([['Implant', 45000], ['Crown', 12000]] as [$n, $p]) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id, 'treatment_name' => $n,
                'unit_price' => $p, 'units' => 1, 'total' => $p,
            ]);
        }

        $this->actingAs($this->admin());
        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        return $plan->fresh('items');
    }

    private function service(): TreatmentPlanAcceptanceService
    {
        return app(TreatmentPlanAcceptanceService::class);
    }

    // ── REJECTED ─────────────────────────────────────────────────────────────

    public function test_rejection_records_the_decision_and_closes_the_opportunity(): void
    {
        $plan = $this->presentedPlan();

        $this->service()->reject($plan, 'Too expensive right now', $this->admin(), 'clinic');
        $plan->refresh();

        $decision = $plan->currentDecision();
        $this->assertSame(PlanDecision::REJECTED, $decision->decision);
        $this->assertSame('Too expensive right now', $decision->notes);
        $this->assertFalse($decision->is_committing);

        $this->assertSame(TreatmentOpportunity::DECLINED,
            TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));
        $this->assertSame(0, TreatmentOpportunity::open()->count());

        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.rejected']);
    }

    /**
     * Rejection is a PATIENT DECISION. The plan is not cancelled — cancelled is
     * an administrative state and collapsing the two would destroy the
     * distinction the ledger exists to preserve.
     */
    public function test_rejection_does_not_cancel_the_plan_or_touch_acceptance(): void
    {
        $plan = $this->presentedPlan();

        $this->service()->reject($plan, null, $this->admin(), 'clinic');
        $plan->refresh();

        $this->assertSame('pending', $plan->status);
        $this->assertNull($plan->accepted_at);
    }

    // ── DEFERRED ─────────────────────────────────────────────────────────────

    public function test_a_dated_deferral_keeps_the_opportunity_open_but_snoozes_the_chase(): void
    {
        $plan = $this->presentedPlan();

        TreatmentOpportunity::where('treatment_plan_id', $plan->id)
            ->update(['follow_up_date' => now()->subDays(3)]);   // currently overdue

        $reviewOn = now()->addMonth()->toDateString();
        $this->service()->defer($plan, $reviewOn, 'Wants to discuss with family', $this->admin(), 'clinic');

        $opp = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->firstOrFail();

        // Still a live opportunity — the patient may yet say yes.
        $this->assertSame(1, TreatmentOpportunity::open()->count());

        // …but nobody chases it until the agreed date.
        $this->assertSame($reviewOn, $opp->follow_up_date->toDateString());
        $this->assertFalse($opp->is_overdue);
        $this->assertFalse($opp->due_today);
        $this->assertEmpty(app(TodayActionsEngine::class)->generate()['opportunities'] ?? []);

        $this->assertSame($reviewOn, $plan->fresh()->currentDecision()->defer_until->toDateString());
    }

    public function test_an_undated_deferral_invents_no_date_at_all(): void
    {
        $plan = $this->presentedPlan();

        TreatmentOpportunity::where('treatment_plan_id', $plan->id)
            ->update(['follow_up_date' => null]);

        $this->service()->defer($plan, null, 'Will think about it', $this->admin(), 'clinic');

        $decision = $plan->fresh()->currentDecision();
        $this->assertNull($decision->defer_until);
        $this->assertTrue($decision->is_open_ended_deferral);

        // No follow-up date was manufactured on the opportunity either.
        $this->assertNull(TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('follow_up_date'));
        $this->assertSame(1, TreatmentOpportunity::open()->count(), 'still open, just undated');
    }

    // ── PARTIAL ACCEPTANCE ───────────────────────────────────────────────────

    public function test_partial_acceptance_commits_the_plan_and_keeps_every_item_verdict(): void
    {
        $plan = $this->presentedPlan();
        [$implant, $crown] = $plan->items->all();

        $this->service()->acceptPartially($plan, [
            $implant->id => PlanDecisionItem::ACCEPTED,
            $crown->id   => PlanDecisionItem::DEFERRED,
        ], $this->admin(), 'clinic');

        $plan->refresh();
        $decision = $plan->currentDecision();

        $this->assertSame(PlanDecision::PARTIALLY_ACCEPTED, $decision->decision);
        $this->assertTrue($decision->is_committing);

        // The patient committed to something, so the mirror is set…
        $this->assertNotNull($plan->accepted_at);
        // …and PRE reads Committed, not Converted.
        $this->assertSame(TreatmentOpportunity::COMMITTED,
            TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // Each verdict survives independently.
        $this->assertSame(PlanDecisionItem::ACCEPTED,
            $decision->items()->where('treatment_plan_item_id', $implant->id)->value('decision'));
        $this->assertSame(PlanDecisionItem::DEFERRED,
            $decision->items()->where('treatment_plan_item_id', $crown->id)->value('decision'));

        // item.status is untouched — it is not the decision store.
        $this->assertSame('pending', $crown->fresh()->status);

        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.partially_accepted']);
    }

    public function test_partial_acceptance_refuses_items_from_another_plan_and_unknown_verdicts(): void
    {
        $plan  = $this->presentedPlan();
        $other = $this->presentedPlan();

        try {
            $this->service()->acceptPartially($plan, [$other->items->first()->id => PlanDecisionItem::ACCEPTED], $this->admin());
            $this->fail('an item from another plan must be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not belong to this plan', $e->getMessage());
        }

        try {
            $this->service()->acceptPartially($plan, [$plan->items->first()->id => 'maybe_later'], $this->admin());
            $this->fail('an unknown verdict must be refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unknown item decision', $e->getMessage());
        }

        $this->assertDatabaseCount('plan_decisions', 0);
    }

    // ── APPEND-ONLY ACROSS VERBS ─────────────────────────────────────────────

    public function test_deferred_then_accepted_keeps_both_and_derives_the_latest(): void
    {
        $plan = $this->presentedPlan();

        $this->service()->defer($plan, null, 'Wants to think', $this->admin(), 'clinic');
        $this->travel(9)->days();
        $this->service()->accept($plan->fresh(), $this->admin(), 'clinic');

        $plan->refresh();

        $this->assertSame(2, $plan->decisions()->count());
        $this->assertSame(PlanDecision::ACCEPTED, $plan->currentDecision()->decision);
        $this->assertDatabaseHas('plan_decisions', ['decision' => PlanDecision::DEFERRED]);

        // The board followed the latest decision.
        $this->assertSame(TreatmentOpportunity::COMMITTED,
            TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));
    }

    public function test_a_cancelled_plan_cannot_carry_any_decision(): void
    {
        $plan = $this->presentedPlan();
        $plan->update(['status' => 'cancelled']);

        foreach ([
            fn () => $this->service()->reject($plan->fresh(), null, $this->admin()),
            fn () => $this->service()->defer($plan->fresh(), null, null, $this->admin()),
            fn () => $this->service()->acceptPartially($plan->fresh(), [$plan->items->first()->id => 'accepted'], $this->admin()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('a cancelled plan must not accept a decision');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('cancelled', $e->getMessage());
            }
        }
    }

    // ── TIMELINE ─────────────────────────────────────────────────────────────

    public function test_the_journey_shows_the_whole_decision_story_not_just_the_latest(): void
    {
        $plan    = $this->presentedPlan();
        $patient = $plan->patient;

        $this->service()->defer($plan, now()->addMonth()->toDateString(), 'Discussing with family', $this->admin(), 'clinic');
        $this->travel(20)->days();
        $this->service()->accept($plan->fresh(), $this->admin(), 'clinic');

        $journey = app(PatientJourneyService::class)->for($patient->fresh(), null, 'clinical');
        $titles  = collect($journey['events'])->pluck('title')->implode(' | ');

        $this->assertStringContainsString('presented to patient', $titles);
        $this->assertStringContainsString('deferred by patient', $titles);
        $this->assertStringContainsString('accepted', $titles);
    }
}
