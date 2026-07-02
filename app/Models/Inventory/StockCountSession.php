<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StockCountSession
 * One record per 15-day physical stock count cycle.
 */
class StockCountSession extends Model
{
    protected $fillable = [
        'session_no', 'count_date', 'next_count_due', 'status',
        'items_counted', 'items_adjusted', 'low_stock_count', 'critical_stock_count',
        'notes', 'started_by', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'count_date'     => 'date',
        'next_count_due' => 'date',
        'completed_at'   => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'session_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'completed_by');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ── Helpers ──────────────────────────────────────────────

    /** Generate the next session number, e.g. SCS-2026-001 */
    public static function generateSessionNo(): string
    {
        $year  = now()->year;
        $last  = static::whereYear('created_at', $year)
                       ->orderByDesc('id')
                       ->value('session_no');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;
        return 'SCS-' . $year . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }
}
