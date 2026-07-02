{{--
    ══════════════════════════════════════════════════════
    CONTENT MANAGER — CASE LIBRARY TAB
    Anonymised patient IDs. Before/After pairs. Grouped by procedure.
    Slide-in case detail panel with timeline.
    RULE: The word "Patient" NEVER appears here — only "Case #XXXX"
    ══════════════════════════════════════════════════════
--}}

@php
/**
 * Phase 9: $casesByProcedure is a collection of anonymised case arrays,
 * grouped by procedure, from ClinicalLibraryController::index().
 *
 * Each case array has:
 *   id, anon_id, procedure, tooth, doctor, duration,
 *   before_url, after_url, stage_counts, tags, rating, file_count
 *
 * RULE: patient_id and patient name are NEVER present — only anon_id.
 */
$stageColors = [
    'before'   => '#2563eb',
    'during'   => '#d97706',
    'after'    => '#16a34a',
    'followup' => '#7c3aed',
];
@endphp

{{-- ─────────────────────────────────────────────────── --}}
{{-- CASE GRID (grouped by procedure)                    --}}
{{-- Source: $casesByProcedure from controller            --}}
{{-- Patient identity is NEVER shown — only anon_id       --}}
{{-- ─────────────────────────────────────────────────── --}}

@if($casesByProcedure->isEmpty())
<div class="cm-empty">
    <div class="cm-empty-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </div>
    <div class="cm-empty-title">No cases yet</div>
    <div class="cm-empty-sub">Tag clinical files as <strong>Case Library Eligible</strong> to populate this view.</div>
</div>
@else

@foreach($casesByProcedure as $procedure => $procedureCases)

