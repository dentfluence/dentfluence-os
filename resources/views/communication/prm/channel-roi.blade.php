@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Channel ROI — PRM')

@section('communication-content')

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;">
    <a href="{{ route('prm.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Pipeline
    </a>
</div>

<div style="padding:28px 28px 60px;">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">
                Channel ROI
            </h1>
            <p style="color:#9a7aaa;font-size:13px;margin:0;">
                Revenue won vs ad spend per source — what each channel actually returns.
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('prm.reports.team') }}" class="btn-outline-sm" style="text-decoration:none;">
                <i class="ti ti-users"></i> Team Performance
            </a>
            <a href="{{ route('prm.source-analytics') }}" class="btn-outline-sm" style="text-decoration:none;">
                <i class="ti ti-chart-bar"></i> Source Analytics
            </a>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#FAFAFB;text-align:left;color:#5A5A56;">
                    <th style="padding:12px 14px;">Channel</th>
                    <th style="padding:12px 14px;">Leads</th>
                    <th style="padding:12px 14px;">Converted</th>
                    <th style="padding:12px 14px;">Won (₹)</th>
                    <th style="padding:12px 14px;">Ad Spend (₹)</th>
                    <th style="padding:12px 14px;">Cost / Lead</th>
                    <th style="padding:12px 14px;">Cost / Acq.</th>
                    <th style="padding:12px 14px;">ROI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $r)
                <tr style="border-top:1px solid #f0f0f0;">
                    <td style="padding:12px 14px;font-weight:600;color:#1a0320;">{{ $r['label'] }}</td>
                    <td style="padding:12px 14px;">{{ $r['total'] }}</td>
                    <td style="padding:12px 14px;font-weight:600;color:#0F6E56;">{{ $r['converted'] }}</td>
                    <td style="padding:12px 14px;font-weight:600;">₹ {{ number_format($r['won']) }}</td>
                    <td style="padding:12px 14px;">{{ $r['spend'] > 0 ? '₹ ' . number_format($r['spend']) : '—' }}</td>
                    <td style="padding:12px 14px;">{{ $r['cpl'] !== null ? '₹ ' . number_format($r['cpl']) : '—' }}</td>
                    <td style="padding:12px 14px;">{{ $r['cpa'] !== null ? '₹ ' . number_format($r['cpa']) : '—' }}</td>
                    <td style="padding:12px 14px;">
                        @if($r['roi'] === null)
                            <span style="color:#bbb;">—</span>
                        @else
                            <span style="font-weight:600;color:{{ $r['roi'] >= 0 ? '#0F6E56' : '#E24B4A' }};">
                                {{ $r['roi'] >= 0 ? '+' : '' }}{{ $r['roi'] }}%
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p style="font-size:11px;color:#9a7aaa;margin-top:10px;">
        <i class="ti ti-info-circle"></i> Set monthly ad spend per channel in <code>config/prm.php → ad_spend</code> (then <code>php artisan config:clear</code>). ROI = (Won − Spend) ÷ Spend. Channels with no spend show "—".
    </p>

</div>

@endsection
