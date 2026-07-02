<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\LabCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Interconnection (Layer 4) Test — Lab case → Communication auto-close
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  When a lab case's status changes, the LabCaseObserver creates a tracking
 *  communication in the Comms Manager. When the lab case reaches a FINISHED
 *  status, that communication should auto-close (so it doesn't linger).
 *
 *  This is a backend "side-effect" test — no browser. It runs against the
 *  separate dentfluence_testing database, which RefreshDatabase rebuilds
 *  fresh each run, so your real data is never touched.
 */
class LabCaseAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_a_lab_case_auto_closes_its_tracking_comm(): void
    {
        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name'  => 'LabAuto',
            'name'       => 'Test LabAuto',
            'gender'     => 'male',
            'phone'      => '9000000020',
            'branch_id'  => 1,
        ]);

        $case = LabCase::create([
            'patient_id'    => $patient->id,
            'work_category' => 'Crown & Bridge',
            'status'        => 'order_placed',
            'branch_id'     => 1,
        ]);

        // A status change triggers the observer → a tracking comm is created.
        $case->update(['status' => 'impression_sent']);
        $this->assertDatabaseHas('communication_queue', [
            'lab_case_id' => $case->id,
        ]);

        // Finishing the lab case should auto-close that comm.
        $case->update(['status' => 'final_received']);

        $this->assertDatabaseHas('communication_queue', [
            'lab_case_id' => $case->id,
            'status'      => 'closed',
        ]);
    }
}
