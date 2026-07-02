{{--
| _tab-placeholder.blade.php
| Generic "coming soon" panel used by Quick Idea, Idea Bank, Festival Planner tabs.
| Variables: $icon (SVG path string), $heading, $body
--}}
<div style="
    background: #ffffff;
    border: 1px solid rgba(185,92,183,0.13);
    border-radius: 10px;
    padding: 64px 32px;
    text-align: center;
">
    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="rgba(185,92,183,0.35)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 14px;">
        {!! $icon !!}
    </svg>
    <p style="font-family:'Cormorant Garamond',serif; font-size:19px; font-weight:600; color:#1e0a2c; margin:0 0 7px;">
        {{ $heading }}
    </p>
    <p style="font-family:'Inter',sans-serif; font-size:13px; font-weight:300; color:#7a6884; max-width:320px; margin:0 auto;">
        {{ $body }}
    </p>
</div>
