<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Review;
use App\Services\Reviews\ReviewService;
use Illuminate\Http\Request;

/**
 * ReviewController — reputation dashboard (Phase B item 2.4, admin side).
 * ----------------------------------------------------------------------------
 * Lists every review request + outcome, shows simple stats, and lets staff send
 * a request to a patient on demand. Lives in the Communication OS shell.
 */
class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request)
    {
        $stats = $this->reviews->stats();

        $query = Review::with('patient')->latest();

        // Optional filter: all | positive | negative | pending
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

        return view('communication.reviews.index', compact('stats', 'reviews', 'filter'));
    }

    /** Send a review request to a patient on demand. */
    public function send(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);

        $res = $this->reviews->requestFromPatient($patient, [
            'requested_by_id' => $request->user()?->id,
            'dedup_key'       => 'review:manual:' . $patient->id . ':' . now()->toDateString(),
        ]);

        if (! ($res['send']['ok'] ?? false)) {
            return back()->with('error', 'Request created but not sent: ' . ($res['send']['reason'] ?? 'unknown'));
        }

        return back()->with('success', config('whatsapp.dry_run')
            ? 'Review request recorded (dry-run — nothing actually sent).'
            : 'Review request sent.');
    }

    /**
     * Log an internal reply/resolution note on a review. Internal only —
     * nothing is sent to the patient or posted to Google (see ReviewService::recordReply()).
     */
    public function reply(Request $request, Review $review)
    {
        $data = $request->validate([
            'clinic_reply' => ['required', 'string', 'max:2000'],
        ]);

        $this->reviews->recordReply($review, $data['clinic_reply'], $request->user()?->id);

        return back()->with('success', 'Reply saved.');
    }
}
