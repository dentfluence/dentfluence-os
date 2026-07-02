<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingPost;
use App\Models\Marketing\PlatformConnection;
use App\Models\Marketing\MarketingSetting;
use Carbon\Carbon;

/**
 * Computes the Marketing Score (0–100) for a clinic.
 *
 * Scoring breakdown:
 *   Posts this month    → up to 30 pts  (target: 20 posts = full score)
 *   Active campaigns    → up to 20 pts  (target: 3+ campaigns)
 *   Connected platforms → up to 20 pts  (target: 3+ platforms)
 *   Completion rate     → up to 30 pts  (published / (published + missed) × 30)
 */
class MarketingScoreService
{
    private int $clinicId;

    public function __construct(int $clinicId)
    {
        $this->clinicId = $clinicId;
    }

    /** Return the composite 0–100 score */
    public function score(): int
    {
        return min(100, (int) (
            $this->postsScore() +
            $this->campaignsScore() +
            $this->platformsScore() +
            $this->completionScore()
        ));
    }

    /** Detailed breakdown for UI display */
    public function breakdown(): array
    {
        return [
            'total'      => $this->score(),
            'posts'      => $this->postsScore(),
            'campaigns'  => $this->campaignsScore(),
            'platforms'  => $this->platformsScore(),
            'completion' => $this->completionScore(),
        ];
    }

    // -------------------------------------------------------------------------
    // Individual components
    // -------------------------------------------------------------------------

    private function postsScore(): float
    {
        $target    = 20;
        $published = MarketingPost::where('clinic_id', $this->clinicId)
            ->where('status', 'published')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return min(30, ($published / $target) * 30);
    }

    private function campaignsScore(): float
    {
        $active = Campaign::where('clinic_id', $this->clinicId)
            ->where('status', 'active')
            ->count();

        return match (true) {
            $active >= 3 => 20,
            $active == 2 => 15,
            $active == 1 => 8,
            default      => 0,
        };
    }

    private function platformsScore(): float
    {
        $connected = PlatformConnection::where('clinic_id', $this->clinicId)
            ->where('status', 'connected')
            ->count();

        return match (true) {
            $connected >= 3 => 20,
            $connected == 2 => 13,
            $connected == 1 => 6,
            default         => 0,
        };
    }

    private function completionScore(): float
    {
        $thisMonth = now()->startOfMonth();

        $published = MarketingPost::where('clinic_id', $this->clinicId)
            ->where('status', 'published')
            ->where('updated_at', '>=', $thisMonth)
            ->count();

        $failed = MarketingPost::where('clinic_id', $this->clinicId)
            ->where('status', 'failed')
            ->where('updated_at', '>=', $thisMonth)
            ->count();

        $total = $published + $failed;

        if ($total === 0) return 15; // neutral when no activity yet

        return ($published / $total) * 30;
    }
}
