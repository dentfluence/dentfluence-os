{{-- Media Gallery — grouped Before/During/After/Follow-up --}}
@php $compact = $compact ?? false; @endphp

@if(isset($timeline))

{{-- Gallery filter tabs --}}
@if(!$compact)
<div style="display:flex;gap:4px;margin-bottom:14px;flex-wrap:wrap;">
    <button type="button" id="gal-btn-all" onclick="filterGallery('all')"
            style="padding:4px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #6a0f70;background:#6a0f70;color:white;cursor:pointer;">
        All ({{ $timeline['summary']['total_media'] }})
    </button>
    @if($timeline['summary']['photos'] > 0)
    <button type="button" id="gal-btn-photo" onclick="filterGallery('photo')"
            style="padding:4px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;color:#6b7280;cursor:pointer;">
        Photos ({{ $timeline['summary']['photos'] }})
    </button>
    @endif
    @if($timeline['summary']['xrays'] > 0)
    <button type="button" id="gal-btn-xray" onclick="filterGallery('xray')"
            style="padding:4px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;color:#6b7280;cursor:pointer;">
        X-Rays ({{ $timeline['summary']['xrays'] }})
    </button>
    @endif
    @if($timeline['summary']['scans'] > 0)
    <button type="button" id="gal-btn-scan" onclick="filterGallery('scan')"
            style="padding:4px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;color:#6b7280;cursor:pointer;">
        Scans ({{ $timeline['summary']['scans'] }})
    </button>
    @endif
    @if($timeline['summary']['videos'] > 0)
    <button type="button" id="gal-btn-video" onclick="filterGallery('video')"
            style="padding:4px 12px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;color:#6b7280;cursor:pointer;">
        Videos ({{ $timeline['summary']['videos'] }})
    </button>
    @endif
</div>
@endif

{{-- Gallery sections --}}
@foreach($timeline['stages'] as $stageKey => $stage)
@if(count($stage['items']) > 0)

<div class="gal-section" style="margin-bottom:{{ $compact ? '14px' : '20px' }};">

    {{-- Section header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:8px;height:8px;border-radius:50%;background:{{ $stage['color'] }};"></div>
            <span style="font-size:11px;font-weight:700;color:{{ $stage['color'] }};text-transform:uppercase;letter-spacing:.05em;">{{ $stage['label'] }}</span>
        </div>
        <span style="font-size:10px;color:#9ca3af;">
            {{ optional(collect($stage['items'])->first())->upload_date?->format('d M Y') ?? '' }}
        </span>
    </div>

    {{-- Image grid --}}
    <div style="display:grid;grid-template-columns:repeat({{ $compact ? 3 : 4 }},1fr);gap:6px;">
        @php $limit = $compact ? 3 : count($stage['items']); @endphp
        @foreach(collect($stage['items'])->take($limit) as $item)
        <div class="gal-item" data-type="{{ $item->media_type }}"
             style="position:relative;border-radius:6px;overflow:hidden;aspect-ratio:1;background:#f3f4f6;cursor:pointer;border:1.5px solid transparent;transition:all .15s;"
             onmouseover="this.style.borderColor='{{ $stage['color'] }}';this.style.transform='scale(1.02)'"
             onmouseout="this.style.borderColor='transparent';this.style.transform='scale(1)'"
             onclick="previewMedia('{{ $item->display_url ?? '' }}', '{{ $item->media_type }}', '{{ $item->original_filename ?? '' }}')">

            @if($item->display_url && in_array($item->media_type, ['photo','xray','opg','cbct']))
                <img src="{{ $item->display_url }}"
                     style="width:100%;height:100%;object-fit:cover;"
                     loading="lazy"
                     onerror="this.style.display='none'">
            @elseif($item->media_type === 'video')
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;background:#111827;gap:4px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </div>
                    <span style="font-size:9px;color:rgba(255,255,255,.5);">Video</span>
                </div>
            @else
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:4px;">
                    <span style="font-size:24px;">{{ $item->media_icon }}</span>
                    <span style="font-size:9px;color:#9ca3af;text-transform:uppercase;font-weight:600;">{{ $item->media_type }}</span>
                </div>
            @endif

            @include('content-management.partials.shared.media-type-badge', ['type' => $item->media_type, 'tiny' => true])

        </div>
        @endforeach

        {{-- Show more tile --}}
        @if($compact && count($stage['items']) > 3)
        <div style="border-radius:6px;aspect-ratio:1;background:#f9fafb;border:1.5px dashed #e5e7eb;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;gap:2px;"
             onclick="switchCaseTab('gallery')">
            <span style="font-size:14px;font-weight:700;color:#6a0f70;">+{{ count($stage['items']) - 3 }}</span>
            <span style="font-size:9px;color:#9ca3af;">more</span>
        </div>
        @endif
    </div>

</div>

@endif
@endforeach

@else
<div style="text-align:center;padding:32px 20px;color:#9ca3af;font-size:13px;">No media files in this case.</div>
@endif
