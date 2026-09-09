<?php

namespace App\Services;

use App\Models\LabCase;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * LabNotificationService
 *
 * Fires role-targeted in-app notifications (app_notifications table)
 * and optional WhatsApp messages on lab case status transitions.
 *
 * All operations are best-effort — wrapped so that a notification
 * failure can NEVER block a status transition in the UI or API.
 *
 * Wired from:
 *   - App\Http\Controllers\LabController::transition()       (web)
 *   - App\Http\Controllers\Api\V1\LabController::transition() (mobile)
 *
 * Scheduled overdue alerts live in App\Console\Commands\LabOverdueAlert.
 *
 * N-5 (2026-09-09): every in-app notification here now goes through
 * NotificationDispatcher instead of writing AppNotification rows directly.
 * Before this, these five alerts hard-coded their own recipients from the
 * LEGACY users.role string ('dentist', 'receptionist' — neither of which is a
 * Role slug), so the Settings > Notifications matrix showed switches for lab
 * events that did nothing at all. The WhatsApp-to-patient path is untouched:
 * that is a different channel with its own DPDP gate.
 */
class LabNotificationService
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /**
     * Called after every status transition.
     *
     * @param LabCase $case     Already saved with the new status.
     * @param string  $from     Previous status slug.
     * @param string  $to       New status slug.
     * @param User    $actor    The user who triggered the transition.
     */
    public function onTransition(LabCase $case, string $from, string $to, User $actor): void
    {
        try {
            match ($to) {
                'trial_received'  => $this->notifyTrialReceived($case, $actor),
                'final_received'  => $this->notifyFinalReceived($case, $actor),
                'complete'        => $this->notifyComplete($case, $actor),
                'rejected'        => $this->notifyRejected($case, $actor),
                default           => null,
            };
        } catch (\Throwable $e) {
            Log::warning('LabNotificationService::onTransition failed', [
                'case'  => $case->id,
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Per-transition handlers ──────────────────────────────────────────────

    /**
     * trial_received → notify the assigned doctor to review the trial.
     */
    private function notifyTrialReceived(LabCase $case, User $actor): void
    {
        $patient = $case->patient?->name ?? 'Patient';
        $vendor  = $case->vendor?->name  ?? ($case->lab_vendor ?? 'Lab');
        $round   = $case->trial_round ?? 1;

        $this->dispatcher->fire('lab.trial_received', [
            'title'        => "Trial {$round} received — {$patient}",
            'message'      => "Lab case {$case->case_number} · Trial {$round} has arrived from {$vendor}. Review and approve before returning.",
            'action_url'   => route('lab.show', $case),
            'action_label' => 'Review case',
            'source'       => $case,
            'branch_id'    => $case->branch_id,
            'owner'        => $case->doctor_id ?: $this->resolveDoctor($case)?->id,
            'actor_id'     => $actor->id,
        ]);
    }

    /**
     * final_received → notify front desk to book delivery + patient WhatsApp.
     */
    private function notifyFinalReceived(LabCase $case, User $actor): void
    {
        $patient = $case->patient?->name ?? 'Patient';
        $vendor  = $case->vendor?->name  ?? ($case->lab_vendor ?? 'Lab');

        // Front desk + admin, from the matrix — NOT the doctor. Whoever books
        // the delivery appointment is the one who has to act on this.
        $this->dispatcher->fire('lab.final_received', [
            'title'        => "Final work received — {$patient}",
            'message'      => "Lab case {$case->case_number} · Final restoration received from {$vendor}. Schedule the patient's delivery appointment.",
            'action_url'   => route('lab.show', $case),
            'action_label' => 'Schedule delivery',
            'source'       => $case,
            'branch_id'    => $case->branch_id,
            'actor_id'     => $actor->id,
        ]);

        // WhatsApp → patient (DPDP-gated, best-effort). A different channel
        // with its own consent rules; the dispatcher has no say over it.
        $this->sendPatientWhatsApp($case, 'lab_ready', [
            'name' => $this->firstName($patient),
            'work' => 'dental work',
        ]);
    }

    /**
     * complete → notify the doctor the job is delivered.
     */
    private function notifyComplete(LabCase $case, User $actor): void
    {
        $patient = $case->patient?->name ?? 'Patient';

        $this->dispatcher->fire('lab.complete', [
            'title'        => "Lab case complete — {$patient}",
            'message'      => "Case {$case->case_number} has been marked complete and delivered to the patient.",
            'action_url'   => route('lab.show', $case),
            'action_label' => 'View case',
            'source'       => $case,
            'branch_id'    => $case->branch_id,
            'owner'        => $case->doctor_id ?: $this->resolveDoctor($case)?->id,
            'actor_id'     => $actor->id,
        ]);
    }

    /**
     * rejected → notify the doctor so they can re-order if needed.
     */
    private function notifyRejected(LabCase $case, User $actor): void
    {
        $patient = $case->patient?->name ?? 'Patient';

        $this->dispatcher->fire('lab.rejected', [
            'title'        => "Lab case rejected — {$patient}",
            'message'      => "Case {$case->case_number} was rejected. Review and decide on next steps.",
            'action_url'   => route('lab.show', $case),
            'action_label' => 'View case',
            'source'       => $case,
            'branch_id'    => $case->branch_id,
            'owner'        => $case->doctor_id ?: $this->resolveDoctor($case)?->id,
            'actor_id'     => $actor->id,
        ]);
    }

    // ── Overdue alert (called from scheduler, not from transition) ───────────

    /**
     * Fire overdue notifications for all open-status cases past their due date.
     * Safe to call daily — uses notification dedup via existing records.
     *
     * Returns the count of notifications sent.
     */
    public function fireOverdueAlerts(): int
    {
        $today    = now()->toDateString();
        $notified = 0;

        LabCase::with(['patient', 'vendor'])
            ->whereIn('status', LabCase::OPEN_STATUSES)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', $today)
            ->chunk(50, function ($cases) use (&$notified, $today) {
                foreach ($cases as $case) {
                    try {
                        $daysLate = now()->diffInDays($case->expected_return_date);
                        $patient  = $case->patient?->name ?? 'Patient';
                        $vendor   = $case->vendor?->name  ?? 'Lab';
                        $caseNo   = $case->case_number;
                        $url      = route('lab.show', $case);

                        // ONE alert per case per DAY. Before N-5 this
                        // claimed "dedup via existing records" and had none —
                        // a case ten days late produced ten notifications, so
                        // the alert taught people to ignore it. The date in
                        // dedupe_scope is what makes the daily sweep honest.
                        $this->dispatcher->fire('lab.overdue', [
                            'title'        => "Lab case overdue {$daysLate}d — {$patient}",
                            'message'      => "Case {$caseNo} from {$vendor} is {$daysLate} day(s) overdue. Status: "
                                . (LabCase::STATUS_LABELS[$case->status] ?? $case->status),
                            'action_url'   => $url,
                            'action_label' => 'View case',
                            'source'       => $case,
                            'branch_id'    => $case->branch_id,
                            'owner'        => $case->doctor_id ?: $this->resolveDoctor($case)?->id,
                            'dedupe_scope' => $today,
                            // A scheduled sweep has no actor — nobody "did"
                            // this, so nobody is excluded from hearing it.
                            'actor_id'     => null,
                        ]);

                        $notified++;
                    } catch (\Throwable $e) {
                        Log::warning('LabNotificationService overdue alert failed', [
                            'case'  => $case->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $notified;
    }

    // ── Resolve users ────────────────────────────────────────────────────────

    private function resolveDoctor(LabCase $case): ?User
    {
        if ($case->doctor_id) {
            return User::find($case->doctor_id);
        }

        // Fallback: any dentist in same branch
        return User::where('branch_id', $case->branch_id)
            ->where('role', 'dentist')->orderBy('id')->first();
    }

    // resolveFrontDesk() was retired on 9 Sep with the N-5 migration: the
    // front desk is now resolved by NotificationDispatcher from the Roles
    // system, not by picking the first user whose LEGACY role string happens
    // to read 'receptionist' or 'front_desk'. That old lookup also told
    // exactly ONE receptionist — whichever had the lowest id — so the other
    // one never saw lab work at all.

    // ── WhatsApp helper ──────────────────────────────────────────────────────

    private function sendPatientWhatsApp(LabCase $case, string $template, array $params): void
    {
        if (! config('whatsapp.enabled')) {
            return;
        }

        $phone = $case->patient?->phone ?? null;
        if (! $phone) {
            return;
        }

        try {
            app(\App\Services\Whatsapp\OutboundMessageService::class)->sendTemplate(
                (string) $phone,
                $template,
                $params,
                ['patient_id' => $case->patient_id, 'dedup_key' => "{$template}:{$case->id}"]
            );
        } catch (\Throwable $e) {
            Log::warning("LabNotificationService WhatsApp ({$template}) failed", [
                'case'  => $case->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function firstName(string $fullName): string
    {
        return trim(explode(' ', trim($fullName))[0] ?? 'there');
    }
}
