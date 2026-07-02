<?php

namespace Tests\Feature\Characterization;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Services\RecallEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHARACTERIZATION TEST — pins the CURRENT behaviour of RecallEngineService so
 * Phase 2 (Automation Engine) cannot change it by accident. These assertions
 * describe how the nightly recall run behaves TODAY. They are a safety net, not
 * a specification of the target Automation Engine.
 *
 * See docs/phase-2/automation-inventory.md §4.
 */
class RecallEngineCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    /** A patient with no visit in 6+ months gets a no-visit recall queued and stamped. */
    public function test_no_visit_6month_recall_is_queued_and_stamped(): void
    {
        $patient = Patient::create([
            'first_name'      => 'Sixmonth',
            'last_name'       => 'NoVisit',
            'name'            => 'Sixmonth NoVisit',
            'gender'          => 'female',
            'phone'           => '9000000101',
            'branch_id'       => 1,
            'last_visit_date' => now()->subMonths(9),  // well past the 6-month cutoff
            // date_of_birth intentionally null so the birthday trigger stays quiet
        ]);

        app(RecallEngineService::class)->runAll();

        // A recall_no_visit queue item was created for this patient by the recall engine.
        $this->assertDatabaseHas('communication_queue', [
            'patient_id'    => $patient->id,
            'purpose'       => 'recall_no_visit',
            'source_engine' => 'recall',
        ]);

        // The patient record is stamped so the 30-day cooldown can gate re-runs.
        $this->assertNotNull(
            $patient->fresh()->recall_no_visit_queued_at,
            'Recall engine should stamp recall_no_visit_queued_at when it queues a no-visit recall.'
        );
    }

    /** Re-running the engine does not create a duplicate no-visit recall (idempotent). */
    public function test_no_visit_recall_is_idempotent_on_rerun(): void
    {
        $patient = Patient::create([
            'first_name'      => 'Idem',
            'last_name'       => 'Potent',
            'name'            => 'Idem Potent',
            'gender'          => 'male',
            'phone'           => '9000000102',
            'branch_id'       => 1,
            'last_visit_date' => now()->subMonths(9),
        ]);

        $engine = app(RecallEngineService::class);
        $engine->runAll();
        $engine->runAll();  // second run same day

        $count = CommunicationQueue::where('patient_id', $patient->id)
            ->where('purpose', 'recall_no_visit')
            ->count();

        $this->assertSame(1, $count, 'A second recall run must not create a duplicate no-visit item.');
    }

    /** A patient who visited recently is NOT queued for a no-visit recall. */
    public function test_recent_visitor_is_not_queued(): void
    {
        $patient = Patient::create([
            'first_name'      => 'Recent',
            'last_name'       => 'Visitor',
            'name'            => 'Recent Visitor',
            'gender'          => 'female',
            'phone'           => '9000000103',
            'branch_id'       => 1,
            'last_visit_date' => now(),  // visited today — inside the 6-month window
        ]);

        app(RecallEngineService::class)->runAll();

        $this->assertDatabaseMissing('communication_queue', [
            'patient_id' => $patient->id,
        ]);
    }
}
