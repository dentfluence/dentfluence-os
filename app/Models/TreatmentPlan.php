<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlan extends Model
{
    use HasFactory, SoftDeletes;

    private const PLAN_TYPE_LABELS = [
        'best'       => 'Best Treatment Plan',
        'acceptable' => 'Acceptable Treatment Plan',
    ];

    protected $fillable = [
        'patient_id',
        'consultation_id',
        'plan_name',
        'plan_type',
        'status',
        'rows',          // legacy JSON — kept for backward compat
        'total',
        'overall_disc_pct',
        'aocp',
        'aocp_plan',
        'created_by',
    ];

    protected $casts = [
        'rows'             => 'array',
        'aocp'             => 'boolean',
        'total'            => 'decimal:2',
        'overall_disc_pct' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class)->orderBy('sort_order');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getPlanTypeLabelAttribute(): string
    {
        return self::PLAN_TYPE_LABELS[$this->plan_type] ?? ucfirst($this->plan_type);
    }

    public function getComputedTotalAttribute(): float
    {
        return (float) $this->items()->sum('total');
    }
}
