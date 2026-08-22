<?php

namespace Tests\Feature\TreatmentVisits;

use App\Http\Middleware\CheckModulePermission;
use App\Models\Patient;
use App\Models\TreatmentVisit;
use App\Models\TreatmentVisitItem;
use App\Services\Clinical\DerivedProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\Feature\TreatmentVisits\Concerns\BuildsVisitFixtures;
use Tests\TestCase;

/**
 * REPEAT-WORK DETECTION vs. UNFINISHED TREATMENT (live defect, 2026-08-22).
 *
 * The defect: a doctor opened an RCT on tooth 46 on 15 Aug (BMP done, course
 * still running), returned on 21 Aug to continue it, and was told
 * "This may be repeat work" and made to type a justification. Repeat-work
 * detection matched only on (procedure, tooth) and had no idea whether the
 * earlier instance had ever been FINISHED.
 *
 * The rule, restored here: repeat work means doing again something that was
 * COMPLETED. Anything still in progress — or with no outcome on record at all
 * — is a continuation, and must pass silently.
 *
 * The completion answer is NOT re-derived for this feature. It comes from
 * DerivedProgressService on the frozen latest-valid-fact-wins rule, which is
 * the single canonical derivation for clinical progress (Slice 2.4c/2.4d).
 * These tests pin the SERVER side of that contract; the browser only reads the
 * map this produces and compares one string.
 */
class RepeatWorkCompletionGateTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;
    use BuildsVisitFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(CheckModulePermission::class);
    }

    private function progress(Patient $patient, ?int $excludeVisitId = null): array
    {
        return app(DerivedProgressService::class)
            ->deriveProcedureProgressForPatient($patient->id, $excludeVisitId);
    }

    /** Record one ad-hoc procedure on a given date, optionally with an outcome. */
    private function record(Patient $patient, string $date, string $treatment, ?string $tooth, ?string $outcome): TreatmentVisit
    {
        $this->actingAs($this->makeUser())
            ->postJson(route('visits.store', $patient), $this->baseVisitPayload([
                'visit_date'     => $date,
                'treatment_name' => $treatment,
                'tooth_number'   => $tooth,
                'visit_items'    => [[
                    'treatment_name' => $treatment,
                    'tooth_number'   => $tooth,
                    'work_outcome'   => $outcome,
                ]],
            ]))->assertOk();

        return TreatmentVisit::where('patient_id', $patient->id)
            ->orderByDesc('id')->firstOrFail();
    }

    // ── Scenario A — previous treatment INCOMPLETE ───────────────────────────

    public function test_scenario_a_an_unfinished_course_is_not_completed_so_it_cannot_be_repeat_work(): void
    {
        $patient = $this->makePatient();

        // 15 Aug — RCT 46 opened, BMP done, course still running.
        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_STARTED);

        $map = $this->progress($patient);

        $this->assertArrayHasKey('rct|46', $map);
        $this->assertNotSame('completed', $map['rct|46'],
            'An RCT still under way must never read as completed — that is what flagged a continuation as repeat work.');
    }

    public function test_worked_on_is_also_not_completed(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_STARTED);
        $this->record($patient, '2026-08-18', 'RCT', '46', TreatmentVisitItem::WORK_WORKED_ON);

        $this->assertSame('in_progress', $this->progress($patient)['rct|46']);
    }

    // ── Scenario B — previous treatment COMPLETED ────────────────────────────

    public function test_scenario_b_a_finished_course_reads_completed_so_repeat_work_can_be_flagged(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertSame('completed', $this->progress($patient)['rct|46']);
    }

    // ── Scenario C — different tooth ─────────────────────────────────────────

    public function test_scenario_c_completion_is_per_tooth(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $map = $this->progress($patient);

        $this->assertSame('completed', $map['rct|46']);
        $this->assertArrayNotHasKey('rct|47', $map, 'A finished RCT on 46 says nothing about 47.');
    }

    // ── Scenario D — different procedure ─────────────────────────────────────

    public function test_scenario_d_completion_is_per_procedure(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertArrayNotHasKey('composite filling|46', $this->progress($patient));
    }

    // ── The safe default ─────────────────────────────────────────────────────

    public function test_a_procedure_with_no_outcome_on_record_is_absent_from_the_map(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', null);

        // Absence is the point: the browser's gate is
        // `progress[key] !== 'completed'`, so an unrecorded procedure reads as
        // unfinished and passes silently. Every legacy Tulip row is this case.
        $this->assertArrayNotHasKey('rct|46', $this->progress($patient));
    }

    // ── Latest valid fact wins ───────────────────────────────────────────────

    public function test_a_completed_treatment_redone_later_reads_in_progress_again(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-03-02', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);
        $this->record($patient, '2026-07-09', 'RCT', '46', TreatmentVisitItem::WORK_WORKED_ON);

        $this->assertSame('in_progress', $this->progress($patient)['rct|46'],
            'Latest fact wins — re-opened work must not stay frozen as completed.');
    }

    public function test_the_newest_fact_wins_even_when_it_completes_an_older_open_course(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_STARTED);
        $this->record($patient, '2026-08-21', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertSame('completed', $this->progress($patient)['rct|46']);
    }

    // ── Shape of the map ─────────────────────────────────────────────────────

    public function test_a_multi_tooth_procedure_registers_under_every_tooth(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'Composite Filling', '16, 17', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $map = $this->progress($patient);

        $this->assertSame('completed', $map['composite filling|16']);
        $this->assertSame('completed', $map['composite filling|17']);
    }

    public function test_the_key_helper_and_the_map_agree(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertArrayHasKey(
            DerivedProgressService::procedureKey('RCT', '46'),
            $this->progress($patient),
        );
    }

    public function test_ad_hoc_work_with_no_plan_item_still_reaches_the_map(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'Scaling', '11', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $item = TreatmentVisitItem::where('treatment_name', 'Scaling')->firstOrFail();
        $this->assertNull($item->treatment_plan_item_id, 'this fixture is deliberately unplanned work');

        $this->assertSame('completed', $this->progress($patient)['scaling|11']);
    }

    // ── Validity ─────────────────────────────────────────────────────────────

    public function test_facts_from_a_soft_deleted_visit_are_ignored(): void
    {
        $patient = $this->makePatient();

        $visit = $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        // Soft-delete the visit directly, leaving its items behind — the exact
        // trap the 2.4c contract warns about (items do not cascade).
        TreatmentVisit::findOrFail($visit->id)->delete();

        $this->assertArrayNotHasKey('rct|46', $this->progress($patient),
            'Work on a deleted visit is not a clinical fact and must not create a repeat-work warning.');
    }

    public function test_the_visit_being_edited_can_be_excluded_from_its_own_history(): void
    {
        $patient = $this->makePatient();

        $visit = $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $this->assertSame('completed', $this->progress($patient)['rct|46']);
        $this->assertArrayNotHasKey('rct|46', $this->progress($patient, $visit->id));
    }

    // ── Delivery to the form ─────────────────────────────────────────────────

    public function test_the_visit_form_publishes_the_progress_map(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $this->record($patient, '2026-08-15', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        $response->assertSee('TV_PROCEDURE_PROGRESS', false);
        $response->assertSee('rct|46', false);
    }

    public function test_the_form_gates_the_warning_on_completion_rather_than_on_a_bare_match(): void
    {
        $user    = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->get(route('visits.create', $patient));

        $response->assertOk();
        // The gate itself, asserted as rendered output rather than as a claim
        // about a source file — the 2.4e precedent.
        $response->assertSee("this.procedureProgress[key] !== 'completed'", false);
    }

    public function test_saving_a_visit_returns_the_recomputed_map(): void
    {
        $user    = $this->makeUser();
        $patient = $this->makePatient();

        $response = $this->actingAs($user)->postJson(route('visits.store', $patient), $this->baseVisitPayload([
            'treatment_name' => 'RCT',
            'tooth_number'   => '46',
            'visit_items'    => [[
                'treatment_name' => 'RCT',
                'tooth_number'   => '46',
                'work_outcome'   => TreatmentVisitItem::WORK_COMPLETED_TODAY,
            ]],
        ]))->assertOk();

        // Without this the map would go stale inside one page session and the
        // second visit of the day would miss a genuine repeat.
        $this->assertSame('completed', $response->json('procedure_progress.rct|46'));
    }

    // ── Protection is not weakened ───────────────────────────────────────────

    public function test_a_genuine_repeat_on_finished_work_is_still_detectable(): void
    {
        $patient = $this->makePatient();

        $this->record($patient, '2026-03-02', 'RCT', '46', TreatmentVisitItem::WORK_COMPLETED_TODAY);

        // Same procedure, same tooth, months later, previous course finished:
        // this is precisely the case the warning exists for, and the map says so.
        $this->assertSame('completed', $this->progress($patient)['rct|46']);
    }
}
