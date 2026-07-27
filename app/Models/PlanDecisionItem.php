<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One treatment item's verdict inside a patient decision.
 *
 * Exists so partial acceptance is queryable per item rather than encoded in a
 * blob. Never conflate with treatment_plan_items.status — that column is an
 * administrative/execution state, not a patient decision.
 */
class PlanDecisionItem extends Model
{
    use HasFactory;

    public const ACCEPTED        = 'accepted';
    public const DEFERRED        = 'deferred';
    public const REJECTED        = 'rejected';
    public const NOT_YET_DECIDED = 'not_yet_decided';

    public const DECISIONS = [
        self::ACCEPTED        => 'Accepted',
        self::DEFERRED        => 'Deferred',
        self::REJECTED        => 'Rejected',
        self::NOT_YET_DECIDED => 'Not Yet Decided',
    ];

    protected $fillable = [
        'plan_decision_id',
        'treatment_plan_item_id',
        'decision',
    ];

    public function planDecision(): BelongsTo
    {
        return $this->belongsTo(PlanDecision::class);
    }

    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    public function getLabelAttribute(): string
    {
        return self::DECISIONS[$this->decision] ?? ucfirst($this->decision);
    }

    protected static function booted(): void
    {
        static::updating(function (self $item) {
            throw new \RuntimeException('Decision items are append-only; record a new decision instead.');
        });

        static::deleting(function (self $item) {
            throw new \RuntimeException('Decision items are append-only and cannot be deleted.');
        });
    }
}
