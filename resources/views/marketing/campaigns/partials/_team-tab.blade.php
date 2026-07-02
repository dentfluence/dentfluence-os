{{--
|==========================================================================
| Campaign Show — Team Tab  (Phase 2.3-C)
| File: resources/views/marketing/campaigns/partials/_team-tab.blade.php
|
| Team member list + "Add Member" row.
| Mock data only — no database calls.
|==========================================================================
--}}

@php
$members = [
    ['name'=>'Dr. Sneha Rao',    'initials'=>'SR', 'role'=>'Campaign Owner',   'days'=>14, 'color'=>'linear-gradient(135deg,#6a0f70,#b95cb7)', 'canRemove'=>false],
    ['name'=>'Priya Mehta',      'initials'=>'PM', 'role'=>'Content Writer',    'days'=>14, 'color'=>'linear-gradient(135deg,#1877F2,#4285F4)', 'canRemove'=>true],
    ['name'=>'Nikhil Kulkarni',  'initials'=>'NK', 'role'=>'Graphic Designer',  'days'=>12, 'color'=>'linear-gradient(135deg,#e67e22,#f1c40f)', 'canRemove'=>true],
    ['name'=>'Aditya Sharma',    'initials'=>'AS', 'role'=>'Social Media Mgr',  'days'=>10, 'color'=>'linear-gradient(135deg,#16a085,#2ecc71)',  'canRemove'=>true],
    ['name'=>'Kavya Joshi',      'initials'=>'KJ', 'role'=>'Photographer',      'days'=>7,  'color'=>'linear-gradient(135deg,#8e44ad,#bb8fce)',  'canRemove'=>true],
];

$roles = [
    'Campaign Owner', 'Content Writer', 'Graphic Designer',
    'Social Media Mgr', 'Photographer', 'Videographer', 'Ads Manager', 'Analyst',
];
@endphp

{{-- ─── PANEL WRAPPER ────────────────────────────────────────────────────── --}}
<div style="
    background:#ffffff;
    border:1px solid rgba(185,92,183,0.14);
    border-radius:10px;
    overflow:hidden;
