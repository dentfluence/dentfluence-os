<?php

namespace Tests\Feature\Clinical;

use App\Models\Module;
use App\Models\Patient;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\Patient\PatientJourneyService;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 2 · Slice 2.2 — PLAN PRESENTATION TRUTH.
 *
 * "The patient was actually shown this plan" is now a clinical fact
 * (treatment_plans.presented_at) written by ONE canonical service used by web
 * and API alike — not an inferred side-effect of an Opportunity reaching
 * 'quoted' (the Slice 2.1 finding).
 *
 * The invariant this slice must never break: PRESENTED IS NOT A DECISION.
 */
class PlanPresentationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Presentation Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function plan(Patient $patient, array $overrides = []): TreatmentPlan
    {
        $plan = TreatmentPlan::create(array_merge([
            'patient_id' => $patient->id,
            'plan_name'  => 'Implant Plan',
            'status'     => 'pending',
            'rows'       => [],
            'total'      => 45000,
        ], $overrides));

        TreatmentPlanItem::create([
            'treatment_plan_id' => $plan->id,
            'treatment_name'    => 'Implant',
            'unit_price'        => 45000,
            'units'             => 1,
            'total'             => 45000,
        ]);

        return $plan->fresh('items');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    // ── 1–2. Created ≠ presented ─────────────────────────────────────────────

    public function test_creating_a_plan_does_not_mark_it_presented(): void
    {
        $plan = $this->plan($this->patient());

        $this->assertNull($plan->presented_at);
        $this->assertFalse($plan->is_presented);
    }

    public function test_marking_presented_sets_presented_at(): void
    {
        $plan  = $this->plan($this->patient());
        $actor = $this->admin();

        $result = app(TreatmentPlanPresentationService::class)->markPresented($plan, $actor, 'clinic');

        $this->assertTrue($result['first_presentation']);
        $this->assertNotNull($plan->fresh()->presented_at);
        $this->assertTrue($plan->fresh()->is_presented);
    }

    // ── 3–5. Presented is NOT a decision ─────────────────────────────────────

    public function test_presentation_does_not_accept_start_or_convert(): void
    {
        $plan = $this->plan($this->patient());

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        $plan->refresh();

        $this->assertNull($plan->accepted_at, 'presentation must never accept a plan');
        $this->assertSame('pending', $plan->status, 'presentation must never start treatment');
        $this->assertTrue($plan->is_decision_pending, 'presented + undecided = Decision Pending');
        $this->assertSame(0, $plan->patient->treatmentVisits()->count());
        $this->assertDatabaseCount('invoices', 0);

        // The opportunity is projected to 'quoted' (estimate given) — NOT
        // 'completed', which is what acceptance means on that board.
        $opp = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->firstOrFail();
        $this->assertSame('quoted', $opp->status);
    }

    // ── 6. Repetition never destroys the original truth ──────────────────────

    public function test_presenting_again_never_overwrites_the_first_presentation(): void
    {
        $plan    = $this->plan($this->patient());
        $service = app(TreatmentPlanPresentationService::class);

        $first = $service->markPresented($plan, $this->admin(), 'clinic');
        $original = $plan->fresh()->presented_at;

        $this->travel(2)->days();

        $again = $service->markPresented($plan->fresh(), $this->admin(), 'clinic');

        $this->assertTrue($first['first_presentation']);
        $this->assertFalse($again['first_presentation']);
        $this->assertEquals($original->toDateTimeString(), $plan->fresh()->presented_at->toDateTimeString(),
            'the original presentation date must survive re-presentation');

        // …but the re-presentation IS in the history.
        $this->assertSame(2, \App\Models\Activity::where('event', 'treatment_plan.presented')->count());
    }

    // ── 7. Activity event ────────────────────────────────────────────────────

    public function test_presentation_records_one_meaningful_activity_event(): void
    {
        $plan  = $this->plan($this->patient());
        $actor = $this->admin();

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $actor, 'clinic');

        $activity = \App\Models\Activity::where('event', 'treatment_plan.presented')->firstOrFail();

        $this->assertSame($actor->id, $activity->actor_id);
        $this->assertSame($plan->id, $activity->metadata['plan_id']);
        $this->assertSame($plan->patient_id, $activity->metadata['patient_id']);
        $this->assertTrue($activity->metadata['first_presentation']);
        $this->assertNotNull($activity->occurred_at);
    }

    // ── 8–9. Web and API both use the canonical service ──────────────────────

    public function test_web_route_records_the_clinical_fact(): void
    {
        $plan = $this->plan($this->patient());

        $this->actingAs($this->admin())
            ->postJson(route('treatment-plans.mark-presented', $plan))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($plan->fresh()->presented_at);
        $this->assertDatabaseHas('activities', ['event' => 'treatment_plan.presented']);
    }

    public function test_api_route_records_the_same_clinical_fact(): void
    {
        // Slice 2.1 found presentation was web-only; mobile could not record it.
        $plan = $this->plan($this->patient());

        Sanctum::actingAs($this->admin(), ['*']);

        $this->postJson("/api/v1/treatment-plans/{$plan->id}/mark-presented")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($plan->fresh()->presented_at);

        $activity = \App\Models\Activity::where('event', 'treatment_plan.presented')->firstOrFail();
        $this->assertSame('mobile', $activity->metadata['source']);
    }

    // ── 10–12. Authorization: owner-configured, never job title ──────────────

    public function test_view_only_role_cannot_mark_presented_on_either_surface(): void
    {
        $plan   = $this->plan($this->patient());
        $viewer = $this->userWithModulePerm('patients', view: true, edit: false, delete: false);

        $this->actingAs($viewer)
            ->postJson(route('treatment-plans.mark-presented', $plan))
            ->assertForbidden();

        Sanctum::actingAs($this->fresh($viewer), ['*']);
        $this->postJson("/api/v1/treatment-plans/{$plan->id}/mark-presented")->assertForbidden();

        $this->assertNull($plan->fresh()->presented_at);
    }

    public function test_edit_role_with_an_arbitrary_name_can_mark_presented(): void
    {
        // Authorization follows permissions, never the role's name.
        $plan   = $this->plan($this->patient());
        $editor = $this->userWithModulePerm('patients', true, true, false,
            roleName: 'Evening Unicorn Clinician ' . uniqid());

        $this->actingAs($editor)
            ->postJson(route('treatment-plans.mark-presented', $plan))
            ->assertOk();

        $this->assertNotNull($plan->fresh()->presented_at);

        // …and the same role works on the API.
        $plan2 = $this->plan($this->patient());
        Sanctum::actingAs($this->fresh($editor), ['*']);
        $this->postJson("/api/v1/treatment-plans/{$plan2->id}/mark-presented")->assertOk();
        $this->assertNotNull($plan2->fresh()->presented_at);
    }

    // ── 13. Journey timeline distinguishes Created from Presented ────────────

    public function test_patient_journey_shows_created_and_presented_as_distinct_facts(): void
    {
        $patient = $this->patient();
        $plan    = $this->plan($patient);
        $actor   = $this->admin();

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $actor, 'clinic');

        $journey = app(PatientJourneyService::class)->for($patient, null, 'clinical');
        $titles  = collect($journey['events'])->pluck('title')->implode(' | ');

        $this->assertStringContainsString('Treatment plan created', $titles);
        $this->assertStringContainsString('Treatment plan presented to patient', $titles);
        $this->assertStringNotContainsString('Treatment plan accepted', $titles);
    }

    // ── 14. Existing acceptance behaviour intact ─────────────────────────────

    public function test_acceptance_still_works_and_presentation_does_not_disturb_it(): void
    {
        $plan  = $this->plan($this->patient());
        $actor = $this->admin();

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $actor, 'clinic');
        $presentedAt = $plan->fresh()->presented_at;

        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh(), $actor, 'clinic');

        $plan->refresh();

        $this->assertNotNull($plan->accepted_at);
        $this->assertSame('ongoing', $plan->status, 'existing acceptance behaviour unchanged');
        $this->assertEquals($presentedAt->toDateTimeString(), $plan->presented_at->toDateTimeString(),
            'acceptance must not disturb the presentation fact');
        $this->assertFalse($plan->is_decision_pending);

        // One opportunity per plan, now at converted.
        $opps = TreatmentOpportunity::where('treatment_plan_id', $plan->id)->get();
        $this->assertCount(1, $opps);
        $this->assertSame('completed', $opps->first()->status);
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    public function test_a_cancelled_plan_cannot_be_presented(): void
    {
        $plan = $this->plan($this->patient(), ['status' => 'cancelled']);

        $this->expectException(\RuntimeException::class);

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');
    }

    // ── 2.2-fix: PRE state must never manufacture clinical truth ─────────────

    public function test_an_opportunity_alone_never_makes_a_plan_read_as_presented(): void
    {
        // ROOT CAUSE of the CEO-found defect: the plan card derived
        // "presented" from "does this plan have an Opportunity row", so PRE
        // pipeline state silently became clinical truth and hid the action.
        $patient = $this->patient();
        $plan    = $this->plan($patient);

        TreatmentOpportunity::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'type'              => 'other',
            'label'             => 'Implant',
            'status'            => 'quoted',
        ]);

        $this->assertFalse($plan->fresh()->is_presented);

        // The clinical card must still offer the action, and must not show
        // commercial pipeline language. (Plans render in the lazy tab fragment.)
        $html = $this->actingAs($this->admin())
            ->get(route('patients.tab', ['patient' => $patient, 'tab' => 'treatment-plan']))
            ->assertOk()
            ->getContent();

        // ONE action, ONE label — presenting the plan and giving the estimate are
        // the same clinical moment, so the card must never offer them separately.
        $this->assertStringContainsString('Present Plan / Give Estimate', $html);
        $this->assertStringNotContainsString('Estimate Given', $html);   // PRE status, not a clinical badge
        $this->assertStringNotContainsString('Mark as Estimate', $html); // no second action, ever

        // …and the plan JSON handed to the card says not presented.
        $this->assertMatchesRegularExpression(
            '/"id":' . $plan->id . ',.*?"is_presented":false/s',
            $html,
        );
    }

    public function test_presenting_projects_onto_the_opportunity_board_one_way_only(): void
    {
        $plan = $this->plan($this->patient());

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        // Clinical → PRE projection is expected and explicitly tested.
        $this->assertSame('quoted', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // The reverse direction does not exist: moving the board by hand
        // leaves the clinical fact exactly as the clinician recorded it.
        $original = $plan->fresh()->presented_at;
        TreatmentOpportunity::where('treatment_plan_id', $plan->id)->update(['status' => 'discussed']);

        $this->assertEquals($original->toDateTimeString(), $plan->fresh()->presented_at->toDateTimeString());
    }

    public function test_accept_is_offered_only_after_the_plan_has_been_presented(): void
    {
        $patient = $this->patient();
        $plan    = $this->plan($patient);
        $admin   = $this->admin();

        $tab = ['patient' => $patient, 'tab' => 'treatment-plan'];

        $before = $this->actingAs($admin)->get(route('patients.tab', $tab))->getContent();
        $this->assertMatchesRegularExpression(
            '/"id":' . $plan->id . ',.*?"is_presented":false/s', $before,
        );

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $admin, 'clinic');

        $after = $this->actingAs($admin)->get(route('patients.tab', $tab))->getContent();
        $this->assertMatchesRegularExpression(
            '/"id":' . $plan->id . ',.*?"is_presented":true/s', $after,
        );
        $this->assertMatchesRegularExpression(
            '/"id":' . $plan->id . ',.*?"presented_at":"[^"]+"/s', $after,
        );
    }

    public function test_presenting_and_giving_the_estimate_are_one_action_with_one_timestamp(): void
    {
        // CEO semantics: in a real consultation the treatment, alternatives and
        // fees are discussed together. "Plan presented" and "estimate given" are
        // the SAME event — one click, one column, one ledger entry. The PRE
        // board's 'quoted' is a representation of it, never a second action.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('treatment_plans', 'estimate_given_at'),
            'there must be no separate estimate timestamp');

        $plan = $this->plan($this->patient());

        app(TreatmentPlanPresentationService::class)->markPresented($plan, $this->admin(), 'clinic');

        // Exactly one clinical event for the one action…
        $events = \App\Models\Activity::where('subject_type', TreatmentPlan::class)
            ->where('subject_id', $plan->id)
            ->pluck('event');

        $this->assertSame(['treatment_plan.presented'], $events->all(),
            'one action must write exactly one clinical event');

        // …and the commercial representation followed automatically, with no
        // second staff action required.
        $this->assertSame('quoted', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));
        $this->assertNotNull($plan->fresh()->presented_at);
    }

    public function test_historical_plans_are_not_backfilled(): void
    {
        // A plan that reached the Opportunity board before Slice 2.2 has no
        // clinical presentation fact. Nothing invents one.
        $plan = $this->plan($this->patient());

        TreatmentOpportunity::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'type'              => 'implant',
            'status'            => 'quoted',
        ]);

        $this->assertNull($plan->fresh()->presented_at,
            'historical opportunity rows must never be converted into clinical presentation truth');
    }
}
