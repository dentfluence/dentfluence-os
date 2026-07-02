{{--
|==========================================================================
| Campaign Show — Leads & Appointments Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_leads-tab.blade.php
|
| Two 50/50 panels:
|   LEFT  — Leads (102): Name / Phone / Source / Date / Status
|   RIGHT — Appointments (28): Patient / Date+Time / Treatment / Status
| Mock data only.
|==========================================================================
--}}

@php
$leads = [
    ['name'=>'Ananya Sharma',    'phone'=>'+91 98200 11223', 'source'=>'Instagram', 'date'=>'Jun 13, 2026', 'status'=>'New'],
    ['name'=>'Rohan Mehta',      'phone'=>'+91 97300 44556', 'source'=>'Facebook',  'date'=>'Jun 12, 2026', 'status'=>'Contacted'],
    ['name'=>'Priya Kulkarni',   'phone'=>'+91 96100 77889', 'source'=>'Google',    'date'=>'Jun 11, 2026', 'status'=>'Qualified'],
    ['name'=>'Deepak Nair',      'phone'=>'+91 99200 33445', 'source'=>'WhatsApp',  'date'=>'Jun 10, 2026', 'status'=>'New'],
    ['name'=>'Sunita Rao',       'phone'=>'+91 98100 66778', 'source'=>'Website',   'date'=>'Jun 9, 2026',  'status'=>'Lost'],
];

$appointments = [
    ['patient'=>'Ananya Sharma',  'datetime'=>'Jun 15 · 10:30 AM', 'treatment'=>'Teeth Whitening',   'status'=>'Confirmed'],
    ['patient'=>'Rohan Mehta',    'datetime'=>'Jun 16 · 11:00 AM', 'treatment'=>'Consultation',       'status'=>'Pending'],
    ['patient'=>'Kavya Iyer',     'datetime'=>'Jun 17 · 2:30 PM',  'treatment'=>'Root Canal',         'status'=>'Confirmed'],
    ['patient'=>'Arjun Singh',    'datetime'=>'Jun 18 · 4:00 PM',  'treatment'=>'Scaling & Polishing','status'=>'Confirmed'],
    ['patient'=>'Meera Joshi',    'datetime'=>'Jun 19 · 9:30 AM',  'treatment'=>'Invisalign Consult', 'status'=>'Cancelled'],
];

$leadStatus = [
    'New'       => ['bg'=>'#e3f2fd','text'=>'#0d47a1'],
    'Contacted' => ['bg'=>'#fff3e0','text'=>'#e65100'],
    'Qualified' => ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
    'Lost'      => ['bg'=>'#fce4ec','text'=>'#b71c1c'],
];

$apptStatus = [
    'Confirmed' => ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
    'Pending'   => ['bg'=>'#fff3e0','text'=>'#e65100'],
    'Cancelled' => ['bg'=>'#fce4ec','text'=>'#b71c1c'],
];

$sourceColors = [
    'Instagram' => 'linear-gradient(135deg,#f09433,#bc1888)',
    'Facebook'  => '#1877F2',
    'Google'    => '#4285F4',
    'WhatsApp'  => '#25d366',
    'Website'   => '#34495e',
];
@endphp

