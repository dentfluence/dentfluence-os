<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\BillingPrompt;
use App\Models\TreatmentVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * Treatment Visits — CRUD, validation, and transactional integrity.
 *
 * Module permission middleware is disabled here (as in the existing
 * TreatmentCreateTest convention) because these tests target business logic,
 * not the access-control layer. Authorization is covered separately in
 * TreatmentVisitAuthorizationTest.
 */
class TreatmentVisitCrudTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_web_can_create_a_treatment_visit(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(
            route('visits.store', $patient),
            $this->baseVisitPayload(['notes' => 'First molar filling'])
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('treatment_visits', [
            'patient_id' => $patient->id,
            'notes'      => 'First molar filling',
            'status'     => 'completed',
        ]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_web_can_update_a_treatment_visit(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $response = $this->actingAs($user)->putJson(
            route('visits.update', $visit),
            $this->baseVisitPayload(['notes' => 'Updated note', 'status' => 'in_chair'])
        );

        $response->assertOk();
        $this->assertDatabaseHas('treatment_visits', [
            'id'     => $visit->id,
            'notes'  => 'Updated note',
            'status' => 'in_chair',
        ]);
    }

    // ── Delete / soft delete ─────────────────────────────────────────────────

    public function test_web_can_delete_a_treatment_visit(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $response = $this->actingAs($user)->deleteJson(route('visits.destroy', $visit));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Soft delete: the row still exists but deleted_at is set, and the
        // model is excluded from default (non-trashed) queries.
        $this->assertSoftDeleted('treatment_visits', ['id' => $visit->id]);
        $this->assertNull(TreatmentVisit::find($visit->id));
        $this->assertNotNull(TreatmentVisit::withTrashed()->find($visit->id));
    }

    /**
     * EXPECTED TO FAIL against current code.
     *
     * Per the 2026-08-05 audit (§2.5, §7 finding "Soft-delete does not
     * cascade to child rows"): TreatmentVisitController::destroy() calls a
     * plain soft $visit->delete(); treatment_visit_items has a
     * cascadeOnDelete FK that only fires on a hard delete, so a "deleted"
     * visit's items and billing prompts remain live. This test encodes the
     * CORRECT/expected behavior (dependents should be cleaned up or at least
     * excluded) so it documents the defect rather than silently passing.
     * Do not "fix" this by editing production code — report it as failing.
     */
    public function test_soft_deleting_a_visit_removes_its_dependent_items_and_prompts(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());

        $item = $visit->visitItems()->create([
            'patient_id'      => $patient->id,
            'treatment_name'  => 'Scaling',
            'billing_status'  => 'pending',
        ]);
        $prompt = BillingPrompt::create([
            'patient_id'   => $patient->id,
            'trigger_type' => 'treatment_visit',
            'trigger_id'   => $visit->id,
            'description'  => 'Bill for: Scaling',
            'status'       => 'pending',
        ]);

        $this->actingAs($user)->deleteJson(route('visits.destroy', $visit))->assertOk();

        // Expected: dependent rows are cleaned up (or at minimum no longer
        // "pending"/live) once the parent visit is gone.
        $this->assertDatabaseMissing('treatment_visit_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('billing_prompts', ['id' => $prompt->id, 'status' => 'pending']);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_missing_required_fields_are_rejected(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_date', 'visit_type', 'status']);
        $this->assertDatabaseCount('treatment_visits', 0);
    }

    public function test_invalid_enum_values_are_rejected(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_type' => 'not-a-real-type',
            'status'     => 'not-a-real-status',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_type', 'status']);
        $this->assertDatabaseCount('treatment_visits', 0);
    }

    public function test_repeat_reason_is_required_when_is_repeat_is_true(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'visit_items' => [[
                'treatment_name' => 'RCT',
                'is_repeat'      => true,
                // repeat_reason intentionally omitted
            ]],
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visit_items.0.repeat_reason']);
    }

    // ── Transaction rollback ─────────────────────────────────────────────────

    /**
     * TreatmentVisitService::create() wraps the visit insert + item inserts
     * in a single DB::transaction. guardPlanItemOwnership() throws a
     * ValidationException when a visit_item references a plan item that
     * doesn't belong to the visit's own plan. Because this throw happens
     * AFTER the treatment_visits row has already been inserted inside the
     * same transaction, a working rollback means NO visit row should survive
     * the failed request.
     */
    public function test_a_rejected_visit_item_rolls_back_the_entire_visit_creation(): void
    {
        $user     = $this->makeUser();
        $patientA = $this->makePatient();
        $patientB = $this->makePatient();

        [$planA, ] = $this->makeAcceptedPlanWithItem($patientA, 'RCT');
        [$planB, $itemB] = $this->makeAcceptedPlanWithItem($patientB, 'Crown');

        // Visit is linked to Plan A but the item references Plan B's item.
        $response = $this->actingAs($user)->postJson(route('visits.store', $patientA), $this->baseVisitPayload([
            'treatment_plan_id' => $planA->id,
            'visit_items'       => [[
                'treatment_plan_item_id' => $itemB->id,
                'treatment_name'         => 'Crown',
            ]],
        ]));

        $response->assertStatus(422);

        // Rollback proof: the transaction must have undone the earlier
        // treatment_visits insert, not just skipped the item insert.
        $this->assertDatabaseCount('treatment_visits', 0);
        $this->assertDatabaseCount('treatment_visit_items', 0);
        $this->assertDatabaseCount('billing_prompts', 0);
    }
}
