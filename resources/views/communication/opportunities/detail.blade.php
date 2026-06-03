@extends('layouts.communication')

@section('title', 'Opportunity Detail')

@section('content')
<div class="opp-page" style="max-width: 900px">
    <div class="opp-topbar">
        <div class="opp-topbar-left">
            <a href="{{ route('communication.opportunities.index') }}" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div>
                <h1 class="opp-page-title">Opportunity Detail</h1>
                <p class="opp-page-sub">View and manage this treatment opportunity</p>
            </div>
        </div>
        <div class="opp-topbar-right">
            <button class="btn-outline-prm">Edit</button>
            <button class="btn-primary-prm" onclick="openConvertModal('Riya Sharma')">Convert to Lead</button>
        </div>
    </div>

    {{-- Detail card placeholder - will be wired in Session 11 --}}
    <div class="opp-detail-card">
        <div class="opp-detail-header">
            <div class="opp-card-avatar" style="width:48px;height:48px;font-size:16px">RS</div>
            <div>
                <h2 style="font-size:18px;font-weight:700;color:#111827;margin:0">Riya Sharma</h2>
                <span style="font-size:13px;color:#6b7280">98765 43210 &middot; Dental Implant</span>
            </div>
            <span class="opp-intent-tag" style="background:#fee2e2;color:#ef4444;margin-left:auto">High Priority</span>
        </div>
        <p style="font-size:13px;color:#6b7280;padding:20px;text-align:center">
            Full opportunity detail view will be wired in Session 11 with real data.
        </p>
    </div>
</div>
@endsection
