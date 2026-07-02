<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorBlockedSlot extends Model
{
    protected $fillable = [
        'doctor_id',
        'block_date',
        'start_time',
        'end_time',
        'reason',
        'block_type',
        'created_by',
    ];

    protected $casts = [
        'block_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Blocked slots within a date range (for calendar queries).
     */
    public function scopeInRange($query, string $start, string $end)
    {
        return $query->whereBetween('block_date', [$start, $end]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check whether a given doctor/date/time range overlaps this block.
     */
    public static function hasConflict(int $doctorId, string $date, string $startTime, string $endTime): bool
    {
        return static::where('doctor_id', $doctorId)
            ->where('block_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time',   '>',  $startTime)
            ->exists();
    }
}
