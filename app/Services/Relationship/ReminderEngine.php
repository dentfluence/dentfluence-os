<?php

namespace App\Services\Relationship;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ReminderEngine — Phase 5, Relationship Engine
 *
 * Creates structured reminder tasks for relationships.
 * Does NOT own any rules — it consumes actions dispatched by RulesEngine.
 *
 * Key behaviours:
 *  - Deduplicates: checks for an existing open task of the same type for the
 *    same relationship before creating (prevents double-reminders on re-runs).
 *  - All created tasks are logged to ActivityEngine: 'reminder.created'.
 *  - Uses the existing Task model and tasks table — additive, non-destructive.
 */
class ReminderEngine
{
    public function __construct(
        protected ActivityEngine $activityEngine,
        protected TaskEngine     $taskEngine,
    ) {}

    /**
     * Create a reminder task for a relationship.
     *
     * @param  string  $type            Reminder type label (e.g. 'recall_6month', 'membership_renewal')
     * @param  Model   $subject         The subject that triggered the reminder (for context/logging)
     * @param  int     $relationshipId
     * @param  Carbon  $dueAt           When the reminder is due
     *
     * @return Task  The created (or existing duplicate) task
     */
    public function createReminder(
        string $type,
        Model  $subject,
        int    $relationshipId,
        Carbon $dueAt,
    ): Task {
        // Deduplication: same type reminder already open for this relationship?
        $existing = Task::where('relationship_id', $relationshipId)
            ->where('category', 'follow_up')
            ->where('description', 'LIKE', "[reminder:{$type}]%")
            ->whereIn('status', ['pending', 'escalated'])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            Log::debug("ReminderEngine: duplicate reminder suppressed", [
                'type'            => $type,
                'relationship_id' => $relationshipId,
                'existing_task'   => $existing->id,
            ]);
            return $existing;
        }

        $title = $this->titleFor($type);

        // Delegate task creation to TaskEngine (handles branch/created_by resolution + its own logging)
        $task = $this->taskEngine->autoCreate(
            category:       'follow_up',
            taskData:       [
                'title'       => $title,
                'description' => "[reminder:{$type}] Auto-created by ReminderEngine",
                'priority'    => $this->priorityFor($type),
                'due_date'    => $dueAt,
            ],
            relationshipId: $relationshipId,
            patientId:      $subject->patient_id ?? (get_class($subject) === \App\Models\Patient::class ? $subject->id : null),
        );

        // Log to ActivityEngine with the reminder-specific event
        $this->activityEngine->log(
            subject:        $subject,
            event:          'reminder.created',
            actor:          null,
            metadata:       [
                'reminder_type'   => $type,
                'task_id'         => $task->id,
                'due_at'          => $dueAt->toDateString(),
                'relationship_id' => $relationshipId,
            ],
            relationshipId: $relationshipId,
            description:    "Reminder created: [{$type}] due {$dueAt->toDateString()}",
        );

        return $task;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Human-readable task title for each reminder type.
     */
    protected function titleFor(string $type): string
    {
        return match ($type) {
            'recall_6month'       => '6-month recall due — call patient',
            'recall_annual'       => 'Annual recall due — call patient',
            'membership_renewal'  => 'Membership renewal reminder',
            'appointment_confirm' => 'Confirm appointment',
            'birthday'            => 'Birthday greeting',
            'lab_followup'        => 'Lab case follow-up',
            default               => ucwords(str_replace('_', ' ', $type)) . ' reminder',
        };
    }

    /**
     * Default priority for each reminder type.
     */
    protected function priorityFor(string $type): string
    {
        return match ($type) {
            'recall_6month', 'recall_annual' => 'medium',
            'membership_renewal'             => 'high',
            'appointment_confirm'            => 'high',
            'birthday'                       => 'low',
            default                          => 'medium',
        };
    }
}
