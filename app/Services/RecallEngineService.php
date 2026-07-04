<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CommunicationQueue;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentVisit;
use App\Services\Relationship\ActivityEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RecallEngineService — Phase 2 of the Communication OS.
 *
 * Runs daily (7am via Laravel scheduler) and auto-creates records in
 * communication_queue for patients/items that need follow-up.
 *
 * 6 triggers:
 *   1. no_visit_6months      — patients with no visit in 6+ months
 *   2. approved_plan_no_appt — approved treatment plan items with no appointment
 *   3. post_op_followup      — post-op check-up due (14 days after surgery visit)
 *   4. lab_received_no_appt  — lab case received but patient appointment not booked
 *   5. recent_tx_followup    — 7-day follow-up after treatment visit
 *   6. birthday_anniversary  — birthday or anniversary re-engagement
 *
 * Deduplication:
 *   Each trigger checks for an existing OPEN comm_queue item with the same
 *   patient_id + purpose combo before inserting. Also uses recall_queued_at
 *   timestamps on source records as a secondary cooldown gate.
 *
 * Returns a summary array for logging/display.
 */
class RecallEngineService
{
    /** Cooldown: don't re-queue no-visit recall within 30 days */
    private const NO_VISIT_COOLDOWN_DAYS = 30;

    /** Birthday window: queue recall from 3 days before to birthday */
    private const BIRTHDAY_DAYS_AHEAD = 3;

    /** Anniversary window: queue 7 days ahead */
    private const ANNIVERSARY_DAYS_AHEAD = 7;

    /** Post-op: queue recall 14 days after a surgical visit */
    private const POST_OP_DAYS_AFTER = 14;

    /** Recent treatment follow-up: 7 days after any treatment visit */
    private const RECENT_TX_DAYS_AFTER = 7;

    /** SLA for recall items: 24 hours from creation */
    private const RECALL_SLA_HOURS = 24;

    private array $summary = [];

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * Run all 6 recall triggers.
     * Returns ['trigger' => count, ...] summary.
     */
    public function runAll(): array
    {
        $this->summary = [];

        // ── Phase 2, Slice 4 — recall cutover (no_visit_6months only) ──────────
        // When automation.engine is ON, the Automation Engine OWNS this trigger and
        // legacy skips it — so exactly one path creates the recall item (no double
        // write). When OFF (default), legacy runs exactly as before. Instant
        // rollback = flip the flag off. The other five triggers stay on legacy.
        if (app(\App\Services\Automation\AutomationEngine::class)->enabled()) {
            $this->summary['no_visit_6months'] =
                app(\App\Services\Automation\RecallAutomationRunner::class)->runNoVisit();
        } else {
            $this->recallNoVisit6Months();
        }

        $this->recallApprovedPlanNoAppointment();
        $this->recallPostOpFollowUp();
        $this->recallLabReceivedNoAppointment();
        $this->recallRecentTreatmentFollowUp();
        $this->recallBirthdayAnniversary();

        $total = array_sum($this->summary);

        Log::info('RecallEngine run complete', array_merge($this->summary, ['total' => $total]));

        return array_merge($this->summary, ['total' => $total]);
    }

    // ── Trigger 1: No Visit in 6 Months ──────────────────────────────────────

