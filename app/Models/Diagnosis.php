<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diagnosis extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consultation_id',
        'primary_diagnosis',
        'secondary_diagnosis',
        'risk_assessment',
        'notes',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter to high-risk diagnoses only.
     *
     * Usage:
     *   Diagnosis::highRisk()->get();
     *   $consultation->diagnoses()->highRisk()->get();
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_assessment', 'high');
    }
}
