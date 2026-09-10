<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (branch, weekday). See the migration for why there are two
 * sessions rather than one open/close pair.
 */
class ClinicHour extends Model
{
    protected $fillable = [
        'branch_id',
        'weekday',
        'is_closed',
        'slot1_start',
        'slot1_end',
        'slot2_start',
        'slot2_end',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'weekday'   => 'integer',
    ];

    /** 0 = Sunday … 6 = Saturday, matching Carbon::dayOfWeek. */
    public const DAY_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dayName(): string
    {
        return self::DAY_NAMES[$this->weekday] ?? 'Unknown';
    }

    /**
     * The day's open sessions as [['start' => 'H:i', 'end' => 'H:i'], …].
     *
     * A session with a missing or reversed pair is skipped rather than
     * returned broken — a half-filled row should narrow the day, never open it
     * to midnight.
     *
     * @return array<int, array{start:string, end:string}>
     */
    public function sessions(): array
    {
        if ($this->is_closed) {
            return [];
        }

        $out = [];

        foreach ([['slot1_start', 'slot1_end'], ['slot2_start', 'slot2_end']] as [$s, $e]) {
            $start = $this->{$s};
            $end   = $this->{$e};

            if (empty($start) || empty($end)) {
                continue;
            }

            $start = substr((string) $start, 0, 5);
            $end   = substr((string) $end, 0, 5);

            if ($end <= $start) {
                continue;
            }

            $out[] = ['start' => $start, 'end' => $end];
        }

        return $out;
    }
}
