<?php

namespace App\Services\Reviews;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Review;
use App\Services\Whatsapp\OutboundMessageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * ReviewService — the brain of the review-request loop (Phase B item 2.4).
 * ----------------------------------------------------------------------------
 *  - requestFromPatient(): create a review row + send the WhatsApp request with
 *    a unique link. Reuses OutboundMessageService, so the send is DPDP
 *    consent-gated, recorded and audited like any other WhatsApp message.
 *  - recordResponse(): store the patient's rating; decide whether a happy
 *    patient is routed to your public Google page.
 *  - stats(): simple numbers for the admin dashboard.
 */
class ReviewService
{
    public function __construct(
        protected OutboundMessageService $wa = new OutboundMessageService(),
    ) {}

    /**
     * Create + send a review request to a patient.
     *
     * @param array $opts appointment_id, requested_by_id, dedup_key
     * @return array{review: Review, send: array}
     */
    public function requestFromPatient(Patient $patient, array $opts = []): array
    {
        $review = Review::create([
            'patient_id'      => $patient->id,
            'appointment_id'  => $opts['appointment_id'] ?? null,
            'token'           => Str::random(48),
            'channel'         => 'whatsapp',
            'status'          => 'requested',
            'requested_by_id' => $opts['requested_by_id'] ?? Auth::id(),
            'requested_at'    => now(),
        ]);

        $first = trim(explode(' ', trim((string) $patient->name))[0] ?? 'there');

        $send = $this->wa->sendTemplate(
            (string) $patient->phone,
            'review_request',
            ['name' => $first, 'link' => $this->link($review)],
            [
                'patient_id' => $patient->id,
                'dedup_key'  => $opts['dedup_key'] ?? null,
            ],
        );

        AuditLog::event('review_requested', $opts['requested_by_id'] ?? Auth::id(), [
            'review_id'  => $review->id,
            'patient_id' => $patient->id,
            'sent_ok'    => (bool) ($send['ok'] ?? false),
            'reason'     => $send['reason'] ?? null,
        ], [
            'module'         => 'reviews',
            'auditable_type' => Review::class,
            'auditable_id'   => $review->id,
        ]);

        return ['review' => $review, 'send' => $send];
    }

    /** Record the patient's rating + comment, and decide Google routing. */
    public function recordResponse(Review $review, int $rating, ?string $comment = null): Review
    {
        $rating = max(1, min(5, $rating));
        $positive = $rating >= (int) config('reviews.positive_threshold', 4);

        $review->update([
            'rating'           => $rating,
            'comment'          => $comment,
            'status'           => 'rated',
            'responded_at'     => now(),
            'routed_to_google' => $positive && filled(config('reviews.google_review_url')),
        ]);

        AuditLog::event('review_submitted', null, [
            'review_id' => $review->id,
            'rating'    => $rating,
            'positive'  => $positive,
        ], [
            'module'         => 'reviews',
            'auditable_type' => Review::class,
            'auditable_id'   => $review->id,
        ]);

        return $review->fresh();
    }

    /** Public link for a review request. */
    public function link(Review $review): string
    {
        return url('/r/' . $review->token);
    }

    /** Has the link expired (older than the configured TTL)? */
    public function isExpired(Review $review): bool
    {
        $ttl = (int) config('reviews.link_ttl_days', 14);
        return $review->requested_at
            && $review->requested_at->copy()->addDays($ttl)->isPast();
    }

    /** Simple aggregate stats for the dashboard. */
    public function stats(): array
    {
        $rated = Review::rated();

        return [
            'requested' => Review::count(),
            'rated'     => (clone $rated)->count(),
            'avg'       => round((float) (clone $rated)->avg('rating'), 2),
            'positive'  => (clone $rated)->where('rating', '>=', (int) config('reviews.positive_threshold', 4))->count(),
            'negative'  => (clone $rated)->where('rating', '<', (int) config('reviews.positive_threshold', 4))->count(),
        ];
    }
}
