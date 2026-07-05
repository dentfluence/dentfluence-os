<?php

namespace App\Services\Relationship;

use App\Models\CommunicationQueue;
use App\Models\Patient;
use App\Models\TreatmentOpportunity;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Support\Facades\Log;

/**
 * OutcomeAutomationService — PRE recall call-outcome automations
 * (Section 5 of the PRE mobile spec, 2026-07-05).
 *
 * Runs the side-effects triggered by a receptionist completing the "Activity
 * Completion Bottom Sheet" for a recall (web RecallPipelineController and the
 * mobile Activity Completion Bottom Sheet both call into this — one engine,
 * so behaviour never drifts between web and app). Every outcome:
 *   1. Always writes a Timeline/Activity Ledger entry via ActivityEngine
 *      (Activity IS the Timeline — see RelationshipController::timeline()).
 *   2. Then runs its specific action (create appointment, close recall,
 *      schedule follow-up, mark invalid contact, disable automations, …).
 *
 * Side-effects are deliberately best-effort: if e.g. an appointment can't be
 * created because no doctor/date was picked yet, the call outcome is still
 * recorded — a receptionist's honest data entry should never be blocked by
 * an automation gap.
 *
 * Outcomes with no defined automation (shifted, other, under_treatment_elsewhere,
 * rejected) fall through to a plain "log + keep open for manual follow-up"
 * default rather than inventing behaviour the spec didn't ask for.
 */
class OutcomeAutomationService
{
    /** How far out to push the long-term recall after "Not Interested". */
    private const LONG_TERM_RECALL_MONTHS = 12;

    public function __construct(
        private readonly ActivityEngine    $activityEngine,
        private readonly AppointmentService $appointmentService,
    ) {}

    /**
     * Apply the outcome + its automation to a recall CommunicationQueue item.
     *
     * @param  CommunicationQueue $comm     The recall/queue row being completed.
     * @param  string             $outcome  A key from CommunicationQueue::allCallOutcomes().
     * @param  User               $actor    The staff member logging the outcome.
     * @param  array              $options  Optional: notes, next_follow_up_date,
     *                                      doctor_id, appointment_date, appointment_time
     *                                      (only used when outcome = appointment_booked).
     *
     * @return array{activity_id: ?int, outcome: string, actions: array<string>}
     */
    public function apply(CommunicationQueue $comm, string $outcome, User $actor, array $options = []): array
    {
        $patient = $comm->patient;
        $notes   = $options['notes'] ?? null;
        $label   = CommunicationQueue::allCallOutcomes()[$outcome] ?? ucfirst(str_replace('_', ' ', $outcome));

        $subject        = $patient ?? $comm;
        $relationshipId = $patient?->relationship_id;

        $activity = $this->activityEngine->log(
            subject:        $subject,
            event:          'call.logged',
            actor:          $actor,
            metadata:       ['outcome' => $outcome, 'outcome_label' => $label, 'notes' => $notes, 'comm_id' => $comm->id],
            relationshipId: $relationshipId,
            description:    "Call outcome: {$label}" . ($notes ? " — {$notes}" : ''),
        );

        $result = ['activity_id' => $activity?->id, 'outcome' => $outcome, 'actions' => []];

        match ($outcome) {
            'appointment_booked' =>
                $this->onAppointmentBooked($comm, $patient, $actor, $options, $result),

            'will_call_back', 'wants_appt_next_week', 'wants_appt_next_month',
            'will_visit_later', 'family_will_decide' =>
                $this->onFollowUpScheduled($comm, $outcome, $options, $result),

            'busy_right_now', 'busy' =>
                $this->onRetry($comm, $notes, $result),

            'wrong_number', 'invalid_number' =>
                $this->onInvalidContact($comm, $patient, $label, $result),

            'financial_constraint' =>
                $this->onTreatmentCoordinatorFollowUp($comm, $options, $result),

            'not_interested' =>
                $this->onNotInterested($comm, $patient, $result),

            'treatment_done_elsewhere' =>
                $this->onTreatmentDoneElsewhere($comm, $patient, $result),

            'deceased' =>
                $this->onDeceased($comm, $patient, $label, $result),

            'whatsapp_sent', 'sms_sent', 'email_sent' =>
                $this->onCommunicationSent($comm, $outcome, $notes, $result),

            default =>
                $this->onDefault($comm, $outcome, $notes, $result),
        };

        return $result;
    }

