{{--
|==========================================================================
| Dentfluence OS — Screen Guide (hint strip + detail panel)
| File: resources/views/components/help-guide.blade.php
|
| Included once in layouts/app.blade.php, just before @yield('content').
| Content comes from resources/help/content.php via App\Support\HelpContent
| — never hardcode guide text in views.
|
| Behaviour:
|   - Thin one-line hint strip above page content, only when the Guide
|     toggle (topbar) is ON and the page has an entry. Deliberately subtle:
|     no animation, no popups.
|   - "More" opens a slide-over panel (z-index 210, clears topbar at 120).
|   - × hides the hint for THIS page only; turning the guide off→on
|     resets all hidden pages.
|   - State lives in localStorage df_prefs: { guide:'on'|'off',
|     guide_hidden:{key:1} }. Guide defaults ON for new users.
|==========================================================================
--}}

@php
    $__gUser    = auth()->user();
    $__gIsAdmin = $__gUser ? ($__gUser->isAdminRole() || $__gUser->isAdmin()) : false;
    $__guide    = \App\Support\HelpContent::forRoute(
        \Illuminate\Support\Facades\Route::currentRouteName(),
        $__gIsAdmin
    );
@endphp

@if($__guide && $__guide['hint'])
{{-- ── Hint strip (subtle, one line) ── --}}
<div id="df-guide-hint"
     role="note"
     style="display:none;align-items:flex-start;gap:10px;padding:8px 12px;margin-bottom:14px;background:#faf6fc;border:1px solid rgba(185,92,183,0.16);border-left:3px solid #8e24aa;border-radius:3px;font-size:12.5px;color:#4a3a56;line-height:1.55;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:2px;">
        <path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.4 1 2.3h6c0-.9.4-1.8 1-2.3A7 7 0 0 0 12 2z"/>
        <path d="M9 20h6"/>
    </svg>
    <span style="flex:1;min-width:0;">
        <span style="font-weight:600;color:#5a006e;">{{ $__guide['title'] }}:</span>
        {{ $__guide['hint'] }}
    </span>
    <button type="button" onclick="DFGuide.openPanel()"
            style="flex-shrink:0;background:none;border:none;cursor:pointer;font-size:12px;font-weight:500;color:#6a0f70;padding:0 2px;white-space:nowrap;"
            onmouseover="this.style.textDecoration='underline';"
            onmouseout="this.style.textDecoration='none';">More &rarr;</button>
    <button type="button" onclick="DFGuide.hideForPage()" aria-label="Hide this hint on this page"
            title="Hide this hint on this page"
            style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#b0a4bc;padding:0 2px;line-height:1;"
            onmouseover="this.style.color='#6a0f70';"
            onmouseout="this.style.color='#b0a4bc';">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
</div>

{{-- ── Detail panel (slide-over, no animation — subtle by design) ── --}}
<div id="df-guide-backdrop"
     style="display:none;position:fixed;inset:0;z-index:205;background:rgba(20,4,30,0.32);"
     onclick="DFGuide.closePanel()" aria-hidden="true"></div>

