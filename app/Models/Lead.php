<?php

namespace App\Models;

use App\Observers\LeadObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([LeadObserver::class])]
class Lead extends Model
{
    use SoftDeletes;

    // Phase 3 source channels (canonical list — matches formData() in PrmController)
    const LEAD_SOURCES = [
        'google_ads'   => 'Google Ads',
        'seo'          => 'SEO / Organic',
        'instagram'    => 'Instagram',
        'facebook'     => 'Facebook',
        'website_form' => 'Website Form',
        'whatsapp'     => 'WhatsApp',
        'phone_call'   => 'Phone Call',
        'walk_in'      => 'Walk-in',
        'referral'     => 'Referral',
        'other'        => 'Other',
    ];

    protected $fillable = [
        'name', 'phone', 'alt_phone', 'email',
        'stage', 'source', 'lead_source', 'urgency',
        'lead_value',
        'treatment', 'secondary_treatment',
        'assigned_to', 'assigned_to_id', 'followup_date', 'followup_time', 'preferred_contact',
        'notes', 'tags',
        'dob', 'gender', 'occupation', 'location', 'language', 'referred_by',
        // AI enrichment (Phase 1) — filled by LeadEnrichmentService.
        'ai_summary', 'ai_treatment_label', 'ai_urgency',
        'ai_estimated_value', 'ai_branch', 'ai_enriched_at',
    ];

    protected $casts = [
        'tags'               => 'array',
        'followup_date'      => 'date',
        'dob'                => 'date',
        'lead_value'         => 'decimal:2',
        'ai_estimated_value' => 'decimal:2',
        'ai_enriched_at'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('activity_date');
    }

    /**
     * The staff user this lead is assigned to (Phase 2a auto-assign).
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    // ── Computed attributes (array-accessible via $lead['is_overdue']) ─────────

    /**
     * Is the follow-up date in the past?
     */
    public function getIsOverdueAttribute(): bool
    {
        if (! $this->followup_date) {
            return false;
        }
        return $this->followup_date->isPast() && $this->stage !== 'converted' && $this->stage !== 'lost';
    }

    /**
     * How many days overdue (0 if not overdue).
     */
    public function getOverdueDaysAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }
        return (int) $this->followup_date->diffInDays(now());
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('followup_date')
                     ->where('followup_date', '<', today())
                     ->whereNotIn('stage', ['converted', 'lost']);
    }
}
