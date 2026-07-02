<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrShift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'branch_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /* ── Relationships ── */

    public function staffShifts(): HasMany
    {
        return $this->hasMany(HrStaffShift::class, 'shift_id');
    }

    /* ── Helpers ── */

    /**
     * Human-readable timing, e.g. "09:00 – 14:00"
     */
    public function getTimingAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' – ' . substr($this->end_time, 0, 5);
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
