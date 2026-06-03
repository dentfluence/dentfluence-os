<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Treatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_category_id',
        'name',
        'code',
        'description',
        'color',
        'default_duration_minutes',
        'default_price',
        'min_price',
        'max_price',
        'gst_pct',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'min_price'     => 'decimal:2',
        'max_price'     => 'decimal:2',
        'gst_pct'       => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(TreatmentCategory::class, 'treatment_category_id');
    }

    /** The currently active SOP (latest active version). */
    public function activeSop(): HasOne
    {
        return $this->hasOne(TreatmentSop::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    /** All SOP versions — used for review history. */
    public function sops(): HasMany
    {
        return $this->hasMany(TreatmentSop::class)->orderByDesc('version');
    }

    /** Active rules only. */
    public function rules(): HasMany
    {
        return $this->hasMany(TreatmentRule::class)->where('is_active', true);
    }

    public function allRules(): HasMany
    {
        return $this->hasMany(TreatmentRule::class);
    }

    /** All media, sorted. */
    public function media(): HasMany
    {
        return $this->hasMany(TreatmentMedia::class)->orderBy('sort_order');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Check if a specific rule type is active on this treatment. */
    public function hasRule(string $ruleType): bool
    {
        return $this->rules()->where('rule_type', $ruleType)->exists();
    }

    /** Get the value payload for a rule type. */
    public function ruleValue(string $ruleType): mixed
    {
        $rule = $this->rules()->where('rule_type', $ruleType)->first();
        return $rule?->value;
    }
}
