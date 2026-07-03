<?php

namespace Tests\Unit;

use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowEngine;
use App\Support\Features\Feature;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5 Workflow Engine core (Slice 1).
 *
 * Covers start/advance/status against a small 3-step test template (kept
 * separate from the seeded `rct_staging` template so these tests don't
 * silently break if the real RCT step list is ever edited) plus the
 * feature-flag gate. No callers are wired to the engine yet — these tests
 * exercise the class directly.
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
        $this->engine = new WorkflowEngine();
    }

    private function makeTemplate(): WorkflowTemplate
    {
        return WorkflowTemplate::create([
            'key'     => 'test_3step',
            'name'    => 'Test 3-Step',
            'version' => 1,
            'steps'   => [
                ['key' => 'one',   'label' => 'Step One',   'min_gap_days_from_previous' => 0],
                ['key' => 'two',   'label' => 'Step Two',   'min_gap_days_from_previous' => 3],
                ['key' => 'three', 'label' => 'Step Three', 'min_gap_days_from_previous' => 7],
            ],
            'active'  => true,
        ]);
    }

    // ── Flag gate ────────────────────────────────────────────────────────────

    public function test_engine_is_disabled_by_default(): void
    {
        $this->assertFalse(
            $this->engine->enabled(),
            'workflow.engine must default OFF so the skeleton stays dormant.'
        );
    }

    public function test_engine_reports_enabled_once_flag_is_set(): void
    {
        Feature::set('workflow.engine', true);
        Feature::flushCache();

        $this->assertTrue($this->engine->enabled());
    }

    // ── start() ──────────────────────────────────────────────────────────────

    public function test_start_creates_an_instance_at_the_first_step(): void
    {
        $this->makeTemplate();

        $instance = $this->engine->start('test_3step', null, ['subject_type' => 'App\\Models\\TreatmentPlan', 'subject_id' => 42]);

        $this->assertInstanceOf(WorkflowInstance::class, $instance);
        $this->assertSame('one', $instance->current_step);
        $this->assertSame('active', $instance->status);
        $this->assertSame(42, $instance->subject_id);
        $this->assertNull($instance->relationship_id);
        $this->assertDatabaseHas('workflow_step_log', [
            'workflow_instance_id' => $instance->id,
            'step'                 => 'one',
        ]);
    }

    public function test_start_throws_for_unknown_template(): void
    {
        $this->expectException(RuntimeException::class);
        $this->engine->start('does_not_exist', null);
    }

    // ── advance() ────────────────────────────────────────────────────────────

    public function test_advance_moves_to_the_next_step_and_closes_the_previous_log_row(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);

        $updated = $this->engine->advance($instance, 'two');

        $this->assertSame('two', $updated->current_step);
        $this->assertSame('active', $updated->status);
        // The newly-entered 'two' row is open (exited_at null)...
        $this->assertDatabaseHas('workflow_step_log', ['workflow_instance_id' => $instance->id, 'step' => 'two', 'exited_at' => null]);
        // ...and the previous 'one' row was closed out (exited_at set).
        $oneRow = $updated->stepLogs()->where('step', 'one')->first();
        $this->assertNotNull($oneRow->exited_at);
    }

    public function test_advance_to_the_last_step_marks_the_instance_completed(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);

        $instance = $this->engine->advance($instance, 'two');
        $instance = $this->engine->advance($instance, 'three');

        $this->assertSame('completed', $instance->status);
        $this->assertNotNull($instance->completed_at);
    }

    public function test_advance_rejects_skipping_a_step(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);

        $this->expectException(RuntimeException::class);
        $this->engine->advance($instance, 'three'); // skips 'two'
    }

    public function test_advance_rejects_moving_backward(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);
        $instance = $this->engine->advance($instance, 'two');

        $this->expectException(RuntimeException::class);
        $this->engine->advance($instance, 'one');
    }

    // ── status() ─────────────────────────────────────────────────────────────

    public function test_status_reports_position_and_next_step(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);

        $status = $this->engine->status($instance);

        $this->assertSame(1, $status['position']);
        $this->assertSame(3, $status['total_steps']);
        $this->assertSame('two', $status['next_step']);
    }

    public function test_status_marks_next_step_not_due_until_the_gap_has_passed(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);
        $instance = $this->engine->advance($instance, 'two'); // gap to 'three' is 7 days

        $justAfter = Carbon::now()->addDays(2);
        $statusSoon = $this->engine->status($instance, $justAfter);
        $this->assertFalse($statusSoon['next_due']);

        $afterGap = Carbon::now()->addDays(8);
        $statusLater = $this->engine->status($instance, $afterGap);
        $this->assertTrue($statusLater['next_due']);
    }

    public function test_status_has_no_next_step_once_completed(): void
    {
        $this->makeTemplate();
        $instance = $this->engine->start('test_3step', null);
        $instance = $this->engine->advance($instance, 'two');
        $instance = $this->engine->advance($instance, 'three');

        $status = $this->engine->status($instance);

        $this->assertNull($status['next_step']);
        $this->assertSame('completed', $status['status']);
    }
}
