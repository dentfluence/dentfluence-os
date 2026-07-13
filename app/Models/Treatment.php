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
        'stages',
        'color',
        'default_duration_minutes',
        'default_price',
        'min_price',
        'max_price',
        'gst_pct',
        'unit_basis',   // per_tooth | whole_mouth | per_arch — drives auto-quantity
        'sort_order',
        'is_active',
        // Lab linkage
        'needs_lab',
        'lab_work_category',
        // Intelligence tab (P2C8)
        'trigger_keywords',
        'patient_concerns',
        'suggested_questions',
        'suggested_findings',
        'suggested_investigations',
        'possible_diagnoses',
        'specialty_tag',
        'consent_template',
        'patient_instructions',
        'treatment_pathways',
        // Extended clinical intelligence (added 2026-06-10)
        'chief_complaint_variations',
        'differential_diagnosis',
        'red_flags',
        'hopi_template',
        'suggested_treatment_options',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'min_price'     => 'decimal:2',
        'max_price'     => 'decimal:2',
        'gst_pct'       => 'decimal:2',
        'is_active'         => 'boolean',
        'needs_lab'         => 'boolean',
        // unit_basis intentionally left as string (per_tooth default)
        'stages'            => 'array',   // [{key: "stage_key", label: "Stage Label"}, ...]
        // Intelligence JSON columns
        'trigger_keywords'            => 'array',
        'patient_concerns'            => 'array',
        'suggested_questions'         => 'array',
        'suggested_findings'          => 'array',
        'suggested_investigations'    => 'array',
        'possible_diagnoses'          => 'array',
        'treatment_pathways'          => 'array',
        // Extended clinical intelligence
        'chief_complaint_variations'  => 'array',
        'differential_diagnosis'      => 'array',
        'red_flags'                   => 'array',
        'suggested_treatment_options' => 'array',
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

    /** Knowledge Bank — diagnoses this treatment is a ranked option for. */
    public function diagnosisOptions(): HasMany
    {
        return $this->hasMany(DiagnosisTreatmentOption::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return true if the complaint text matches any of this treatment's trigger keywords.
     */
    public function matchesComplaint(string $text): bool
    {
        $lower = strtolower($text);
        foreach ($this->trigger_keywords ?? [] as $keyword) {
            if (str_contains($lower, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return active treatments whose trigger_keywords match the given complaint text.
     * Ordered by sort_order. Capped at 5 results to keep the sidebar clean.
     */
    public static function matchComplaint(string $text, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->with('category')
            ->whereNotNull('trigger_keywords')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($t) => $t->matchesComplaint($text))
            ->take($limit)
            ->values();
    }

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
