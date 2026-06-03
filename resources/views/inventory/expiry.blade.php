@extends('layouts.app')
@section('page-title', 'Inventory — Expiry')
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Expiry Tracker · Batches monitored for clinical safety</div>
    </div>
</div>

@include('inventory.partials.subnav')

{{-- Urgency filter tabs --}}
@php
    $filter = request('filter', 'all');
    $filters = [
        ['val'=>'all',     'label'=>'All Upcoming',  'color'=>'#6a0f70'],
        ['val'=>'expired', 'label'=>'Already Expired','color'=>'#b52020'],
        ['val'=>'7',       'label'=>'Within 7 days', 'color'=>'#b52020'],
        ['val'=>'30',      'label'=>'Within 30 days','color'=>'#a05c00'],
        ['val'=>'90',      'label'=>'Within 90 days','color'=>'#a05c00'],
    ];
@endphp
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
    @foreach($filters as $f)
    <a href="?filter={{ $f['val'] }}"
        style="padding:6px 14px;border-radius:3px;font-size:12.5px;font-weight:500;text-decoration:none;
        {{ $filter===$f['val'] ? 'background:'.$f['color'].';color:#fff;' : 'background:#fff;color:#4e2060;border:1px solid rgba(185,92,183,0.18);' }}">
        {{ $f['label'] }}
    </a>
    @endforeach
</div>

<div class="df-card" style="overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.10);">
                <th style="padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Item</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Batch No.</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Location</th>
                <th style="padding:10px 14px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Qty Received</th>
                <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Expiry Date</th>
                <th style="padding:10px 18px;text-align:center;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Urgency</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $mv)
            @php
                $daysLeft = (int) now()->diffInDays($mv->expiry_date, false);
                if($daysLeft < 0){
                    $urgencyBg='#fdeaea';$urgencyColor='#b52020';$urgencyLabel='Expired';
                } elseif($daysLeft<=7){
                    $urgencyBg='#fdeaea';$urgencyColor='#b52020';$urgencyLabel=$daysLeft.' days left';
                } elseif($daysLeft<=30){
                    $urgencyBg='#fff4e0';$urgencyColor='#a05c00';$urgencyLabel=$daysLeft.' days left';
                } else {
                    $urgencyBg='#e8f7ef';$urgencyColor='#1a7a45';$urgencyLabel=$daysLeft.' days left';
                }
            @endphp
            <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;"
                onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                <td style="padding:11px 18px;">
                    <div style="font-weight:500;color:#1e0a2c;">{{ $mv->item?->product_name ?? '—' }}</div>
                    @if($mv->item?->category)<div style="font-size:11px;color:#9a85aa;">{{ $mv->item->category->name }}</div>@endif
                </td>
                <td style="padding:11px 14px;font-family:'DM Mono',monospace;font-size:12px;color:#4e2060;">{{ $mv->batch_no ?: '—' }}</td>
                <td style="padding:11px 14px;font-size:12.5px;color:#2e1040;">{{ $mv->toLocation?->name ?? 'Main Store' }}</td>
                <td style="padding:11px 14px;text-align:center;font-family:'DM Mono',monospace;font-weight:600;color:#1e0a2c;">
                    {{ number_format($mv->qty, 0) }}
                    <span style="font-size:10px;color:#9a85aa;font-weight:400;">{{ $mv->item?->consumption_unit }}</span>
                </td>
                <td style="padding:11px 14px;font-size:13px;color:#1e0a2c;">
                    {{ $mv->expiry_date ? $mv->expiry_date->format('d M Y') : '—' }}
                </td>
                <td style="padding:11px 18px;text-align:center;">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;background:{{ $urgencyBg }};color:{{ $urgencyColor }};">
                        @if($daysLeft < 0 || $daysLeft <= 7)
                        <span style="width:5px;height:5px;border-radius:50%;background:{{ $urgencyColor }};display:inline-block;"></span>
                        @endif
                        {{ $urgencyLabel }}
                    </span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="padding:48px;text-align:center;color:#9a85aa;">
                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.2)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                No batches with expiry dates found.<br>
                <span style="font-size:12px;">Add batch numbers and expiry dates when doing Stock In.</span>
            </td></tr>
            @endforelse
        </tbody>
    </table>
    @if($movements->hasPages())
    <div style="padding:14px 18px;border-top:1px solid rgba(185,92,183,0.07);">{{ $movements->links() }}</div>
    @endif
</div>
@endsection
