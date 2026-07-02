<?php

namespace App\Services;

use App\Models\LabCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * LabAlertService — v3 automatic lab alerts.
 *
 * One place that answers "what needs attention today?".
 * Consumed by Lab dashboard, notification center, Daily Huddle, and ERP dashboard.
 *
 * Now also creates Task records so overdue / stale alerts
 * show in the notification bell and are assigned to staff.
 */
class LabAlertService
{
    /** Eager loads used by every alert query */
    protected function base()
    {
        return LabCase::with(['patient:id,name,phone', 'doctor:id,name', 'vendor:id,name,phone']);
    }

    // ── Query helpers ────────────────────────────────────────────────────

    public function dueToday(): Collection
    {
        return $this->base()->dueToday()->orderBy('expected_return_date')->get();
    }

    public function dueTomorrow(): Collection
    {
        return $this->base()->dueTomorrow()->orderBy('expected_return_date')->get();
    }

    public function overdue(): Collection
    {
        return $this->base()->overdue()->orderBy('expected_return_date')->get();
    }

    /** Urgent / Express cases still open */
    public function urgent(): Collection
    {
        return $this->base()->active()
            ->whereIn('priority', ['urgent', 'express'])
            ->orderByRaw("FIELD(priority, 'express', 'urgent')")
            ->orderBy('expected_return_date')
            ->get();
    }

    /** Final work received — patient not yet collected (awaiting delivery) */
    public function awaitingDelivery(): Collection
    {
        return $this->base()->awaitingDelivery()->orderBy('final_received_date')->get();
    }

    /** Open cases sitting with no progress for more than N days */
    public function stale(int $days = 15): Collection
    {
        return $this->base()->pendingMoreThan($days)->orderBy('order_placed_date')->get();
    }

    /** Cases in the trial loop (trial_received / trial_returned) */
    public function trialLoop(): Collection
    {
        return $this->base()->inTrialLoop()->orderBy('updated_at')->get();
    }

    /** Open remake / repeat-work cases */
    public function openRemakes(): Collection
    {
        return $this->base()->remakes()->active()->orderBy('created_at')->get();
    }

    /** Counts for dashboard cards and notification badge */
    public function counts(): array
    {
        return [
            'due_today'         => LabCase::dueToday()->count(),
            'due_tomorrow'      => LabCase::dueTomorrow()->count(),
            'overdue'           => LabCase::overdue()->count(),
            'urgent'            => LabCase::active()->whereIn('priority', ['urgent', 'express'])->count(),
            'awaiting_delivery' => LabCase::awaitingDelivery()->count(),
            'stale'             => LabCase::pendingMoreThan(15)->count(),
            'trial_loop'        => LabCase::inTrialLoop()->count(),
            'open_remakes'      => LabCase::remakes()->active()->count(),
        ];
    }

    /**
     * Flat list of alerts for the notification center.
     * Each item: ['severity' => danger|warning|info, 'title', 'message', 'case'].
     */
    public function all(): Collection
    {
        $alerts = collect();

        foreach ($this->overdue() as $case) {
            $alerts->push($this->alert('danger', 'Lab Overdue',
                "{$case->case_number} · {$case->patient?->name} — {$case->overdueDays()} days late at {$case->vendor?->name}", $case));
        }

        foreach ($this->dueToday() as $case) {
            $alerts->push($this->alert('warning', 'Due Today',
                "{$case->case_number} · {$case->patient?->name} — expected from {$case->vendor?->name}", $case));
        }

        foreach ($this->dueTomorrow() as $case) {
            $alerts->push($this->alert('info', 'Due Tomorrow',
                "{$case->case_number} · {$case->patient?->name} — expected from {$case->vendor?->name}", $case));
        }

        foreach ($this->urgent() as $case) {
            $alerts->push($this->alert('warning', ucfirst($case->priority) . ' Case',
                "{$case->case_number} · {$case->patient?->name} — " . $case->statusLabel(), $case));
        }

        foreach ($this->awaitingDelivery() as $case) {
            $alerts->push($this->alert('info', 'Ready — Schedule Patient',
                "{$case->case_number} · {$case->patient?->name} — final work in, delivery not booked", $case));
        }

        foreach ($this->trialLoop() as $case) {
            $alerts->push($this->alert('warning', 'Trial Awaiting Review',
                "{$case->case_number} · {$case->patient?->name} — Trial #{$case->trial_round} " . $case->statusLabel(), $case));
        }

        foreach ($this->stale() as $case) {
            $alerts->push($this->alert('warning', 'Pending 15+ Days',
                "{$case->case_number} · {$case->patient?->name} — at lab since {$case->order_placed_date?->format('d M')}", $case));
        }

        return $alerts;
    }

