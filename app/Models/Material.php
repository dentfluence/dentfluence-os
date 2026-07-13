<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 4 — Material/Brand master (docs/gap-analysis-treatment-planning-knowledge-bank.md).
 * Deliberately name-only, same shape as Complaint/Diagnosis/Investigation.
 */
class Material extends Model
{
    protected $guarded = [];

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }
}
