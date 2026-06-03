{{-- Case Viewer — slide-in panel content --}}
@if(isset($media) && isset($timeline))
<div style="display:flex;flex-direction:column;height:100%;">

    {{-- ── Panel Header ── --}}
    <div style="padding:16px 20px;background:linear-gradient(135deg,#6a0f70,#380740);flex-shrink:0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:16px;font-weight:700;color:white;font-family:'Cormorant Garamond',serif;">
                    {{ $media->patient->name ?? 'Unknown Patient' }}
                </div>
                <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:3px;display:flex;align-items:center;gap:8px;">
                    <span>{{ $media->treatment_name ?? '—' }}</span>
                    @if($media->tooth_no)
                    <span style="background:rgba(255,255,255,.15);padding:1px 6px;border-radius:3px;">Tooth {{ $media->tooth_no }}</span>
                    @endif
                    <span style="padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;
                        background: {{ $media->treatment_status === 'completed' ? '#dcfce7' : '#dbeafe' }};
                        color: {{ $media->treatment_status === 'completed' ? '#16a34a' : '#2563eb' }};">
                        {{ ucfirst($media->treatment_status ?? 'ongoing') }}
                    </span>
                </div>
            </div>
            <button onclick="closeCaseViewer()"
                    style="background:rgba(255,255,255,.15);border:none;color:white;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;flex-shrink:0;">×</button>
        </div>

        {{-- Case tabs --}}
        <div style="display:flex;gap:0;margin-top:14px;border-bottom:1px solid rgba(255,255,255,.15);">
            @foreach(['overview'=>'Case Overview','timeline'=>'Timeline','gallery'=>'Media Gallery','notes'=>'Notes'] as $t=>$l)
            <button type="button" id="cv-tab-{{ $t }}" onclick="switchCaseTab('{{ $t }}')"
                    style="padding:7px 14px;font-size:11px;font-weight:600;border:none;cursor:pointer;background:none;transition:all .15s;border-bottom:2px solid transparent;margin-bottom:-1px;
                    color: {{ $t === 'overview' ? 'white' : 'rgba(255,255,255,.6)' }};
                    border-bottom-color: {{ $t === 'overview' ? 'white' : 'transparent' }};">
                {{ $l }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── Panel Body (scrollable) ── --}}
    <div style="flex:1;overflow-y:auto;padding:20px;">

        {{-- ── OVERVIEW TAB ── --}}
        <div id="cv-panel-overview">

            {{-- Case details grid --}}
            <div style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:8px;padding:16px;margin-bottom:16px;">
                <div style="font-size:11px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;">Case Details</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    @foreach([
                        ['Start Date',    $media->treatment_start_date?->format('d M Y') ?? '—'],
                        ['Completion',    $media->treatment_end_date?->format('d M Y') ?? '—'],
                        ['Doctor',        optional(\App\Models\User::find($media->doctor_id))->name ?? '—'],
                        ['Total Media',   $timeline['summary']['total_media']],
                        ['Total Visits',  $timeline['summary']['total_visits']],
                        ['Treatment',     $media->treatment_name ?? '—'],
                    ] as [$label, $value])
                    <div>
                        <div style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px;">{{ $label }}</div>
                        <div style="font-size:13px;color:#111827;font-weight:500;">{{ $value }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Media summary pills --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                @foreach([
                    ['Photos',  $timeline['summary']['photos'],  '#6a0f70'],
                    ['X-Rays',  $timeline['summary']['xrays'],   '#2563eb'],
                    ['Scans',   $timeline['summary']['scans'],   '#16a34a'],
                    ['Videos',  $timeline['summary']['videos'],  '#d97706'],
                ] as [$label, $count, $color])
                <div style="padding:6px 12px;background:{{ $color }}10;border:1px solid {{ $color }}30;border-radius:99px;font-size:11px;font-weight:600;color:{{ $color }};cursor:pointer;"
                     onclick="switchCaseTab('gallery')">
                    {{ $label }}: {{ $count }}
                </div>
                @endforeach
            </div>

            {{-- Tags --}}
            @if($media->searchable_tags)
            <div style="margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Tags</div>
                <div style="display:flex;flex-wrap:wrap;gap:5px;">
                    @foreach($media->searchable_tags as $tag)
                    @include('content-management.partials.shared.tag-pill', ['label' => $tag, 'color' => '#6a0f70', 'bg' => '#f5f3ff'])
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Quick gallery preview --}}
            @include('content-management.partials.clinical.media-gallery', ['timeline' => $timeline, 'compact' => true])

        </div>

        {{-- ── TIMELINE TAB ── --}}
        <div id="cv-panel-timeline" style="display:none;">
            @include('content-management.partials.clinical.case-timeline', ['timeline' => $timeline])
        </div>

        {{-- ── GALLERY TAB ── --}}
        <div id="cv-panel-gallery" style="display:none;">
            @include('content-management.partials.clinical.media-gallery', ['timeline' => $timeline, 'compact' => false])
        </div>

        {{-- ── NOTES TAB ── --}}
        <div id="cv-panel-notes" style="display:none;">
            @include('content-management.partials.clinical.case-notes', ['media' => $media])
        </div>

    </div>

    {{-- ── Panel Footer ── --}}
    <div style="padding:12px 20px;border-top:1px solid #f3f4f6;background:white;display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <a href="{{ route('patients.show', $media->patient_id) }}"
           style="flex:1;padding:8px 0;text-align:center;border:1px solid #e5e7eb;border-radius:5px;font-size:12px;font-weight:600;color:#374151;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:5px;"
           onmouseover="this.style.borderColor='#6a0f70';this.style.color='#6a0f70'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            View Full Profile
        </a>
        <button type="button"
                onclick="tagAsMarketing({{ $media->id }}, this)"
                style="flex:1;padding:8px 0;text-align:center;border:1px solid {{ $media->is_marketing ? '#dc2626' : '#e5e7eb' }};border-radius:5px;font-size:12px;font-weight:600;color:{{ $media->is_marketing ? '#dc2626' : '#374151' }};background:{{ $media->is_marketing ? '#fef2f2' : 'white' }};cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            {{ $media->is_marketing ? 'Remove Marketing Tag' : 'Tag as Marketing' }}
        </button>
    </div>

</div>
@endif

<script>
function switchCaseTab(tab) {
    ['overview','timeline','gallery','notes'].forEach(function(t) {
        var panel = document.getElementById('cv-panel-' + t);
        var btn   = document.getElementById('cv-tab-' + t);
        if (panel) panel.style.display = t === tab ? 'block' : 'none';
        if (btn) {
            btn.style.color = t === tab ? 'white' : 'rgba(255,255,255,.6)';
            btn.style.borderBottomColor = t === tab ? 'white' : 'transparent';
        }
    });
}
</script>
