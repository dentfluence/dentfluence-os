{{-- Case Visit History --}}
<div style="padding:4px 0;">
    @if(isset($timeline) && $timeline['summary']['total_visits'] > 0)

    @foreach($timeline['all']->groupBy('visit_id')->filter(fn($g, $k) => $k) as $visitId => $visitMedia)
    <div style="border:1px solid #f3f4f6;border-radius:8px;padding:14px;margin-bottom:10px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="font-size:12px;font-weight:600;color:#374151;">
                Visit #{{ $visitId }}
            </div>
            <div style="font-size:11px;color:#9ca3af;">
                {{ optional($visitMedia->first())->upload_date?->format('d M Y') ?? '' }}
            </div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            @foreach($visitMedia->take(5) as $item)
            <div style="width:48px;height:48px;border-radius:4px;overflow:hidden;background:#f3f4f6;flex-shrink:0;">
                @if($item->display_url && in_array($item->media_type, ['photo','xray','opg']))
                    <img src="{{ $item->display_url }}" style="width:100%;height:100%;object-fit:cover;">
                @else
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:16px;">
                        {{ $item->media_icon }}
                    </div>
                @endif
            </div>
            @endforeach
            @if($visitMedia->count() > 5)
            <div style="width:48px;height:48px;border-radius:4px;background:#f9fafb;border:1px dashed #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6b7280;">
                +{{ $visitMedia->count() - 5 }}
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @else
    <div style="text-align:center;padding:40px 20px;color:#9ca3af;font-size:13px;">
        No visit history found for this case.
    </div>
    @endif
</div>
