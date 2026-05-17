<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Task extends Model
{
    use SoftDeletes;

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
        'status',
        'done_at',
        'escalated_at',
        'escalation_note',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'done_at'      => 'datetime',
        'escalated_at' => 'datetime',
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

    public function escalations()
    {
        return $this->hasMany(Escalation::class, 'task_id');
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
