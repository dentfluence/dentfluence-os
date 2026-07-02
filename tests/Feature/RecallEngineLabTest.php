<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\LabCase;
use App\Services\RecallEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Interconnection (Layer 4) Test — Recall Engine → lab case recall
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  When a lab case's final work has been received and the patient has no
 *  upcoming appointment, the nightly Recall Engine should queue a recall
 *  (to bring the patient in to collect / book). It marks the case with
 *  `recall_queued_at` when it does.
 *
 *  Guards the fix where the rule looked for the old status 'received'
 *  (which never matched) instead of v2 'final_received'.
 */
class RecallEngineLabTest extends TestCase
{
    use RefreshDatabase;

    public function test_recall_engine_queues_recall_for_a_finished_lab_case(): void
    {
        $patient = Patient::create([
            'first_name'      => 'Test',
            'last_name'       => 'RecallLab',
            'name'            => 'Test RecallLab',
            'gender'          => 'male',
            'phone'           => '9000000021',
            'branch_id'       => 1,
            'last_visit_date' => now(),   // recent, so other recall rules stay quiet
        ]);

        $case = LabCase::create([
            'patient_id'    => $patient->id,
            'work_category' => 'Crown & Bridge',
            'status'        => 'final_received',
            'branch_id'     => 1,
        ]);

        // Run the real nightly recall engine.
        app(RecallEngineService::class)->runAll();

        // The lab-recall rule stamps recall_queued_at when it queues the case
        // (this stamp only saves now that recall_queued_at is fillable).
        $this->assertNotNull(
            $case->fresh()->recall_queued_at,
            'Recall Engine should have queued the finished lab case for recall.'
        );

        $this->assertDatabaseHas('communication_queue', [
            'source_engine' => 'recall',
        ]);
    }
}
