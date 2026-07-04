{{--
|==========================================================================
| PRE — Today's Actions snapshot partial (Phase 1 · Workstream E, slice E4)
|
| A shared, embeddable read of the Today's Actions projection. Any surface
| (Daily Huddle, reception, etc.) can @include this — it reads ONE view, never
| the live 12 domains.
|
| Expects: $snapshot = TodayActionsProjector::summary()
|   ['total' => int, 'by_category' => array, 'by_priority' => array, 'generated_at' => ?string]
|==========================================================================
--}}
@php
    use Illuminate\Support\Carbon;
    $snap = $snapshot ?? ['total' => 0, 'by_category' => [], 'by_priority' => [], 'generated_at' => null];
    $high = $snap['by_priority']['high'] ?? 0;
    arsort($snap['by_category']);
    $topCategories = array_slice($snap['by_category'], 0, 4, true);
@endphp

<div style="background:#fff;border:1px solid #eceef2;border-radius:12px;padding:16px 18px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;">
        <span style="font-weight:700;color:#1f2937;font-size:14px;">Today's Actions</span>
        <a href="{{ route('relationship.today') }}" style="color:#534AB7;text-decoration:none;font-size:12.5px;font-weight:600;">Open Today's Actions →</a>
    </div>

    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <div style="font-size:26px;font-weight:800;color:#534AB7;line-height:1;">{{ number_format($snap['total']) }}</div>
            <div style="margin-top:4px;color:#6b7280;font-size:12px;font-weight:600;">Total actions</div>
        </div>
        <div>
            <div style="font-size:26px;font-weight:800;color:{{ $high > 0 ? '#8A1F1F' : '#4b5563' }};line-height:1;">{{ number_format($high) }}</div>
            <div style="margin-top:4px;color:#6b7280;font-size:12px;font-weight:600;">High priority</div>
        </div>
    </div>

    @if (! empty($topCategories))
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            @foreach ($topCategories as $cat => $count)
                <span style="font-size:11px;padding:2px 9px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                    {{ ucwords(str_replace('_', ' ', $cat)) }}: {{ $count }}
                </span>
            @endforeach
        </div>
    @endif

    <div style="margin-top:10px;color:#9ca3af;font-size:11px;">
        @if ($snap['generated_at'])
            Updated {{ Carbon::parse($snap['generated_at'])->diffForHumans() }} · from the shared projection
        @else
            Projection not built yet — run <code>today:rebuild-projection</code>
        @endif
    </div>
</div>
