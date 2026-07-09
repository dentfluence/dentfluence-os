<?php

namespace App\Services\Marketing\Providers\Standalone;

use App\Contracts\Marketing\Providers\ReviewProvider;
use App\Models\Marketing\MarketingSetting;

/**
 * A standalone clinic has no Patient/Appointment records to request reviews
 * from, so there's no real request/reply loop here — just a manually
 * maintained count for "reviews I collected some other way", entered in
 * Settings. Real collect/reply/track only exists once Integrated (see
 * IntegratedReviewProvider, which wraps the already-built Communication
 * Reviews system).
 */
class StandaloneReviewProvider implements ReviewProvider
{
    public function pendingCount(int $clinicId): int
    {
        return 0; // nothing to "reply to" without a real request/response loop
    }

    public function stats(int $clinicId): array
    {
        return [
            'requested' => 0,
            'rated'     => (int) MarketingSetting::get($clinicId, 'manual_reviews_collected', 0),
            'avg'       => (float) MarketingSetting::get($clinicId, 'manual_reviews_avg_rating', 0),
            'positive'  => (int) MarketingSetting::get($clinicId, 'manual_reviews_positive', 0),
            'negative'  => (int) MarketingSetting::get($clinicId, 'manual_reviews_negative', 0),
        ];
    }

    public function isManual(): bool
    {
        return true;
    }
}
