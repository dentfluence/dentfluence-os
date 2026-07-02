<?php

namespace Tests\Feature\Automation;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Services\RecallEngineService;
use App\Support\Features\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2, Slice 4 — recall cutover (no_visit_6months).
 *
 * Proves the flag routes ownership correctly and NEVER double-writes:
 *   - flag OFF (default) → legacy path queues the recall (unchanged behaviour)
 *   - flag ON            → Automation path queues it; legacy skips → exactly one item
 *   - flag ON + cooldown → suppressed, matching legacy semantics
 *   - re-run is idempotent under both owners
 */
class RecallCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    private function eligiblePatient(string $phone, ?string $queuedAt = null): Patient
    {
        return Patient::create([
            'name'                      => 'Cutover ' . $phone,
            'phone'                     => $phone,
            'branch_id'                 => 1,
            'last_visit_date'           => now()->subMonths(9)->toDateString(),
            'recall_no_visit_queued_at' => $queuedAt,
        ]);
    }

    private function noVisitCount(int $patientId): int
    {
        return CommunicationQueue::where('patient_id', $patientId)
            ->where('purpose', 'recall_no_visit')
            ->count();
    }

    public function test_legacy_path_queues_when_flag_off(): void
    {
        $patient = $this->eligiblePatient('9000000401');

        // Flag defaults OFF — legacy owns the trigger.
        app(RecallEngineService::class)->runAll();

        $this->assertSame(1, $this->noVisitCount($patient->id));
    }

    public function test_automation_path_queues_exactly_once_when_flag_on(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        $patient = $this->eligiblePatient('9000000402');

        app(RecallEngineService::class)->runAll();

        // Exactly one item — Automation ran, legacy skipped (no double write).
        $this->assertSame(1, $this->noVisitCount($patient->id));

        // The Automation runner stamps the patient so cooldown gates re-runs.
        $this->assertNotNull($patient->fresh()->recall_no_visit_queued_at);
    }

    public function test_automation_rerun_is_idempotent(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        $patient = $this->eligiblePatient('9000000403');

        $engine = app(RecallEngineService::class);
        $engine->runAll();
        $engine->runAll(); // same day

        $this->assertSame(1, $this->noVisitCount($patient->id), 'No duplicate on re-run under Automation.');
    }

    public function test_automation_respects_cooldown(): void
    {
        Feature::set('automation.engine', true);
        Feature::flushCache();

        // Queued 5 days ago → inside the 30-day cooldown → must be suppressed.
        $patient = $this->eligiblePatient('9000000404', now()->subDays(5));

        app(RecallEngineService::class)->runAll();

        $this->assertSame(0, $this->noVisitCount($patient->id));
    }
}
