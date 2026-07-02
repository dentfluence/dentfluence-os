<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\Request;

/**
 * ReviewPublicController — the patient-facing rating page (Phase B item 2.4).
 * ----------------------------------------------------------------------------
 * PUBLIC (no login): reached via the unique link we WhatsApp to the patient
 * (/r/{token}). They pick 1–5 stars and optionally leave a comment. Happy
 * ratings are offered the Google review link; lower ratings are thanked and kept
 * internal so the clinic can follow up privately.
 */
class ReviewPublicController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    /** Show the rating page. */
    public function show(string $token)
    {
        $review = Review::where('token', $token)->first();

        if (! $review || $this->reviews->isExpired($review)) {
            return response()->view('reviews.public.expired', [], $review ? 200 : 404);
        }

        // Already answered → straight to the thank-you page.
        if ($review->status === 'rated') {
            return $this->thanksView($review);
        }

        return view('reviews.public.rate', [
            'review'  => $review,
            'clinic'  => config('reviews.clinic_name') ?: config('app.name', 'our clinic'),
        ]);
    }

    /** Capture the rating. */
    public function submit(Request $request, string $token)
    {
        $review = Review::where('token', $token)->firstOrFail();

        if ($review->status === 'rated') {
            return $this->thanksView($review);
        }

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $this->reviews->recordResponse($review, $data['rating'], $data['comment'] ?? null);

        return $this->thanksView($review->fresh());
    }

    /** Render the thank-you page, offering Google only to happy patients. */
    private function thanksView(Review $review)
    {
        $googleUrl = ($review->isPositive() && filled(config('reviews.google_review_url')))
            ? config('reviews.google_review_url')
            : null;

        return view('reviews.public.thanks', [
            'review'    => $review,
            'clinic'    => config('reviews.clinic_name') ?: config('app.name', 'our clinic'),
            'googleUrl' => $googleUrl,
        ]);
    }
}
