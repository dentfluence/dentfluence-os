@extends('layouts.communication')
@push('communication-styles')
    @vite('resources/css/communication/prm.css')
@endpush
@section('title', 'Team Performance — PRM')

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
                Team Performance
            </h1>
            <p style="color:#9a7aaa;font-size:13px;margin:0;">
                Lead load, conversions, revenue and response time per staff member.
            </p>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('prm.reports.channel-roi') }}" class="btn-outline-sm" style="text-decoration:none;">
                <i class="ti ti-coin"></i> Channel ROI
            </a>
            <a href="{{ route('prm.source-analytics') }}" class="btn-outline-sm" style="text-decoration:none;">
                <i class="ti ti-chart-bar"></i> Source Analytics
            </a>
        </div>
    </div>

    @if(empty($rows))
        <div style="padding:40px;text-align:center;color:#9a7aaa;background:#fff;border:1px solid #eee;border-radius:12px;">
            No assigned leads yet. Once leads are auto-assigned to staff, their performance shows here.
        </div>
    @else
    <div style="background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#FAFAFB;text-align:left;color:#5A5A56;">
                    <th style="padding:12px 14px;">Staff</th>
                    <th style="padding:12px 14px;">Assigned</th>
                    <th style="padding:12px 14px;">In Pipeline</th>
                    <th style="padding:12px 14px;">Converted</th>
                    <th style="padding:12px 14px;">Conv %</th>
                    <th style="padding:12px 14px;">Won (₹)</th>
                    <th style="padding:12px 14px;">Replies</th>
                    <th style="padding:12px 14px;">Avg Response</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $r)
                <tr style="border-top:1px solid #f0f0f0;">
                    <td style="padding:12px 14px;">
                        <div style="display:flex;align-items:center;gap:9px;">
                            <span style="width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:600;background:{{ $r['user']->color ?? '#534AB7' }};">
                                {{ strtoupper(substr($r['user']->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $r['user']->name)[1] ?? '', 0, 1)) }}
                            </span>
                            <div>
                                <div style="font-weight:600;color:#1a0320;">{{ $r['user']->name }}</div>
                                <div style="font-size:11px;color:#9a7aaa;">{{ $r['user']->designation ?: ucfirst($r['user']->role ?? 'Staff') }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:12px 14px;">{{ $r['assigned'] }}</td>
                    <td style="padding:12px 14px;">{{ $r['in_pipeline'] }}</td>
                    <td style="padding:12px 14px;font-weight:600;color:#0F6E56;">{{ $r['converted'] }}</td>
                    <td style="padding:12px 14px;">{{ $r['conversion_pct'] }}%</td>
                    <td style="padding:12px 14px;font-weight:600;">₹ {{ number_format($r['won']) }}</td>
                    <td style="padding:12px 14px;">{{ $r['replies'] }}</td>
                    <td style="padding:12px 14px;">
                        @if($r['avg_resp_hrs'] === null)
                            <span style="color:#bbb;">—</span>
                        @elseif($r['avg_resp_hrs'] < 1)
                            {{ round($r['avg_resp_hrs'] * 60) }} min
                        @else
                            {{ $r['avg_resp_hrs'] }} hrs
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p style="font-size:11px;color:#9a7aaa;margin-top:10px;">
        <i class="ti ti-info-circle"></i> "Replies" and "Avg Response" come from the lead activity timeline (AI Draft Reply sends + logged calls/WhatsApp). Response time = lead created → first outbound contact.
    </p>
    @endif

</div>

@endsection
