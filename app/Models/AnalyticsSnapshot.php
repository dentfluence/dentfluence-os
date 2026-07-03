<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * AnalyticsSnapshot — one row of the Analytics Engine projection
 * (Phase 6 · Slice 2). Derived/disposable — rebuilt by AnalyticsProjector
 * from AnalyticsController's own (now-public) metric methods, never written
 * by hand and never a source of truth. See create_analytics_snapshots_table.
 *
 * @property string $metric
 * @property mixed  $value
 * @property \Carbon\Carbon|null $computed_at
 * @property \Carbon\Carbon|null $generated_at
 */
class AnalyticsSnapshot extends Model
{
    protected $table = 'analytics_snapshots';

    protected $fillable = [
        'metric',
        'value',
        'computed_at',
        'generated_at',
    ];

    protected $casts = [
        'value'        => 'array',
        'computed_at'  => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function scopeMetric(Builder $query, string $metric): Builder
    {
        return $query->where('metric', $metric);
    }
}
