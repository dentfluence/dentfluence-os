<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * PatientJourney — journey instance (frozen §5.4). Journey-shaped spine, but
 * only `case_acceptance` is used in V1. Public access uses the NATIVE `token`
 * column and mirrors the PublicPresentationController pattern (isValid /
 * recordView), NOT the PresentationAccessToken table (impl plan Phase 2 §2.2).
 * Edit-after-send supersedes via `superseded_by`, never mutates (§6).
 */
class PatientJourney extends Model
{
    protected $fillable = [
        'journey_type', 'treatment_plan_id', 'patient_id', 'relationship_id',
        'decision_tree_id', 'token', 'delivery_mode', 'cost_visibility', 'phase',
        'status', 'pinned_kb_version', 'pinned_tree_version', 'superseded_by',
        'sent_at', 'expires_at', 'view_count', 'last_viewed_at', 'created_by',
    ];

    protected $casts = [
        'sent_at'        => 'datetime',
        'expires_at'     => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count'     => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    public function decisionTree(): BelongsTo
    {
        return $this->belongsTo(DecisionTree::class);
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function curations(): HasMany
    {
        return $this->hasMany(JourneyCuration::class)->orderBy('sort_order');
    }

    /** Doctor-added treatment options for this journey (from the Treatment list). */
    public function customOptions(): HasMany
    {
        return $this->hasMany(JourneyCustomOption::class)->orderBy('sort_order');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(CaseSelection::class);
    }

    public function sentSnapshot(): HasOne
    {
        return $this->hasOne(JourneySentSnapshot::class);
    }

    public function consentSnapshot(): HasOne
    {
        return $this->hasOne(CaseConsentSnapshot::class);
    }

    // ── Public-token helpers (mirror PresentationAccessToken) ─────────────

    /** Valid = not superseded and not past expiry. */
    public function isValid(): bool
    {
        if ($this->superseded_by !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function recordView(): void
    {
        $this->increment('view_count');
        $this->update(['last_viewed_at' => now()]);
    }

    /**
     * Public case-journey URL for a plan's latest live (sent, non-superseded,
     * unexpired) journey, or null. Used to OPTIONALLY place a "scan to explore
     * your plan" QR on the treatment-plan print — it only surfaces a journey
     * that already exists, never creates one.
     */
    public static function activeLinkUrlForPlan(int $treatmentPlanId): ?string
    {
        $journey = static::where('treatment_plan_id', $treatmentPlanId)
            ->whereNotNull('token')
            ->whereNull('superseded_by')
            ->latest('id')
            ->first();

        return $journey && $journey->isValid()
            ? route('case.public.show', $journey->token)
            : null;
    }
}
