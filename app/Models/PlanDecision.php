<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patient's decision about a presented treatment plan.
 *
 * APPEND-ONLY. Nothing in the application may update or delete a decision row.
 * A change of mind is a NEW row; the history is the point.
 */
class PlanDecision extends Model
{
    use HasFactory;

    public const ACCEPTED           = 'accepted';
    public const PARTIALLY_ACCEPTED = 'partially_accepted';
    public const DEFERRED           = 'deferred';
    public const REJECTED           = 'rejected';

    public const DECISIONS = [
        self::ACCEPTED           => 'Accepted',
        self::PARTIALLY_ACCEPTED => 'Partially Accepted',
        self::DEFERRED           => 'Deferred',
        self::REJECTED           => 'Rejected',
    ];

    /**
     * Decisions that mean "the patient has committed to at least some
     * treatment". Used for the accepted_at compatibility mirror and the PRE
     * Committed projection.
     */
    public const COMMITTING = [self::ACCEPTED, self::PARTIALLY_ACCEPTED];

    protected $fillable = [
        'treatment_plan_id',
        'decision',
        'defer_until',
        'notes',
        'source',
        'recorded_by',
    ];

    protected $casts = [
        'defer_until' => 'date',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanDecisionItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getLabelAttribute(): string
    {
        return self::DECISIONS[$this->decision] ?? ucfirst($this->decision);
    }

    /** Did the patient commit to at least part of this plan? */
    public function getIsCommittingAttribute(): bool
    {
        return in_array($this->decision, self::COMMITTING, true);
    }

    /**
     * A deferral with no agreed review date. Legitimate and common — the
     * patient said "later" without saying when. It must never be given an
     * invented date, and it creates no dated follow-up obligation.
     */
    public function getIsOpenEndedDeferralAttribute(): bool
    {
        return $this->decision === self::DEFERRED && is_null($this->defer_until);
    }

    protected static function booted(): void
    {
        // Append-only, enforced in the model rather than by convention alone.
        static::updating(function (self $decision) {
            throw new \RuntimeException(
                'Plan decisions are append-only. Record a new decision instead of editing decision #' . $decision->id . '.'
            );
        });

        static::deleting(function (self $decision) {
            throw new \RuntimeException(
                'Plan decisions are append-only and cannot be deleted (decision #' . $decision->id . ').'
            );
        });
    }
}
