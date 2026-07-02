@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Lead Source Analytics')

@section('communication-content')

@php
/**
 * Source channel SVG icons — keyed by lead_source enum value.
 * Defined here as an array (not a function) so Blade recompile is safe.
 */
$sourceIcons = [
    'google_ads'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4285F4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    'seo'          => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#34A853" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
    'instagram'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#E1306C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.5" fill="#E1306C"/></svg>',
    'facebook'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#1877F2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
    'website_form' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6D28D9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
    'whatsapp'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
    'phone_call'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.44 19.79 19.79 0 0 1 1.61 4.87 2 2 0 0 1 3.58 2.68h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 10a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    'walk_in'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#854F0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5.5 20a7 7 0 0 1 13 0"/></svg>',
    'referral'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#993556" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
    'other'        => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
];
$defaultIcon = $sourceIcons['other'];
@endphp

<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;">
    <a href="{{ route('prm.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Pipeline
    </a>
</div>

<div style="padding:28px 28px 60px;">

    {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;color:#1a0320;margin:0 0 4px;">
                Lead Source Analytics
            </h1>
            <p style="color:#9a7aaa;font-size:13px;margin:0;">
                Conversion rates, pipeline value, and ROI by channel.
            </p>
        </div>
        <a href="{{ route('prm.index') }}" class="btn-outline-sm" style="text-decoration:none;">
            <i class="ti ti-layout-kanban"></i> Pipeline Board
        </a>
    </div>

    {{-- ── KPI CARDS ───────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px;">

        <div class="sa-kpi-card">
            <div class="sa-kpi-icon" style="background:#EDE8F3;color:#5B21B6;">
                <i class="ti ti-users"></i>
            </div>
            <div class="sa-kpi-val">{{ $totals['total'] }}</div>
            <div class="sa-kpi-lbl">Total Leads</div>
        </div>

        <div class="sa-kpi-card">
            <div class="sa-kpi-icon" style="background:#E1F5EE;color:#0F6E56;">
                <i class="ti ti-user-check"></i>
            </div>
            <div class="sa-kpi-val">{{ $totals['converted'] }}</div>
            <div class="sa-kpi-lbl">Converted</div>
        </div>

        <div class="sa-kpi-card">
            <div class="sa-kpi-icon" style="background:#E6F1FB;color:#185FA5;">
                <i class="ti ti-trending-up"></i>
            </div>
            <div class="sa-kpi-val">{{ $totals['conversion_pct'] }}%</div>
            <div class="sa-kpi-lbl">Overall Conversion</div>
        </div>

        <div class="sa-kpi-card">
            <div class="sa-kpi-icon" style="background:#FAEEDA;color:#854F0B;">
                <i class="ti ti-currency-rupee"></i>
            </div>
            <div class="sa-kpi-val">Rs. {{ number_format($totals['won_value'] ?? 0) }}</div>
            <div class="sa-kpi-lbl">Total Won Value</div>
        </div>

    </div>

    {{-- ── SOURCE TABLE ─────────────────────────────────────────────────── --}}
    <div class="sa-table-wrap">
        <table class="sa-table">
            <thead>
                <tr>
                    <th style="width:180px;">Lead Source</th>
                    <th class="sa-num">Total Leads</th>
                    <th class="sa-num">Appt Set</th>
                    <th class="sa-num">Converted</th>
                    <th class="sa-num">Lost</th>
                    <th class="sa-num">Conversion %</th>
                    <th class="sa-num">Pipeline Value</th>
                    <th class="sa-num">Won Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $key => $row)
                    @if($row['total'] > 0)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="sa-src-icon sa-src-{{ $key }}">
                                        {!! $sourceIcons[$key] ?? $defaultIcon !!}
                                    </span>
                                    <span style="font-weight:500;font-size:13px;">{{ $row['label'] }}</span>
                                </div>
                            </td>
                            <td class="sa-num">
                                <span class="sa-badge sa-badge-neutral">{{ $row['total'] }}</span>
                            </td>
                            <td class="sa-num">{{ $row['appt_set'] }}</td>
                            <td class="sa-num">
                                <span class="sa-badge sa-badge-green">{{ $row['converted'] }}</span>
                            </td>
                            <td class="sa-num">
                                @if($row['lost'] > 0)
                                    <span class="sa-badge sa-badge-red">{{ $row['lost'] }}</span>
                                @else
                                    <span style="color:#bbb;">—</span>
                                @endif
                            </td>
                            <td class="sa-num">
                                @php $pct = $row['conversion_pct']; @endphp
                                <div class="sa-conv-wrap">
                                    <div class="sa-conv-bar">
                                        <div class="sa-conv-fill" style="width:{{ min($pct,100) }}%;background:{{ $pct >= 30 ? '#0F6E56' : ($pct >= 15 ? '#854F0B' : '#E24B4A') }};"></div>
                                    </div>
                                    <span class="sa-conv-pct" style="color:{{ $pct >= 30 ? '#0F6E56' : ($pct >= 15 ? '#854F0B' : '#E24B4A') }}">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="sa-num sa-money">
                                @if($row['pipeline_value'] > 0)
                                    Rs. {{ number_format($row['pipeline_value']) }}
                                @else
                                    <span style="color:#bbb;">—</span>
                                @endif
                            </td>
                            <td class="sa-num sa-money sa-won">
                                @if($row['won_value'] > 0)
                                    Rs. {{ number_format($row['won_value']) }}
                                @else
                                    <span style="color:#bbb;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach

                {{-- Sources with zero leads: show grayed out --}}
                @foreach($rows as $key => $row)
                    @if($row['total'] === 0)
                        <tr style="opacity:0.4;">
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="sa-src-icon sa-src-{{ $key }}">
                                        {!! $sourceIcons[$key] ?? $defaultIcon !!}
                                    </span>
                                    <span style="font-size:13px;">{{ $row['label'] }}</span>
                                </div>
                            </td>
                            <td class="sa-num">0</td>
                            <td class="sa-num">—</td>
                            <td class="sa-num">—</td>
                            <td class="sa-num">—</td>
                            <td class="sa-num">—</td>
                            <td class="sa-num">—</td>
                            <td class="sa-num">—</td>
                        </tr>
                    @endif
                @endforeach

            </tbody>
            <tfoot>
                <tr class="sa-totals">
                    <td><strong>TOTAL</strong></td>
                    <td class="sa-num"><strong>{{ $totals['total'] }}</strong></td>
                    <td class="sa-num"><strong>{{ $totals['appt_set'] }}</strong></td>
                    <td class="sa-num"><strong>{{ $totals['converted'] }}</strong></td>
                    <td class="sa-num"><strong>{{ $totals['lost'] }}</strong></td>
                    <td class="sa-num"><strong>{{ $totals['conversion_pct'] }}%</strong></td>
                    <td class="sa-num sa-money"><strong>Rs. {{ number_format($totals['pipeline_value'] ?? 0) }}</strong></td>
                    <td class="sa-num sa-money sa-won"><strong>Rs. {{ number_format($totals['won_value'] ?? 0) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ── INSIGHT CALLOUT ─────────────────────────────────────────────── --}}
    @php
        $best = collect($rows)->where('total', '>', 0)->sortByDesc('conversion_pct')->first();
        $biggest = collect($rows)->where('total', '>', 0)->sortByDesc('total')->first();
    @endphp
    @if($best || $biggest)
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:20px;">
            @if($best)
                <div class="sa-insight">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#0F6E56;">Best Converting Channel</div>
                        <div style="font-size:15px;font-weight:700;color:#1a0320;">{{ $best['label'] }}</div>
                        <div style="font-size:12px;color:#5A5A56;">{{ $best['conversion_pct'] }}% conversion rate · {{ $best['converted'] }} of {{ $best['total'] }} leads won</div>
                    </div>
                </div>
            @endif
            @if($biggest)
                <div class="sa-insight">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#185FA5;">Highest Volume Channel</div>
                        <div style="font-size:15px;font-weight:700;color:#1a0320;">{{ $biggest['label'] }}</div>
                        <div style="font-size:12px;color:#5A5A56;">{{ $biggest['total'] }} leads · Rs. {{ number_format($biggest['pipeline_value'] + $biggest['won_value']) }} total value</div>
                    </div>
                </div>
            @endif
        </div>
    @endif

</div>

@endsection

@push('communication-scripts')
<style>
/* Source Analytics — admin/manager view, can be data-dense */
.sa-table-wrap {
    overflow-x: auto;
    border: 1px solid #EDE8F3;
    border-radius: 10px;
    background: #fff;
}
.sa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 780px;
}
.sa-table thead tr {
    background: #F5F0FA;
    border-bottom: 1px solid #EDE8F3;
}
.sa-table th {
    padding: 11px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #7C5CA8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}
.sa-table td {
    padding: 11px 14px;
    border-bottom: 1px solid #F5F0FA;
    vertical-align: middle;
}
.sa-table tbody tr:last-child td { border-bottom: none; }
.sa-table tbody tr:hover td { background: #FDFAFF; }
.sa-num { text-align: right; }
.sa-money { font-weight: 500; }
.sa-won { color: #0F6E56; }
.sa-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.sa-badge-neutral { background: #EDE8F3; color: #5B21B6; }
.sa-badge-green   { background: #E1F5EE; color: #0F6E56; }
.sa-badge-red     { background: #FEE2E2; color: #B91C1C; }
.sa-src-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #F5F0FA;
    flex-shrink: 0;
}
.sa-conv-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
    justify-content: flex-end;
}
.sa-conv-bar {
    width: 60px;
    height: 5px;
    background: #EDE8F3;
    border-radius: 3px;
    overflow: hidden;
}
.sa-conv-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}
.sa-conv-pct {
    font-size: 12px;
    font-weight: 600;
    min-width: 36px;
    text-align: right;
}
.sa-totals td {
    background: #F5F0FA;
    border-top: 2px solid #DDD6E8;
    font-size: 13px;
    padding: 12px 14px;
}
.sa-kpi-card {
    background: #fff;
    border: 1px solid #EDE8F3;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.sa-kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.sa-kpi-val {
    font-size: 22px;
    font-weight: 700;
    color: #1a0320;
    line-height: 1.1;
}
.sa-kpi-lbl {
    font-size: 11px;
    color: #9a7aaa;
    margin-top: 2px;
}
.sa-insight {
    background: #fff;
    border: 1px solid #EDE8F3;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}
.sa-insight-icon {
    font-size: 24px;
    line-height: 1;
    flex-shrink: 0;
}
@media (max-width: 700px) {
    [style*="grid-template-columns:repeat(4"] { grid-template-columns: repeat(2,1fr) !important; }
    [style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
@endpush
