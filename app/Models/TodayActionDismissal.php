<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TodayActionDismissal — "not today" suppression for one occurrence of a
 * live-computed Today's Actions row. See
 * docs/feature-specs/feature-spec-action-board-dismiss.md and
 * App\Services\Relationship\TodayActionsEngine.
 */
class TodayActionDismissal extends Model
{
    protected $fillable = [
        'category',
        'subject_type',
        'subject_id',
        'dismissed_for_date',
        'reason_key',
        'notes',
        'dismissed_by',
    ];

    protected $casts = [
        'dismissed_for_date' => 'date',
    ];

    public function dismissedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /** Subject ids already dismissed today for a given category — used to exclude them from the live query. */
    public static function dismissedIdsFor(string $category, string $subjectType, \Illuminate\Support\Carbon $date): array
    {
        return static::query()
            ->where('category', $category)
            ->where('subject_type', $subjectType)
            ->whereDate('dismissed_for_date', $date->toDateString())
            ->pluck('subject_id')
            ->all();
    }
}
