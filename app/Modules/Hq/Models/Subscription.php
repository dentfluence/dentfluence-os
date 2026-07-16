<?php

namespace App\Modules\Hq\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'clinic_id', 'plan_id', 'billing_cycle', 'amount',
        'starts_at', 'expires_at', 'status', 'notes',
    ];

    protected $casts = [
        'starts_at'  => 'date',
        'expires_at' => 'date',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('status', 'active')->whereDate('expires_at', '>=', now());
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->live()->whereDate('expires_at', '<=', now()->addDays($days));
    }

    public function scopeLapsed(Builder $q): Builder
    {
        return $q->where('status', 'active')->whereDate('expires_at', '<', now());
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_at, false);
    }

    // Monthly-equivalent revenue for MRR math.
    public function getMonthlyValueAttribute(): float
    {
        return $this->billing_cycle === 'annual'
            ? round($this->amount / 12)
            : $this->amount;
    }
}
