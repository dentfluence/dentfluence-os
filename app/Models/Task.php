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
        // Task Manager V2 — outcome / reschedule / cancel facts.
        // NOTE: attempt_count, reschedule_count and original_due_date are NOT
        // fillable. They are counters and a one-time stamp; they may only be
        // moved by TaskOutcomeService, never by mass assignment from a form.
        'last_outcome_key',
        'last_outcome_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'next_due_date' => 'date',
        'done_at'       => 'datetime',
        'escalated_at'  => 'datetime',
        'is_recurring'  => 'boolean',
        'requires_evidence' => 'boolean',
        'original_due_date' => 'date',
        'last_outcome_at'   => 'datetime',
        'cancelled_at'      => 'datetime',
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

    /**
     * Outcome vocabulary for NON-communication tasks (clinical, lab, admin,
     * maintenance, other).
     *
     * Communication tasks (call / whatsapp / follow_up) do NOT use this list —
     * they read action_option_lists (option_type = 'call_outcome'), the same
     * clinic-editable list the PRE Today board uses, so reception never learns
     * two vocabularies for the same act of picking up a phone.
     *
     * Keys marked in NON_CLOSING_WORK_OUTCOMES leave the task open. This is the
     * same rule as the 12 Sep non-contact fix on the PRE side: work that did
     * not actually happen must never mark itself handled.
     */
    public const WORK_OUTCOMES = [
        'completed'        => 'Completed',
        'partially_done'   => 'Partially done',
        'blocked_material' => 'Blocked — material / stock not available',
        'blocked_vendor'   => 'Blocked — waiting on vendor / lab',
        'blocked_patient'  => 'Blocked — waiting on patient',
        'not_required'     => 'Not required any more',
    ];

    /**
     * Work outcomes that leave the task OPEN.
     * 'not_required' is absent on purpose — it resolves the task (via cancel),
     * exactly as 'wrong_number' resolves a call on the PRE side.
     */
    public const NON_CLOSING_WORK_OUTCOMES = [
        'partially_done',
        'blocked_material',
        'blocked_vendor',
        'blocked_patient',
    ];

    /** Terminal statuses — a task in one of these is off the working list. */
    public const CLOSED_STATUSES = ['done', 'cancelled'];

    /**
     * Maintenance sub-types — the SHIPPED DEFAULTS only.
     *
     * Since 22 Sep the live list is action_option_lists
     * (option_type = 'maintenance_type'), editable in Tasks > Settings, and
     * tasks.maintenance_type is a varchar rather than an enum so a clinic's
     * own type can actually be saved. Read the list through
     * maintenanceTypeOptions(); this constant is the fallback for a database
     * where the rows were deleted, and the seed source for the migration.
     */
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

    /**
     * The appointment this task produced, if it produced one.
     *
     * Deliberately NOT in $fillable: a booking link is a fact the system
     * observes after the calendar accepts a slot, never something a form
     * posts. It is stamped in TaskController::markDone() and nowhere else.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /** True when this task ended in a booked chair. */
    public function converted(): bool
    {
        return $this->appointment_id !== null;
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

    /**
     * The append-only outcome trail — every attempt, reschedule, close and
     * reopen, newest first.
     */
    public function outcomes(): HasMany
    {
        return $this->hasMany(TaskOutcome::class)->latest('created_at');
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

    /**
     * Open work — everything still on somebody's list.
     *
     * Prefer this over scopePending() in new code. It is written as "not
     * closed" rather than "= pending" so that a future non-terminal state
     * cannot silently vanish off the boards the way an 'attempted' STATUS
     * would have — see the Slice 1a migration note.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::CLOSED_STATUSES);
    }

    /** Closed work — done or cancelled. */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    /** Open tasks that have been worked at least once but not finished. */
    public function scopeAttempted(Builder $query): Builder
    {
        return $query->open()->where('attempt_count', '>', 0);
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

    /**
     * Query-builder twin of scopeVisibleToReception().
     *
     * Several staff-facing surfaces (the Huddle board and its report) read the
     * tasks table through DB::table() for speed, so the Eloquent scope never
     * runs there. They must still obey the same rule, so the rule lives in ONE
     * place and both entry points call it.
     *
     * Also guards deleted_at: a DB::table() read does not apply SoftDeletes.
     */
    public static function applyReceptionVisibility($query, string $table = 'tasks'): void
    {
        $query->whereNull("{$table}.deleted_at");

        if (\App\Support\Features\Feature::enabled('tasks.human_system_split')) {
            $query->where("{$table}.task_type", 'human');
        }
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Still on someone's list. */
    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Worked on at least once without being finished. */
    public function wasAttempted(): bool
    {
        return $this->isOpen() && (int) $this->attempt_count > 0;
    }

    public function wasRescheduled(): bool
    {
        return (int) $this->reschedule_count > 0;
    }

    /**
     * Days late, measured against the date this task was FIRST due — not the
     * date it has since been pushed to. Rescheduling must not launder a
     * backlog; the owner's red number has to stay honest.
     */
    public function daysLate(): int
    {
        if (! $this->isOpen()) {
            return 0;
        }

        $reference = $this->original_due_date ?? $this->due_date;

        return $reference->lt(today()) ? $reference->diffInDays(today()) : 0;
    }

    public function isOverdue(): bool
    {
        return $this->daysLate() > 0;
    }

    /** e.g. "Attempted x2" for the row badge. Empty string when never worked. */
    public function attemptLabel(): string
    {
        $n = (int) $this->attempt_count;

        return match (true) {
            $n <= 0 => '',
            $n === 1 => 'Attempted',
            default  => "Attempted x{$n}",
        };
    }

    /**
     * Does this outcome key leave the task open?
     *
     * The clinic's own setting decides (action_option_lists.closes_task),
     * EXCEPT for communication tasks, where a non-contact outcome always
     * leaves the task open whatever the stored value says. Nobody was spoken
     * to, so nothing was resolved — see TodayActionOptions and migration
     * 2026_09_12_000001.
     */
    public function outcomeLeavesOpen(?string $outcomeKey): bool
    {
        if (! $outcomeKey) {
            return false;
        }

        if ($this->isCommTask() && \App\Services\Relationship\TodayActionOptions::isNonContact($outcomeKey)) {
            return true;
        }

        $row = \App\Models\ActionOptionList::query()
            ->taskOutcomesFor($this->category)
            ->where('key', $outcomeKey)
            ->first();

        if ($row) {
            return ! $row->closes_task;
        }

        // No configured row (fallback vocabulary in use).
        return in_array($outcomeKey, self::NON_CLOSING_WORK_OUTCOMES, true);
    }

    public function outcomeLabelFor(?string $outcomeKey): ?string
    {
        return $outcomeKey ? (self::WORK_OUTCOMES[$outcomeKey] ?? null) : null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    /**
     * key => label for every active maintenance type, clinic's list first.
     * Falls back to the shipped constant only if a clinic has deactivated
     * every row — an empty dropdown is worse than a generic one.
     */
    public static function maintenanceTypeOptions(): array
    {
        $rows = \App\Models\ActionOptionList::query()
            ->where('option_type', 'maintenance_type')
            ->active()
            ->get();

        return $rows->isNotEmpty()
            ? \App\Models\ActionOptionList::labelMap($rows)
            : self::MAINTENANCE_TYPES;
    }

    public function maintenanceTypeLabel(): string
    {
        if (! $this->maintenance_type) {
            return 'Other';
        }

        // The clinic's current list first, then the shipped names, then the
        // stored key humanised — a type that was later deactivated must still
        // render as words on the task that used it, never as a blank.
        return self::maintenanceTypeOptions()[$this->maintenance_type]
            ?? self::MAINTENANCE_TYPES[$this->maintenance_type]
            ?? ucfirst(str_replace('_', ' ', $this->maintenance_type));
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
