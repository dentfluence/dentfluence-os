{{-- Auto Visit Timeline --}}
@if(isset($timeline) && count($timeline['stages']) > 0)

<div style="position:relative;padding-left:24px;">

    {{-- Vertical line --}}
    <div style="position:absolute;left:8px;top:8px;bottom:8px;width:2px;background:linear-gradient(to bottom,#6a0f70,#e9d5ff);border-radius:99px;"></div>

    @foreach($timeline['stages'] as $stageKey => $stage)
    <div style="margin-bottom:24px;position:relative;">

        {{-- Stage dot --}}
        <div style="position:absolute;left:-20px;top:4px;width:14px;height:14px;border-radius:50%;background:{{ $stage['color'] }};border:2px solid white;box-shadow:0 0 0 2px {{ $stage['color'] }}40;"></div>

        {{-- Stage header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div>
                <span style="font-size:12px;font-weight:700;color:{{ $stage['color'] }};">{{ $stage['label'] }}</span>
                @if(!empty($stage['items']))
                <span style="font-size:10px;color:#9ca3af;margin-left:6px;">
                    {{ optional(collect($stage['items'])->first())->upload_date?->format('d M Y') ?? '' }}
                </span>
                @endif
            </div>
            <span style="font-size:10px;color:#9ca3af;font-weight:600;">{{ count($stage['items']) }} file(s)</span>
        </div>

        {{-- Media grid for this stage --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
            @foreach(collect($stage['items'])->take(6) as $item)
            <div style="position:relative;border-radius:6px;overflow:hidden;aspect-ratio:1;background:#f3f4f6;cursor:pointer;border:1.5px solid transparent;transition:border-color .15s;"
                 onmouseover="this.style.borderColor='{{ $stage['color'] }}'" onmouseout="this.style.borderColor='transparent'"
                 onclick="previewMedia('{{ $item->display_url ?? '' }}', '{{ $item->media_type }}')">

                @if($item->display_url && in_array($item->media_type, ['photo','xray','opg','cbct']))
                    <img src="{{ $item->display_url }}"
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.parentElement.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;\'><span style=\'font-size:20px;\'>{{ $item->media_icon }}</span></div>'">
                @elseif($item->media_type === 'video')
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:4px;background:#111827;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white" stroke="none"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span style="font-size:9px;color:rgba(255,255,255,.6);">Video</span>
                    </div>
                @else
                    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:4px;">
                        <span style="font-size:22px;">{{ $item->media_icon }}</span>
                        <span style="font-size:9px;color:#9ca3af;text-transform:uppercase;">{{ $item->media_type }}</span>
                    </div>
                @endif

                {{-- Media type badge --}}
                <div style="position:absolute;top:4px;left:4px;">
                    @include('content-management.partials.shared.media-type-badge', ['type' => $item->media_type, 'tiny' => true])
                </div>

                {{-- Marketing tag indicator --}}
                @if($item->is_marketing)
                <div style="position:absolute;top:4px;right:4px;width:14px;height:14px;border-radius:50%;background:#dc2626;display:flex;align-items:center;justify-content:center;">
                    <svg width="7" height="7" viewBox="0 0 24 24" fill="white"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
                @endif

            </div>
            @endforeach

            @if(count($stage['items']) > 6)
            <div style="border-radius:6px;aspect-ratio:1;background:#f9fafb;border:1.5px dashed #e5e7eb;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;font-weight:700;color:#6b7280;"
                 onclick="switchCaseTab('gallery')">
                +{{ count($stage['items']) - 6 }}
            </div>
            @endif
        </div>

    </div>
    @endforeach

</div>

@else
<div style="text-align:center;padding:40px 20px;color:#9ca3af;font-size:13px;">
    No timeline data available for this case.
</div>
@endif
