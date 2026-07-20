@extends('layouts.app')
@section('page-title', 'Inventory — Stock Movement')
@section('content')

@php
    // Map raw movement types to plain, dentist-friendly words.
    $kindOf = function ($m) {
        if ($m->reversal_of_id || $m->reversed_at) return ['Correction', '#a05c00', '#fff4e0'];
        return match ($m->movement_type) {
            'stock_in', 'opening_stock'                      => ['Purchased', '#1a7a45', '#e8f7ef'],
            'stock_out', 'treatment_usage', 'retail_sale'    => ['Used',      '#6a0f70', '#f6ecf8'],
            'adjustment'                                     => ['Adjustment','#a05c00', '#fff4e0'],
            'transfer'                                       => ['Moved',     '#1a5ea8', '#e8f0fb'],
            'expired', 'damaged'                             => ['Removed',   '#b52020', '#fdeaea'],
            default                                          => [$m->getMovementLabel(), '#555', '#f0f0f0'],
        };
    };
    $addTypes = ['stock_in', 'opening_stock'];
@endphp

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Stock Movement · {{ $movements->total() }} {{ Str::plural('entry', $movements->total()) }}</div>
    </div>
</div>

@include('inventory.partials.subnav')

<div style="font-size:12.5px;color:#7a6884;margin-bottom:16px;line-height:1.6;">
    A simple timeline of every change to your stock — what was purchased, used, adjusted or corrected, and by whom.
</div>

{{-- Filter chips --}}
@php
    $chips = [
        ''           => 'All',
        'purchased'  => 'Purchased',
        'used'       => 'Used',
        'adjustment' => 'Adjustment',
        'other'      => 'Other',
    ];
@endphp
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    @foreach($chips as $val => $label)
        @php $on = ($filter ?? '') === $val; @endphp
        <a href="{{ route('inventory.stock-movement', array_filter(['kind' => $val])) }}"
           style="padding:6px 14px;border-radius:20px;font-size:12.5px;font-weight:500;text-decoration:none;
                  {{ $on ? 'background:#6a0f70;color:#fff;' : 'background:#fff;color:#4e2060;border:1px solid rgba(185,92,183,0.18);' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:780px;">
            <thead>
                <tr style="background:#faf5fb;border-bottom:1px solid rgba(185,92,183,0.12);">
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">When</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Action</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Item</th>
                    <th style="padding:11px 18px;text-align:right;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Change</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Location</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">By</th>
                    <th style="padding:11px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $m)
                    @php
                        [$kLabel, $kColor, $kBg] = $kindOf($m);
                        $isAdd  = in_array($m->movement_type, $addTypes) || ($m->movement_type === 'adjustment' && $m->qty >= 0);
                        $isMove = $m->movement_type === 'transfer';
                        $sign   = $isMove ? '' : ($isAdd ? '+' : '−');
                        $loc    = $isMove
                            ? (($m->fromLocation->name ?? '?') . ' → ' . ($m->toLocation->name ?? '?'))
                            : ($m->toLocation->name ?? $m->fromLocation->name ?? '—');
                    @endphp
                    <tr style="border-bottom:1px solid rgba(185,92,183,0.06);">
                        <td style="padding:12px 18px;font-size:12.5px;color:#4e2060;white-space:nowrap;">
                            {{ $m->created_at->format('d M Y') }}
                            <div style="font-size:11px;color:#9a85aa;">{{ $m->created_at->format('h:i A') }}</div>
                        </td>
                        <td style="padding:12px 18px;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;background:{{ $kBg }};color:{{ $kColor }};">
                                {{ $kLabel }}
                            </span>
                        </td>
                        <td style="padding:12px 18px;font-size:13px;color:#1e0a2c;">
                            {{ $m->item->product_name ?? 'Item #'.$m->inventory_item_id }}
                            @if($m->item?->item_code)<div style="font-size:11px;color:#9a85aa;">{{ $m->item->item_code }}</div>@endif
                        </td>
                        <td style="padding:12px 18px;font-size:13px;text-align:right;font-weight:600;color:{{ $isMove ? '#1a5ea8' : ($isAdd ? '#1a7a45' : '#b52020') }};">
                            {{ $sign }}{{ rtrim(rtrim(number_format(abs($m->qty), 2), '0'), '.') }}
                        </td>
                        <td style="padding:12px 18px;font-size:12.5px;color:#4e2060;">{{ $loc }}</td>
                        <td style="padding:12px 18px;font-size:12.5px;color:#4e2060;">{{ $m->createdBy->name ?? '—' }}</td>
                        <td style="padding:12px 18px;font-size:12px;color:#7a6884;max-width:260px;">{{ $m->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:48px;text-align:center;color:#9a85aa;font-size:13px;">
                            No stock movements yet. Purchases, usage and adjustments will appear here.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($movements->hasPages())
<div style="margin-top:16px;">
    {{ $movements->links() }}
</div>
@endif

@endsection
