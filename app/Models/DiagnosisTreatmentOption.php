<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DiagnosisTreatmentOption — Knowledge Bank MVP (Phase 1). One row = "for
 * this Diagnosis, this Treatment is ranked best/acceptable/alternative."
 *
 * Populated entirely by dentists via Settings → Knowledge Bank. Nothing in
 * the app reads this table automatically yet — it's a standalone reference
 * asset today, not (yet) wired into consultation-time suggestions. See
 * docs/gap-analysis-treatment-planning-knowledge-bank.md Phase 1.
 */
class DiagnosisTreatmentOption extends Model
{
    protected $fillable = [
        'diagnosis_id',
        'treatment_id',
        'rank',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    const RANKS = [
        'best'        => 'Recommended',
        'acceptable'  => 'Acceptable',
        'alternative' => 'Alternative',
    ];

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class, 'diagnosis_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function getRankLabelAttribute(): string
    {
        return self::RANKS[$this->rank] ?? ucfirst($this->rank);
    }
}
