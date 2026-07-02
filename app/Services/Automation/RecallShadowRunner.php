<?php

namespace App\Services\Automation;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RecallShadowRunner — Phase 2, Slice 3 (shadow dual-run).
 *
 * For the `no_visit_6months` recall trigger, this computes — READ ONLY — the set
 * of candidates and, for each, the decision the LEGACY path (RecallEngineService)
 * and the new AUTOMATION path (AutomationEngine primitives) would make. Both
 * decisions are written to `automation_shadow_log`; NOTHING is written to
 * communication_queue or patients. The parity command then diffs the two sources.
 *
 * Scope: this slice shadows ONLY `no_visit_6months` (one trigger at a time, per
 * the blueprint). Later slices add the other five recall triggers the same way.
 *
 * Mirrors legacy criteria from RecallEngineService::recallNoVisit6Months():
 *   - candidate: phone present AND (last_visit_date <= now-6mo OR never visited)
 *   - cooldown : 30 days, tracked via patients.recall_no_visit_queued_at
 *   - dedup    : no open (non-closed) comm item with purpose 'recall_no_visit'
 */
class RecallShadowRunner
{
    private const TRIGGER            = 'no_visit_6months';
    private const PURPOSE            = 'recall_no_visit';
    private const NO_VISIT_MONTHS    = 6;
    private const COOLDOWN_DAYS      = 30;

    public function __construct(
        protected AutomationEngine $automation,
    ) {}

    /**
     * Run the shadow comparison for the no-visit trigger.
     *
     * @param  string  $runId  Groups every row written by this run.
     * @return array{trigger:string,candidates:int,legacy_queue:int,automation_queue:int,divergences:int,divergent_patient_ids:array<int>}
     */
    public function run(string $runId): array
    {
        $now      = Carbon::now();
        $cutoff   = $now->copy()->subMonths(self::NO_VISIT_MONTHS);

        $candidates          = 0;
        $legacyQueue         = 0;
        $automationQueue     = 0;
        $divergences         = 0;
        $divergentPatientIds = [];
        $rows                = [];

        Patient::query()
            ->whereNotNull('phone')
            ->where(function ($q) use ($cutoff) {
                $q->whereDate('last_visit_date', '<=', $cutoff)
                  ->orWhereNull('last_visit_date');
            })
            ->chunkById(200, function ($patients) use (
                $now, $runId, &$candidates, &$legacyQueue, &$automationQueue,
                &$divergences, &$divergentPatientIds, &$rows
            ) {
                foreach ($patients as $patient) {
                    $candidates++;

                    $hasOpen  = $this->hasOpenQueueItem($patient->id);
                    $queuedAt = $patient->recall_no_visit_queued_at
                        ? Carbon::parse($patient->recall_no_visit_queued_at)
                        : null;

                    // ── LEGACY decision (faithful to the SQL in RecallEngineService) ──
                    $legacyInCooldown = $this->legacyInCooldown($queuedAt, $now);
                    $legacyWouldQueue = ! $legacyInCooldown && ! $hasOpen;

                    // ── AUTOMATION decision (via AutomationEngine primitives) ──
                    $autoInCooldown = $this->automation->inCooldown($queuedAt, self::COOLDOWN_DAYS, $now);
                    $autoWouldQueue = ! $autoInCooldown && ! $hasOpen;

                    $legacyReason = $legacyWouldQueue ? null : ($hasOpen ? 'duplicate_open' : 'cooldown');
                    $autoReason   = $autoWouldQueue   ? null : ($hasOpen ? 'duplicate_open' : 'cooldown');

                    $rows[] = $this->rowFor($runId, $patient->id, $legacyWouldQueue, $legacyReason, 'legacy');
                    $rows[] = $this->rowFor($runId, $patient->id, $autoWouldQueue,   $autoReason,   'automation');

                    if ($legacyWouldQueue) { $legacyQueue++; }
                    if ($autoWouldQueue)   { $automationQueue++; }

                    if ($legacyWouldQueue !== $autoWouldQueue) {
                        $divergences++;
                        $divergentPatientIds[] = $patient->id;
                    }
                }
            });

        // Single bulk insert into the shadow log — no other writes anywhere.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('automation_shadow_log')->insert($chunk);
        }

        return [
            'trigger'               => self::TRIGGER,
            'candidates'            => $candidates,
            'legacy_queue'          => $legacyQueue,
            'automation_queue'      => $automationQueue,
            'divergences'           => $divergences,
            'divergent_patient_ids' => $divergentPatientIds,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Mirror of RecallEngineService::hasOpenQueueItem() for this purpose. */
    private function hasOpenQueueItem(int $patientId): bool
    {
        return CommunicationQueue::where('patient_id', $patientId)
            ->where('purpose', self::PURPOSE)
            ->whereNotIn('status', ['closed'])
            ->exists();
    }

    /**
     * Faithful replica of legacy's date-granular cooldown SQL:
     *   queue-eligible when recall_no_visit_queued_at IS NULL
     *   OR DATE(recall_no_visit_queued_at) <= DATE(now - 30 days).
     * "In cooldown" = the negation (queued recently).
     */
    private function legacyInCooldown(?Carbon $queuedAt, Carbon $now): bool
    {
        if ($queuedAt === null) {
            return false;
        }

        $threshold = $now->copy()->subDays(self::COOLDOWN_DAYS)->toDateString();

        return $queuedAt->toDateString() > $threshold;
    }

    /** Build one shadow-log row. */
    private function rowFor(string $runId, int $patientId, bool $wouldQueue, ?string $reason, string $source): array
    {
        return [
            'run_id'     => $runId,
            'trigger'    => self::TRIGGER,
            'source'     => $source,
            'patient_id' => $patientId,
            'purpose'    => self::PURPOSE,
            'decision'   => $wouldQueue ? 'queue' : 'suppress',
            'reason'     => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
