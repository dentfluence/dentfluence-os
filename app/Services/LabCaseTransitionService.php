<?php

namespace App\Services;

use App\Models\LabCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * LabCaseTransitionService — the single brain for lab-case status transitions.
 *
 * Extracted 2026-07-14 from web LabController::transition(), which was the
 * canonical implementation. The API copy had drifted badly:
 *   - stamped different (partly non-fillable) date columns,
 *   - never closed/created the follow-up Task chain,
 *   - never posted the lab Finance expense on final_received.
 *
 * Side effects (identical for web and mobile):
 *   1. Status + step-date stamped (web's canonical column mapping)
 *   2. trial_round incremented on trial_received
 *   3. Previous active Task closed, next workflow Task created
 *   4. LabNotificationService::onTransition fired (best-effort)
 *   5. LabExpenseService::createForCase on final_received (best-effort, idempotent)
 */
class LabCaseTransitionService
{
    /**
     * @return Task|null the follow-up task created for the new status (if any)
     * @throws \RuntimeException when the transition is not allowed
     */
    public function transition(LabCase $labCase, string $to, User $user): ?Task
    {
        if (! $labCase->canTransitionTo($to)) {
            throw new \RuntimeException("Cannot move case from '{$labCase->status}' to '{$to}'.");
        }

        $from    = $labCase->status; // capture before update for notifications
        $updates = ['status' => $to];
        $today   = today()->toDateString();

        // ── Stamp the date for this step ────────────────────────────────
        match ($to) {
            'order_placed'    => $updates['order_placed_date']    = $today,
            'impression_sent' => $updates['impression_sent_date'] = $today,
            'scan_sent'       => $updates['impression_sent_date'] = $today,
            'final_received'  => $updates['final_received_date']  = $today,
            'complete'        => $updates['delivered_date']        = $today,
            default           => null,
        };

        // ── Trial round tracking ─────────────────────────────────────────
        if ($to === 'trial_received') {
            $updates['trial_round'] = ($labCase->trial_round ?? 0) + 1;
        }

        $labCase->update($updates);
        $labCase->refresh();

        $patient = $labCase->patient?->name  ?? 'Patient';
        $vendor  = $labCase->vendor?->name   ?? ($labCase->lab_vendor ?? 'Lab');
        $caseNo  = $labCase->case_number;
        $dueDate = $labCase->expected_return_date?->format('Y-m-d') ?? $today;

        // ── Close / complete the previous active task for this case ─────
        if ($labCase->active_task_id) {
            Task::find($labCase->active_task_id)?->update([
                'status'  => 'done',
                'done_at' => now(),
            ]);
        }

        // ── Auto-create next task based on the new status ────────────────
        $task = null;

        // Find front desk and manager user IDs for assignment
        $frontDesk = User::where('role', 'receptionist')
            ->orWhere('role', 'front_desk')
            ->orderBy('id')->value('id') ?? $user->id;

        $doctor = $labCase->doctor_id ?? $user->id;

        $taskBase = [
            'created_by'  => $user->id,
            'branch_id'   => $labCase->branch_id ?? 1,
            'patient_id'  => $labCase->patient_id,
            'lab_case_id' => $labCase->id,
            'category'    => 'lab',
            'status'      => 'pending',
            // tasks.priority enum = ['urgent','high','medium','low'] — no 'normal'
            'priority'    => $labCase->priority === 'express' ? 'high' : ($labCase->priority === 'urgent' ? 'high' : 'medium'),
        ];

        match ($to) {

            'order_placed' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Dispatch impression/scan to {$vendor} — {$patient}",
                'description' => "Lab case {$caseNo} has been ordered. Send physical impression or digital scan to lab. Expected return: {$dueDate}.",
                'assigned_to' => $frontDesk,
                'due_date'    => $today,
            ])),

            'impression_sent', 'scan_sent' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Confirm {$vendor} received impression — {$patient}",
                'description' => "Impression/scan dispatched for {$caseNo}. Call/WhatsApp lab to confirm receipt and expected return date.",
                'assigned_to' => $frontDesk,
                'due_date'    => now()->addDay()->toDateString(),
            ])),

            'trial_received' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Doctor to review Trial {$labCase->trial_round} — {$patient}",
                'description' => "Trial {$labCase->trial_round} received from {$vendor} for {$caseNo}. Doctor approval needed before next step.",
                'assigned_to' => $doctor,
                'due_date'    => $today,
                'priority'    => 'high',
            ])),

            'trial_returned' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Follow up with {$vendor} — Trial {$labCase->trial_round} correction — {$patient}",
                'description' => "Trial returned to lab for adjustment ({$caseNo}). Follow up in 2 days on correction progress.",
                'assigned_to' => $frontDesk,
                'due_date'    => now()->addDays(2)->toDateString(),
            ])),

            'final_received' => $task = Task::create(array_merge($taskBase, [
                'title'       => "Schedule delivery appointment — {$patient}",
                'description' => "Final work received from {$vendor} for {$caseNo}. Book patient appointment for delivery/fit.",
                'assigned_to' => $frontDesk,
                'due_date'    => $today,
                'priority'    => 'high',
            ])),

            'complete' => null,   // case is done — no new task
            'rejected' => null,

            default => null,
        };

        // Store reference to the newly created task
        if ($task) {
            $labCase->update(['active_task_id' => $task->id]);
        }

        // ── In-app notifications + patient WhatsApp (all transitions, best-effort) ─
        try {
            app(LabNotificationService::class)
                ->onTransition($labCase, $from, $to, $user);
        } catch (\Throwable $e) {
            Log::warning('LabNotificationService::onTransition failed', [
                'case'  => $labCase->id,
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
        }

        // ── Auto-create Finance expense when final work received (best-effort) ──
        // Idempotent: LabExpenseService checks expense_id guard + skips if no cost.
        if ($to === 'final_received') {
            try {
                app(LabExpenseService::class)->createForCase($labCase);
            } catch (\Throwable $e) {
                Log::warning('LabExpenseService::createForCase failed', [
                    'case'  => $labCase->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $task;
    }
}
