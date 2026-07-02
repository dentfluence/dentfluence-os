<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\LabCase;
use App\Models\User;
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
 */
class LabNotificationService
{
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
        $doctor   = $this->resolveDoctor($case);
        $patient  = $case->patient?->name   ?? 'Patient';
        $vendor   = $case->vendor?->name    ?? ($case->lab_vendor ?? 'Lab');
        $round    = $case->trial_round ?? 1;
        $caseNo   = $case->case_number;
        $url      = route('lab.show', $case);

        AppNotification::notify(
            $doctor?->id,
            'lab',
            "Trial {$round} received — {$patient}",
            "Lab case {$caseNo} · Trial {$round} has arrived from {$vendor}. Please review and approve before returning.",
            $url,
            'Review Case'
        );
    }

    /**
     * final_received → notify front desk to book delivery + patient WhatsApp.
     */
    private function notifyFinalReceived(LabCase $case, User $actor): void
    {
        $frontDesk = $this->resolveFrontDesk($case);
        $patient   = $case->patient?->name ?? 'Patient';
        $vendor    = $case->vendor?->name  ?? ($case->lab_vendor ?? 'Lab');
        $caseNo    = $case->case_number;
        $url       = route('lab.show', $case);

        // In-app → front desk (or broadcast if no receptionist found)
        AppNotification::notify(
            $frontDesk?->id,
            'lab',
            "Final work received — {$patient}",
            "Lab case {$caseNo} · Final restoration received from {$vendor}. Please schedule patient delivery appointment.",
            $url,
            'Schedule Delivery'
        );

        // In-app → admin as well (for awareness)
        $admin = User::where('branch_id', $case->branch_id)
            ->where('role', 'admin')->orderBy('id')->first();
        if ($admin && $admin->id !== $frontDesk?->id) {
            AppNotification::notify(
                $admin->id,
                'lab',
                "Lab case ready for delivery — {$patient}",
                "{$caseNo} · Final work received from {$vendor}.",
                $url,
                'View Case'
            );
        }

        // WhatsApp → patient (DPDP-gated, best-effort)
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
        $doctor  = $this->resolveDoctor($case);
        $patient = $case->patient?->name ?? 'Patient';
        $caseNo  = $case->case_number;
        $url     = route('lab.show', $case);

        AppNotification::notify(
            $doctor?->id,
            'lab',
            "Lab case complete — {$patient}",
            "Case {$caseNo} has been marked complete and delivered to the patient.",
            $url,
            'View Case'
        );
    }

    /**
     * rejected → notify the doctor so they can re-order if needed.
     */
    private function notifyRejected(LabCase $case, User $actor): void
    {
        $doctor  = $this->resolveDoctor($case);
        $patient = $case->patient?->name ?? 'Patient';
        $caseNo  = $case->case_number;
        $url     = route('lab.show', $case);

        AppNotification::notify(
            $doctor?->id,
            'lab',
            "Lab case rejected — {$patient}",
            "Case {$caseNo} was rejected. Please review and decide on next steps.",
            $url,
            'View Case'
        );
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

                        // Notify doctor
                        $doctor = $this->resolveDoctor($case);
                        AppNotification::notify(
                            $doctor?->id,
                            'lab',
                            "⏰ Lab case overdue {$daysLate}d — {$patient}",
                            "Case {$caseNo} from {$vendor} is {$daysLate} day(s) overdue. Current status: " . (LabCase::STATUS_LABELS[$case->status] ?? $case->status),
                            $url,
                            'View Case'
                        );

                        // Notify admin
                        $admin = User::where('branch_id', $case->branch_id)
                            ->where('role', 'admin')->orderBy('id')->first();
                        if ($admin && $admin->id !== $doctor?->id) {
                            AppNotification::notify(
                                $admin->id,
                                'lab',
                                "Lab overdue — {$patient}",
                                "Case {$caseNo} is {$daysLate}d overdue.",
                                $url,
                                'View'
                            );
                        }

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

    private function resolveFrontDesk(LabCase $case): ?User
    {
        return User::where('branch_id', $case->branch_id)
            ->whereIn('role', ['receptionist', 'front_desk'])
            ->orderBy('id')->first();
    }

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
