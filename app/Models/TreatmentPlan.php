<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Human-readable labels for plan_type values.
     * Extend this map as new types are introduced.
     */
    private const PLAN_TYPE_LABELS = [
        'best'       => 'Best Treatment Plan',
        'acceptable' => 'Acceptable Treatment Plan',
    ];

    protected $fillable = [
        'consultation_id',
        'plan_type',
        'rows',
        'total',
        'aocp',
        'aocp_plan',
    ];

    protected $casts = [
        'rows'  => 'array',
        'aocp'  => 'boolean',
        'total' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Returns a human-readable label for the plan_type.
     * Falls back to the raw value (title-cased) for unknown types.
     *
     * Usage: $plan->plan_type_label
     */
    public function getPlanTypeLabelAttribute(): string
    {
        return self::PLAN_TYPE_LABELS[$this->plan_type]
            ?? ucfirst($this->plan_type);
    }
}
