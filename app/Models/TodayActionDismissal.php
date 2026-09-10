<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * TodayActionDismissal — suppression of one live-computed Today's Actions row.
 * See docs/feature-specs/feature-spec-action-board-dismiss.md and
 * App\Services\Relationship\TodayActionsEngine.
 *
 * Two lifetimes (W-10, 2026-09-10):
 *  - is_permanent = true  — "Stop chasing", "Not needed", or a closes_task
 *    outcome. The row stays off the board on every later day until the date
 *    that drives it moves (App\Observers\TodayActionDismissalLiftObserver).
 *  - is_permanent = false — one occurrence only ("not today"). Kept for the
 *    birthday WhatsApp send, which must come back next year.
 * dismissed_for_date is always the day the row was handled — that is what
 * the board's faded "done today" render keys on.
 */
class TodayActionDismissal extends Model
{
    protected $fillable = [
        'category',
        'subject_type',
        'subject_id',
        'dismissed_for_date',
        'is_permanent',
        'reason_key',
        'notes',
        'dismissed_by',
    ];

    protected $casts = [
        'dismissed_for_date' => 'date',
        'is_permanent'       => 'boolean',
    ];

    public function dismissedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /** Rows for one category + subject model. */
    private static function scopedTo(string $category, string $subjectType): Builder
    {
        return static::query()
            ->where('category', $category)
            ->where('subject_type', $subjectType);
    }

    /** Subject ids to keep off the board on $date — handled that day, or closed for good. */
    public static function dismissedIdsFor(string $category, string $subjectType, Carbon $date): array
    {
        return static::scopedTo($category, $subjectType)
            ->where(function (Builder $q) use ($date) {
                $q->whereDate('dismissed_for_date', $date->toDateString())
                  ->orWhere('is_permanent', true);
            })
            ->pluck('subject_id')
            ->all();
    }

    /** Dismiss-reason keys configured in Settings > Dismiss Reasons (cached per request). */
    protected static ?array $dismissReasonKeyCache = null;

    public static function dismissReasonKeys(): array
    {
        return static::$dismissReasonKeyCache ??= ActionOptionList::query()
            ->where('option_type', 'dismiss_reason')
            ->pluck('key')
            ->all();
    }

    /**
     * Action Board (includeDone) variant of dismissedIdsFor(): hides a TRUE
     * dismiss ("wrong number", "not needed" — reason_key is a configured
     * dismiss reason) whether it was written today or is permanent, and hides
     * a permanent HANDLED row from any earlier day. A row handled TODAY by a
     * call outcome, "Stop chasing" ('closed_manually') or a birthday send
     * ('whatsapp_sent') is deliberately NOT returned, so the board can render
     * it faded with its outcome instead of hiding it (2026-07-14).
     */
    public static function trueDismissedIdsFor(string $category, string $subjectType, Carbon $date): array
    {
        $day = $date->toDateString();

        return static::scopedTo($category, $subjectType)
            ->where(function (Builder $q) use ($day) {
                $q->where(function (Builder $true) use ($day) {
                    $true->whereIn('reason_key', static::dismissReasonKeys())
                         ->where(function (Builder $when) use ($day) {
                             $when->whereDate('dismissed_for_date', $day)
                                  ->orWhere('is_permanent', true);
                         });
                })->orWhere(function (Builder $earlier) use ($day) {
                    $earlier->where('is_permanent', true)
                            ->whereDate('dismissed_for_date', '<', $day);
                });
            })
            ->pluck('subject_id')
            ->all();
    }

    /**
     * The date that drives this subject's row moved (appointment rescheduled,
     * follow-up date changed) — a permanent close no longer describes the
     * new occurrence. Demote to a one-day row; the audit trail stays.
     */
    public static function liftFor(Model $subject): int
    {
        return static::query()
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey())
            ->where('is_permanent', true)
            ->update(['is_permanent' => false]);
    }
}
