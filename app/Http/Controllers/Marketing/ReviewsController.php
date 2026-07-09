<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Marketing's native Reviews & Reputation page.
 * ----------------------------------------------------------------------------
 * Reviews data/actions (ReviewService, Review model, send/reply routes) are
 * still owned by Communication — this controller only reuses that same
 * service to render the identical board under Marketing's own URL/layout
 * (/marketing/reviews) instead of linking out to /communication/reviews.
 * Built 2026-07-09 replacing an earlier ?from=marketing overlay approach
 * that kept the Communication URL and just bolted Marketing's tab bar on
 * top — Sumit flagged that as still feeling like leaving the module.
 *
 * The send/reply POST actions still target the Communication routes
 * (communication.reviews.send / .reply) — Laravel's back() redirect uses
 * the HTTP referer, so submitting from this page correctly returns here.
 *
 * See App\Http\Controllers\Communication\ReviewController for the
 * Communication-native equivalent — keep both in sync if the query/filter
 * logic changes.
 */
class ReviewsController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): View
    {
        $stats = $this->reviews->stats();

        $query = Review::with('patient')->latest();

        $filter = $request->query('filter', 'all');
        $threshold = (int) config('reviews.positive_threshold', 4);
        if ($filter === 'positive') {
            $query->where('rating', '>=', $threshold);
        } elseif ($filter === 'negative') {
            $query->whereNotNull('rating')->where('rating', '<', $threshold);
        } elseif ($filter === 'pending') {
            $query->where('status', 'requested');
        }

        $reviews = $query->paginate(20)->withQueryString();

        return view('marketing.reviews.index', compact('stats', 'reviews', 'filter'));
    }
}
