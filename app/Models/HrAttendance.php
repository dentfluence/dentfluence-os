<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrAttendance extends Model
{
    // Migration creates 'hr_attendance' — override Eloquent's auto-plural
    protected $table = 'hr_attendance';

    protected $fillable = [
        'user_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'check_in_method',
        'check_out_method',
        'marked_by',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /* ── Status constants ── */

    const STATUS_PRESENT  = 'present';
    const STATUS_ABSENT   = 'absent';
    const STATUS_LATE     = 'late';
    const STATUS_HALF_DAY = 'half_day';
    const STATUS_ON_LEAVE = 'on_leave';
    const STATUS_HOLIDAY  = 'holiday';

    /* ── Relationships ── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /* ── Helpers ── */

    /**
     * Total hours worked (check_in to check_out), or null if incomplete.
     */
    public function getHoursWorkedAttribute(): ?string
    {
        if (! $this->check_in || ! $this->check_out) return null;

        $in  = \Carbon\Carbon::parse($this->check_in);
        $out = \Carbon\Carbon::parse($this->check_out);
        $diff = $in->diff($out);

        return $diff->h . 'h ' . $diff->i . 'm';
    }

    /**
     * Tailwind badge colour for status.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'present'  => 'bg-green-100 text-green-800',
            'late'     => 'bg-yellow-100 text-yellow-800',
            'half_day' => 'bg-blue-100 text-blue-800',
            'on_leave' => 'bg-purple-100 text-purple-800',
            'holiday'  => 'bg-gray-100 text-gray-600',
            default    => 'bg-red-100 text-red-800',   // absent
        };
    }

    /**
     * Human label for status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'present'  => 'Present',
            'absent'   => 'Absent',
            'late'     => 'Late',
            'half_day' => 'Half Day',
            'on_leave' => 'On Leave',
            'holiday'  => 'Holiday',
            default    => ucfirst($this->status),
        };
    }

    /* ── Scopes ── */

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopePresent($query)
    {
        return $query->whereIn('status', ['present', 'late', 'half_day']);
    }
}
