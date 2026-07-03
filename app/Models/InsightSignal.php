<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InsightSignal — one row of the Insights Engine projection
 * (Phase 6 · Slice 1). Derived/disposable — rebuilt by InsightsProjector from
 * the relevant domain tables, never written by hand and never a source of
 * truth. See the create_insight_signals_table migration.
 *
 * One row per (relationship_id, signal) — `signal` is one of:
 *   'health' — Relationship Health (warming/cooling)
 *   'ltv'    — Lifetime Value (realized + projected)
 *   'risk'   — Risk (dormancy/no-shows/unanswered outreach)
 *
 * @property int         $relationship_id
 * @property string      $signal
 * @property float|null  $score
 * @property string|null $level
 * @property float|null  $value_realized
 * @property float|null  $value_projected
 * @property array|null  $factors
 * @property \Carbon\Carbon|null $computed_at
 * @property \Carbon\Carbon|null $generated_at
 */
class InsightSignal extends Model
{
    protected $table = 'insight_signals';

    public const SIGNAL_HEALTH = 'health';
    public const SIGNAL_LTV    = 'ltv';
    public const SIGNAL_RISK   = 'risk';

    protected $fillable = [
        'relationship_id',
        'signal',
        'score',
        'level',
        'value_realized',
        'value_projected',
        'factors',
        'computed_at',
        'generated_at',
    ];

    protected $casts = [
        'score'           => 'float',
        'value_realized'  => 'float',
        'value_projected' => 'float',
        'factors'         => 'array',
        'computed_at'     => 'datetime',
        'generated_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForRelationship(Builder $query, int $relationshipId): Builder
    {
        return $query->where('relationship_id', $relationshipId);
    }

    public function scopeHealth(Builder $query): Builder
    {
        return $query->where('signal', self::SIGNAL_HEALTH);
    }

    public function scopeLtv(Builder $query): Builder
    {
        return $query->where('signal', self::SIGNAL_LTV);
    }

    public function scopeRisk(Builder $query): Builder
    {
        return $query->where('signal', self::SIGNAL_RISK);
    }
}
