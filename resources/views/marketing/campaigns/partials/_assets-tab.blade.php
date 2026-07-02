{{--
|==========================================================================
| Campaign Show — Assets Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_assets-tab.blade.php
|
| Campaign-filtered asset grid (5 mock assets).
| Matches the Marketing Library card style.
| Mock data only.
|==========================================================================
--}}

@php
$assets = [
    [
        'name'     => 'Smile Before-After.jpg',
        'type'     => 'Image',
        'size'     => '2.4 MB',
        'added'    => 'Jun 12, 2026',
        'tags'     => ['before-after','instagram','reel'],
        'preview'  => 'IM', // initials placeholder
        'color'    => 'linear-gradient(135deg,#f09433,#dc2743)',
    ],
    [
        'name'     => 'Clinic Tour Video.mp4',
        'type'     => 'Video',
        'size'     => '84 MB',
        'added'    => 'Jun 10, 2026',
        'tags'     => ['tour','facebook','video'],
        'preview'  => 'VD',
        'color'    => 'linear-gradient(135deg,#6a0f70,#b95cb7)',
    ],
    [
        'name'     => 'Monsoon Offer Banner.png',
        'type'     => 'Image',
        'size'     => '1.1 MB',
        'added'    => 'Jun 8, 2026',
        'tags'     => ['offer','banner','website'],
        'preview'  => 'IM',
        'color'    => 'linear-gradient(135deg,#1877F2,#4285F4)',
    ],
    [
        'name'     => 'Patient Testimonial Script.docx',
        'type'     => 'Document',
        'size'     => '48 KB',
        'added'    => 'Jun 5, 2026',
        'tags'     => ['testimonial','script'],
        'preview'  => 'DC',
        'color'    => 'linear-gradient(135deg,#2980b9,#6dd5fa)',
    ],
    [
        'name'     => 'Campaign Brand Kit.zip',
        'type'     => 'Archive',
        'size'     => '12.3 MB',
        'added'    => 'Jun 1, 2026',
        'tags'     => ['brand','kit','all-channels'],
        'preview'  => 'ZP',
        'color'    => 'linear-gradient(135deg,#16a085,#f4d03f)',
    ],
];

$typeBadge = [
    'Image'    => ['bg'=>'#e3f2fd','text'=>'#0d47a1'],
    'Video'    => ['bg'=>'#fce4ec','text'=>'#880e4f'],
    'Document' => ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
    'Archive'  => ['bg'=>'#fff3e0','text'=>'#e65100'],
];
@endphp

{{-- ─── TOOLBAR ──────────────────────────────────────────────────────────── --}}
<div style="
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:16px;
">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-family:'Inter',sans-serif; font-size:13px; color:#5a4868;">
            <strong style="color:#1e0a2c;">5</strong> assets in this campaign
        </span>

        {{-- Filter chips --}}
        <div style="display:flex; gap:6px;">
            @foreach(['All','Image','Video','Document'] as $f)
            <button style="
                padding:4px 11px; border-radius:20px;
                background:{{ $f==='All' ? '#6a0f70' : '#f9f3fa' }};
                border:1px solid {{ $f==='All' ? '#6a0f70' : 'rgba(185,92,183,0.2)' }};
                font-family:'Inter',sans-serif; font-size:11.5px; font-weight:{{ $f==='All' ? '600' : '400' }};
                color:{{ $f==='All' ? '#fff' : '#5a4868' }}; cursor:pointer;
            ">{{ $f }}</button>
            @endforeach
        </div>
    </div>

    {{-- Upload button --}}
    <button style="
        display:inline-flex; align-items:center; gap:6px;
        padding:7px 15px; border-radius:6px;
        background:linear-gradient(135deg,#6a0f70,#b95cb7); border:none;
        font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
        color:#fff; cursor:pointer;
    "
    onmouseover="this.style.opacity='0.88'"
    onmouseout="this.style.opacity='1'"
    >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        Upload Asset
    </button>
</div>

{{-- ─── ASSET GRID ───────────────────────────────────────────────────────── --}}
<div style="
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(200px,1fr));
    gap:14px;
">
    @foreach($assets as $asset)
    @php $tb = $typeBadge[$asset['type']] ?? ['bg'=>'#eee','text'=>'#555']; @endphp

    <div style="
        background:#ffffff;
        border:1px solid rgba(185,92,183,0.12);
        border-radius:10px;
        overflow:hidden;
        cursor:pointer;
        transition:box-shadow 150ms;
    "
    onmouseover="this.style.boxShadow='0 3px 14px rgba(106,15,112,0.1)'"
    onmouseout="this.style.boxShadow='none'"
    >
        {{-- Preview area --}}
        <div style="
            height:110px;
            background:{{ $asset['color'] }};
            display:flex; align-items:center; justify-content:center;
            position:relative;
        ">
            <span style="
                font-family:'Inter',sans-serif;
                font-size:26px; font-weight:700; color:rgba(255,255,255,0.35);
                letter-spacing:2px;
            ">{{ $asset['preview'] }}</span>

            {{-- Type badge overlay --}}
            <span style="
                position:absolute; top:8px; left:8px;
                background:{{ $tb['bg'] }}; color:{{ $tb['text'] }};
                font-family:'Inter',sans-serif;
                font-size:10px; font-weight:600;
                padding:2px 7px; border-radius:8px;
            ">{{ $asset['type'] }}</span>

            {{-- "…" overlay --}}
            <button style="
                position:absolute; top:6px; right:8px;
                background:rgba(255,255,255,0.25); border:none;
                border-radius:4px; padding:2px 6px;
                color:#fff; font-size:13px; cursor:pointer;
                line-height:1;
            ">•••</button>
        </div>

        {{-- Meta --}}
        <div style="padding:10px 12px 12px;">
            <p style="
                font-family:'Inter',sans-serif;
                font-size:12.5px; font-weight:500;
                color:#1e0a2c; margin:0 0 4px;
                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
            ">{{ $asset['name'] }}</p>
            <p style="
                font-family:'Inter',sans-serif;
                font-size:11px; color:#9b6aad; margin:0 0 8px;
            ">{{ $asset['size'] }} · Added {{ $asset['added'] }}</p>

            {{-- Tags --}}
            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                @foreach($asset['tags'] as $tag)
                <span style="
                    background:#f3eaf5; color:#6a0f70;
                    font-family:'Inter',sans-serif;
                    font-size:10px; font-weight:500;
                    padding:2px 7px; border-radius:8px;
                ">#{{ $tag }}</span>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach

    {{-- Upload drop zone --}}
    <div style="
        border:2px dashed rgba(185,92,183,0.3);
        border-radius:10px; height:176px;
        display:flex; flex-direction:column;
        align-items:center; justify-content:center; gap:8px;
        cursor:pointer; color:#9b6aad;
        transition:border-color 150ms;
    "
    onmouseover="this.style.borderColor='#b95cb7'; this.style.background='#faf5ff'"
    onmouseout="this.style.borderColor='rgba(185,92,183,0.3)'; this.style.background='transparent'"
    >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500;">
            Drop files here
        </span>
        <span style="font-family:'Inter',sans-serif; font-size:11px; color:#c9b0d4;">
            or click to browse
        </span>
    </div>
</div>
