<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrStaffShift extends Model
{
    protected $fillable = ['user_id', 'shift_id', 'effective_from', 'effective_to'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];

    /* ── Relationships ── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'shift_id');
    }

    /* ── Scope: currently active shift assignment ── */

    public function scopeCurrent($query)
    {
        return $query->where('effective_from', '<=', now())
                     ->where(function ($q) {
                         $q->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', now());
                     });
    }
}
