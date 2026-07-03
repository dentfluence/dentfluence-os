<?php

namespace App\Services\Relationship;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * TaskEngine — Phase 5, Relationship Engine
 *
 * Additive extension of the existing Task model.
 * Auto-creates relationship-linked tasks from RulesEngine actions.
 * Does NOT replace any existing task creation flow.
 *
 * Key behaviours:
 *  - Deduplicates: will not create a task if an identical open task already
 *    exists for the same relationship/category/title on the same due date.
 *  - Sets relationship_id on the created task (requires Phase 5 migration).
 *  - Logs every auto-creation to ActivityEngine: 'task.auto_created'.
 */
class TaskEngine
{
    public function __construct(
        protected ActivityEngine $activityEngine,
    ) {}

    /**
     * Auto-create a task linked to a relationship.
     *
     * @param  string    $category        Task category ('call', 'whatsapp', 'admin', etc.)
     * @param  array     $taskData        Task fields: title, priority, due_date, description
     * @param  int       $relationshipId  The relationship this task belongs to
     * @param  int|null  $patientId       If known, link the task to a patient too
     * @param  int|null  $branchId        Branch the task belongs to (falls back to default)
     *
     * @return Task  The created task (or the existing duplicate)
     */
    public function autoCreate(
        string $category,
        array  $taskData,
        int    $relationshipId,
        ?int   $patientId = null,
        ?int   $branchId  = null,
    ): Task {
        $dueDate = $taskData['due_date'] instanceof Carbon
            ? $taskData['due_date']
            : Carbon::parse($taskData['due_date'] ?? now());

        // Deduplication: same relationship + category + title + same due date + still open
        $existing = Task::where('relationship_id', $relationshipId)
            ->where('category', $category)
            ->where('title', $taskData['title'])
            ->whereDate('due_date', $dueDate->toDateString())
            ->whereIn('status', ['pending', 'escalated'])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            Log::debug("TaskEngine: duplicate suppressed", [
                'relationship_id' => $relationshipId,
                'task_id'         => $existing->id,
                'title'           => $taskData['title'],
            ]);
            return $existing;
        }

        // Resolve branch_id — fallback to 1 (default branch) when nothing is passed
        $resolvedBranchId = $branchId
            ?? (Auth::check() ? Auth::user()->branch_id : null)
            ?? 1;

        // Resolve created_by — use system user (id=1) for automated tasks
        $createdBy = Auth::check() ? Auth::id() : 1;

        $task = Task::create([
            'title'           => $taskData['title'],
            'description'     => $taskData['description'] ?? null,
            'category'        => $category,
            'priority'        => $taskData['priority'] ?? 'medium',
            'status'          => 'pending',
            'due_date'        => $dueDate->toDateString(),
            'due_time'        => $taskData['due_time'] ?? null,
            'assigned_to'     => $taskData['assigned_to'] ?? null,
            'created_by'      => $createdBy,
            'branch_id'       => $resolvedBranchId,
            'patient_id'      => $patientId,
            'relationship_id' => $relationshipId,
            // Phase 3 — Task Engine Human/System split: every task that comes
            // through TaskEngine::autoCreate() is Automation-created, so it is
            // tagged 'system'. Hidden from reception lists once
            // tasks.human_system_split is flipped on (Task::scopeVisibleToReception).
            'task_type'       => 'system',
        ]);

        // Log to ActivityEngine so the Timeline shows why this task appeared
        $this->activityEngine->log(
            subject:        $task,
            event:          'task.auto_created',
            actor:          null,
            metadata:       [
                'category'        => $category,
                'title'           => $task->title,
                'due_date'        => $dueDate->toDateString(),
                'relationship_id' => $relationshipId,
            ],
            relationshipId: $relationshipId,
            description:    "Auto-task created: [{$task->title}] due {$dueDate->toDateString()}",
        );

        return $task;
    }
}
