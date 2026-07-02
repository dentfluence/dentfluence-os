{{--
|==========================================================================
| Partial: _running-campaigns
| File: resources/views/marketing/overview/partials/_running-campaigns.blade.php
|
| Variables expected:
|   $runningCampaigns — array of campaigns, each with:
|       name, description, status, leads, appointments, revenue
|==========================================================================
--}}

<x-marketing.marketing-card title="Running Campaigns">

    {{-- ── Header action: "View All →" ── --}}
    <x-slot:actions>
        <a href="{{ route('marketing.campaigns.index') }}" style="
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 500;
            color: #6a0f70;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid rgba(106,15,112,0.18);
            background: rgba(106,15,112,0.04);
            transition: background 150ms;
        "
        onmouseover="this.style.background='rgba(106,15,112,0.09)'"
        onmouseout="this.style.background='rgba(106,15,112,0.04)'"
        >
            View All
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
    </x-slot:actions>

    {{-- ── Column headers ── --}}
    <div style="
        display: grid;
        grid-template-columns: 1fr 120px 80px 100px 100px;
        gap: 12px;
        padding: 0 4px 10px;
        border-bottom: 1px solid rgba(185,92,183,0.08);
        font-family: 'Inter', sans-serif;
        font-size: 11px;
        font-weight: 600;
        color: #9b6aad;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    ">
        <span>Campaign</span>
        <span style="text-align: center;">Status</span>
        <span style="text-align: center;">Leads</span>
        <span style="text-align: center;">Appointments</span>
        <span style="text-align: right;">Revenue (Rs. )</span>
    </div>

    {{-- ── Campaign rows ── --}}
    @foreach($runningCampaigns as $index => $campaign)
    <div style="
        display: grid;
        grid-template-columns: 1fr 120px 80px 100px 100px;
        gap: 12px;
        align-items: center;
        padding: 14px 4px;
        {{ !$loop->last ? 'border-bottom: 1px solid rgba(185,92,183,0.07);' : '' }}
        font-family: 'Inter', sans-serif;
    ">

        {{-- Campaign name + description --}}
        <div style="min-width: 0;">
            <div style="
                font-size: 13.5px;
                font-weight: 600;
                color: #1e0a2c;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            ">{{ $campaign['name'] }}</div>
            <div style="
                font-size: 12px;
                font-weight: 400;
                color: #9b6aad;
                margin-top: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            ">{{ $campaign['description'] }}</div>
        </div>

        {{-- Status badge --}}
        <div style="text-align: center;">
            <x-marketing.status-badge :status="$campaign['status']" />
        </div>

        {{-- Leads count --}}
        <div style="
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: #1e0a2c;
        ">
            {{ $campaign['leads'] }}
            <span style="font-size: 11px; font-weight: 400; color: #9b6aad; display: block; margin-top: 1px;">leads</span>
        </div>

        {{-- Appointments count --}}
        <div style="
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: #1e0a2c;
        ">
            {{ $campaign['appointments'] }}
            <span style="font-size: 11px; font-weight: 400; color: #9b6aad; display: block; margin-top: 1px;">booked</span>
        </div>

        {{-- Revenue --}}
        <div style="
            text-align: right;
            font-size: 14px;
            font-weight: 700;
            color: #16a34a;
        ">
            Rs. {{ $campaign['revenue'] }}
        </div>

    </div>
    @endforeach

</x-marketing.marketing-card>
