<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LabCaseRating — doctor's quality rating for a completed lab case.
 *
 * Scores are 1–5. The computed avgScore() rolls up into vendor quality metrics.
 */
class LabCaseRating extends Model
{
    protected $fillable = [
        'lab_case_id', 'lab_vendor_id', 'rated_by',
        'fit', 'shade', 'margins', 'occlusion', 'quality',
        'communication', 'value', 'overall', 'notes',
    ];

    protected $casts = [
        'fit'           => 'integer',
        'shade'         => 'integer',
        'margins'       => 'integer',
        'occlusion'     => 'integer',
        'quality'       => 'integer',
        'communication' => 'integer',
        'value'         => 'integer',
        'overall'       => 'integer',
    ];

    // ── Score labels / display ───────────────────────────────────────────

    public const SCORE_LABELS = [
        1 => 'Poor',
        2 => 'Below Average',
        3 => 'Acceptable',
        4 => 'Good',
        5 => 'Excellent',
    ];

    public const CLINICAL_FIELDS = ['fit', 'shade', 'margins', 'occlusion', 'quality'];
    public const SERVICE_FIELDS   = ['communication', 'value'];

    // ── Relationships ────────────────────────────────────────────────────

    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'lab_vendor_id');
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    // ── Computed ─────────────────────────────────────────────────────────

    /**
     * Average across all filled scores (excluding nulls).
     */
    public function avgScore(): ?float
    {
        $allFields = array_merge(self::CLINICAL_FIELDS, self::SERVICE_FIELDS, ['overall']);
        $scores = collect($allFields)
            ->map(fn($f) => $this->$f)
            ->filter(fn($v) => $v !== null);

        return $scores->isNotEmpty() ? round($scores->avg(), 1) : null;
    }

    /**
     * Star display string — e.g. "★★★★☆"
     */
    public static function stars(int $score): string
    {
        $score = max(1, min(5, $score));
        return str_repeat('★', $score) . str_repeat('☆', 5 - $score);
    }

    public function overallLabel(): string
    {
        return self::SCORE_LABELS[$this->overall] ?? '—';
    }
}
