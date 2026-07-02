<?php

namespace App\Services\Automation;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Services\Relationship\ActivityEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * RecallAutomationRunner — Phase 2, Slice 4 (recall cutover).
 *
 * The Automation-Engine-owned implementation of the `no_visit_6months` recall
 * trigger. When the `automation.engine` flag is ON, RecallEngineService::runAll()
 * delegates this ONE trigger here instead of running its own legacy method — so
 * exactly one path ever creates the recall item (no double-write).
 *
 * Behaviour is deliberately identical to RecallEngineService::recallNoVisit6Months(),
 * with one difference that is the whole point of the cutover: the cooldown gate is
 * now owned by AutomationEngine::inCooldown() rather than inline SQL. The
 * communication_queue payload, the patient stamp, and the activity log all match
 * legacy exactly (proven equivalent by the shadow parity run in Slice 3).
 *
 * The other five recall triggers stay with the legacy engine for now; they cut
 * over in later slices the same way.
 */
class RecallAutomationRunner
{
    private const PURPOSE          = 'recall_no_visit';
    private const NO_VISIT_MONTHS  = 6;
    private const COOLDOWN_DAYS    = 30;
    private const RECALL_SLA_HOURS = 24;

    public function __construct(
        protected AutomationEngine $automation,
    ) {}

    /**
     * Create no-visit recall items for eligible patients.
     *
     * @return int  How many items were queued.
     */
    public function runNoVisit(): int
    {
        $now    = Carbon::now();
        $cutoff = $now->copy()->subMonths(self::NO_VISIT_MONTHS);
        $count  = 0;

        // Suppression tallies — kept for the Decision Log summary (explainability:
        // "why didn't we contact these patients?"). Per-patient reasons are
        // available via `php artisan automation:parity recall` (shadow log).
        $suppressedCooldown  = 0;
        $suppressedDuplicate = 0;

        Patient::query()
            ->whereNotNull('phone')
            ->where(function ($q) use ($cutoff) {
                $q->whereDate('last_visit_date', '<=', $cutoff)
                  ->orWhereNull('last_visit_date');
            })
            ->chunkById(100, function ($patients) use ($now, &$count, &$suppressedCooldown, &$suppressedDuplicate) {
                foreach ($patients as $patient) {
                    $queuedAt = $patient->recall_no_visit_queued_at
                        ? Carbon::parse($patient->recall_no_visit_queued_at)
                        : null;

                    // COOLDOWN — now owned by the Automation Engine.
                    if ($this->automation->inCooldown($queuedAt, self::COOLDOWN_DAYS, $now)) {
                        $suppressedCooldown++;
                        continue;
                    }

                    // DEDUP — never a second open item for the same patient + purpose.
                    if ($this->hasOpenQueueItem($patient->id)) {
                        $suppressedDuplicate++;
                        continue;
                    }

                    $lastVisit = $patient->last_visit_date
                        ? Carbon::parse($patient->last_visit_date)->format('d M Y')
                        : 'never';

                    $this->createQueueItem([
                        'patient_id'      => $patient->id,
                        'person_name'     => $patient->name,
                        'phone'           => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'         => self::PURPOSE,
                        'comm_type'       => 'existing_patient',
                        'priority'        => 'medium',
                        'note'            => "Patient has not visited since {$lastVisit}. Recall after 6 months of no activity.",
                        'source_engine'   => 'recall',
                        'tags'            => ['recall', 'no_visit_6m'],
                    ]);

                    $this->logRecallActivity($patient);

                    $patient->update(['recall_no_visit_queued_at' => now()]);
                    $count++;
                }
            });

        Log::info('RecallAutomationRunner: no_visit_6months complete', [
            'queued'               => $count,
            'suppressed_cooldown'  => $suppressedCooldown,
            'suppressed_duplicate' => $suppressedDuplicate,
            'owner'                => 'automation',
        ]);

        return $count;
    }

    // ── Helpers (mirror RecallEngineService) ─────────────────────────────────

    private function hasOpenQueueItem(int $patientId): bool
    {
        return CommunicationQueue::where('patient_id', $patientId)
            ->where('purpose', self::PURPOSE)
            ->whereNotIn('status', ['closed'])
            ->exists();
    }

    private function createQueueItem(array $data): CommunicationQueue
    {
        return CommunicationQueue::create(array_merge([
            'channel'        => 'call',
            'direction'      => 'outbound',
            'status'         => 'pending',
            'next_action'    => 'call_back',
            'attempt_count'  => 0,
            'follow_up_date' => today(),
            'sla_deadline'   => now()->addHours(self::RECALL_SLA_HOURS),
            'sla_breached'   => false,
            'created_by'     => null, // system-generated
        ], $data));
    }

    private function logRecallActivity(Patient $patient): void
    {
        try {
            app(ActivityEngine::class)->log(
                $patient,
                'recall.queued',
                null, // system action
                ['trigger' => 'no_visit_6months', 'owner' => 'automation'],
                $patient->relationship_id ?? null,
            );
        } catch (\Throwable $e) {
            Log::debug('RecallAutomationRunner: ActivityEngine log failed', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
