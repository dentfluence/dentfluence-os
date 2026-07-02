<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingPost;

class CampaignService
{
    /**
     * Completion percentage based on published vs. total planned posts.
     */
    public static function completionPercentage(Campaign $campaign): float
    {
        $total     = MarketingPost::where('campaign_id', $campaign->id)->count();
        $published = MarketingPost::where('campaign_id', $campaign->id)
            ->where('status', 'published')
            ->count();

        if ($total === 0) return 0;

        return round(($published / $total) * 100, 1);
    }

    /**
     * Budget utilization percentage (0–100).
     */
    public static function budgetUtilizationPct(Campaign $campaign): float
    {
        if ($campaign->budget_total <= 0) return 0;
        return min(100, round(($campaign->budget_utilized / $campaign->budget_total) * 100, 1));
    }

    /**
     * Days remaining until end_date. Null if no end_date set.
     */
    public static function daysRemaining(Campaign $campaign): ?int
    {
        if (! $campaign->end_date) return null;
        $diff = now()->startOfDay()->diffInDays($campaign->end_date->startOfDay(), false);
        return max(0, (int) $diff);
    }
}
