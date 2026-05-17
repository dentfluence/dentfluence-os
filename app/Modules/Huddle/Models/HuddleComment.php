<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class HuddleComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'huddle_board_id',
        'huddle_card_id',
        'user_id',
        'body',
        'type',
        'parent_id',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function board(): BelongsTo
    {
        return $this->belongsTo(HuddleBoard::class, 'huddle_board_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(HuddleCard::class, 'huddle_card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'resolved_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(HuddleComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(HuddleComment::class, 'parent_id')->whereNull('deleted_at');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeHurdles(Builder $query): Builder
    {
        return $query->where('type', 'hurdle');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isHurdle(): bool
    {
        return $this->type === 'hurdle';
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }
}
