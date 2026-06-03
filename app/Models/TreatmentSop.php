<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentSop extends Model
{
    protected $fillable = [
        'treatment_id',
        'version',
        'status',
        'doctor_steps',
        'assistant_steps',
        'pre_instructions',
        'post_instructions',
        'clinical_notes',
        'consent_notes',
        'last_reviewed_at',
        'next_review_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'doctor_steps'    => 'array',
        'assistant_steps' => 'array',
        'last_reviewed_at'=> 'date',
        'next_review_at'  => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Mark this SOP as reviewed, archive others for the same treatment. */
    public function markReviewed(int $userId, ?string $notes = null, ?\Carbon\Carbon $nextReview = null): void
    {
        $this->update([
            'status'          => 'active',
            'last_reviewed_at'=> now()->toDateString(),
            'next_review_at'  => $nextReview?->toDateString(),
            'reviewed_by'     => $userId,
            'review_notes'    => $notes,
        ]);

        // Archive all other active SOPs for this treatment
        static::where('treatment_id', $this->treatment_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'active')
            ->update(['status' => 'archived']);
    }
}