{{-- ─── TWO-COLUMN LAYOUT ────────────────────────────────────────────────── --}}
<div style="display:flex; gap:16px; align-items:flex-start;">

    {{-- ══ LEFT: LEADS PANEL ══════════════════════════════════════════════ --}}
    <div style="flex:1; min-width:0;">
        <div style="
            background:#ffffff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:10px;
            overflow:hidden;
        ">
            {{-- Panel Header --}}
            <div style="
                display:flex; align-items:center; justify-content:space-between;
                padding:14px 16px 13px;
                border-bottom:1px solid rgba(185,92,183,0.1);
            ">
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    <span style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">
                        Leads
                    </span>
                    <span style="
                        background:#f0eeff; color:#6a0f70;
                        font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                        padding:2px 8px; border-radius:10px;
                    ">102</span>
                </div>
                <button style="
                    padding:5px 12px; border-radius:6px;
                    background:#f9f3fa; border:1px solid rgba(185,92,183,0.2);
                    font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500;
                    color:#6a0f70; cursor:pointer;
                ">Export CSV</button>
            </div>

            {{-- Table --}}
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#faf5ff;">
                        <th style="padding:9px 16px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; white-space:nowrap;">Name</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; white-space:nowrap;">Phone</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad;">Source</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; white-space:nowrap;">Date</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $i => $lead)
                    @php
                        $ls   = $leadStatus[$lead['status']] ?? ['bg'=>'#eee','text'=>'#555'];
                        $sclr = $sourceColors[$lead['source']] ?? '#888';
                    @endphp
                    <tr style="border-top:1px solid rgba(185,92,183,0.07); {{ $i%2===1 ? 'background:#fdfbff;' : '' }}">
                        <td style="padding:10px 16px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="
                                    width:28px; height:28px; border-radius:50%;
                                    background:linear-gradient(135deg,#6a0f70,#b95cb7);
                                    display:flex; align-items:center; justify-content:center;
                                    font-family:'Inter',sans-serif; font-size:9.5px; font-weight:700; color:#fff;
                                    flex-shrink:0;
                                ">{{ strtoupper(substr($lead['name'],0,1).substr(explode(' ',$lead['name'])[1]??'',0,1)) }}</div>
                                <span style="font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500; color:#1e0a2c; white-space:nowrap;">{{ $lead['name'] }}</span>
                            </div>
                        </td>
                        <td style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; white-space:nowrap;">{{ $lead['phone'] }}</td>
                        <td style="padding:10px 12px;">
                            <span style="
                                display:inline-flex; align-items:center; gap:4px;
                                font-family:'Inter',sans-serif; font-size:12px; color:#1e0a2c;
                            ">
                                <span style="
                                    width:8px; height:8px; border-radius:50%;
                                    background:{{ $sclr }};
                                    display:inline-block; flex-shrink:0;
                                "></span>
                                {{ $lead['source'] }}
                            </span>
                        </td>
                        <td style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#7a6884; white-space:nowrap;">{{ $lead['date'] }}</td>
                        <td style="padding:10px 12px;">
                            <span style="
                                background:{{ $ls['bg'] }}; color:{{ $ls['text'] }};
                                font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600;
                                padding:3px 9px; border-radius:10px;
                            ">{{ $lead['status'] }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- Footer --}}
            <div style="
                padding:10px 16px;
                border-top:1px solid rgba(185,92,183,0.08);
                text-align:center;
            ">
                <button style="
                    background:transparent; border:none;
                    font-family:'Inter',sans-serif; font-size:12px;
                    color:#9b6aad; cursor:pointer;
                ">View all 102 leads →</button>
            </div>
        </div>
    </div>

    {{-- ══ RIGHT: APPOINTMENTS PANEL ══════════════════════════════════════ --}}
    <div style="flex:1; min-width:0;">
        <div style="
            background:#ffffff;
            border:1px solid rgba(185,92,183,0.14);
            border-radius:10px;
            overflow:hidden;
        ">
            {{-- Panel Header --}}
            <div style="
                display:flex; align-items:center; justify-content:space-between;
                padding:14px 16px 13px;
                border-bottom:1px solid rgba(185,92,183,0.1);
            ">
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <span style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">
                        Appointments
                    </span>
                    <span style="
                        background:#e8f5e9; color:#1b5e20;
                        font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                        padding:2px 8px; border-radius:10px;
                    ">28</span>
                </div>
                <button style="
                    padding:5px 12px; border-radius:6px;
                    background:#f9f3fa; border:1px solid rgba(185,92,183,0.2);
                    font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500;
                    color:#6a0f70; cursor:pointer;
                ">View Calendar</button>
            </div>

            {{-- Table --}}
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#faf5ff;">
                        <th style="padding:9px 16px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad;">Patient</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad; white-space:nowrap;">Date & Time</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad;">Treatment</th>
                        <th style="padding:9px 12px; text-align:left; font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#9b6aad;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointments as $i => $appt)
                    @php $as = $apptStatus[$appt['status']] ?? ['bg'=>'#eee','text'=>'#555']; @endphp
                    <tr style="border-top:1px solid rgba(185,92,183,0.07); {{ $i%2===1 ? 'background:#fdfbff;' : '' }}">
                        <td style="padding:10px 16px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="
                                    width:28px; height:28px; border-radius:50%;
                                    background:linear-gradient(135deg,#2980b9,#6dd5fa);
                                    display:flex; align-items:center; justify-content:center;
                                    font-family:'Inter',sans-serif; font-size:9.5px; font-weight:700; color:#fff;
                                    flex-shrink:0;
                                ">{{ strtoupper(substr($appt['patient'],0,1).substr(explode(' ',$appt['patient'])[1]??'',0,1)) }}</div>
                                <span style="font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500; color:#1e0a2c; white-space:nowrap;">{{ $appt['patient'] }}</span>
                            </div>
                        </td>
                        <td style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#5a4868; white-space:nowrap;">{{ $appt['datetime'] }}</td>
                        <td style="padding:10px 12px; font-family:'Inter',sans-serif; font-size:12px; color:#1e0a2c;">{{ $appt['treatment'] }}</td>
                        <td style="padding:10px 12px;">
                            <span style="
                                background:{{ $as['bg'] }}; color:{{ $as['text'] }};
                                font-family:'Inter',sans-serif; font-size:10.5px; font-weight:600;
                                padding:3px 9px; border-radius:10px;
                            ">{{ $appt['status'] }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- Footer --}}
            <div style="
                padding:10px 16px;
                border-top:1px solid rgba(185,92,183,0.08);
                text-align:center;
            ">
                <button style="
                    background:transparent; border:none;
                    font-family:'Inter',sans-serif; font-size:12px;
                    color:#9b6aad; cursor:pointer;
                ">View all 28 appointments →</button>
            </div>
        </div>
    </div>

</div>{{-- /two-panel --}}

{{-- ─── "Powered by" label ──────────────────────────────────────────────── --}}
<div style="
    margin-top:12px;
    display:flex; align-items:center; justify-content:center; gap:6px;
">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#c9b0d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
    </svg>
    <span style="font-family:'Inter',sans-serif; font-size:11px; color:#c9b0d4; letter-spacing:0.02em;">
        Powered by <strong style="color:#b95cb7;">Communication OS</strong>
    </span>
</div>