    /**
     * Patients whose last_visit_date is 6+ months ago (or who have never visited).
     * Skips if already queued within cooldown window.
     */
    private function recallNoVisit6Months(): void
    {
        $cutoff   = now()->subMonths(6);
        $cooldown = now()->subDays(self::NO_VISIT_COOLDOWN_DAYS);
        $count    = 0;

        Patient::query()
            ->whereNotNull('phone')
            ->where(function ($q) use ($cutoff) {
                $q->whereDate('last_visit_date', '<=', $cutoff)
                  ->orWhereNull('last_visit_date');
            })
            ->where(function ($q) use ($cooldown) {
                // Not queued recently
                $q->whereNull('recall_no_visit_queued_at')
                  ->orWhereDate('recall_no_visit_queued_at', '<=', $cooldown);
            })
            ->chunk(100, function ($patients) use (&$count) {
                foreach ($patients as $patient) {
                    if ($this->hasOpenQueueItem($patient->id, 'recall_no_visit')) {
                        continue;
                    }

                    $lastVisit = $patient->last_visit_date
                        ? Carbon::parse($patient->last_visit_date)->format('d M Y')
                        : 'never';

                    $this->createQueueItem([
                        'patient_id'    => $patient->id,
                        'person_name'   => $patient->name,
                        'phone'         => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'       => 'recall_no_visit',
                        'comm_type'     => 'existing_patient',
                        'priority'      => 'medium',
                        'note'          => "Patient has not visited since {$lastVisit}. Recall after 6 months of no activity.",
                        'source_engine' => 'recall',
                        'tags'          => ['recall', 'no_visit_6m'],
                    ]);

                    $this->logRecallActivity($patient, 'no_visit_6months');

                    // Stamp the patient record
                    $patient->update(['recall_no_visit_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['no_visit_6months'] = $count;
    }

    // ── Trigger 2: Approved Treatment Plan, No Appointment ───────────────────

    /**
     * Treatment plan items with status 'approved' that have no linked appointment.
     * Looks back up to 90 days for plans that were approved but never actioned.
     */
    private function recallApprovedPlanNoAppointment(): void
    {
        $count = 0;

        TreatmentPlanItem::query()
            ->where('status', 'approved')
            ->whereNull('recall_queued_at')
            ->whereHas('plan', function ($q) {
                $q->where('status', 'approved')
                  ->whereNotNull('patient_id')
                  ->whereDate('created_at', '>=', now()->subDays(90));
            })
            ->with(['plan.patient'])
            ->chunk(100, function ($items) use (&$count) {
                foreach ($items as $item) {
                    $patient = $item->plan->patient ?? null;
                    if (!$patient || !$patient->phone) continue;

                    if ($this->hasOpenQueueItem($patient->id, 'recall_approved_plan')) {
                        continue;
                    }

                    $value = (float) ($item->total ?? 0);

                    $this->createQueueItem([
                        'patient_id'        => $patient->id,
                        'person_name'       => $patient->name,
                        'phone'             => $patient->phone,
                        'whatsapp_number'   => $patient->phone,
                        'purpose'           => 'recall_approved_plan',
                        'comm_type'         => 'existing_patient',
                        'priority'          => $value >= 10000 ? 'high' : 'medium',
                        'opportunity_value' => $value,
                        'note'              => "Approved treatment plan: {$item->treatment_name}" .
                                              ($item->tooth_number ? " (Tooth #{$item->tooth_number})" : '') .
                                              ". No appointment booked. Plan value: ₹" . number_format($value),
                        'source_engine'     => 'recall',
                        'tags'              => ['recall', 'approved_plan'],
                    ]);

                    $this->logRecallActivity($patient, 'approved_plan_no_appt');

                    $item->update(['recall_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['approved_plan_no_appt'] = $count;
    }

    // ── Trigger 3: Post-Op Follow-Up ─────────────────────────────────────────

    /**
     * Treatment visits that were surgical (extraction, implant, RCT) exactly
     * POST_OP_DAYS_AFTER days ago, with no follow-up appointment booked.
     */
    private function recallPostOpFollowUp(): void
    {
        $targetDate = now()->subDays(self::POST_OP_DAYS_AFTER)->toDateString();
        $count      = 0;

        $surgicalVisitTypes = ['extraction', 'implant', 'rct', 'surgery', 'surgical', 'post_op'];

        TreatmentVisit::query()
            ->whereDate('visit_date', $targetDate)
            ->where(function ($q) use ($surgicalVisitTypes) {
                // Match on visit_type or procedure containing surgical keywords
                foreach ($surgicalVisitTypes as $type) {
                    $q->orWhere('visit_type', 'like', "%{$type}%")
                      ->orWhere('procedure', 'like', "%{$type}%");
                }
            })
            ->whereNull('recall_queued_at')
            ->with('patient')
            ->chunk(100, function ($visits) use (&$count) {
                foreach ($visits as $visit) {
                    $patient = $visit->patient ?? null;
                    if (!$patient || !$patient->phone) continue;

                    if ($this->hasOpenQueueItem($patient->id, 'recall_post_op')) {
                        $visit->update(['recall_queued_at' => now()]);
                        continue;
                    }

                    $this->createQueueItem([
                        'patient_id'      => $patient->id,
                        'person_name'     => $patient->name,
                        'phone'           => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'         => 'recall_post_op',
                        'comm_type'       => 'existing_patient',
                        'priority'        => 'high',
                        'note'            => "Post-op follow-up due. Patient had " .
                                            ($visit->procedure ?? $visit->visit_type ?? 'procedure') .
                                            " on " . Carbon::parse($visit->visit_date)->format('d M Y') . ".",
                        'source_engine'   => 'recall',
                        'tags'            => ['recall', 'post_op'],
                    ]);

                    $this->logRecallActivity($patient, 'post_op_followup');

                    $visit->update(['recall_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['post_op_followup'] = $count;
    }

    // ── Trigger 4: Lab Case Received, No Appointment Booked ──────────────────

    /**
     * Lab cases whose final work has been received but the patient has no
     * upcoming appointment. Checks within the last 30 days to avoid stale cases.
     * (v2 status is 'final_received' — old value 'received' never matched.)
     */
    private function recallLabReceivedNoAppointment(): void
    {
        $count = 0;

        LabCase::query()
            ->where('status', 'final_received')
            ->whereNull('recall_queued_at')
            ->whereDate('updated_at', '>=', now()->subDays(30))
            ->whereNotNull('patient_id')
            ->with('patient')
            ->chunk(100, function ($cases) use (&$count) {
                foreach ($cases as $case) {
                    $patient = $case->patient ?? null;
                    if (!$patient || !$patient->phone) continue;

                    // Check if patient already has a future appointment
                    $hasAppointment = Appointment::where('patient_id', $patient->id)
                        ->whereDate('appointment_date', '>=', today())
                        ->whereIn('status', ['scheduled', 'confirmed'])
                        ->exists();

                    if ($hasAppointment) {
                        // Lab ready but appointment already booked — mark and skip
                        $case->update(['recall_queued_at' => now()]);
                        continue;
                    }

                    if ($this->hasOpenQueueItem($patient->id, 'recall_lab_received')) {
                        continue;
                    }

                    $workDesc = $case->work_category ?? $case->work_type ?? 'Lab work';

                    $this->createQueueItem([
                        'patient_id'      => $patient->id,
                        'person_name'     => $patient->name,
                        'phone'           => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'         => 'recall_lab_received',
                        'comm_type'       => 'existing_patient',
                        'priority'        => 'high',
                        'note'            => "Lab work received and ready: {$workDesc} (Case #{$case->id}). " .
                                            "Patient appointment not yet booked.",
                        'source_engine'   => 'recall',
                        'tags'            => ['recall', 'lab_ready'],
                    ]);

                    $this->logRecallActivity($patient, 'lab_received_no_appt');

                    $case->update(['recall_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['lab_received_no_appt'] = $count;
    }

    // ── Trigger 5: Recent Treatment Follow-Up (7 days) ───────────────────────

    /**
     * Patients who had a treatment visit exactly 7 days ago.
     * Excludes purely consultation/exam visit types.
     */
    private function recallRecentTreatmentFollowUp(): void
    {
        $targetDate = now()->subDays(self::RECENT_TX_DAYS_AFTER)->toDateString();
        $count      = 0;

        $excludedTypes = ['consultation', 'exam', 'xray', 'review', 'checkup', 'check_up'];

        TreatmentVisit::query()
            ->whereDate('visit_date', $targetDate)
            ->where(function ($q) use ($excludedTypes) {
                foreach ($excludedTypes as $type) {
                    $q->where('visit_type', 'not like', "%{$type}%")
                      ->where('procedure', 'not like', "%{$type}%");
                }
            })
            ->whereNull('recall_queued_at')
            ->with('patient')
            ->chunk(100, function ($visits) use (&$count) {
                foreach ($visits as $visit) {
                    $patient = $visit->patient ?? null;
                    if (!$patient || !$patient->phone) continue;

                    if ($this->hasOpenQueueItem($patient->id, 'recall_7day_followup')) {
                        $visit->update(['recall_queued_at' => now()]);
                        continue;
                    }

                    $this->createQueueItem([
                        'patient_id'      => $patient->id,
                        'person_name'     => $patient->name,
                        'phone'           => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'         => 'recall_7day_followup',
                        'comm_type'       => 'existing_patient',
                        'priority'        => 'medium',
                        'note'            => "7-day post-treatment follow-up. Patient had " .
                                            ($visit->procedure ?? $visit->treatment_name ?? 'treatment') .
                                            " on " . Carbon::parse($visit->visit_date)->format('d M Y') . ".",
                        'source_engine'   => 'recall',
                        'tags'            => ['recall', '7day_followup'],
                    ]);

                    $this->logRecallActivity($patient, 'recent_tx_followup');

                    $visit->update(['recall_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['recent_tx_followup'] = $count;
    }

    // ── Trigger 6: Birthday / Anniversary ────────────────────────────────────

    /**
     * Patients whose birthday or anniversary (via patient notes) falls within
     * the next N days. Queues a re-engagement recall.
     * Cooldown: once per calendar year (tracked via recall_birthday_queued_at).
     */
    private function recallBirthdayAnniversary(): void
    {
        $count   = 0;
        $today   = now();

        // Birthday window: today + BIRTHDAY_DAYS_AHEAD days
        $birthdayDates = collect(range(0, self::BIRTHDAY_DAYS_AHEAD))->map(
            fn($d) => $today->copy()->addDays($d)->format('m-d')
        );

        Patient::query()
            ->whereNotNull('phone')
            ->whereNotNull('date_of_birth')
            ->where(function ($q) use ($birthdayDates) {
                foreach ($birthdayDates as $md) {
                    // Match month-day regardless of year
                    $q->orWhereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = ?", [$md]);
                }
            })
            ->where(function ($q) {
                // Only queue once per calendar year
                $q->whereNull('recall_birthday_queued_at')
                  ->orWhereYear('recall_birthday_queued_at', '<', now()->year);
            })
            ->chunk(100, function ($patients) use (&$count) {
                foreach ($patients as $patient) {
                    if ($this->hasOpenQueueItem($patient->id, 'recall_birthday')) {
                        continue;
                    }

                    $dob         = Carbon::parse($patient->date_of_birth);
                    $birthdayStr = $dob->format('d M');
                    $age         = $dob->age;

                    $this->createQueueItem([
                        'patient_id'      => $patient->id,
                        'person_name'     => $patient->name,
                        'phone'           => $patient->phone,
                        'whatsapp_number' => $patient->phone,
                        'purpose'         => 'recall_birthday',
                        'comm_type'       => 'existing_patient',
                        'priority'        => 'low',
                        'note'            => "Birthday recall — {$patient->name} turns {$age} on {$birthdayStr}. " .
                                            "Great opportunity for re-engagement.",
                        'source_engine'   => 'recall',
                        'tags'            => ['recall', 'birthday'],
                    ]);

                    $this->logRecallActivity($patient, 'birthday_anniversary');

                    $patient->update(['recall_birthday_queued_at' => now()]);
                    $count++;
                }
            });

        $this->summary['birthday_anniversary'] = $count;
    }

    // ── Manual entry point (staff-initiated, via PRE Recall Pipeline "+ Add Recall") ─

    /**
     * Create a single recall for one patient, initiated by a staff member —
     * as opposed to the 6 automated triggers above. Uses the same
     * createQueueItem() defaults (SLA, status, channel) so it behaves
     * identically to a system-generated recall everywhere it's read
     * (this board, Today's Actions, analytics) — just tagged 'manual' and
     * attributed to the staff member who added it.
     */
    public function createManual(Patient $patient, array $data): CommunicationQueue
    {
        $item = $this->createQueueItem([
            'patient_id'      => $patient->id,
            'person_name'     => $patient->name,
            'phone'           => $patient->phone,
            'whatsapp_number' => $patient->phone,
            'purpose'         => 'recall_manual',
            'comm_type'       => 'existing_patient',
            'priority'        => $data['priority'] ?? 'medium',
            'note'            => $data['note'] ?: 'Manually added recall.',
            'source_engine'   => 'recall',
            'tags'            => ['recall', 'manual'],
            'follow_up_date'  => $data['follow_up_date'] ?? today(),
            'created_by'      => \Illuminate\Support\Facades\Auth::id(),
        ]);

        $this->logRecallActivity($patient, 'manual');

        return $item;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check if an open (non-closed) queue item already exists for this
     * patient + purpose combination.
     */
    private function hasOpenQueueItem(int $patientId, string $purpose): bool
    {
        return CommunicationQueue::where('patient_id', $patientId)
            ->where('purpose', $purpose)
            ->whereNotIn('status', ['closed'])
            ->exists();
    }

    /**
     * Create a communication_queue record for a recall item.
     * Sets SLA deadline, status, channel defaults automatically.
     */
    private function createQueueItem(array $data): CommunicationQueue
    {
        return CommunicationQueue::create(array_merge([
            'channel'        => 'call',
            'direction'      => 'outbound',
            'status'         => 'pending',
            'next_action'    => 'call_back',
            'attempt_count'  => 0,
            'follow_up_date' => today(),   // show in dashboard "today" view
            'sla_deadline'   => now()->addHours(self::RECALL_SLA_HOURS),
            'sla_breached'   => false,
            'created_by'     => null, // system-generated
        ], $data));
    }

    // ── Phase 4 — ActivityEngine integration ─────────────────────────────────

    /**
     * Log a recall.queued event to the ActivityEngine.
     *
     * Additive only — never changes recall logic. Silently fails so that a
     * logging error can never block a recall run.
     *
     * @param  Patient  $patient      The patient being queued.
     * @param  string   $triggerName  The recall trigger name (e.g. 'no_visit_6months').
     */
    private function logRecallActivity(Patient $patient, string $triggerName): void
    {
        try {
            app(ActivityEngine::class)->log(
                $patient,
                'recall.queued',
                null,   // system action — no human actor
                ['trigger' => $triggerName],
                $patient->relationship_id ?? null,
            );
        } catch (\Throwable $e) {
            Log::debug('RecallEngine: ActivityEngine log failed', [
                'trigger'    => $triggerName,
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
