<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    use SoftDeletes;

    protected $table = 'mkt_campaigns';

    protected $fillable = [
        'clinic_id',
        'name',
        'description',
        'status',
        'channels',
        'start_date',
        'end_date',
        'budget_total',
        'budget_utilized',
        'campaign_color',
        'cover_image',
        'owner_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'channels'        => 'array',
        'budget_total'    => 'decimal:2',
        'budget_utilized' => 'decimal:2',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'deleted_at'      => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(CampaignGoal::class, 'campaign_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(MarketingPost::class, 'campaign_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(MarketingAsset::class, 'campaign_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(MarketingActivityLog::class, 'subject_id')
                    ->where('subject_type', static::class);
    }

    /** Team members (users) with their pivot role */
    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mkt_campaign_team', 'campaign_id', 'user_id')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    // -----------------------------------------------------------------------
    // Computed helpers
    // -----------------------------------------------------------------------

    /** Budget utilization as a percentage (0–100) */
    public function budgetUtilizationPct(): float
    {
        if ($this->budget_total <= 0) return 0;
        return round(($this->budget_utilized / $this->budget_total) * 100, 1);
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