    // ── Connected: Appointment Booked ────────────────────────────────────────

    private function onAppointmentBooked(CommunicationQueue $comm, ?Patient $patient, User $actor, array $options, array &$result): void
    {
        if ($patient && !empty($options['doctor_id']) && !empty($options['appointment_date']) && !empty($options['appointment_time'])) {
            try {
                $appointment = $this->appointmentService->create([
                    'patient_id'       => $patient->id,
                    'doctor_id'        => $options['doctor_id'],
                    'appointment_date' => $options['appointment_date'],
                    'appointment_time' => $options['appointment_time'],
                    'notes'            => 'Booked from recall call outcome (PRE).',
                ], $actor);

                $this->activityEngine->log(
                    subject:        $patient,
                    event:          'appointment.booked',
                    actor:          $actor,
                    metadata:       ['appointment_id' => $appointment->id, 'source' => 'recall_outcome'],
                    relationshipId: $patient->relationship_id,
                    description:    'Appointment booked from recall call.',
                );

                $result['actions'][] = 'appointment_created:' . $appointment->id;
            } catch (\Throwable $e) {
                Log::warning('OutcomeAutomationService: appointment creation failed', ['comm_id' => $comm->id, 'error' => $e->getMessage()]);
                $result['actions'][] = 'appointment_create_failed';
            }
        } else {
            // No slot picked in the bottom sheet — still close the recall so it
            // doesn't linger; front desk books the actual slot from the calendar.
            $result['actions'][] = 'no_slot_provided';
        }

        $comm->autoClose('appointment_booked', 'Appointment booked — recall closed.');
        $result['actions'][] = 'recall_closed';
    }

    // ── Connected: Will Call Back / Wants Appt Next Week|Month / Will Visit Later / Family Will Decide ──

    private function onFollowUpScheduled(CommunicationQueue $comm, string $outcome, array $options, array &$result): void
    {
        $defaultDays = match ($outcome) {
            'will_call_back'        => 2,
            'wants_appt_next_week'  => 7,
            'wants_appt_next_month' => 30,
            'will_visit_later'      => 60,
            'family_will_decide'    => 5,
            default                 => 3,
        };

        $followUpDate = $options['next_follow_up_date'] ?? now()->addDays($defaultDays)->toDateString();

        $comm->update([
            'outcome'        => $outcome,
            'outcome_reason' => $options['notes'] ?? null,
            'follow_up_date' => $followUpDate,
            'status'         => 'waiting_for_patient',
        ]);

        $result['actions'][] = "follow_up_scheduled:{$followUpDate}";
    }

    // ── Not Connected: Busy Right Now / Busy → Retry ─────────────────────────

    private function onRetry(CommunicationQueue $comm, ?string $notes, array &$result): void
    {
        // Reuses the existing attempt-tracking helper (attempt_count++,
        // pending -> waiting_for_patient, SLA check) — no new mechanism needed.
        $comm->logAttempt($notes ?? 'Busy — will retry.');
        $result['actions'][] = 'retry_logged:attempt_' . $comm->attempt_count;
    }

    // ── Wrong Number / Invalid Number → Mark Invalid Contact ─────────────────

    private function onInvalidContact(CommunicationQueue $comm, ?Patient $patient, string $label, array &$result): void
    {
        $patient?->markContactInvalid("Outcome: {$label}");
        $comm->autoClose('invalid_contact', "Contact marked invalid ({$label}).");
        $result['actions'][] = 'contact_marked_invalid';
    }

    // ── Financial Constraint → Treatment Coordinator Follow-up ───────────────

    private function onTreatmentCoordinatorFollowUp(CommunicationQueue $comm, array $options, array &$result): void
    {
        $tags = array_unique(array_merge($comm->tags ?? [], ['financial_constraint', 'needs_tc_followup']));

        $comm->update([
            'outcome'        => 'financial_constraint',
            'outcome_reason' => $options['notes'] ?? null,
            'follow_up_date' => $options['next_follow_up_date'] ?? now()->addDays(7)->toDateString(),
            'status'         => 'waiting_for_patient',
            'priority'       => 'high',
            'tags'           => $tags,
        ]);

        $result['actions'][] = 'flagged_for_treatment_coordinator';
    }

