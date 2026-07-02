<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Your feedback · {{ $clinic }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f3f4f6; color:#111827; }
        .wrap { max-width:440px; margin:0 auto; padding:32px 18px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:26px 22px; box-shadow:0 2px 10px rgba(0,0,0,.04); }
        h1 { font-size:20px; margin:0 0 6px; }
        p.lead { margin:0 0 22px; color:#6b7280; font-size:14px; }
        .stars { display:flex; justify-content:center; gap:8px; direction:rtl; margin:8px 0 6px; }
        .stars input { display:none; }
        .stars label { font-size:42px; color:#d1d5db; cursor:pointer; transition:color .12s, transform .12s; line-height:1; }
        .stars label:hover, .stars label:hover ~ label,
        .stars input:checked ~ label { color:#FBBF24; }
        .stars label:active { transform:scale(1.15); }
        .hint { text-align:center; font-size:13px; color:#9ca3af; min-height:18px; margin-bottom:14px; }
        textarea { width:100%; border:1px solid #d1d5db; border-radius:10px; padding:11px 12px; font-size:14px; font-family:inherit; resize:vertical; }
        .comment-wrap { margin-top:8px; }
        label.field { display:block; font-size:13px; color:#374151; margin-bottom:5px; }
        button { width:100%; margin-top:18px; background:#0F6E56; color:#fff; border:none; border-radius:11px; padding:13px; font-size:15px; font-weight:600; cursor:pointer; }
        button:disabled { background:#9ca3af; cursor:not-allowed; }
        .err { color:#dc2626; font-size:13px; margin-top:8px; }
        .foot { text-align:center; color:#9ca3af; font-size:12px; margin-top:18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>How was your visit?</h1>
            <p class="lead">Your feedback helps {{ $clinic }} take better care of you. It only takes a moment.</p>

            <form method="POST" action="{{ route('review.submit', $review->token) }}">
                @csrf

                {{-- Stars (rtl so :checked ~ siblings highlight left-to-right visually) --}}
                <div class="stars">
                    @for($i = 5; $i >= 1; $i--)
                        <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}" required>
                        <label for="star{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">&#9733;</label>
                    @endfor
                </div>
                <div class="hint" id="hint">Tap a star to rate</div>

                <div class="comment-wrap">
                    <label class="field" for="comment">Anything you'd like to add? (optional)</label>
                    <textarea id="comment" name="comment" rows="3" maxlength="1500" placeholder="Tell us more…"></textarea>
                </div>

                @error('rating')<div class="err">{{ $message }}</div>@enderror

                <button type="submit" id="submitBtn" disabled>Submit feedback</button>
            </form>
            <div class="foot">{{ $clinic }}</div>
        </div>
    </div>

    <script>
        const labels = { 1:'Poor', 2:'Fair', 3:'Good', 4:'Very good', 5:'Excellent' };
        const hint = document.getElementById('hint');
        const btn  = document.getElementById('submitBtn');
        document.querySelectorAll('input[name="rating"]').forEach(r => {
            r.addEventListener('change', e => {
                hint.textContent = labels[e.target.value] || '';
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
