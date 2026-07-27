<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\PlanDecision;
use App\Models\PlanDecisionItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.3b — THE DECISION LEDGER.
 *
 * plan_decisions is the canonical, append-only record of what the patient
 * decided. treatment_plans.accepted_at is demoted to a read-model mirror.
 */
class PlanDecisionLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        return Patient::create([
            'name' => 'Ledger Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
        ]);
    }

    private function presentedPlan(?Patient $patient = null): TreatmentPlan
    {
        $patient ??= $this->patient();

        $plan = TreatmentPlan::create([
            'patient_id' => $patient->id, 'plan_name' => 'Implant Plan',
            'status' => 'pending', 'rows' => [], 'total' => 45000,
        ]);

        foreach ([['Implant', 45000], ['Crown', 12000]] as [$name, $price]) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id, 'treatment_name' => $name,
                'unit_price' => $price, 'units' => 1, 'total' => $price,
            ]);
        }

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        return $plan->fresh('items');
    }

    /**
     * Instance property, NOT `static` — a static would survive between tests
     * while RefreshDatabase rolls its row back, leaving a User object whose id
     * no longer exists and breaking every foreign key that references it.
     */
    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create([
            'role' => 'admin', 'branch_id' => 1, 'is_active' => true,
        ]);
    }

    // ── Decision pending ─────────────────────────────────────────────────────

    public function test_a_presented_plan_with_no_decision_is_decision_pending(): void
    {
        $plan = $this->presentedPlan();

        $this->assertNull($plan->currentDecision());
        $this->assertTrue($plan->is_decision_pending);
        $this->assertDatabaseCount('plan_decisions', 0);
    }

    // ── Accept writes the ledger, accepted_at mirrors it ─────────────────────

    public function test_acceptance_appends_a_decision_row_and_mirrors_accepted_at(): void
    {
        $plan  = $this->presentedPlan();
        $actor = $this->admin();
        $this->actingAs($actor);

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, 'clinic');
        $plan->refresh();

        $decision = $plan->currentDecision();

        $this->assertNotNull($decision);
        $this->assertSame(PlanDecision::ACCEPTED, $decision->decision);
        $this->assertSame('clinic', $decision->source);
        $this->assertSame($actor->id, $decision->recorded_by);
        $this->assertTrue($decision->is_committing);

        // accepted_at survives as the compatibility mirror…
        $this->assertNotNull($plan->accepted_at);
        // …and the plan is no longer awaiting a decision.
        $this->assertFalse($plan->is_decision_pending);
    }

    public function test_the_source_records_the_channel_so_a_future_microsite_needs_no_new_store(): void
    {
        $actor = $this->admin();
        $this->actingAs($actor);

        foreach (['clinic', 'mobile', 'smart_presentation'] as $channel) {
            $plan = $this->presentedPlan();
            app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, $channel);

            $this->assertSame($channel, $plan->fresh()->currentDecision()->source);
        }
    }

    // ── Append-only ──────────────────────────────────────────────────────────

    public function test_a_change_of_mind_appends_and_never_overwrites_history(): void
    {
        $plan  = $this->presentedPlan();
        $actor = $this->admin();

        // 27 Jul — the patient defers.
        $deferred = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::DEFERRED,
            'defer_until'       => null,
            'source'            => 'clinic',
            'recorded_by'       => $actor->id,
        ]);

        $this->travel(9)->days();

        // 05 Aug — the patient accepts.
        $this->actingAs($actor);
        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh(), $actor, 'clinic');

        $plan->refresh();

        // Both rows survive; the latest is current.
        $this->assertSame(2, $plan->decisions()->count());
        $this->assertSame(PlanDecision::ACCEPTED, $plan->currentDecision()->decision);
        $this->assertDatabaseHas('plan_decisions', ['id' => $deferred->id, 'decision' => PlanDecision::DEFERRED]);
    }

    public function test_a_recorded_decision_can_never_be_edited_or_deleted(): void
    {
        $plan = $this->presentedPlan();

        $decision = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::REJECTED,
            'source'            => 'clinic',
        ]);

        try {
            $decision->update(['decision' => PlanDecision::ACCEPTED]);
            $this->fail('a decision must not be editable');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $decision->delete();
            $this->fail('a decision must not be deletable');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame(PlanDecision::REJECTED, $decision->fresh()->decision);
    }

    // ── Deferral without a date ──────────────────────────────────────────────

    public function test_a_deferral_may_carry_no_date_and_none_is_invented(): void
    {
        $plan = $this->presentedPlan();

        $openEnded = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::DEFERRED,
            'defer_until'       => null,
            'source'            => 'clinic',
        ]);

        $this->assertNull($openEnded->fresh()->defer_until, 'no fake review date may be manufactured');
        $this->assertTrue($openEnded->is_open_ended_deferral);

        $dated = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::DEFERRED,
            'defer_until'       => now()->addMonth()->toDateString(),
            'source'            => 'clinic',
        ]);

        $this->assertNotNull($dated->fresh()->defer_until);
        $this->assertFalse($dated->is_open_ended_deferral);
    }

    // ── Partial acceptance is per-item and queryable ─────────────────────────

    public function test_partial_acceptance_records_an_independent_verdict_per_item(): void
    {
        $plan  = $this->presentedPlan();
        [$implant, $crown] = $plan->items->all();

        $decision = PlanDecision::create([
            'treatment_plan_id' => $plan->id,
            'decision'          => PlanDecision::PARTIALLY_ACCEPTED,
            'source'            => 'clinic',
        ]);

        PlanDecisionItem::create([
            'plan_decision_id' => $decision->id, 'treatment_plan_item_id' => $implant->id,
            'decision' => PlanDecisionItem::ACCEPTED,
        ]);
        PlanDecisionItem::create([
            'plan_decision_id' => $decision->id, 'treatment_plan_item_id' => $crown->id,
            'decision' => PlanDecisionItem::DEFERRED,
        ]);

        $this->assertTrue($decision->is_committing);
        $this->assertSame(2, $decision->items()->count());

        // Independently queryable — "who deferred a crown" is answerable in SQL.
        $this->assertSame(1, PlanDecisionItem::where('decision', PlanDecisionItem::DEFERRED)->count());
        $this->assertSame(
            PlanDecisionItem::ACCEPTED,
            $decision->items()->where('treatment_plan_item_id', $implant->id)->value('decision'),
        );

        // The four item verdicts stay distinct — never collapsed.
        $this->assertSame(
            ['accepted', 'deferred', 'rejected', 'not_yet_decided'],
            array_keys(PlanDecisionItem::DECISIONS),
        );

        // And item.status is NOT abused to carry any of this.
        $this->assertSame('pending', $crown->fresh()->status);
    }

    // ── The F-3 regression ───────────────────────────────────────────────────

    public function test_revert_no_longer_dies_without_an_authenticated_session(): void
    {
        $plan  = $this->presentedPlan();
        $actor = $this->admin();

        $this->actingAs($actor);
        app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, 'clinic');

        auth()->logout();   // queue worker / console / service-to-service

        app(TreatmentPlanAcceptanceService::class)->revert($plan->fresh(), 'Wrong plan', $actor, 'clinic');

        $this->assertNull($plan->fresh()->accepted_at);
    }

    /**
     * Reverting clears the accepted_at MIRROR, but the decision that was
     * genuinely recorded stays in history — the patient did accept, and that
     * happened. Correcting the record is a later decision row, not amnesia.
     */
    public function test_revert_clears_the_mirror_without_erasing_decision_history(): void
    {
        $plan  = $this->presentedPlan();
        $actor = $this->admin();
        $this->actingAs($actor);

        app(TreatmentPlanAcceptanceService::class)->accept($plan, $actor, 'clinic');
        app(TreatmentPlanAcceptanceService::class)->revert($plan->fresh(), 'Recorded on the wrong plan', $actor, 'clinic');

        $plan->refresh();

        $this->assertNull($plan->accepted_at);
        $this->assertSame(1, $plan->decisions()->count(), 'the acceptance still happened and is still on record');
    }
}
