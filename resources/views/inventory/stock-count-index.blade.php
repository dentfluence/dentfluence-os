{{--
|===========================================================================
| Stock Count Index
| Lists past stock count sessions. Staff starts a new 15-day cycle here.
|===========================================================================
--}}
@extends('layouts.app')
@section('title', 'Stock Count')

@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Stock Count · 15-day physical count cycle</div>
    </div>
    <div class="df-page-actions">

    @if($activeSession)
        {{-- Resume open session --}}
        <a href="{{ route('inventory.stock-count.sheet', $activeSession) }}"
           style="display:inline-flex;align-items:center;gap:8px;background:#e8a000;
                  color:#fff;border:none;padding:10px 20px;border-radius:7px;
                  font-size:13px;font-family:'Inter',sans-serif;font-weight:600;
                  text-decoration:none;cursor:pointer;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Resume {{ $activeSession->session_no }}
        </a>
    @else
        {{-- Start new session --}}
        <form method="POST" action="{{ route('inventory.stock-count.start') }}">
            @csrf
            <button type="submit"
                    style="display:inline-flex;align-items:center;gap:8px;background:#6a0f70;
                           color:#fff;border:none;padding:10px 20px;border-radius:7px;
                           font-size:13px;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                Start New Count
            </button>
        </form>
    @endif
    </div>
</div>

@include('inventory.partials.subnav')

{{-- Flash messages --}}
@if(session('success'))
<div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:12px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    ✓ {{ session('success') }}
</div>
@endif
@if(session('info'))
<div style="background:#e8f0fb;border:1px solid #a3c0e8;color:#1a5ea8;padding:12px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    ℹ {{ session('info') }}
</div>
@endif

{{-- ── Info strip ── --}}
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
    <div style="flex:1;min-width:160px;background:#faf5fb;border:1px solid #e8d8f0;
                border-radius:8px;padding:14px 18px;">
        <div style="font-size:11px;font-family:'Inter',sans-serif;color:#9070a0;
                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Total Items</div>
        <div style="font-size:24px;font-family:'Cormorant Garamond',serif;
                    font-weight:600;color:#1e0a2c;">{{ $totalItems }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#fff8e8;border:1px solid #f5dea3;
                border-radius:8px;padding:14px 18px;">
        <div style="font-size:11px;font-family:'Inter',sans-serif;color:#a07020;
                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Low Stock</div>
        <div style="font-size:24px;font-family:'Cormorant Garamond',serif;
                    font-weight:600;color:#a07020;">{{ $lowCount }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#fdeaea;border:1px solid #f5c6c6;
                border-radius:8px;padding:14px 18px;">
        <div style="font-size:11px;font-family:'Inter',sans-serif;color:#b52020;
                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Critical / Out</div>
        <div style="font-size:24px;font-family:'Cormorant Garamond',serif;
                    font-weight:600;color:#b52020;">{{ $criticalCount }}</div>
    </div>
    <div style="flex:1;min-width:160px;background:#e8f7ee;border:1px solid #a3d9b8;
                border-radius:8px;padding:14px 18px;">
        <div style="font-size:11px;font-family:'Inter',sans-serif;color:#1a7a45;
                    text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Next Count Due</div>
        <div style="font-size:16px;font-family:'Inter',sans-serif;
                    font-weight:600;color:#1a7a45;margin-top:4px;">
            {{ \Carbon\Carbon::parse($nextDue)->format('d M Y') }}
        </div>
    </div>
</div>