    /**
     * Auto-create follow-up tasks for overdue lab cases that have no active task.
     *
     * Called by: php artisan lab:create-overdue-tasks
     * Scheduled:  daily at 9am via Console/Kernel.php
     *
     * Returns count of new tasks created.
     */
    public function createOverdueTasks(?int $branchId = null): int
    {
        $created = 0;

        // Pick a manager/admin to assign overdue tasks to
        $adminUser = User::where('is_active', true)
            ->whereIn('role', ['admin', 'manager'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderByRaw("FIELD(role,'manager','admin')")
            ->first();

        $assignTo = $adminUser?->id ?? 1;

        // 1. Overdue cases with no active task
        $overdueCases = $this->base()
            ->overdue()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNull('active_task_id')
            ->get();

        foreach ($overdueCases as $case) {
            $days = $case->overdueDays();
            $task = Task::create([
                'title'       => "⚠ Lab overdue {$days}d: {$case->case_number} — {$case->patient?->name}",
                'description' => "Lab case {$case->case_number} for {$case->patient?->name} is {$days} day(s) overdue at {$case->vendor?->name}. Status: " . $case->statusLabel() . ". Expected: {$case->expected_return_date?->format('d M Y')}. Follow up with lab to confirm delivery date.",
                'assigned_to' => $assignTo,
                'created_by'  => $assignTo,
                'branch_id'   => $case->branch_id,
                'patient_id'  => $case->patient_id,
                'lab_case_id' => $case->id,
                'due_date'    => now()->toDateString(),
                'priority'    => $days > 7 ? 'urgent' : 'high',
                'category'    => 'lab',
                'status'      => 'pending',
            ]);

            $case->update(['active_task_id' => $task->id]);
            $case->logEvent('task_created', "Auto-created overdue follow-up task #{$task->id} ({$days}d late)");
            $created++;
        }

        // 2. Trial-received cases sitting more than 2 days without doctor action
        $staleTrial = $this->base()
            ->where('status', 'trial_received')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('updated_at', '<', now()->subDays(2))
            ->whereNull('active_task_id')
            ->get();

        foreach ($staleTrial as $case) {
            $doctorId = $case->doctor_id ?? $assignTo;
            $task = Task::create([
                'title'       => "🔬 Trial review needed: {$case->case_number} — {$case->patient?->name}",
                'description' => "Trial work #{$case->trial_round} for {$case->patient?->name} has been at the clinic for 2+ days. Doctor needs to review and either approve (return to lab) or accept for final work.",
                'assigned_to' => $doctorId,
                'created_by'  => $assignTo,
                'branch_id'   => $case->branch_id,
                'patient_id'  => $case->patient_id,
                'lab_case_id' => $case->id,
                'due_date'    => now()->toDateString(),
                'priority'    => 'high',
                'category'    => 'lab',
                'status'      => 'pending',
            ]);

            $case->update(['active_task_id' => $task->id]);
            $case->logEvent('task_created', "Auto-created trial review task #{$task->id}");
            $created++;
        }

        return $created;
    }

    protected function alert(string $severity, string $title, string $message, LabCase $case): array
    {
        return [
            'severity' => $severity,
            'title'    => $title,
            'message'  => $message,
            'case_id'  => $case->id,
            'case'     => $case,
        ];
    }
}
