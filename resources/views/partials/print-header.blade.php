{{--
    resources/views/partials/print-header.blade.php
    Shared print header — included by layouts/print.blade.php.

    Variables expected:
      $print  — array from AppSetting::group('print')
      $clinic — array from AppSetting::group('clinic')

    Header types:
      plain       → nothing rendered (pre-printed stationery)
      logo        → clinic logo + name + address block
      letterhead  → full-width uploaded letterhead image
--}}
@php
    $headerType = $print['print_header_type'] ?? 'plain';
    $logoPath   = $clinic['clinic_logo'] ?? null;
    $lhPath     = $print['print_letterhead'] ?? null;
@endphp

@if($headerType === 'letterhead' && $lhPath)

    {{-- ── Letterhead image (full width) ── --}}
    <img src="{{ Storage::url($lhPath) }}"
         class="print-header-letterhead"
         alt="Clinic Letterhead">

@elseif($headerType === 'logo')

    {{-- ── Logo + clinic info block ── --}}
    <div class="print-header-logo">
        @if($logoPath)
        <img src="{{ Storage::url($logoPath) }}" alt="Clinic Logo">
        @endif
        <div>
            <div class="clinic-name">{{ $clinic['clinic_name'] ?? 'Clinic' }}</div>
            @if(!empty($clinic['clinic_tagline']))
            <div class="clinic-sub">{{ $clinic['clinic_tagline'] }}</div>
            @endif
            <div class="clinic-sub" style="margin-top:4px;">
                @if(!empty($clinic['clinic_address'])){{ $clinic['clinic_address'] }}@endif
                @if(!empty($clinic['clinic_city'])), {{ $clinic['clinic_city'] }}@endif
            </div>
            <div class="clinic-sub">
                @if(!empty($clinic['clinic_phone']))📞 {{ $clinic['clinic_phone'] }}  @endif
                @if(!empty($clinic['clinic_email']))✉ {{ $clinic['clinic_email'] }}@endif
            </div>
        </div>
    </div>

@else

    {{-- ── Plain paper: no header rendered ── --}}

@endif
