<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use SoftDeletes, \App\Traits\BelongsToBranch, \App\Traits\Auditable;

    /** Tag audit-log entries for this model with the "tasks" module. */
    protected $auditModule = 'tasks';

    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'created_by',
        'branch_id',
        'patient_id',
        'due_date',
        'due_time',
        'priority',
        'category',
        'task_type',
        'status',
        'done_at',
        'escalated_at',
        'escalation_note',
        // Recurring / AMC fields
        'is_recurring',
        'recurrence_interval',
        'recurrence_unit',
        'maintenance_type',
        'parent_task_id',
        'next_due_date',
        // Vendor / PO-linked tasks
        'po_id',
        'vendor_note',
        // Lab case-linked tasks
        'lab_case_id',
        // Practice Protocol-generated tasks
        'practice_protocol_id',
        'requires_evidence',
        // Phase 5 — Relationship Engine: links auto-created tasks to a relationship
        'relationship_id',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'next_due_date' => 'date',
        'done_at'       => 'datetime',
        'escalated_at'  => 'datetime',
        'is_recurring'  => 'boolean',
        'requires_evidence' => 'boolean',
    ];

    // ── Constants ─────────────────────────────────────────────────

    /**
     * All task categories.
     * Communication categories (call/whatsapp/follow_up) auto-create a CommunicationQueue entry.
     * Maintenance category unlocks recurring fields.
     */
    public const CATEGORIES = [
        // Communication tasks → feed comm queue
        'call'        => 'Call',
        'whatsapp'    => 'WhatsApp',
        'follow_up'   => 'Follow-up',
        // Internal tasks
        'clinical'    => 'Clinical',
        'lab'         => 'Lab',
        'admin'       => 'Admin',
        'maintenance' => 'Maintenance / AMC',
        'other'       => 'Other',
    ];

    /** Categories that are communication-type (auto-create CommunicationQueue). */
    public const COMM_CATEGORIES = ['call', 'whatsapp', 'follow_up'];

    /**
     * Phase 3 — Task Engine Human/System split (flag: tasks.human_system_split).
     *
     * 'human'  → a person must act on this. Default for every task, including
     *            manual, Practice Protocol, Lab, PO, TreatmentVisit, and
     *            AppointmentReminderEngine tasks — a staff member still has
     *            to do the work even though those are auto-generated.
     * 'system' → a record created by TaskEngine::autoCreate() (i.e. Automation
     *            / RulesEngine-driven). Hidden from reception-facing task
     *            lists when the flag is on.
     */
    public const TASK_TYPES = [
        'human'  => 'Human',
        'system' => 'System',
    ];

    /** Maintenance sub-types. */
    public const MAINTENANCE_TYPES = [
        'ac_service'    => 'AC Service',
        'pest_control'  => 'Pest Control',
        'deep_cleaning' => 'Deep Cleaning',
        'autoclave'     => 'Autoclave Maintenance',
        'dental_chair'  => 'Dental Chair Servicing',
        'xray_machine'  => 'X-Ray Machine',
        'water_purifier'=> 'Water Purifier',
        'fire_safety'   => 'Fire Safety Check',
        'generator'     => 'Generator / UPS',
        'other'         => 'Other',
    ];

    /** Recurrence units for display. */
    public const RECURRENCE_UNITS = [
        'days'   => 'Day(s)',
        'weeks'  => 'Week(s)',
        'months' => 'Month(s)',
        'years'  => 'Year(s)',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\PurchaseOrder::class, 'po_id');
    }

    /** The practice protocol that generated this task (null for hand-made tasks). */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\PracticeProtocols\Models\PracticeProtocol::class, 'practice_protocol_id');
    }

    public function escalations()
    {
        return $this->hasMany(Escalation::class, 'task_id');
    }

    /** The root task of this recurring chain (null if this IS the root). */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Phase 5 — The relationship this task was auto-created for.
     * Null for manually created tasks.
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Relationship::class, 'relationship_id');
    }

    /** All child tasks spawned from this task. */
    public function childTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('due_date', '<', today())
                     ->where('status', 'pending');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('due_date', '>', today());
    }

    public function scopeMaintenance(Builder $query): Builder
    {
        return $query->where('category', 'maintenance');
    }

    /** Only tasks a person must act on. */
    public function scopeHuman(Builder $query): Builder
    {
        return $query->where('task_type', 'human');
    }

    /** Only Automation-created record tasks. */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('task_type', 'system');
    }

    /**
     * Reception-facing lists should call this instead of scopeHuman() directly —
     * it respects the tasks.human_system_split flag. While the flag is off,
     * behaviour is unchanged (System tasks still show, exactly like before the
     * split existed). Once the flag is flipped on, System tasks disappear from
     * staff "my work" lists without any further code changes.
     */
    public function scopeVisibleToReception(Builder $query): Builder
    {
        if (\App\Support\Features\Feature::enabled('tasks.human_system_split')) {
            return $query->where('task_type', 'human');
        }

        return $query;
    }

    // ── Recurring / AMC Helper ────────────────────────────────────

    /**
     * Spawn the next occurrence of this recurring task.
     * Called inside TaskController::markDone() when is_recurring = true.
     *
     * Calculates next due date from the CURRENT due_date (not today),
     * so the schedule stays consistent regardless of when it was completed.
     *
     * Returns the newly created Task.
     */
    public function spawnNext(): self
    {
        $unit     = $this->recurrence_unit ?? 'months';
        $interval = $this->recurrence_interval ?? 1;

        // Use Carbon's add method: addDays / addWeeks / addMonths / addYears
        $addMethod = 'add' . ucfirst($unit);
        $nextDue   = $this->due_date->copy()->{$addMethod}($interval);

        $next = self::create([
            'title'               => $this->title,
            'description'         => $this->description,
            'assigned_to'         => $this->assigned_to,
            'created_by'          => $this->created_by,
            'branch_id'           => $this->branch_id,
            'patient_id'          => $this->patient_id,
            'due_date'            => $nextDue,
            'due_time'            => $this->due_time,
            'priority'            => $this->priority,
            'category'            => $this->category,
            'maintenance_type'    => $this->maintenance_type,
            'is_recurring'        => true,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_unit'     => $this->recurrence_unit,
            // Always point to the root task of the chain
            'parent_task_id'      => $this->parent_task_id ?? $this->id,
            'status'              => 'pending',
        ]);

        // Store next_due_date on the completed task for quick display
        $this->update(['next_due_date' => $nextDue]);

        return $next;
    }

    /**
     * Human-readable recurrence label.
     * e.g. "Every 3 months" | "Every 2 weeks"
     */
    public function recurrenceLabel(): string
    {
        if (! $this->is_recurring) {
            return '';
        }
        $unit = $this->recurrence_unit ?? 'months';
        $n    = $this->recurrence_interval ?? 1;
        return "Every {$n} " . ($n == 1 ? rtrim($unit, 's') : $unit);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCommTask(): bool
    {
        return in_array($this->category, self::COMM_CATEGORIES);
    }

    public function isSystemTask(): bool
    {
        return $this->task_type === 'system';
    }

    public function isHumanTask(): bool
    {
        return $this->task_type !== 'system';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function maintenanceTypeLabel(): string
    {
        return self::MAINTENANCE_TYPES[$this->maintenance_type] ?? 'Other';
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'high'   => 'amber',
            'medium' => 'blue',
            'low'    => 'green',
            default  => 'gray',
        };
    }
}
