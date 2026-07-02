<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * TreatmentKnowledge — the rules engine brain.
 *
 * Each row represents one dental specialty (orthodontics, periodontics, etc.).
 * The Consult Assist engine scans the chief complaint against trigger_keywords
 * and surfaces the matched specialties as suggestions.
 *
 * Adding a new specialty = inserting one row. Zero code changes needed.
 */
class TreatmentKnowledge extends Model
{
    use HasFactory;

    protected $table = 'treatment_knowledge';

    protected $fillable = [
        'specialty_tag',
        'display_label',
        'display_icon',
        'trigger_keywords',
        'patient_concerns',
        'suggested_questions',
        'suggested_findings',
        'suggested_investigations',
        'possible_diagnoses',
        'module_config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'trigger_keywords'         => 'array',
        'patient_concerns'         => 'array',
        'suggested_questions'      => 'array',
        'suggested_findings'       => 'array',
        'suggested_investigations' => 'array',
        'possible_diagnoses'       => 'array',
        'module_config'            => 'array',
        'is_active'                => 'boolean',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check whether the given text matches any trigger keyword for this specialty.
     */
    public function matchesText(string $text): bool
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
     * Return all active specialties that match the given chief complaint text.
     *
     * @return \Illuminate\Database\Eloquent\Collection<TreatmentKnowledge>
     */
    public static function matchComplaint(string $text): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->get()->filter(fn($spec) => $spec->matchesText($text))->values();
    }
}
