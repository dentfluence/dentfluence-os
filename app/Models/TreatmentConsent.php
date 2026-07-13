<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 2 — Clinical Consent (docs/gap-analysis-treatment-planning-knowledge-bank.md).
 *
 * An immutable-by-convention snapshot of a merged consent document generated
 * for one treatment plan: which teeth/procedures it covered and the consent
 * text shown at that moment. Nothing in the app edits a row after creation —
 * generating consent again just creates a new row, preserving history.
 */
class TreatmentConsent extends Model
{
    protected $fillable = [
        'treatment_plan_id',
        'patient_id',
        'generated_by',
        'sections',
    ];

    protected $casts = [
        'sections' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
