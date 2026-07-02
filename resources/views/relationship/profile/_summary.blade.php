{{--
|==========================================================================
| Relationship Summary Card — reusable partial
| Used by: relationship/profile/index.blade.php, Today's Actions drawer
|
| Required variables:
|   $relationship    — Relationship model
|   $lead            — Lead|null
|   $patient         — Patient|null
|   $since           — Carbon\Carbon|string  (relationship start date)
|   $relationshipAge — string  e.g. "3 years 2 months"
|   $lifetimeRevenue — float
|   $totalVisits     — int
|   $pendingTreatment — int
|   $opportunities   — Collection  (open TreatmentOpportunity)
|   $recallStatus    — string|null
|   $membershipStatus — string|null
|   $score           — int (0–100)
|   $scoreColor      — string (green|amber|red)
|   $nextAction      — string
|==========================================================================
--}}

<div class="rp-summary-card" style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:4px;overflow:hidden;">

    {{-- ── Top strip: name + status badges ── --}}
    <div style="padding:20px 24px 16px;border-bottom:1px solid rgba(185,92,183,0.08);display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

        {{-- Left: identity --}}
        <div style="display:flex;align-items:center;gap:14px;">
            {{-- Avatar initials --}}
            @php
                $words = explode(' ', $relationship->name ?? 'U');
                $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
            @endphp
            <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:18px;font-weight:600;font-family:'Cormorant Garamond',serif;">{{ $initials }}</span>
            </div>

            <div>
                <h2 style="margin:0 0 3px;font-size:20px;font-weight:600;font-family:'Cormorant Garamond',serif;color:#1a0a24;">
                    {{ $relationship->name }}
                </h2>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    @if($relationship->phone)
                        <span style="font-size:12px;color:#7a6884;">📞 {{ $relationship->phone }}</span>
                    @endif
                    @if($relationship->email)
                        <span style="font-size:12px;color:#7a6884;">✉ {{ $relationship->email }}</span>
                    @endif
                    @if($relationship->source)
                        <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:2px 8px;border-radius:99px;font-weight:500;">
                            {{ ucfirst(str_replace('_',' ', $relationship->source)) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: score badge + status --}}
        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
            {{-- Relationship status --}}
            <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:99px;
                {{ $relationship->status === 'active'  ? 'background:#e8f7ef;color:#1a7a45;' :
                  ($relationship->status === 'dormant' ? 'background:#fff4e0;color:#a05c00;' : 'background:#fdeaea;color:#b52020;') }}">
                {{ ucfirst($relationship->status) }}
            </span>

            {{-- Score badge --}}
            <div style="text-align:center;">
                <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;
                    {{ $scoreColor === 'green' ? 'background:#e8f7ef;color:#1a7a45;border:2px solid #a8e0b8;' :
                      ($scoreColor === 'amber' ? 'background:#fff4e0;color:#a05c00;border:2px solid #fcd47a;' : 'background:#fdeaea;color:#b52020;border:2px solid #f0a0a0;') }}">
                    {{ $score }}
                </div>
                <div style="font-size:10px;color:#9a7aaa;margin-top:2px;font-weight:500;">Score</div>
            </div>
        </div>
    </div>

    {{-- ── Stat grid ── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0;border-bottom:1px solid rgba(185,92,183,0.08);">

        {{-- Relationship Since --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Since</div>
            <div style="font-size:14px;font-weight:600;color:#1a0a24;">{{ \Carbon\Carbon::parse($since)->format('M Y') }}</div>
            <div style="font-size:11px;color:#9a7aaa;">{{ $relationshipAge }}</div>
        </div>

        {{-- Lifetime Revenue --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Revenue</div>
            <div style="font-size:14px;font-weight:600;color:#1a7a45;">₹ {{ number_format($lifetimeRevenue, 0) }}</div>
            <div style="font-size:11px;color:#9a7aaa;">Lifetime paid</div>
        </div>

        {{-- Total Visits --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Visits</div>
            <div style="font-size:14px;font-weight:600;color:#1a0a24;">{{ $totalVisits }}</div>
            <div style="font-size:11px;color:#9a7aaa;">Treatment visits</div>
        </div>

        {{-- Pending Treatment --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Pending Tx</div>
            <div style="font-size:14px;font-weight:600;color:{{ $pendingTreatment > 0 ? '#a05c00' : '#1a0a24' }};">{{ $pendingTreatment }}</div>
            <div style="font-size:11px;color:#9a7aaa;">Treatment items</div>
        </div>

        {{-- Open Opportunities --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Opportunities</div>
            <div style="font-size:14px;font-weight:600;color:{{ $opportunities->count() > 0 ? '#6a0f70' : '#1a0a24' }};">{{ $opportunities->count() }}</div>
            <div style="font-size:11px;color:#9a7aaa;">Open</div>
        </div>

        {{-- Recall Status --}}
        <div style="padding:14px 20px;border-right:1px solid rgba(185,92,183,0.08);">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Recall</div>
            @if($recallStatus)
                <div style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;display:inline-block;
                    {{ $recallStatus === 'overdue' ? 'background:#fdeaea;color:#b52020;' :
                      ($recallStatus === 'active' ? 'background:#e8f7ef;color:#1a7a45;' : 'background:#fff4e0;color:#a05c00;') }}">
                    {{ ucfirst($recallStatus) }}
                </div>
            @else
                <div style="font-size:12px;color:#9a7aaa;">No recall</div>
            @endif
        </div>

        {{-- Membership --}}
        <div style="padding:14px 20px;">
            <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Membership</div>
            @if($membershipStatus && $membershipStatus !== 'not_enrolled')
                <div style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:99px;display:inline-block;
                    {{ $membershipStatus === 'active' ? 'background:#e8f7ef;color:#1a7a45;' : 'background:#f0e6f2;color:#6a0f70;' }}">
                    {{ ucfirst(str_replace('_',' ', $membershipStatus)) }}
                </div>
            @else
                <div style="font-size:12px;color:#9a7aaa;">None</div>
            @endif
        </div>

    </div>

    {{-- ── Next Recommended Action ── --}}
    <div style="padding:12px 24px;background:#faf5fb;display:flex;align-items:center;gap:10px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/>
        </svg>
        <span style="font-size:12px;font-weight:500;color:#6a0f70;">
            <strong>Next action:</strong> {{ $nextAction }}
        </span>
    </div>

</div>
