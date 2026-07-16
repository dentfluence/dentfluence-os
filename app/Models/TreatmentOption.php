<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TreatmentOption
 * ---------------
 * A structured, priced choice within a Treatment (e.g. an implant system or a
 * crown material). OWNED BY THE TREATMENT MODULE — the single source of truth
 * for money. The Case Acceptance Engine reads these through
 * {@see \App\Services\Treatment\TreatmentPricingService}; it never writes or
 * caches prices.
 *
 * See docs/plan-case-acceptance-engine.md §4.1.
 */
class TreatmentOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_id',
        'group',       // implant_system | crown_material | addon | …
        'name',
        'price',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
