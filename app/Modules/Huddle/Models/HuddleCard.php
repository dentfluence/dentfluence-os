<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class HuddleCard extends Model
{
    protected $fillable = [
        'huddle_board_id',
        'card_type',
        'source_type',
        'source_id',
        'column_key',
        'position',
        'status',
        'snapshot',
        'instruction',
        'assigned_to',
        'is_flagged',
        'is_carried_forward',
        'carried_from_date',
    ];

    protected $casts = [
        'snapshot'           => 'array',
        'is_flagged'         => 'boolean',
        'is_carried_forward' => 'boolean',
        'carried_from_date'  => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function board(): BelongsTo
    {
        return $this->belongsTo(HuddleBoard::class, 'huddle_board_id');
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(HuddleTaskLog::class)->latest('performed_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HuddleComment::class)->whereNull('parent_id')->latest();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeInColumn(Builder $query, string $columnKey): Builder
    {
        return $query->where('column_key', $columnKey);
    }

    public function scopeOfType(Builder $query, string $cardType): Builder
    {
        return $query->where('card_type', $cardType);
    }

    public function scopeForSource(Builder $query, string $sourceType, int $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue');
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
    }

    public function scopeCarriedForward(Builder $query): Builder
    {
        return $query->where('is_carried_forward', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPatientFlow(): bool
    {
        return $this->card_type === 'patient_flow';
    }

    public function isTask(): bool
    {
        return $this->card_type === 'task';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