<aside id="df-guide-panel"
       role="dialog" aria-modal="true" aria-label="Screen guide"
       style="display:none;position:fixed;top:0;right:0;bottom:0;z-index:210;width:min(400px,94vw);background:#ffffff;border-left:1px solid rgba(185,92,183,0.18);box-shadow:-8px 0 28px rgba(14,1,24,0.16);overflow-y:auto;">

    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f0e8f8;background:#faf4fb;position:sticky;top:0;">
        <span style="font-size:13.5px;font-weight:600;color:#1a0a24;">Guide — {{ $__guide['title'] }}</span>
        <button type="button" onclick="DFGuide.closePanel()" aria-label="Close guide"
                style="width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:#9e8fa0;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    <div style="padding:16px 18px 24px;">

        @if($__guide['what'])
        <p style="font-size:13px;color:#4a3a56;line-height:1.6;margin:0 0 18px;">{{ $__guide['what'] }}</p>
        @endif

        @if(count($__guide['tasks']))
        <p style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#9e8fa0;margin:0 0 8px;">Common tasks</p>
        <div style="margin-bottom:18px;">
            @foreach($__guide['tasks'] as $__i => $__task)
            <div style="display:flex;gap:10px;padding:8px 0;{{ $loop->last ? '' : 'border-bottom:1px solid #f8f2fb;' }}">
                <span style="flex-shrink:0;width:20px;height:20px;display:flex;align-items:center;justify-content:center;background:#f5eef9;border-radius:3px;font-size:11px;font-weight:600;color:#6a0f70;">{{ $__i + 1 }}</span>
                <span style="flex:1;min-width:0;">
                    <span style="display:block;font-size:12.5px;font-weight:600;color:#2a1440;line-height:1.4;">{{ $__task[0] }}</span>
                    <span style="display:block;font-size:12px;color:#7a6884;line-height:1.55;margin-top:2px;">{{ $__task[1] ?? '' }}</span>
                </span>
            </div>
            @endforeach
        </div>
        @endif

        @if(count($__guide['flows']))
        <p style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#9e8fa0;margin:0 0 8px;">Where this goes</p>
        <div style="margin-bottom:18px;">
            @foreach($__guide['flows'] as $__flow)
            <div style="display:flex;gap:8px;padding:5px 0;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:3px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                <span style="font-size:12px;color:#4a3a56;line-height:1.55;">{{ $__flow }}</span>
            </div>
            @endforeach
        </div>
        @endif

        @if($__guide['roi'])
        <div style="padding:10px 12px;background:#fdf8ff;border:1px solid rgba(185,92,183,0.16);border-radius:3px;margin-bottom:18px;">
            <p style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#8e24aa;margin:0 0 4px;">Why it matters</p>
            <p style="font-size:12px;color:#4a3a56;line-height:1.55;margin:0;">{{ $__guide['roi'] }}</p>
        </div>
        @endif

        <a href="{{ route('help.index') }}#guide-{{ $__guide['key'] }}"
           style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:#6a0f70;text-decoration:none;"
           onmouseover="this.style.textDecoration='underline';"
           onmouseout="this.style.textDecoration='none';">
            Open full Help Centre &rarr;
        </a>

    </div>
</aside>
@endif

<script>
window.DFGuide = (function () {
    var KEY = @js($__guide['key'] ?? null);

    function prefs()  { try { return JSON.parse(localStorage.getItem('df_prefs') || '{}'); } catch (e) { return {}; } }
    function save(p)  { try { localStorage.setItem('df_prefs', JSON.stringify(p)); } catch (e) {} }
    function enabled()    { return prefs().guide !== 'off'; } /* default ON */
    function hiddenHere() { var p = prefs(); return !!(KEY && p.guide_hidden && p.guide_hidden[KEY]); }

    function apply() {
        var strip = document.getElementById('df-guide-hint');
        if (strip) strip.style.display = (enabled() && !hiddenHere()) ? 'flex' : 'none';

        var btn = document.getElementById('df-guide-toggle');
        if (btn) {
            var on = enabled();
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.title = on ? 'Screen guide is ON — click to turn off' : 'Screen guide is OFF — click to turn on';
            btn.style.color       = on ? '#6a0f70' : '#b0a4bc';
            btn.style.background  = on ? '#f5eef9' : 'none';
            btn.style.borderColor = on ? 'rgba(185,92,183,0.35)' : 'rgba(185,92,183,0.18)';
        }
    }

    return {
        toggle: function () {
            var p = prefs();
            if (p.guide === 'off') {
                p.guide = 'on';
                p.guide_hidden = {}; /* turning back on = show all hints again */
            } else {
                p.guide = 'off';
            }
            save(p);
            apply();
        },
        hideForPage: function () {
            if (!KEY) return;
            var p = prefs();
            p.guide_hidden = p.guide_hidden || {};
            p.guide_hidden[KEY] = 1;
            save(p);
            apply();
        },
        openPanel: function () {
            var b = document.getElementById('df-guide-backdrop');
            var pn = document.getElementById('df-guide-panel');
            if (b)  b.style.display = 'block';
            if (pn) pn.style.display = 'block';
        },
        closePanel: function () {
            var b = document.getElementById('df-guide-backdrop');
            var pn = document.getElementById('df-guide-panel');
            if (b)  b.style.display = 'none';
            if (pn) pn.style.display = 'none';
        },
        apply: apply
    };
})();

DFGuide.apply();

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') DFGuide.closePanel();
});
</script>