{{-- ── Session history table ── --}}
<div style="background:#fff;border:1px solid #e8d8f0;border-radius:8px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid #f0e8f4;background:#faf5fb;
                display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-family:'Inter',sans-serif;font-size:14px;font-weight:600;
                   color:#1e0a2c;margin:0;">Count History</h3>
        <span style="font-size:12px;font-family:'Inter',sans-serif;color:#9070a0;">
            {{ $sessions->total() }} sessions
        </span>
    </div>

    @if($sessions->isEmpty())
    <div style="padding:60px;text-align:center;font-family:'Inter',sans-serif;
                font-size:14px;color:#9070a0;">
        No stock counts yet. Click <strong>Start New Count</strong> to begin.
    </div>
    @else
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#faf5fb;">
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Session</th>
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Date</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Status</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Items</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Adjusted</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Low</th>
                <th style="padding:10px 16px;text-align:center;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">Critical</th>
                <th style="padding:10px 16px;text-align:left;font-size:11px;font-family:'Inter',sans-serif;
                           font-weight:600;color:#7a6884;text-transform:uppercase;letter-spacing:.06em;">By</th>
                <th style="padding:10px 16px;"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($sessions as $s)
            @php
                $statusColor = match($s->status) {
                    'completed'   => ['bg'=>'#e8f7ee','color'=>'#1a7a45','label'=>'Completed'],
                    'in_progress' => ['bg'=>'#fff8e8','color'=>'#a07020','label'=>'In Progress'],
                    default       => ['bg'=>'#f0f0f0','color'=>'#666','label'=>'Draft'],
                };
            @endphp
            <tr style="border-top:1px solid #f0e8f4;">
                <td style="padding:12px 16px;font-family:'Inter',sans-serif;font-size:13px;
                           font-weight:600;color:#1e0a2c;">{{ $s->session_no }}</td>
                <td style="padding:12px 16px;font-family:'Inter',sans-serif;font-size:13px;color:#4a3a5c;">
                    {{ $s->count_date?->format('d M Y') ?? '—' }}
                </td>
                <td style="padding:12px 16px;text-align:center;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;
                                 font-size:11px;font-family:'Inter',sans-serif;font-weight:600;
                                 background:{{ $statusColor['bg'] }};color:{{ $statusColor['color'] }};">
                        {{ $statusColor['label'] }}
                    </span>
                </td>
                <td style="padding:12px 16px;text-align:center;font-family:'Inter',sans-serif;
                           font-size:13px;color:#4a3a5c;">{{ $s->items_counted ?: '—' }}</td>
                <td style="padding:12px 16px;text-align:center;font-family:'Inter',sans-serif;
                           font-size:13px;color:#4a3a5c;">{{ $s->items_adjusted ?: '—' }}</td>
                <td style="padding:12px 16px;text-align:center;">
                    @if($s->low_stock_count > 0)
                    <span style="color:#a07020;font-weight:600;font-size:13px;
                                 font-family:'Inter',sans-serif;">{{ $s->low_stock_count }}</span>
                    @else
                    <span style="color:#ccc;font-size:13px;font-family:'Inter',sans-serif;">—</span>
                    @endif
                </td>
                <td style="padding:12px 16px;text-align:center;">
                    @if($s->critical_stock_count > 0)
                    <span style="color:#b52020;font-weight:600;font-size:13px;
                                 font-family:'Inter',sans-serif;">{{ $s->critical_stock_count }}</span>
                    @else
                    <span style="color:#ccc;font-size:13px;font-family:'Inter',sans-serif;">—</span>
                    @endif
                </td>
                <td style="padding:12px 16px;font-family:'Inter',sans-serif;font-size:12px;color:#7a6884;">
                    {{ $s->startedBy?->name ?? '—' }}
                </td>
                <td style="padding:12px 16px;text-align:right;">
                    @if($s->isEditable())
                    <a href="{{ route('inventory.stock-count.sheet', $s) }}"
                       style="color:#6a0f70;font-size:12px;font-family:'Inter',sans-serif;
                              font-weight:600;text-decoration:none;">Resume →</a>
                    @else
                    <a href="{{ route('inventory.stock-count.sheet', $s) }}"
                       style="color:#9070a0;font-size:12px;font-family:'Inter',sans-serif;
                              text-decoration:none;">View</a>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($sessions->hasPages())
    <div style="padding:14px 20px;border-top:1px solid #f0e8f4;">
        {{ $sessions->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
