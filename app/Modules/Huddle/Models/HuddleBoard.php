<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class HuddleBoard extends Model
{
    protected $fillable = [
        'branch_id',
        'role',
        'date',
        'title',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'date'      => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(HuddleCard::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HuddleComment::class);
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(HuddleTaskLog::class, 'huddle_board_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('date', Carbon::today());
    }

    public function isToday(): bool
    {
        return $this->date->isToday();
    }

    public function cardsByColumn(string $columnKey): HasMany
    {
        return $this->hasMany(HuddleCard::class)
            ->where('column_key', $columnKey)
            ->orderBy('position');
    }
}
