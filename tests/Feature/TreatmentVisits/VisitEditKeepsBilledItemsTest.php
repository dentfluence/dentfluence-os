<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\BillingPrompt;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * INT-16 (security audit 24 Sep 2026) — editing a visit must never turn
 * already-billed work back into billable work.
 *
 * Before the fix, update() deleted every work line and re-created them all as
 * 'pending': an invoiced RCT came back billable after a note edit, the desk
 * got a fresh "Bill for" prompt, and the invoice link was lost.
 */
class VisitEditKeepsBilledItemsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    /** A visit with one INVOICED line (RCT, tooth 36). */
    private function billedVisit(): array
    {
        $patient = $this->makePatient();
        $visit   = $patient->treatmentVisits()->create($this->baseVisitPayload());
        $billed  = TreatmentVisitItem::create([
            'treatment_visit_id' => $visit->id,
            'patient_id'         => $patient->id,
            'treatment_name'     => 'RCT',
            'tooth_number'       => '36',
            'suggested_price'    => 6000,
            'billing_status'     => 'invoiced',
        ]);

        return [$visit, $billed];
    }

    private function row(TreatmentVisitItem $i, array $over = []): array
    {
        return array_merge([
            'treatment_plan_item_id' => null,
            'treatment_name'         => $i->treatment_name,
            'tooth_number'           => $i->tooth_number,
            'suggested_price'        => 6000,
        ], $over);
    }

    private function pendingPrompts(TreatmentVisit $visit): int
    {
        return BillingPrompt::where('trigger_type', 'treatment_visit')
            ->where('trigger_id', $visit->id)->where('status', 'pending')->count();
    }

    public function test_note_edit_from_web_keeps_the_billed_line_and_raises_no_prompt(): void
    {
        [$visit, $billed] = $this->billedVisit();

        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'notes'       => 'Edited note',
            'visit_items' => [$this->row($billed, ['id' => $billed->id])],
        ]))->assertOk();

        $this->assertSame(1, $visit->visitItems()->count());
        $this->assertDatabaseHas('treatment_visit_items', ['id' => $billed->id, 'billing_status' => 'invoiced']);
        $this->assertSame(0, $visit->visitItems()->where('billing_status', 'pending')->count());
        $this->assertSame(0, $this->pendingPrompts($visit));
    }

    public function test_client_without_ids_is_matched_by_treatment_and_tooth(): void
    {
        [$visit, $billed] = $this->billedVisit();

        // Mobile sends no id; tooth written differently must still match.
        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [$this->row($billed, ['treatment_name' => ' rct ', 'tooth_number' => ' 36 '])],
        ]))->assertOk();

        $this->assertSame([$billed->id], $visit->visitItems()->pluck('id')->all());
        $this->assertSame(0, $this->pendingPrompts($visit));
    }

    public function test_removing_a_billed_line_is_refused_and_nothing_changes(): void
    {
        [$visit, $billed] = $this->billedVisit();

        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [],
        ]))->assertStatus(422)->assertJsonValidationErrors('visit_items');

        $this->assertDatabaseHas('treatment_visit_items', ['id' => $billed->id, 'billing_status' => 'invoiced']);
    }

    public function test_changing_the_tooth_on_a_billed_line_is_refused(): void
    {
        [$visit, $billed] = $this->billedVisit();

        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [$this->row($billed, ['id' => $billed->id, 'tooth_number' => '46'])],
        ]))->assertStatus(422);

        $this->assertDatabaseHas('treatment_visit_items', ['id' => $billed->id, 'tooth_number' => '36']);
    }

    public function test_new_work_added_beside_billed_work_is_the_only_thing_prompted(): void
    {
        [$visit, $billed] = $this->billedVisit();

        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [
                $this->row($billed, ['id' => $billed->id]),
                ['treatment_name' => 'Scaling', 'tooth_number' => null, 'suggested_price' => 800],
            ],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', ['id' => $billed->id, 'billing_status' => 'invoiced']);
        $this->assertSame(['Scaling'], $visit->visitItems()->where('billing_status', 'pending')->pluck('treatment_name')->all());

        $prompt = BillingPrompt::where('trigger_id', $visit->id)->where('status', 'pending')->sole();
        $this->assertSame('Bill for: Scaling', $prompt->description);
    }

    public function test_doctor_can_still_record_the_outcome_on_a_billed_line(): void
    {
        [$visit, $billed] = $this->billedVisit();
        $outcome = array_key_first(TreatmentVisitItem::WORK_OUTCOMES);

        $this->actingAs($this->makeUser())->putJson(route('visits.update', $visit), $this->baseVisitPayload([
            'visit_items' => [$this->row($billed, ['id' => $billed->id, 'work_outcome' => $outcome, 'notes' => 'Obturation done'])],
        ]))->assertOk();

        $this->assertDatabaseHas('treatment_visit_items', [
            'id' => $billed->id, 'billing_status' => 'invoiced', 'work_outcome' => $outcome, 'notes' => 'Obturation done',
        ]);
    }
}
