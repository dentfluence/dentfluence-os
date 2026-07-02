<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignGoal extends Model
{
    protected $table = 'mkt_campaign_goals';

    protected $fillable = [
        'campaign_id',
        'goal_type',
        'custom_label',
        'target_value',
        'actual_value',
        'unit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    // -----------------------------------------------------------------------
    // Computed helpers
    // -----------------------------------------------------------------------

    /** Progress percentage (0–100) */
    public function progressPct(): float
    {
        if ($this->target_value <= 0) return 0;
        return min(round(($this->actual_value / $this->target_value) * 100, 1), 100);
    }

    /** Display label (custom_label overrides goal_type) */
    public function displayLabel(): string
    {
        return $this->custom_label ?: ucfirst($this->goal_type);
    }
}
