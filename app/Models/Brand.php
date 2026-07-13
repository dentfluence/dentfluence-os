<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 4 — Material/Brand master (docs/gap-analysis-treatment-planning-knowledge-bank.md).
 * Optionally scoped to a Material (e.g. "Ivoclar" under "Zirconia"); can
 * also stand alone (e.g. "Nobel Biocare" for implants).
 */
class Brand extends Model
{
    protected $guarded = [];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
