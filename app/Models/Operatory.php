<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Operatory — a named chair or room inside a branch.
 *
 * Intentionally simple. This is just a label that can be
 * attached to an appointment. Future phases will add notifications,
 * occupancy tracking, and analytics.
 */
class Operatory extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'display_order' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