    // ── Not Interested → Close Recall + Schedule Long-term Recall ────────────

    private function onNotInterested(CommunicationQueue $comm, ?Patient $patient, array &$result): void
    {
        $comm->autoClose('not_interested', 'Not interested — moved to long-term preventive recall.');
        $result['actions'][] = 'recall_closed';

        if (!$patient) {
            return;
        }

        $alreadyQueued = CommunicationQueue::where('patient_id', $patient->id)
            ->where('purpose', 'recall_long_term')
            ->whereNotIn('status', ['closed'])
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        CommunicationQueue::create([
            'patient_id'      => $patient->id,
            'person_name'     => $patient->name,
            'phone'           => $patient->phone,
            'whatsapp_number' => $patient->phone,
            'channel'         => 'call',
            'direction'       => 'outbound',
            'comm_type'       => 'existing_patient',
            'purpose'         => 'recall_long_term',
            'status'          => 'pending',
            'priority'        => 'low',
            'next_action'     => 'call_back',
            'attempt_count'   => 0,
            'follow_up_date'  => now()->addMonths(self::LONG_TERM_RECALL_MONTHS)->toDateString(),
            'source_engine'   => 'recall',
            'note'            => 'Long-term preventive recall — patient said not interested previously.',
            'tags'            => ['recall', 'long_term'],
        ]);

        $result['actions'][] = 'long_term_recall_scheduled';
    }

    // ── Treatment Done Elsewhere → Close Opportunity + Continue Preventive Recall ──

    private function onTreatmentDoneElsewhere(CommunicationQueue $comm, ?Patient $patient, array &$result): void
    {
        $comm->autoClose('treatment_done_elsewhere', 'Patient completed treatment elsewhere.');
        $result['actions'][] = 'recall_closed';

        if ($patient) {
            $opportunity = TreatmentOpportunity::where('patient_id', $patient->id)
                ->whereNotIn('status', ['completed', 'declined'])
                ->latest()
                ->first();

            if ($opportunity) {
                $opportunity->update(['status' => 'declined', 'notes' => trim(($opportunity->notes ?? '') . "\nClosed: treatment done elsewhere.")]);
                $result['actions'][] = 'opportunity_declined:' . $opportunity->id;
            }
        }

        // Continue preventive recall: deliberately a no-op here — the normal
        // 6-month no-visit cycle (RecallAutomationRunner::runNoVisit) will
        // naturally re-queue this patient once due, as long as automations
        // aren't disabled. No separate mechanism needed.
        $result['actions'][] = 'preventive_recall_unaffected';
    }

    // ── Deceased → Disable Future Automations ────────────────────────────────

    private function onDeceased(CommunicationQueue $comm, ?Patient $patient, string $label, array &$result): void
    {
        $patient?->disableAutomations("Outcome: {$label}");
        $comm->autoClose('deceased', 'Patient deceased — all future automations disabled for this patient.');
        $result['actions'][] = 'automations_disabled';
    }

    // ── Communication-only outcomes (WhatsApp/SMS/Email Sent) ────────────────

    private function onCommunicationSent(CommunicationQueue $comm, string $outcome, ?string $notes, array &$result): void
    {
        $comm->update([
            'outcome'         => $outcome,
            'outcome_reason'  => $notes,
            'last_attempt_at' => now(),
        ]);
        $result['actions'][] = 'message_logged';
    }

    // ── Everything else (No Answer, Switched Off, Out of Coverage, Rejected, ─
    //    Shifted, Under Treatment Elsewhere, Other) — log + keep open for
    //    manual staff follow-up. No automation defined for these in the spec. ─

    private function onDefault(CommunicationQueue $comm, string $outcome, ?string $notes, array &$result): void
    {
        if (in_array($outcome, ['no_answer', 'switched_off', 'out_of_coverage'], true)) {
            $comm->logAttempt($notes ?? $outcome);
            $result['actions'][] = 'retry_logged:attempt_' . $comm->attempt_count;
            return;
        }

        $comm->update(['outcome' => $outcome, 'outcome_reason' => $notes]);
        $result['actions'][] = 'logged_no_automation';
    }
}
