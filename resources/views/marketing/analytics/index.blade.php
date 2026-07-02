{{--
| Marketing — Analytics & ROI
| File: resources/views/marketing/analytics/index.blade.php
| Phase 6 — Intelligence Layer: real data, ROI engine, insights panel
--}}
@extends('marketing.layouts.app')

@php $marketingPageTitle = 'Analytics'; @endphp

@section('page-title', 'Marketing — Analytics & ROI')

@section('marketing-content')

{{-- ── Page Header ──────────────────────────────────────────────────── --}}
<div class="df-page-header" style="margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="df-page-title" style="display:inline-flex; align-items:center; gap:10px;">
            Analytics &amp; ROI
            <span style="
                display:inline-flex; align-items:center;
                background: linear-gradient(135deg, #7a1fa2, #6a0f70);
                color:#fff; border-radius:20px;
                font-family:'Inter',sans-serif; font-size:11px; font-weight:700;
                letter-spacing:.5px; padding:3px 10px; vertical-align:middle;
            ">LIVE</span>
        </h1>
        <p class="df-page-subtitle">Posts, campaigns, leads, and ROI — all from real data.</p>
    </div>

    {{-- Marketing Score pill --}}
    <div style="
        display:inline-flex; align-items:center; gap:10px;
        background:#fff; border:1px solid #e9d5f5; border-radius:12px; padding:10px 18px;
    ">
        <div style="position:relative; width:36px; height:36px;">
            <svg width="36" height="36" viewBox="0 0 36 36" style="transform:rotate(-90deg);">
                <circle cx="18" cy="18" r="15.9155" fill="transparent" stroke="#f3f4f6" stroke-width="3.5"/>
                <circle cx="18" cy="18" r="15.9155" fill="transparent" stroke="#7a1fa2" stroke-width="3.5"
                    stroke-dasharray="{{ $scoreData['total'] }} {{ 100 - $scoreData['total'] }}"
                    stroke-dashoffset="0" stroke-linecap="round"/>
            </svg>
            <span style="
                position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                font-family:'Inter',sans-serif; font-size:9px; font-weight:700; color:#7a1fa2;
            ">{{ $scoreData['total'] }}</span>
        </div>
        <div>
            <div style="font-family:'Inter',sans-serif; font-size:11px; font-weight:600; color:#1e0a2c;">Marketing Score</div>
            <div style="font-family:'Inter',sans-serif; font-size:10px; color:#9ca3af;">out of 100</div>
        </div>
    </div>
</div>

{{-- ── KPI Cards (6 cards) ──────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">

    {{-- Published This Month --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Published This Month</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#fdf4ff; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7a1fa2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </div>
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:28px; font-weight:700; color:#1e0a2c; margin-bottom:6px;">{{ $kpi['published'] }}</div>
        <div style="display:flex; align-items:center; gap:5px;">
            <span style="
                font-family:'Inter',sans-serif; font-size:11px; font-weight:600;
                color:{{ $kpi['trend_positive'] ? '#16a34a' : '#dc2626' }};
            ">{{ $kpi['published_trend'] }}</span>
            <span style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">vs last month</span>
        </div>
    </div>

    {{-- Scheduled --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Scheduled</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#eff6ff; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:28px; font-weight:700; color:#1e0a2c; margin-bottom:6px;">{{ $kpi['scheduled'] }}</div>
        <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">queued posts</div>
    </div>

    {{-- Active Campaigns --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Active Campaigns</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
            </div>
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:28px; font-weight:700; color:#1e0a2c; margin-bottom:6px;">{{ $kpi['active_campaigns'] }}</div>
        <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">running now</div>
    </div>

    {{-- Total Leads --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Total Leads</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#fff7ed; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
            </div>
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:28px; font-weight:700; color:#1e0a2c; margin-bottom:6px;">{{ $kpi['total_leads'] }}</div>
        <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">from all campaigns</div>
    </div>

    {{-- Budget Spent --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Budget Spent</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#fef2f2; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
                </svg>
            </div>
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:28px; font-weight:700; color:#1e0a2c; margin-bottom:6px;">
            Rs. {{ number_format($kpi['total_budget_spent'], 0) }}
        </div>
        <div style="font-family:'Inter',sans-serif; font-size:11px; color:#9ca3af;">total utilised</div>
    </div>

    {{-- Completion Rate --}}
    <div style="background:#fff; border:1px solid #f0eaf5; border-radius:12px; padding:22px 20px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
            <span style="font-family:'Inter',sans-serif; font-size:12px; font-weight:500; color:#9ca3af;">Completion Rate</span>
            <div style="width:32px; height:32px; border-radius:8px; background:#f0fdf4; display:flex; align-items:center; justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" 