<div class="cm-group-header">
    <span class="cm-group-label">{{ $procedure }}</span>
    <span class="cm-group-count">{{ $procedureCases->count() }} cases</span>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:28px;">
    @foreach($procedureCases as $case)

    {{-- ── CASE CARD ── --}}
    {{-- Note: $case['id'] is 'cl_{file_id}', NOT a patient_id --}}
    <div style="background:white;border-radius:10px;border:1px solid #e5e7eb;overflow:hidden;cursor:pointer;transition:all .2s;box-shadow:0 1px 3px rgba(0,0,0,.06);"
         onmouseover="this.style.boxShadow='0 4px 16px rgba(106,15,112,.12)';this.style.borderColor='#d8b4fe'"
         onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,.06)';this.style.borderColor='#e5e7eb'"
         @click="openCaseViewer('{{ $case['id'] }}')">

        {{-- BEFORE / AFTER thumbnail pair --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;height:120px;">

            {{-- Before --}}
            <div style="position:relative;background:#0f2027;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:3px;overflow:hidden;">
                @if(!empty($case['before_url']))
                    <img src="{{ $case['before_url'] }}" alt="Before" loading="lazy"
                         style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                @else
                @endif
                <div style="position:absolute;bottom:4px;left:4px;padding:2px 6px;background:rgba(37,99,235,.85);border-radius:99px;font-size:8px;font-weight:700;color:white;z-index:1;">
                    Before
                </div>
            </div>

            {{-- After --}}
            <div style="position:relative;background:#0d3b26;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:3px;border-left:1px solid rgba(255,255,255,.05);overflow:hidden;">
                @if(!empty($case['after_url']))
                    <img src="{{ $case['after_url'] }}" alt="After" loading="lazy"
                         style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;">
                @else
                @endif
                <div style="position:absolute;bottom:4px;right:4px;padding:2px 6px;background:rgba(22,163,74,.85);border-radius:99px;font-size:8px;font-weight:700;color:white;z-index:1;">
                    After
                </div>
            </div>

        </div>

        {{-- CARD BODY --}}
        <div style="padding:10px 12px;">

            {{-- Anon ID + rating --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:12px;font-weight:800;color:#111827;">{{ $case['anon_id'] }}</span>
                <span style="font-size:11px;color:#f59e0b;letter-spacing:.02em;">
                    @for($s=1;$s<=5;$s++)
                        {{ $s <= $case['rating'] ? '★' : '☆' }}
                    @endfor
                </span>
            </div>

            {{-- Procedure + tooth --}}
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                <span style="font-size:12px;font-weight:600;color:#374151;">{{ $case['procedure'] }}</span>
                <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;"></span>
                <span style="font-size:11px;color:#9ca3af;">Tooth {{ $case['tooth'] }}</span>
            </div>

            {{-- Doctor + duration --}}
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span style="font-size:11px;color:#6b7280;">{{ $case['doctor'] }}</span>
                <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;display:inline-block;"></span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span style="font-size:11px;color:#6b7280;">{{ $case['duration'] }}</span>
            </div>

            {{-- Stage count pills --}}
            <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;">
                @foreach($case['stage_counts'] as $stage => $count)
                <span style="padding:2px 7px;border-radius:99px;font-size:9px;font-weight:700;background:{{ ($stageColors[$stage] ?? '#9ca3af') }}18;color:{{ $stageColors[$stage] ?? '#9ca3af' }};">
                    {{ $count }} {{ $stage }}
                </span>
                @endforeach
            </div>

            {{-- Tags --}}
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                @foreach($case['tags'] as $tag)
                <span style="padding:2px 7px;background:#f5f3ff;border:1px solid #e9d5ff;border-radius:99px;font-size:9px;font-weight:600;color:#6a0f70;">
                    {{ $tag }}
                </span>
                @endforeach
            </div>

        </div>

        {{-- VIEW CASE FOOTER --}}
        <div style="padding:8px 12px;background:#faf5fb;border-top:1px solid #f3e8ff;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;color:#6a0f70;">View Full Case</span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </div>

    </div>
    @endforeach
</div>

@endforeach

@endif {{-- /empty state --}}

{{-- ─────────────────────────────────────────────────── --}}
{{-- CASE DETAIL SLIDE-IN PANEL                          --}}
{{-- Opens when a case card is clicked                   --}}
{{-- ─────────────────────────────────────────────────── --}}
<div id="cv-overlay" :class="caseViewerOpen ? 'open' : ''" @click.self="closeCaseViewer()"></div>

<div id="cv-panel" :class="caseViewerOpen ? 'open' : ''" style="position:fixed;top:0;right:0;width:560px;max-width:96vw;height:100vh;background:white;box-shadow:-8px 0 40px rgba(0,0,0,.18);z-index:101;display:flex;flex-direction:column;overflow:hidden;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);"
     :style="caseViewerOpen ? 'transform:translateX(0)' : 'transform:translateX(100%)'">

    {{-- ── PANEL HEADER ── --}}
    <div style="padding:16px 20px 0;border-bottom:1px solid #e5e7eb;flex-shrink:0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;">
            <div>
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;">Case Library</div>
                <div style="font-size:18px;font-weight:800;color:#111827;" x-text="activeCaseId ? 'Case #' + activeCaseId.replace('cl','') + '— Placeholder' : 'Case Detail'"></div>
                <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                    <span style="font-size:11px;color:#6b7280;">Root Canal · Tooth 26</span>
                    <span style="width:3px;height:3px;background:#d1d5db;border-radius:50%;"></span>
                    <span style="font-size:11px;color:#6b7280;">Dr. Mehta</span>
                    <span style="padding:2px 7px;background:#dcfce7;border-radius:99px;font-size:9px;font-weight:700;color:#16a34a;">Completed</span>
                </div>
            </div>
            <button @click="closeCaseViewer()"
                    style="width:30px;height:30px;border-radius:50%;background:#f3f4f6;border:none;cursor:pointer;color:#9ca3af;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .12s;"
                    onmouseover="this.style.background='#fee2e2';this.style.color='#dc2626'"
                    onmouseout="this.style.background='#f3f4f6';this.style.color='#9ca3af'">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        {{-- Panel tabs --}}
        <div style="display:flex;gap:0;" id="cv-tab-bar">
            @foreach(['timeline'=>'Timeline','files'=>'All Files','notes'=>'Notes'] as $key=>$label)
            <div style="padding:8px 16px;font-size:12px;font-weight:600;color:#9ca3af;border-bottom:2px solid transparent;cursor:pointer;transition:all .15s;"
                 onclick="cvSetTab(this,'{{ $key }}')"
                 id="cv-tab-{{ $key }}"
                 onmouseover="if(!this.classList.contains('cv-active'))this.style.color='#6a0f70'"
                 onmouseout="if(!this.classList.contains('cv-active'))this.style.color='#9ca3af'">
                {{ $label }}
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── PANEL BODY ── --}}
    <div style="flex:1;overflow-y:auto;padding:16px 20px;">

        {{-- TIMELINE TAB --}}
        <div id="cv-body-timeline">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:14px;text-transform:uppercase;letter-spacing:.05em;">Treatment Timeline</div>

            @php
            $timelineItems = [
                ['stage'=>'Before',    'color'=>'#2563eb', 'date'=>'12 Jan 2024', 'label'=>'Pre-treatment photos',   'files'=>2, 'note'=>'Initial assessment, clear pathology on OPG'],
                ['stage'=>'Before',    'color'=>'#2563eb', 'date'=>'12 Jan 2024', 'label'=>'Diagnostic X-Ray',       'files'=>1, 'note'=>'Periapical X-Ray taken'],
                ['stage'=>'During',    'color'=>'#d97706', 'date'=>'18 Jan 2024', 'label'=>'Access preparation',     'files'=>2, 'note'=>'Working length confirmed'],
                ['stage'=>'During',    'color'=>'#d97706', 'date'=>'25 Jan 2024', 'label'=>'Canal shaping',          'files'=>1, 'note'=>'Master cone fit X-Ray'],
                ['stage'=>'During',    'color'=>'#d97706', 'date'=>'01 Feb 2024', 'label'=>'Obturation',             'files'=>1, 'note'=>'Post-obturation X-Ray'],
                ['stage'=>'After',     'color'=>'#16a34a', 'date'=>'08 Feb 2024', 'label'=>'Post-treatment review',  'files'=>2, 'note'=>'Healing satisfactory'],
                ['stage'=>'Follow-up', 'color'=>'#7c3aed', 'date'=>'08 Mar 2024', 'label'=>'3-month follow-up',     'files'=>1, 'note'=>'Asymptomatic, crown placed'],
            ];
            @endphp

            @foreach($timelineItems as $i => $entry)
            <div style="display:flex;gap:12px;margin-bottom:14px;">
                {{-- Dot + line --}}
                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                    <div style="width:10px;height:10px;border-radius:50%;background:{{ $entry['color'] }};margin-top:3px;flex-shrink:0;"></div>
                    @if($i < count($timelineItems)-1)
                    <div style="flex:1;width:1px;background:#f3f4f6;margin:3px 0;min-height:20px;"></div>
                    @endif
                </div>
                {{-- Content --}}
                <div style="flex:1;padding-bottom:4px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                        <span style="font-size:12px;font-weight:700;color:#374151;">{{ $entry['label'] }}</span>
                        <span style="padding:1px 7px;border-radius:99px;font-size:9px;font-weight:700;background:{{ $entry['color'] }}18;color:{{ $entry['color'] }};">{{ $entry['stage'] }}</span>
                    </div>
                    <div style="font-size:10px;color:#9ca3af;margin-bottom:3px;">{{ $entry['date'] }} · {{ $entry['files'] }} file{{ $entry['files']>1?'s':'' }}</div>
                    <div style="font-size:11px;color:#6b7280;font-style:italic;">{{ $entry['note'] }}</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- FILES TAB (hidden by default) --}}
        <div id="cv-body-files" style="display:none;">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:14px;text-transform:uppercase;letter-spacing:.05em;">All Files (10)</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                @foreach(['#1c1c2e','#0f2027','#1a1a2e','#0d1b2a','#16213e','#0a0a1a','#14213d','#1b1b2e','#0a2030','#1c2b3a'] as $bg)
                <div style="aspect-ratio:1;border-radius:6px;background:{{ $bg }};display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform .15s;"
                     onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
                @endforeach
            </div>
        </div>

        {{-- NOTES TAB (hidden by default) --}}
        <div id="cv-body-notes" style="display:none;">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:14px;text-transform:uppercase;letter-spacing:.05em;">Case Notes</div>
            <div style="background:#f9fafb;border-radius:8px;padding:14px;font-size:13px;color:#374151;line-height:1.6;">
                Complex multi-rooted case. MB2 located and negotiated. All 4 canals shaped and obturated. Adequate working length confirmed with apex locator. Patient asymptomatic post-op. Crown recommended within 3 months.
            </div>
            {{-- Education flag --}}
            <div style="margin-top:16px;padding:12px;border:1px solid #e9d5ff;border-radius:8px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:2px;">Use for Education</div>
                    <div style="font-size:11px;color:#9ca3af;">Flag this case for the Education Library</div>
                </div>
                {{-- Toggle switch (visual only) --}}
                <div style="width:40px;height:22px;background:#6a0f70;border-radius:99px;position:relative;cursor:pointer;transition:background .2s;">
                    <div style="width:18px;height:18px;background:white;border-radius:50%;position:absolute;top:2px;right:2px;transition:right .2s;"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── PANEL FOOTER ── --}}
    <div style="border-top:1px solid #f3f4f6;padding:10px 20px;background:white;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:10px;color:#9ca3af;">
            Identity anonymised · Original records in Clinical Files
        </div>
        <div style="display:flex;gap:6px;">
            <button style="padding:7px 14px;border-radius:6px;border:1px solid #e5e7eb;background:white;font-size:11px;font-weight:600;color:#374151;cursor:pointer;">
                Download Case
            </button>
            <button style="padding:7px 14px;border-radius:6px;border:none;background:#6a0f70;font-size:11px;font-weight:700;color:white;cursor:pointer;">
                Share Case
            </button>
        </div>
    </div>

</div>{{-- /cv-panel --}}

<script>
// Simple tab switcher for Case Viewer panel (vanilla JS, no Alpine conflict)
function cvSetTab(el, key) {
    // Reset all tabs
    document.querySelectorAll('#cv-tab-bar > div').forEach(t => {
        t.style.color = '#9ca3af';
        t.style.borderBottomColor = 'transparent';
        t.classList.remove('cv-active');
    });
    // Activate clicked
    el.style.color = '#6a0f70';
    el.style.borderBottomColor = '#6a0f70';
    el.classList.add('cv-active');
    // Show/hide bodies
    ['timeline','files','notes'].forEach(k => {
        const body = document.getElementById('cv-body-' + k);
        if (body) body.style.display = k === key ? 'block' : 'none';
    });
}
// Set default active tab styling on load
document.addEventListener('DOMContentLoaded', () => {
    const defaultTab = document.getElementById('cv-tab-timeline');
    if (defaultTab) {
        defaultTab.style.color = '#6a0f70';
        defaultTab.style.borderBottomColor = '#6a0f70';
        defaultTab.classList.add('cv-active');
    }
});
</script>
