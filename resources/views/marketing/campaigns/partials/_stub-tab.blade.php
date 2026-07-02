{{--
| Stub tab — placeholder for tabs not yet built.
| Variables: $label (string), $icon (emoji)
--}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14);
    border-radius:10px;
    padding:64px 32px;
    text-align:center;
">
    <div style="font-size:32px; margin-bottom:12px;">{{ $icon ?? '' }}</div>
    <p style="
        font-family:'Cormorant Garamond',serif; font-size:20px;
        font-weight:600; color:#1e0a2c; margin:0 0 8px;
    ">{{ $label ?? 'Coming Soon' }}</p>
    <p style="
        font-family:'Inter',sans-serif; font-size:13px;
        font-weight:300; color:#7a6884; max-width:320px; margin:0 auto;
    ">This tab is being built. Check back soon.</p>
</div>
