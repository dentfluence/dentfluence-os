{{--
    resources/views/partials/print-letterhead.blade.php

    SELF-CONTAINED print banner — safe to @include at the top of ANY standalone
    print page (it carries its own inline styles, so it does NOT depend on the
    CSS in layouts/print.blade.php).

    Reads Settings → Print:
        plain       → renders NOTHING (pre-printed stationery; page keeps its own header)
        logo        → clinic logo + name/address/contact block
        letterhead  → full-width uploaded letterhead image (replaces the page header)

    The including page should, when $headerType !== 'plain', HIDE its own
    built-in clinic-identity header so this banner replaces it cleanly.

    Variables (auto-resolved if the parent did not set them):
        $print       — AppSetting::group('print')
        $clinic      — AppSetting::group('clinic')
        $headerType  — 'plain' | 'logo' | 'letterhead'
--}}
@php
    // Resolve from settings if the parent view didn't already provide them.
    $print      = $print      ?? \App\Models\AppSetting::group('print');
    $clinic     = $clinic     ?? \App\Models\AppSetting::group('clinic');
    $headerType = $headerType ?? ($print['print_header_type'] ?? 'plain');

    $lhPath   = $print['print_letterhead'] ?? null;
    $logoPath = $clinic['clinic_logo']     ?? null;
@endphp

@if($headerType === 'letterhead' && $lhPath)

    {{-- ── Full-width letterhead image (replaces the page's own header) ── --}}
    <img src="{{ \Illuminate\Support\Facades\Storage::url($lhPath) }}"
         alt="Clinic Letterhead"
         style="width:100%;display:block;margin:0 0 16px;">

@elseif($headerType === 'logo')

    {{-- ── Logo + clinic identity block ── --}}
    <div style="display:flex;align-items:center;gap:16px;
                border-bottom:2px solid var(--accent,#6a0f70);
                padding-bottom:12px;margin-bottom:16px;">
        @if($logoPath)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($logoPath) }}"
                 alt="Clinic Logo"
                 style="width:60px;height:60px;object-fit:contain;flex-shrink:0;">
        @endif
        <div>
            <div style="font-size:18px;font-weight:700;color:var(--accent-dark,#3a0050);line-height:1.2;">
                {{ $clinic['clinic_name'] ?? 'Clinic' }}
            </div>
            @if(!empty($clinic['clinic_tagline']))
                <div style="font-size:11px;color:#666;margin-top:2px;">{{ $clinic['clinic_tagline'] }}</div>
            @endif
            @php
                // Build address + contact lines here to avoid inline @if/@endif adjacency
                $addrLine = $clinic['clinic_address'] ?? '';
                if (!empty($clinic['clinic_city'])) {
                    $addrLine = $addrLine !== '' ? $addrLine . ', ' . $clinic['clinic_city'] : $clinic['clinic_city'];
                }
                $contactLine = $clinic['clinic_phone'] ?? '';
                if (!empty($clinic['clinic_email'])) {
                    $contactLine = $contactLine !== '' ? $contactLine . ' · ' . $clinic['clinic_email'] : $clinic['clinic_email'];
                }
            @endphp
            @if($addrLine !== '')
                <div style="font-size:10px;color:#666;margin-top:3px;">{{ $addrLine }}</div>
            @endif
            @if($contactLine !== '')
                <div style="font-size:10px;color:#666;">{{ $contactLine }}</div>
            @endif
        </div>
    </div>

@endif

{{-- headerType === 'plain' → nothing rendered; page keeps its own built-in header --}}
