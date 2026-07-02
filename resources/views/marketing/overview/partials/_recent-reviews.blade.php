{{--
    _recent-reviews — social proof for the Marketing module (Phase B 4.7 tie-in).
    Self-contained: pulls the latest happy (threshold+) reviews from the reviews
    loop (2.4) so staff can turn real patient praise into posts. No controller
    wiring needed.
--}}
@php
    $reviewThreshold = (int) config('reviews.positive_threshold', 4);
    $happyReviews = \App\Models\Review::with('patient')
        ->where('status', 'rated')
        ->where('rating', '>=', $reviewThreshold)
        ->latest('responded_at')
        ->take(6)
        ->get();
    $googleUrl = config('reviews.google_review_url');
@endphp

<div style="margin-top:20px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 20px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px;">
        <div style="font-size:15px; font-weight:700; color:#111827; display:flex; align-items:center; gap:7px;">
            <i class="ti ti-heart" style="color:#e11d48;"></i> Patient love — turn it into posts
        </div>
        <a href="{{ url('/communication/reviews') }}" style="font-size:12.5px; color:#6b7280; text-decoration:none;">View all reviews →</a>
    </div>

    @if($happyReviews->isEmpty())
        <p style="margin:0; color:#9ca3af; font-size:13px;">
            No 5-star reviews yet. They'll appear here as patients respond to review requests — ready to share as social proof.
        </p>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:12px;">
            @foreach($happyReviews as $r)
                <div style="border:1px solid #f1f5f9; background:#fafafa; border-radius:10px; padding:12px 14px;">
                    <div style="color:#FBBF24; font-size:14px; letter-spacing:1px;">{!! str_repeat('&#9733;', (int) $r->rating) !!}</div>
                    <p style="margin:6px 0 8px; font-size:13px; color:#374151; line-height:1.4;">
                        {{ $r->comment ? '“'.\Illuminate\Support\Str::limit($r->comment, 140).'”' : 'Rated '.$r->rating.'/5' }}
                    </p>
                    <div style="font-size:12px; color:#6b7280;">— {{ $r->patient->name ?? 'A patient' }}</div>
                </div>
            @endforeach
        </div>
        @if($googleUrl)
            <p style="margin:12px 0 0; font-size:11.5px; color:#9ca3af;">Tip: happy patients are auto-routed to your Google page, building public proof over time.</p>
        @endif
    @endif
</div>
