<?php

namespace Tests\Feature\Automation;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Services\Automation\RecallShadowRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 2, Slice 3 — shadow dual-run parity for the no_visit_6months recall trigger.
 *
 * Proves that (a) the Automation Engine reproduces the legacy recall decision on
 * real-shaped data (zero divergence), (b) each decision is recorded to the shadow
 * log for both sources, and — critically — (c) the shadow run writes NOTHING to
 * communication_queue.
 */
class RecallShadowParityTest extends TestCase
{
    use RefreshDatabase;

    private function makePatient(string $phone, ?string $lastVisit, ?string $queuedAt = null): Patient
    {
        return Patient::create([
            'name'                      => 'Shadow ' . $phone,
            'phone'                     => $phone,
            'branch_id'                 => 1,
            'last_visit_date'           => $lastVisit,
            'recall_no_visit_queued_at' => $queuedAt,
        ]);
    }

    public function test_shadow_run_has_zero_divergence_and_writes_no_comm_items(): void
    {
        // P1 — clearly queue: old visit, never queued, no open item → BOTH queue.
        $p1 = $this->makePatient('9000000301', now()->subMonths(9)->toDateString());

        // P2 — cooldown: old visit but queued 5 days ago → BOTH suppress (cooldown).
        $p2 = $this->makePatient('9000000302', now()->subMonths(9)->toDateString(), now()->subDays(5));

        // P3 — duplicate: old visit, but already has an OPEN recall item → BOTH suppress.
        $p3 = $this->makePatient('9000000303', now()->subMonths(9)->toDateString());
        CommunicationQueue::create([
            'patient_id'  => $p3->id,
            'person_name' => $p3->name,
            'phone'       => $p3->phone,
            'purpose'     => 'recall_no_visit',
            'status'      => 'pending',
            'channel'     => 'call',
            'direction'   => 'outbound',
        ]);

        // P4 — recent visitor: visited today → NOT a candidate at all.
        $this->makePatient('9000000304', now()->toDateString());

        $commCountBefore = CommunicationQueue::count(); // = 1 (the P3 fixture)

        $runId   = (string) Str::uuid();
        $summary = app(RecallShadowRunner::class)->run($runId);

        // Only P1, P2, P3 are candidates (P4 excluded by the 6-month window).
        $this->assertSame(3, $summary['candidates']);
        $this->assertSame(1, $summary['legacy_queue'], 'Only P1 should be queued by legacy.');
        $this->assertSame(1, $summary['automation_queue'], 'Automation must match legacy: only P1.');
        $this->assertSame(0, $summary['divergences'], 'Automation must reproduce legacy exactly.');

        // The shadow run must not create any communication_queue rows.
        $this->assertSame($commCountBefore, CommunicationQueue::count(), 'Shadow run must not write comm items.');

        // Two rows per candidate (legacy + automation) = 6.
        $this->assertDatabaseCount('automation_shadow_log', 6);

        // Spot-check the decisions per patient/source.
        $this->assertDatabaseHas('automation_shadow_log', [
            'run_id' => $runId, 'patient_id' => $p1->id, 'source' => 'legacy', 'decision' => 'queue',
        ]);
        $this->assertDatabaseHas('automation_shadow_log', [
            'run_id' => $runId, 'patient_id' => $p1->id, 'source' => 'automation', 'decision' => 'queue',
        ]);
        $this->assertDatabaseHas('automation_shadow_log', [
            'run_id' => $runId, 'patient_id' => $p2->id, 'source' => 'automation', 'decision' => 'suppress', 'reason' => 'cooldown',
        ]);
        $this->assertDatabaseHas('automation_shadow_log', [
            'run_id' => $runId, 'patient_id' => $p3->id, 'source' => 'automation', 'decision' => 'suppress', 'reason' => 'duplicate_open',
        ]);
    }
}
