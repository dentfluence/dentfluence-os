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
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.3e — SURFACES + LIFECYCLE LOCKDOWN.
 *
 * The decision verbs are now reachable from web and API, and the old ability to
 * manufacture lifecycle truth by writing treatment_plans.status directly is
 * closed on both surfaces.
 */
class PlanDecisionSurfacesTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

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
            'name' => 'Surface Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1,
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

    // ── WEB / API PARITY ─────────────────────────────────────────────────────

    public function test_web_and_api_rejection_produce_identical_truth(): void
    {
        foreach (['web', 'api'] as $surface) {
            $plan = $this->presentedPlan();

            if ($surface === 'web') {
                $this->actingAs($this->admin())
                    ->postJson(route('treatment-plans.reject', $plan), ['reason' => 'Too expensive'])
                    ->assertOk()->assertJson(['success' => true]);
            } else {
                Sanctum::actingAs($this->admin(), ['*']);
                $this->postJson("/api/v1/treatment-plans/{$plan->id}/reject", ['reason' => 'Too expensive'])
                    ->assertOk()->assertJson(['success' => true]);
            }

            $decision = $plan->fresh()->currentDecision();
            $this->assertSame(PlanDecision::REJECTED, $decision->decision, $surface);
            $this->assertSame('Too expensive', $decision->notes, $surface);
            $this->assertSame($surface === 'web' ? 'clinic' : 'mobile', $decision->source);

            $this->assertSame(TreatmentOpportunity::DECLINED,
                TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'), $surface);
            $this->assertSame('pending', $plan->fresh()->status, 'rejection never cancels the plan');
        }

        $this->assertSame(2, \App\Models\Activity::where('event', 'treatment_plan.rejected')->count());
    }

    public function test_web_and_api_deferral_produce_identical_truth(): void
    {
        $reviewOn = now()->addMonth()->toDateString();

        foreach (['web', 'api'] as $surface) {
            $plan = $this->presentedPlan();

            if ($surface === 'web') {
                $this->actingAs($this->admin())
                    ->postJson(route('treatment-plans.defer', $plan), ['defer_until' => $reviewOn])
                    ->assertOk();
            } else {
                Sanctum::actingAs($this->admin(), ['*']);
                $this->postJson("/api/v1/treatment-plans/{$plan->id}/defer", ['defer_until' => $reviewOn])
                    ->assertOk();
            }

            $decision = $plan->fresh()->currentDecision();
            $this->assertSame(PlanDecision::DEFERRED, $decision->decision, $surface);
            $this->assertSame($reviewOn, $decision->defer_until->toDateString(), $surface);

            // Still open — the patient may yet say yes — but snoozed.
            $opp = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->firstOrFail();
            $this->assertSame($reviewOn, $opp->follow_up_date->toDateString(), $surface);
            $this->assertFalse($opp->is_overdue, $surface);
        }

        $this->assertSame(2, TreatmentOpportunity::open()->count(), 'deferred stays open on both surfaces');
    }

    public function test_web_and_api_partial_acceptance_produce_identical_truth(): void
    {
        foreach (['web', 'api'] as $surface) {
            $plan = $this->presentedPlan();
            [$implant, $crown] = $plan->items->all();
            $payload = ['items' => [
                $implant->id => PlanDecisionItem::ACCEPTED,
                $crown->id   => PlanDecisionItem::REJECTED,
            ]];

            if ($surface === 'web') {
                $this->actingAs($this->admin())
                    ->postJson(route('treatment-plans.partial-accept', $plan), $payload)->assertOk();
            } else {
                Sanctum::actingAs($this->admin(), ['*']);
                $this->postJson("/api/v1/treatment-plans/{$plan->id}/partial-accept", $payload)->assertOk();
            }

            $plan->refresh();
            $decision = $plan->currentDecision();

            $this->assertSame(PlanDecision::PARTIALLY_ACCEPTED, $decision->decision, $surface);
            $this->assertSame(2, $decision->items()->count(), $surface);
            $this->assertNotNull($plan->accepted_at, $surface);
            $this->assertSame(TreatmentOpportunity::COMMITTED,
                TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'), $surface);

            // The rejected item is NOT cancelled — its status is untouched.
            $this->assertSame('pending', $crown->fresh()->status, $surface);
        }
    }

    public function test_no_decision_ever_produces_converted(): void
    {
        $reject  = $this->presentedPlan();
        $defer   = $this->presentedPlan();
        $partial = $this->presentedPlan();

        $this->actingAs($this->admin());
        $this->postJson(route('treatment-plans.reject', $reject))->assertOk();
        $this->postJson(route('treatment-plans.defer', $defer))->assertOk();
        $this->postJson(route('treatment-plans.partial-accept', $partial), [
            'items' => [$partial->items->first()->id => PlanDecisionItem::ACCEPTED],
        ])->assertOk();

        $this->assertSame(0, TreatmentOpportunity::where('status', TreatmentOpportunity::CONVERTED)->count(),
            'Converted means treatment started and is not implemented in Slice 2.3');
    }

    // ── LIFECYCLE LOCKDOWN ───────────────────────────────────────────────────

    public function test_lifecycle_status_cannot_be_set_through_the_web_edit_form(): void
    {
        $plan = $this->presentedPlan();

        foreach (['cancelled', 'completed', 'ongoing', 'pending'] as $status) {
            $this->actingAs($this->admin())
                ->putJson(route('treatment-plans.update', $plan), [
                    'consultation_id' => $plan->consultation_id,
                    'plan_name'       => $plan->plan_name,
                    'status'          => $status,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        }

        $this->assertSame('pending', $plan->fresh()->status, 'no lifecycle write got through');
    }

    public function test_the_lockdown_message_names_the_verb_the_user_actually_wants(): void
    {
        $plan = $this->presentedPlan();

        $response = $this->actingAs($this->admin())
            ->putJson(route('treatment-plans.update', $plan), [
                'consultation_id' => $plan->consultation_id,
                'plan_name'       => $plan->plan_name,
                'status'          => 'cancelled',
            ])->assertStatus(422);

        $message = $response->json('errors.status.0');

        $this->assertStringContainsString('Reject Plan', $message);
        $this->assertStringContainsString('Defer', $message);
    }

    public function test_lifecycle_status_cannot_be_set_through_the_api_either(): void
    {
        $plan = $this->presentedPlan();

        Sanctum::actingAs($this->admin(), ['*']);

        foreach (['cancelled', 'completed'] as $status) {
            $this->putJson("/api/v1/treatment-plans/{$plan->id}", [
                'plan_name' => $plan->plan_name,
                'status'    => $status,
            ])->assertStatus(422);
        }

        $this->assertSame('pending', $plan->fresh()->status);
    }

    public function test_an_ordinary_edit_still_works_without_a_status(): void
    {
        $plan = $this->presentedPlan();

        $this->actingAs($this->admin())
            ->putJson(route('treatment-plans.update', $plan), [
                'consultation_id' => $plan->consultation_id,
                'plan_name'       => 'Implant Plan (revised)',
                'doctor_notes'    => 'Discussed alternatives',
            ])->assertOk();

        $this->assertSame('Implant Plan (revised)', $plan->fresh()->plan_name);
    }

    // ── PERMISSIONS ──────────────────────────────────────────────────────────

    public function test_view_only_role_cannot_record_any_decision(): void
    {
        $plan   = $this->presentedPlan();
        $viewer = $this->userWithModulePerm('patients', view: true, edit: false, delete: false);

        foreach (['reject', 'defer', 'partial-accept'] as $verb) {
            $this->actingAs($viewer)
                ->postJson(url("/treatment-plans/{$plan->id}/{$verb}"))
                ->assertForbidden();
        }

        Sanctum::actingAs($this->fresh($viewer), ['*']);
        foreach (['reject', 'defer', 'partial-accept'] as $verb) {
            $this->postJson("/api/v1/treatment-plans/{$plan->id}/{$verb}")->assertForbidden();
        }

        $this->assertDatabaseCount('plan_decisions', 0);
    }

    public function test_an_arbitrarily_named_edit_role_can_record_decisions(): void
    {
        $plan   = $this->presentedPlan();
        $editor = $this->userWithModulePerm('patients', true, true, false,
            roleName: 'Evening Unicorn Clinician ' . uniqid());

        $this->actingAs($editor)
            ->postJson(route('treatment-plans.reject', $plan), ['reason' => 'Declined'])
            ->assertOk();

        $this->assertSame(PlanDecision::REJECTED, $plan->fresh()->currentDecision()->decision);
    }

    // ── TIMELINE INFERENCE RETIRED ───────────────────────────────────────────

    public function test_a_cancelled_plan_is_no_longer_reported_as_a_patient_rejection(): void
    {
        $plan = $this->presentedPlan();
        $plan->forceFill(['status' => 'cancelled'])->save();   // legacy/administrative state

        $journey = app(PatientJourneyService::class)->for($plan->patient->fresh(), null, 'clinical');
        $titles  = collect($journey['events'])->pluck('title')->implode(' | ');

        $this->assertStringContainsString('cancelled', strtolower($titles));
        $this->assertStringNotContainsString('rejected', strtolower($titles),
            'a cancelled plan asserts no patient decision — nobody recorded one');
    }

    public function test_a_real_rejection_still_appears_on_the_timeline(): void
    {
        $plan = $this->presentedPlan();

        $this->actingAs($this->admin())
            ->postJson(route('treatment-plans.reject', $plan), ['reason' => 'Cost'])->assertOk();

        $journey = app(PatientJourneyService::class)->for($plan->patient->fresh(), null, 'clinical');
        $titles  = collect($journey['events'])->pluck('title')->implode(' | ');

        $this->assertStringContainsString('rejected by patient', $titles);
    }
}
