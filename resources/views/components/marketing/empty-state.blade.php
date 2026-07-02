{{--
|==========================================================================
| Component: empty-state
| Usage:
|   <x-marketing.empty-state
|       icon="<svg>...</svg>"
|       heading="No campaigns yet"
|       message="Create your first campaign to get started."
|       cta_text="New Campaign"
|       cta_url="{{ route('marketing.campaigns.create') }}"
|   />
|
| Props:
|   icon      — raw SVG string (48×48 recommended)
|   heading   — bold heading text
|   message   — muted description
|   cta_text  (optional) — button label
|   cta_url   (optional) — button href
|==========================================================================
--}}
@props([
    'icon'     => null,
    'heading'  => 'Nothing here yet',
    'message'  => '',
    'cta_text' => null,
    'cta_url'  => null,
])

<div style="
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 56px 24px;
    font-family: 'Inter', sans-serif;
">

    {{-- ── Icon bubble ── --}}
    @if($icon)
    <div style="
        width: 72px;
        height: 72px;
        border-radius: 18px;
        background: rgba(185,92,183,0.08);
        border: 1px solid rgba(185,92,183,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        color: #b95cb7;
    ">
        {!! $icon !!}
    </div>
    @endif

    {{-- ── Heading ── --}}
    <div style="
        font-size: 16px;
        font-weight: 600;
        color: #1e0a2c;
        margin-bottom: 8px;
        max-width: 320px;
    ">{{ $heading }}</div>

    {{-- ── Message ── --}}
    @if($message)
    <div style="
        font-size: 13.5px;
        font-weight: 400;
        color: #9b6aad;
        line-height: 1.6;
        max-width: 380px;
        margin-bottom: 24px;
    ">{{ $message }}</div>
    @endif

    {{-- ── CTA button ── --}}
    @if($cta_text && $cta_url)
    <a
        href="{{ $cta_url }}"
        style="
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 7px;
            background: linear-gradient(135deg, #6a0f70 0%, #b95cb7 100%);
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(106,15,112,0.25);
            transition: opacity 150ms;
        "
        onmouseover="this.style.opacity='0.88'"
        onmouseout="this.style.opacity='1'"
    >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ $cta_text }}
    </a>
    @endif

</div>