">

    {{-- Header --}}
    <div style="
        display:flex; align-items:center; justify-content:space-between;
        padding:14px 20px 13px;
        border-bottom:1px solid rgba(185,92,183,0.1);
    ">
        <div style="display:flex; align-items:center; gap:8px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            <span style="font-family:'Inter',sans-serif; font-size:14px; font-weight:600; color:#1e0a2c;">
                Campaign Team
            </span>
            <span style="
                background:#f0eeff; color:#6a0f70;
                font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                padding:2px 8px; border-radius:10px;
            ">{{ count($members) }} members</span>
        </div>
        <p style="
            font-family:'Inter',sans-serif; font-size:12px;
            color:#9b6aad; margin:0;
        ">Manage who can view and edit this campaign</p>
    </div>

    {{-- Member rows --}}
    <div style="padding:8px 0;">
        @foreach($members as $i => $m)
        <div style="
            display:flex; align-items:center; gap:14px;
            padding:13px 20px;
            {{ !$loop->last ? 'border-bottom:1px solid rgba(185,92,183,0.07);' : '' }}
            transition:background 120ms;
        "
        onmouseover="this.style.background='#faf5ff'"
        onmouseout="this.style.background='transparent'"
        >
            {{-- Avatar --}}
            <div style="
                width:40px; height:40px; border-radius:50%;
                background:{{ $m['color'] }};
                display:flex; align-items:center; justify-content:center;
                font-family:'Inter',sans-serif; font-size:13px; font-weight:700; color:#fff;
                flex-shrink:0;
            ">{{ $m['initials'] }}</div>

            {{-- Name + joined --}}
            <div style="flex:1; min-width:0;">
                <p style="
                    font-family:'Inter',sans-serif;
                    font-size:13.5px; font-weight:500; color:#1e0a2c;
                    margin:0 0 2px;
                ">{{ $m['name'] }}</p>
                <p style="
                    font-family:'Inter',sans-serif;
                    font-size:11.5px; font-weight:300; color:#9b6aad;
                    margin:0;
                ">Joined {{ $m['days'] }} day{{ $m['days']!==1 ? 's' : '' }} ago</p>
            </div>

            {{-- Role badge --}}
            @php
                $roleColors = [
                    'Campaign Owner'  => ['bg'=>'#f3e5f5','text'=>'#6a0f70'],
                    'Content Writer'  => ['bg'=>'#e3f2fd','text'=>'#0d47a1'],
                    'Graphic Designer'=> ['bg'=>'#fff3e0','text'=>'#e65100'],
                    'Social Media Mgr'=> ['bg'=>'#e8f5e9','text'=>'#1b5e20'],
                    'Photographer'    => ['bg'=>'#fce4ec','text'=>'#880e4f'],
                ];
                $rc = $roleColors[$m['role']] ?? ['bg'=>'#f2f2f2','text'=>'#555'];
            @endphp
            <span style="
                background:{{ $rc['bg'] }}; color:{{ $rc['text'] }};
                font-family:'Inter',sans-serif; font-size:11.5px; font-weight:600;
                padding:4px 12px; border-radius:20px; white-space:nowrap;
            ">{{ $m['role'] }}</span>

            {{-- Remove button (hidden for owner) --}}
            @if($m['canRemove'])
            <button style="
                display:inline-flex; align-items:center; gap:4px;
                padding:5px 11px; border-radius:6px;
                background:#fdecea; border:1px solid rgba(231,76,60,0.2);
                font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500;
                color:#c0392b; cursor:pointer; white-space:nowrap;
            "
            onmouseover="this.style.background='#fce4e1'"
            onmouseout="this.style.background='#fdecea'"
            >
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Remove
            </button>
            @else
            <span style="
                display:inline-flex; align-items:center; gap:4px;
                padding:5px 11px;
                font-family:'Inter',sans-serif; font-size:11.5px; font-weight:500;
                color:#c9b0d4;
            ">Owner</span>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ── ADD MEMBER ROW ──────────────────────────────────────────────── --}}
    <div style="
        padding:14px 20px;
        border-top:1px solid rgba(185,92,183,0.12);
        background:#faf5ff;
    ">
        <p style="
            font-family:'Inter',sans-serif; font-size:12px; font-weight:600;
            color:#5a4868; margin:0 0 10px;
        ">+ Add Member</p>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">

            {{-- User search dropdown (visual only) --}}
            <div style="position:relative; flex:2; min-width:180px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9b6aad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="position:absolute; left:10px; top:50%; transform:translateY(-50%); pointer-events:none;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    placeholder="Search staff by name or email..."
                    style="
                        width:100%; padding:8px 10px 8px 32px;
                        border:1px solid rgba(185,92,183,0.25); border-radius:7px;
                        background:#fff; font-family:'Inter',sans-serif;
                        font-size:12.5px; color:#1e0a2c;
                        outline:none; box-sizing:border-box;
                    "
                    onfocus="this.style.borderColor='#6a0f70'; this.style.boxShadow='0 0 0 3px rgba(106,15,112,0.08)'"
                    onblur="this.style.borderColor='rgba(185,92,183,0.25)'; this.style.boxShadow='none'"
                >
            </div>

            {{-- Role select --}}
            <select style="
                flex:1; min-width:150px; padding:8px 12px;
                border:1px solid rgba(185,92,183,0.25); border-radius:7px;
                background:#fff; font-family:'Inter',sans-serif;
                font-size:12.5px; color:#5a4868;
                outline:none; cursor:pointer;
            "
            onfocus="this.style.borderColor='#6a0f70'"
            onblur="this.style.borderColor='rgba(185,92,183,0.25)'"
            >
                <option value="" disabled selected>Select role…</option>
                @foreach($roles as $role)
                <option value="{{ Str::slug($role) }}">{{ $role }}</option>
                @endforeach
            </select>

            {{-- Add button --}}
            <button style="
                display:inline-flex; align-items:center; gap:6px;
                padding:8px 18px; border-radius:7px;
                background:linear-gradient(135deg,#6a0f70,#b95cb7); border:none;
                font-family:'Inter',sans-serif; font-size:12.5px; font-weight:500;
                color:#fff; cursor:pointer; white-space:nowrap;
            "
            onmouseover="this.style.opacity='0.88'"
            onmouseout="this.style.opacity='1'"
            >
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add
            </button>
        </div>
    </div>

</div>
