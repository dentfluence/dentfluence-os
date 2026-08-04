<?php

namespace Tests\Feature\Clinical;

use App\Enums\PlanLifecycleState;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PlanDecision;
use App\Models\TreatmentOpportunity;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Models\User;
use App\Services\Billing\TreatmentPlanBillingService;
use App\Services\TreatmentPlan\PlanLifecycleService;
use App\Services\TreatmentPlan\TreatmentPlanAcceptanceService;
use App\Services\TreatmentPlan\TreatmentPlanPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * CANONICAL TREATMENT LIFECYCLE V1 — the contract, asserted.
 *
 * Consultation → Treatment Plan → Presentation → Opportunity → Acceptance
 *   → Treatment Visit → Performed Treatment → Billing
 *
 * These tests do not describe the code; they describe the architecture, and
 * they fail when the code drifts from it. The boundaries they defend:
 *
 *   §5   presentation is stamped once, by the clinic
 *   §7   acceptance is the only authorization to treat; decisions accumulate
 *   §8   Treatment Visit is the sole source of clinical truth
 *   §10  billing records performed work and never touches clinical truth
 *   §12  completion is clinical, scoped to what the patient accepted
 *   §15  one writer for plan lifecycle; the visit supplies facts, not state
 */
class CanonicalTreatmentLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function lifecycle(): PlanLifecycleService
    {
        return app(PlanLifecycleService::class);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Lifecycle Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private ?User $doctor = null;

    /** One treating clinician for the whole scenario, created on first use. */
    private function doctor(): User
    {
        return $this->doctor ??= User::factory()->create([
            'role'      => 'doctor',
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * A consultation with a diagnosis — the only legitimate origin of a plan (§3).
     *
     * patient_id, doctor_id and branch_id are the three NOT NULL columns on
     * consultations: a clinical observation always has a subject, an author and
     * a place, which is exactly what makes it citable downstream.
     */
    private function consultation(Patient $patient): Consultation
    {
        return Consultation::create([
            'patient_id'            => $patient->id,
            'doctor_id'             => $this->doctor()->id,
            'branch_id'             => $patient->branch_id,
            'consultation_date'     => now()->toDateString(),
            'provisional_diagnosis' => 'Irreversible pulpitis 16; caries 26',
        ]);
    }

    /** A six-tooth plan — the shape that exposes partial execution (§8). */
    private function planFor(Patient $patient, Consultation $consultation): TreatmentPlan
    {
        $plan = TreatmentPlan::create([
            'patient_id'      => $patient->id,
            'consultation_id' => $consultation->id,
            'plan_name'       => 'Option A',
            'status'          => 'pending',
            'rows'            => [],
            'total'           => 60000,
        ]);

        foreach (['16', '26', '36', '46', '11', '21'] as $tooth) {
            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id,
                'treatment_name'    => 'Composite Restoration',
                'tooth_number'      => $tooth,
                'unit_price'        => 10000,
                'units'             => 1,
                'total'             => 10000,
            ]);
        }

        return $plan->fresh('items');
    }

    /** Record real performed work — the clinical fact (§8, §9). */
    private function recordPerformedWork(TreatmentPlan $plan, array $items, string $outcome = TreatmentVisitItem::WORK_COMPLETED_TODAY): TreatmentVisit
    {
        $visit = TreatmentVisit::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'doctor_id'         => $this->doctor()->id,
            'visit_date'        => now()->toDateString(),
            'treatment_name'    => 'Composite Restoration',
        ]);

        foreach ($items as $item) {
            TreatmentVisitItem::create([
                'treatment_visit_id'     => $visit->id,
                'patient_id'             => $plan->patient_id,
                'treatment_name'         => $item->treatment_name,
                'tooth_number'           => $item->tooth_number,
                'treatment_plan_item_id' => $item->id,
                'work_outcome'           => $outcome,
                'billing_status'         => 'pending',
            ]);
        }

        return $visit;
    }

    // ── The canonical path, end to end ──────────────────────────────────────

    public function test_the_full_canonical_lifecycle(): void
    {
        $patient      = $this->patient();
        $consultation = $this->consultation($patient);
        $plan         = $this->planFor($patient, $consultation);
        $clinician    = $this->doctor();

        // Draft — the patient has not seen it.
        $this->assertSame(PlanLifecycleState::Draft, $this->lifecycle()->derive($plan));

        // Presentation — the clinic shows it. Opportunity comes into existence.
        app(TreatmentPlanPresentationService::class)->markPresented($plan, $clinician, 'clinic');
        $plan->refresh();

        $this->assertNotNull($plan->presented_at);
        $this->assertSame(PlanLifecycleState::DecisionPending, $this->lifecycle()->derive($plan));
        $this->assertSame('quoted', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // Acceptance — the only authorization to treat.
        app(TreatmentPlanAcceptanceService::class)->accept($plan, $clinician);
        $plan->refresh();

        $this->assertSame(PlanLifecycleState::Accepted, $this->lifecycle()->derive($plan));
        $this->assertTrue($plan->authorizes_treatment);
        $this->assertSame(TreatmentOpportunity::COMMITTED,
            TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // Visit 1 — two of six teeth treated. Partial execution is normal.
        $items = $plan->items()->orderBy('id')->get();
        $this->recordPerformedWork($plan, [$items[0], $items[1]]);
        $this->lifecycle()->reactToClinicalFact($plan->fresh());
        $plan->refresh();

        $this->assertSame(PlanLifecycleState::TreatmentStarted, $this->lifecycle()->derive($plan));
        $this->assertSame('ongoing', $plan->status);
        $this->assertSame(TreatmentOpportunity::CONVERTED,
            TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));

        // The remaining four teeth are still planned and untreated.
        $this->assertSame(4, $items->slice(2)->count());
        $this->assertNotSame(PlanLifecycleState::TreatmentComplete, $this->lifecycle()->derive($plan));

        // Visits 2 and 3 — the rest of the accepted work is performed.
        $this->recordPerformedWork($plan, [$items[2], $items[3]]);
        $this->recordPerformedWork($plan, [$items[4], $items[5]]);
        $this->lifecycle()->reactToClinicalFact($plan->fresh());
        $plan->refresh();

        // Completion is CLINICAL — reached without a single invoice existing.
        $this->assertSame(PlanLifecycleState::TreatmentComplete, $this->lifecycle()->derive($plan));
        $this->assertSame('completed', $plan->status);
        $this->assertSame(0, $plan->invoices()->count());
    }

    // ── §15 · one writer for plan lifecycle ─────────────────────────────────

    public function test_billing_never_completes_a_plan(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');
        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh());
        $plan->refresh();

        // Invoice every tooth on the plan. No clinical work has been recorded.
        $billing = app(TreatmentPlanBillingService::class);
        $billing->ensurePlanTeeth($plan);
        $toothIds = \App\Models\TreatmentPlanItemTooth::whereIn(
            'treatment_plan_item_id', $plan->items()->pluck('id')
        )->pluck('id')->all();

        $billing->createInvoiceFromSelection($plan, $toothIds);
        $plan->refresh();

        // Fully invoiced, but nothing was performed — so it is NOT complete.
        $this->assertNotSame('completed', $plan->status);
        $this->assertSame(PlanLifecycleState::Accepted, $this->lifecycle()->derive($plan));
    }

    public function test_a_visit_supplies_facts_and_the_plan_writes_its_own_state(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');
        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh());
        $plan->refresh();

        $items = $plan->items()->orderBy('id')->get();
        $this->recordPerformedWork($plan, [$items[0]]);

        // The fact alone does not move the plan; the plan re-derives from it.
        $this->lifecycle()->reactToClinicalFact($plan->fresh());

        $this->assertSame(PlanLifecycleState::TreatmentStarted,
            $this->lifecycle()->derive($plan->fresh()));
    }

    public function test_completion_is_scoped_to_what_the_patient_accepted(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');

        $items = $plan->items()->orderBy('id')->get();

        // The patient accepts two teeth and defers the rest.
        $decisions = [];
        foreach ($items as $i => $item) {
            $decisions[$item->id] = $i < 2 ? 'accepted' : 'deferred';
        }
        app(TreatmentPlanAcceptanceService::class)->acceptPartially($plan->fresh(), $decisions);

        $this->recordPerformedWork($plan, [$items[0], $items[1]]);
        $this->lifecycle()->reactToClinicalFact($plan->fresh());

        // The accepted work is delivered, so the plan is complete — the
        // deferred teeth do not hold it open.
        $this->assertSame(PlanLifecycleState::TreatmentComplete,
            $this->lifecycle()->derive($plan->fresh()));
    }

    // ── §5 · presentation truth ─────────────────────────────────────────────

    public function test_presentation_is_stamped_once_and_cannot_be_rewritten(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        $first = app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');
        $this->assertTrue($first['first_presentation']);

        $stampedAt = $plan->fresh()->presented_at;

        $second = app(TreatmentPlanPresentationService::class)->markPresented($plan->fresh(), null, 'clinic');
        $this->assertFalse($second['first_presentation']);
        $this->assertEquals($stampedAt, $plan->fresh()->presented_at);
    }

    public function test_a_presented_plan_refuses_any_change_to_its_presentation(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');

        $this->expectException(\RuntimeException::class);
        $plan->fresh()->update(['presented_at' => null]);
    }

    // ── §7 · decision truth ─────────────────────────────────────────────────

    public function test_acceptance_is_idempotent_and_keeps_the_original_moment(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');

        $acceptance = app(TreatmentPlanAcceptanceService::class);
        $acceptance->accept($plan->fresh());

        $acceptedAt = $plan->fresh()->accepted_at;

        $this->travel(2)->minutes();
        $acceptance->accept($plan->fresh());
        $acceptance->accept($plan->fresh());
        $this->travelBack();

        $this->assertSame(1, PlanDecision::where('treatment_plan_id', $plan->id)
            ->where('decision', PlanDecision::ACCEPTED)->count());
        $this->assertEquals($acceptedAt, $plan->fresh()->accepted_at);
    }

    public function test_reversing_an_acceptance_appends_a_decision_and_reopens_follow_up(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');
        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh());
        app(TreatmentPlanAcceptanceService::class)->revert($plan->fresh(), 'Patient changed their mind');

        $plan->refresh();

        // History accumulates — the acceptance is still on record.
        $this->assertSame(1, PlanDecision::where('treatment_plan_id', $plan->id)
            ->where('decision', PlanDecision::ACCEPTED)->count());
        $this->assertSame(PlanDecision::REVERTED, $plan->currentDecision()->decision);

        // The plan is awaiting a decision again, and so is the pipeline.
        $this->assertNull($plan->accepted_at);
        $this->assertTrue($plan->is_decision_pending);
        $this->assertSame(PlanLifecycleState::DecisionPending, $this->lifecycle()->derive($plan));
        $this->assertSame('quoted', TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));
    }

    public function test_a_patient_decline_on_their_own_link_reaches_the_decision_ledger(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');

        // The public journey path writes through the same door as the clinic.
        app(TreatmentPlanAcceptanceService::class)->reject(
            $plan->fresh(), 'Declined via Case Acceptance journey', null, 'case_acceptance'
        );

        $decision = $plan->fresh()->currentDecision();

        $this->assertSame(PlanDecision::REJECTED, $decision->decision);
        $this->assertSame('case_acceptance', $decision->source);
        $this->assertSame(PlanLifecycleState::Declined, $this->lifecycle()->derive($plan->fresh()));
    }

    public function test_every_patient_facing_decline_channel_reaches_the_decision_ledger(): void
    {
        // §7 — the channel is not the truth, the decision is. Both public
        // channels must land in the same ledger as a chairside decision.
        foreach (['case_acceptance', 'smart_presentation'] as $channel) {
            $patient = $this->patient();
            $plan    = $this->planFor($patient, $this->consultation($patient));

            app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');

            app(TreatmentPlanAcceptanceService::class)->reject(
                $plan->fresh(), 'Declined via ' . $channel, null, $channel
            );

            $decision = $plan->fresh()->currentDecision();

            $this->assertSame(PlanDecision::REJECTED, $decision->decision,
                $channel . ' must write a decision, not just a pipeline stage');
            $this->assertSame($channel, $decision->source);
            $this->assertSame(TreatmentOpportunity::DECLINED,
                TreatmentOpportunity::where('treatment_plan_id', $plan->id)->value('status'));
        }
    }

    public function test_an_unaccepted_plan_does_not_authorize_treatment(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));

        $this->assertFalse($plan->authorizes_treatment);

        app(TreatmentPlanPresentationService::class)->markPresented($plan, null, 'clinic');
        $this->assertFalse($plan->fresh()->authorizes_treatment);

        app(TreatmentPlanAcceptanceService::class)->accept($plan->fresh());
        $this->assertTrue($plan->fresh()->authorizes_treatment);
    }

    // ── §14 · the plan owns its own contents ────────────────────────────────

    public function test_an_item_cannot_be_moved_between_plans_by_a_client(): void
    {
        $patient = $this->patient();
        $consult = $this->consultation($patient);
        $planA   = $this->planFor($patient, $consult);
        $planB   = $this->planFor($patient, $consult);

        $foreignItem = $planA->items()->first();
        $user        = $this->userWithModulePerm('patients', true, true, true);

        $this->actingAs($this->fresh($user))->putJson(route('treatment-plans.update', $planB), [
            'items' => [[
                'id'             => $foreignItem->id,
                'treatment_name' => 'Hijacked',
                'unit_price'     => 1,
            ]],
        ]);

        $foreignItem->refresh();

        $this->assertSame($planA->id, $foreignItem->treatment_plan_id);
        $this->assertSame('Composite Restoration', $foreignItem->treatment_name);
    }

    public function test_item_lifecycle_state_is_never_client_supplied(): void
    {
        $patient = $this->patient();
        $plan    = $this->planFor($patient, $this->consultation($patient));
        $item    = $plan->items()->first();
        $user    = $this->userWithModulePerm('patients', true, true, true);

        $this->actingAs($this->fresh($user))->putJson(route('treatment-plans.update', $plan), [
            'items' => [[
                'id'             => $item->id,
                'treatment_name' => $item->treatment_name,
                'unit_price'     => $item->unit_price,
                'status'         => 'completed',
            ]],
        ])->assertStatus(422);

        $this->assertSame('pending', $item->fresh()->status);
    }

    // ── §14 · authorization ─────────────────────────────────────────────────

    public function test_a_plan_from_another_branch_is_not_readable(): void
    {
        $otherBranchPatient = Patient::create([
            'name'      => 'Other Branch Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 2,
        ]);

        $plan = $this->planFor($otherBranchPatient, $this->consultation($otherBranchPatient));
        $user = $this->userWithModulePerm('patients', true, true, true);

        $this->actingAs($this->fresh($user))
            ->getJson(route('treatment-plans.items', $plan))
            ->assertStatus(404);
    }

    public function test_plans_from_different_patients_cannot_be_printed_together(): void
    {
        $patientA = $this->patient();
        $patientB = $this->patient();

        $planA = $this->planFor($patientA, $this->consultation($patientA));
        $planB = $this->planFor($patientB, $this->consultation($patientB));

        $user = $this->userWithModulePerm('patients', true, true, true);

        $this->actingAs($this->fresh($user))
            ->get(route('treatment-plans.print', ['ids' => [$planA->id, $planB->id]]))
            ->assertStatus(400);
    }
}
