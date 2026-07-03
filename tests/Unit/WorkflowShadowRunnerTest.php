<?php

namespace Tests\Unit;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowEngine;
use App\Services\Workflow\WorkflowShadowRunner;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5 Workflow Engine Slice 2 shadow-run.
 *
 * Exercises WorkflowShadowRunner directly against a real `rct_staging`-shaped
 * template (created here, not relying on the migration seed, so these tests
 * don't depend on migration state). No HTTP/controller layer involved.
 */
class WorkflowShadowRunnerTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowShadowRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
        $this->runner = new WorkflowShadowRunner(new WorkflowEngine());

        // updateOrCreate, not create() — the workflow_templates migration
        // permanently seeds both of these keys (by design, so production has
        // them after `migrate`). Under RefreshDatabase against a real MySQL
        // test DB, migrations run once and each test only gets a transaction
        // rollback, so the seeded row already exists before this setUp() runs.
        // A plain create() would collide with it on the unique `key` column.
        WorkflowTemplate::updateOrCreate(
            ['key' => 'rct_staging'],
            [
                'name'    => 'RCT Staging',
                'version' => 1,
                'steps'   => [
                    ['key' => 'diagnosis',       'label' => 'Diagnosis & X-ray',     'min_gap_days_from_previous' => 0],
                    ['key' => 'access',          'label' => 'Access Opening',        'min_gap_days_from_previous' => 0],
                    ['key' => 'instrumentation', 'label' => 'Canal Preparation',     'min_gap_days_from_previous' => 0],
                    ['key' => 'obturation',      'label' => 'Obturation',            'min_gap_days_from_previous' => 3],
                    ['key' => 'review',          'label' => 'Post-op Review',        'min_gap_days_from_previous' => 7],
                    ['key' => 'crown',           'label' => 'Crown Placement',       'min_gap_days_from_previous' => 14],
                ],
                'active'  => true,
            ]
        );

        WorkflowTemplate::updateOrCreate(
            ['key' => 'implant_staging'],
            [
                'name'    => 'Implant Staging',
                'version' => 1,
                'steps'   => [
                    ['key' => 'planning',        'label' => 'CBCT Planning',               'min_gap_days_from_previous' => 0],
                    ['key' => 'implant_surgery', 'label' => 'Implant Placement',            'min_gap_days_from_previous' => 0],
                    ['key' => 'healing',         'label' => 'Healing & Osseointegration',   'min_gap_days_from_previous' => 0],
                    ['key' => 'abutment',        'label' => 'Abutment Placement',           'min_gap_days_from_previous' => 90],
                    ['key' => 'crown',           'label' => 'Implant Crown',                'min_gap_days_from_previous' => 14],
                    ['key' => 'review',          'label' => 'Annual Review',                'min_gap_days_from_previous' => 90],
                ],
                'active'  => true,
            ]
        );
    }

    /** Patient + Consultation + TreatmentPlan chain (treatment_visits.treatment_plan_id has a real FK). */
    private function makePlan(): TreatmentPlan
    {
        $doctor = User::factory()->create(['branch_id' => 1]);

        $patient = Patient::create([
            'name'      => 'Shadow Test Patient',
            'phone'     => '90000' . random_int(10000, 99999),
            'branch_id' => 1,
        ]);

        $consultation = Consultation::create([
            'patient_id' => $patient->id,
            'doctor_id'  => $doctor->id,
            'branch_id'  => 1,
            'status'     => 'completed',
        ]);

        return TreatmentPlan::create([
            'consultation_id' => $consultation->id,
            'patient_id'      => $patient->id,
            'plan_type'       => 'best',
            'rows'            => [],
        ]);
    }

    private function makeVisit(TreatmentPlan $plan, string $stage, ?int $doctorId = null): TreatmentVisit
    {
        return TreatmentVisit::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'doctor_id'         => $doctorId,
            'visit_date'        => now(),
            'visit_type'        => 'treatment',
            'status'            => 'completed',
            'treatment_name'    => 'Root Canal Treatment',
            'current_stage'     => $stage,
        ]);
    }

    // ── Flag gate ────────────────────────────────────────────────────────────

    public function test_run_does_nothing_when_flag_is_off(): void
    {
        $plan  = $this->makePlan();
        $visit = $this->makeVisit($plan, 'diagnosis');

        $this->runner->run($visit);

        $this->assertDatabaseCount('workflow_instances', 0);
        $this->assertDatabaseCount('workflow_shadow_log', 0);
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_first_visit_starts_a_shadow_instance_in_agreement(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan  = $this->makePlan();
        $visit = $this->makeVisit($plan, 'diagnosis');

        $this->runner->run($visit);

        $instance = WorkflowInstance::first();
        $this->assertNotNull($instance);
        $this->assertSame('diagnosis', $instance->current_step);

        $this->assertDatabaseHas('workflow_shadow_log', [
            'treatment_visit_id' => $visit->id,
            'action'             => 'started',
            'agreed'             => true,
        ]);
    }

    public function test_next_visit_in_sequence_advances_the_shadow_instance_in_agreement(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan = $this->makePlan();

        $this->runner->run($this->makeVisit($plan, 'diagnosis'));
        $this->runner->run($this->makeVisit($plan, 'access'));

        $instance = WorkflowInstance::first();
        $this->assertSame('access', $instance->current_step);

        $this->assertDatabaseHas('workflow_shadow_log', [
            'action' => 'advanced',
            'agreed' => true,
        ]);
    }

    public function test_repeating_the_same_stage_is_a_noop_in_agreement(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan = $this->makePlan();

        $this->runner->run($this->makeVisit($plan, 'diagnosis'));
        $this->runner->run($this->makeVisit($plan, 'diagnosis'));

        $this->assertDatabaseHas('workflow_shadow_log', ['action' => 'noop', 'agreed' => true]);
        $this->assertSame(1, WorkflowInstance::count());
    }

    // ── Divergence ───────────────────────────────────────────────────────────

    public function test_skipping_a_stage_is_logged_as_diverged_and_resyncs(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan = $this->makePlan();

        $this->runner->run($this->makeVisit($plan, 'diagnosis'));
        // Doctor jumps straight to 'obturation', skipping access/instrumentation.
        $this->runner->run($this->makeVisit($plan, 'obturation'));

        $instance = WorkflowInstance::first();
        $this->assertSame('obturation', $instance->current_step, 'Shadow must resync to reality, not stay stuck.');

        $this->assertDatabaseHas('workflow_shadow_log', ['action' => 'diverged', 'agreed' => false]);
    }

    // ── Slice 5: second template (implant_staging) ──────────────────────────

    public function test_implant_visits_are_also_shadow_run(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan = $this->makePlan();

        $visit = TreatmentVisit::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'visit_date'        => now(),
            'visit_type'        => 'treatment',
            'status'            => 'completed',
            'treatment_name'    => 'Single Dental Implant',
            'current_stage'     => 'planning',
        ]);

        $this->runner->run($visit);

        $instance = WorkflowInstance::first();
        $this->assertNotNull($instance);
        $this->assertSame('planning', $instance->current_step);

        $this->assertDatabaseHas('workflow_shadow_log', [
            'template_key' => 'implant_staging',
            'action'       => 'started',
            'agreed'       => true,
        ]);
    }

    public function test_unrelated_treatment_is_never_shadow_run(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $plan = $this->makePlan();

        $visit = TreatmentVisit::create([
            'patient_id'        => $plan->patient_id,
            'treatment_plan_id' => $plan->id,
            'visit_date'        => now(),
            'visit_type'        => 'treatment',
            'status'            => 'completed',
            'treatment_name'    => 'Composite Filling',
            'current_stage'     => 'preparation',
        ]);

        $this->runner->run($visit);

        $this->assertDatabaseCount('workflow_instances', 0);
        $this->assertDatabaseCount('workflow_shadow_log', 0);
    }

    public function test_visit_without_a_treatment_plan_is_skipped(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $patient = Patient::create([
            'name'      => 'No Plan Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);

        $visit = TreatmentVisit::create([
            'patient_id'     => $patient->id,
            'visit_date'     => now(),
            'visit_type'     => 'treatment',
            'status'         => 'completed',
            'treatment_name' => 'Root Canal Treatment',
            'current_stage'  => 'diagnosis',
        ]);

        $this->runner->run($visit);

        $this->assertDatabaseCount('workflow_instances', 0);
    }

    public function test_a_thrown_error_is_captured_as_an_error_row_not_bubbled(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        // Deactivate the template so WorkflowEngine::start() throws internally
        // — run() must swallow it and log an 'error' row, never bubble up.
        WorkflowTemplate::where('key', 'rct_staging')->update(['active' => false]);

        $plan  = $this->makePlan();
        $visit = $this->makeVisit($plan, 'diagnosis');

        // Must not throw.
        $this->runner->run($visit);

        $this->assertDatabaseCount('workflow_instances', 0);
        $this->assertDatabaseHas('workflow_shadow_log', ['action' => 'error', 'agreed' => false]);
    }
}
