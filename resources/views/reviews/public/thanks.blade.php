<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you · {{ $clinic }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f3f4f6; color:#111827; }
        .wrap { max-width:440px; margin:0 auto; padding:48px 18px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:30px 24px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,.04); }
        .tick { font-size:54px; line-height:1; }
        h1 { font-size:21px; margin:14px 0 8px; }
        p { color:#6b7280; font-size:14.5px; margin:0 0 6px; }
        a.btn { display:inline-block; margin-top:20px; background:#4285F4; color:#fff; text-decoration:none; border-radius:11px; padding:13px 22px; font-size:15px; font-weight:600; }
        .stars { color:#FBBF24; font-size:26px; margin:6px 0 2px; }
        .foot { color:#9ca3af; font-size:12px; margin-top:22px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @if($googleUrl)
                <div class="tick">🌟</div>
                <div class="stars">{!! str_repeat('&#9733;', (int) $review->rating) !!}</div>
                <h1>Thank you so much!</h1>
                <p>We're thrilled you had a great experience. Would you mind sharing it on Google? It really helps others find {{ $clinic }}.</p>
                <a class="btn" href="{{ $googleUrl }}" target="_blank" rel="noopener">Leave a Google review</a>
            @else
                <div class="tick">✅</div>
                <h1>Thank you for your feedback</h1>
                <p>We've received your response and truly appreciate you taking the time. Someone from {{ $clinic }} may reach out to make things right.</p>
            @endif
            <div class="foot">{{ $clinic }}</div>
        </div>
    </div>
</body>
</html>
