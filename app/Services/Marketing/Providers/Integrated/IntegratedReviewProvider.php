<?php

namespace App\Services\Marketing\Providers\Integrated;

use App\Contracts\Marketing\Providers\ReviewProvider;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the existing Communication Reviews system — nothing new
 * built here, just exposed through the Marketing-facing interface (see
 * docs/marketing-module-technical-dossier.md §2 for the verified existing
 * collect/reply/track flow this delegates to).
 *
 * Every method is wrapped defensively — same pattern CampaignLeadService
 * already uses for cross-module reads — so a problem in the Reviews system
 * never breaks whatever Marketing screen asked for this data (e.g. the
 * Dashboard, which previously had this same try/catch inline before the
 * logic moved here in V3).
 */
class IntegratedReviewProvider implements ReviewProvider
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function pendingCount(int $clinicId): int
    {
        try {
            $threshold = (int) config('reviews.positive_threshold', 4);

            return Review::where('status', 'rated')
                ->where('rating', '<', $threshold)
                ->whereNull('clinic_reply')
                ->count();
        } catch (Throwable $e) {
            Log::warning('IntegratedReviewProvider: could not read pending reviews', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    public function stats(int $clinicId): array
    {
        try {
            return $this->reviews->stats();
        } catch (Throwable $e) {
            Log::warning('IntegratedReviewProvider: could not read review stats', ['error' => $e->getMessage()]);

            return ['requested' => 0, 'rated' => 0, 'avg' => 0, 'positive' => 0, 'negative' => 0];
        }
    }

    public function isManual(): bool
    {
        return false;
    }
}
