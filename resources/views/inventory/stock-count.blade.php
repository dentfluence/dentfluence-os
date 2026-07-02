{{--
|===========================================================================
| Stock Count Sheet
| Staff enters physical counts here. Grouped by category.
| Save = save progress. Submit = finalise + apply adjustments.
|===========================================================================
--}}
@extends('layouts.app')
@section('title', 'Stock Count — ' . $session->session_no)

@section('content')
@include('inventory.partials.subnav')

{{-- ── Header ── --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;
            flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <a href="{{ route('inventory.stock-count.index') }}"
               style="font-size:12px;color:#9070a0;font-family:'Inter',sans-serif;
                      text-decoration:none;">← Back</a>
            <span style="color:#d8c8e4;">|</span>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:600;
                       color:#1a0a1e;margin:0;">{{ $session->session_no }}</h1>
            <span style="background:#fff8e8;color:#a07020;font-size:11px;padding:3px 10px;
                         border-radius:20px;font-family:'Inter',sans-serif;font-weight:600;">
                In Progress
            </span>
        </div>
        <p style="font-family:'Inter',sans-serif;font-size:13px;color:#7a6884;margin:0;">
            Count date: <strong>{{ $session->count_date->format('d M Y') }}</strong>
            &nbsp;·&nbsp; {{ $lines->count() }} items to count
            &nbsp;·&nbsp;
            <span style="color:#1a7a45;">
                {{ $lines->whereNotNull('physical_qty')->count() }} counted so far
            </span>
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        {{-- Save progress --}}
        <button form="count-form" name="_action" value="save"
                style="padding:9px 18px;border:1.5px solid #6a0f70;border-radius:7px;
                       background:#fff;color:#6a0f70;font-size:13px;
                       font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
            Save Progress
        </button>
        {{-- Submit & complete --}}
        <button form="count-form" name="_action" value="complete"
                onclick="return confirmSubmit()"
                style="padding:9px 18px;border:none;border-radius:7px;background:#6a0f70;
                       color:#fff;font-size:13px;font-family:'Inter',sans-serif;
                       font-weight:600;cursor:pointer;">
            ✓ Submit & Apply
        </button>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    ✓ {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:#fdeaea;border:1px solid #f5c6c6;color:#b52020;padding:10px 16px;
            border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;margin-bottom:16px;">
    @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
</div>
@endif

{{-- ── Instructions banner ── --}}
<div style="background:#f0f7ff;border:1px solid #b3d4f5;border-radius:8px;padding:12px 16px;
            margin-bottom:20px;font-family:'Inter',sans-serif;font-size:13px;color:#1a4a8a;">
    <strong>How to count:</strong>
    Enter the <em>actual physical quantity</em> you see on the shelf — not a +/- change.
    The system will calculate the variance and create adjustments automatically when you submit.
    You can save progress and come back. Leave a field blank to skip that item.
</div>

{{-- ── Count form ── --}}
<form id="count-form" method="POST">
    @csrf

    @foreach($grouped as $categoryName => $categoryLines)
    {{-- Category section --}}
    <div style="margin-bottom:24px;">
        <div style="background:#f5eefa;border-left:4px solid #6a0f70;padding:10px 16px;
                    border-radius:0 6px 6px 0;margin-bottom:0;">
            <span style="font-family:'Inter',sans-serif;font-size:12px;font-weight:700;
                         color:#6a0f70;text-transform:uppercase;letter-spacing:.08em;">
                {{ $categoryName }}
            </span>
            <span style="font-family:'Inter',sans-serif;font-size:12px;color:#9070a0;
                         margin-left:8px;">{{ $categoryLines->count() }} items</span>
        </div>

        <div style="background:#fff;border:1px solid #e8d8f0;border-top:none;
                    border-radius:0 0 8px 8px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#faf5fb;">
                        <th style="padding:9px 14px;text-align:left;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;width:35%;">Product</th>
                        <th style="padding:9px 14px;text-align:center;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;width:12%;">Unit</th>
                        <th style="padding:9px 14px;text-align:center;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;width:12%;">System Qty</th>
                        <th style="padding:9px 14px;text-align:center;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#1e0a2c;
                                   text-transform:uppercase;letter-spacing:.06em;width:14%;">Physical Count ✏</th>
                        <th style="padding:9px 14px;text-align:center;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;width:10%;">Variance</th>
                        <th style="padding:9px 14px;text-align:center;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;width:9%;">Min</th>
                        <th style="padding:9px 14px;text-align:left;font-size:11px;
                                   font-family:'Inter',sans-serif;font-weight:600;color:#7a6884;
                                   text-transform:uppercase;letter-spacing:.06em;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($categoryLines as $idx => $line)
                    @php
                        $hasPhysical = $line->physical_qty !== null;
                        $variance    = $hasPhysical ? ($line->physical_qty - $line->system_qty) : null;
                        $varClass    = !$hasPhysical ? '' : ($variance > 0 ? 'positive' : ($variance < 0 ? 'negative' : 'zero'));
                        $statusColor = match($line->stock_status) {
                            'critical' => '#b52020',
                            'out'      => '#b52020',
                            'low'      => '#a07020',
                            'healthy'  => '#1a7a45',
                            default    => '#999',
                        };
                        $rowBg = $hasPhysical ? (
                            in_array($line->stock_status, ['critical','out']) ? '#fff8f8' :
                            ($line->stock_status === 'low' ? '#fffdf0' : '#f9fff9')
                        ) : '#fff';
                    @endphp
                    <tr style="border-top:1px solid #f0e8f4;background:{{ $rowBg }};">

                        {{-- Hidden line_id --}}
                        <input type="hidden" name="counts[{{ $loop->parent->index * 1000 + $loop->index }}][line_id]"
                               value="{{ $line->id }}">

                        {{-- Product name --}}
                        <td style="padding:10px 14px;">
                            <div style="font-family:'Inter',sans-serif;font-size:13px;
                                        font-weight:500;color:#1e0a2c;line-height:1.3;">
                                {{ $line->product_name }}
                            </div>
                            @if($line->stock_status)
                            <span style="font-size:10px;font-family:'Inter',sans-serif;
                                         color:{{ $statusColor }};font-weight:600;">
                                ● {{ strtoupper($line->stock_status) }}
                            </span>
                            @endif
                        </td>

                        {{-- Unit --}}
                        <td style="padding:10px 14px;text-align:center;font-family:'Inter',sans-serif;
                                   font-size:12px;color:#7a6884;">
                            {{ $line->consumption_unit ?? '—' }}
                        </td>

                        {{-- System qty --}}
                        <td style="padding:10px 14px;text-align:center;font-family:'Inter',sans-serif;
                                   font-size:14px;font-weight:600;color:#4a3a5c;">
                            {{ number_format($line->system_qty, 0) }}
                        </td>

                        {{-- Physical count input --}}
                        <td style="padding:6px 8px;text-align:center;">
                            <input type="number"
                                   name="counts[{{ $loop->parent->index * 1000 + $loop->index }}][qty]"
                                   value="{{ $line->physical_qty !== null ? number_format($line->physical_qty, 0) : '' }}"
                                   min="0" step="1" placeholder="—"
                                   class="count-input"
                                   data-system="{{ $line->system_qty }}"
                                   data-idx="{{ $loop->parent->index * 1000 + $loop->index }}"
                                   style="width:80px;padding:7px 8px;border:2px solid #d8c8e4;
                                          border-radius:6px;font-size:14px;font-weight:600;
                                          font-family:'Inter',sans-serif;text-align:center;
                                          outline:none;box-sizing:border-box;
                                          {{ $hasPhysical ? 'background:#f5f0fa;border-color:#9070a0;' : '' }}">
                        </td>

                        {{-- Variance (live-updated by JS) --}}
                        <td style="padding:10px 8px;text-align:center;" id="var-{{ $loop->parent->index * 1000 + $loop->index }}">
                            @if($variance !== null)
                                <span style="font-family:'Inter',sans-serif;font-size:13px;font-weight:700;
                                             color:{{ $variance > 0 ? '#1a7a45' : ($variance < 0 ? '#b52020' : '#999') }};">
                                    {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 0) }}
                                </span>
                            @else
                                <span style="color:#ccc;font-size:13px;">—</span>
                            @endif
                        </td>

                        {{-- Min qty --}}
                        <td style="padding:10px 14px;text-align:center;font-family:'Inter',sans-serif;
                                   font-size:12px;color:#9070a0;">
                            {{ number_format($line->minimum_qty, 0) }}
                        </td>

                        {{-- Notes --}}
                        <td style="padding:6px 8px;">
                            <input type="text"
                                   name="counts[{{ $loop->parent->index * 1000 + $loop->index }}][notes]"
                                   value="{{ $line->notes ?? '' }}"
                                   placeholder="Optional note…"
                                   style="width:100%;padding:6px 10px;border:1px solid #e0d0ea;
                                          border-radius:5px;font-size:12px;
                                          font-family:'Inter',sans-serif;box-sizing:border-box;">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    {{-- Hidden action field — set by buttons above --}}
    <input type="hidden" name="_action" id="form-action" value="save">
</form>

{{-- Floating summary bar at bottom --}}
<div id="summary-bar"
     style="position:fixed;bottom:0;left:0;right:0;background:#1e0a2c;color:#fff;
            padding:12px 24px;display:flex;align-items:center;justify-content:space-between;
            font-family:'Inter',sans-serif;font-size:13px;z-index:100;
            box-shadow:0 -4px 20px rgba(0,0,0,0.2);">
    <div style="display:flex;gap:24px;">
        <span>Counted: <strong id="bar-counted">0</strong> / {{ $lines->count() }}</span>
        <span style="color:#f5c66b;">Low: <strong id="bar-low">0</strong></span>
        <span style="color:#f5a0a0;">Critical/Out: <strong id="bar-critical">0</strong></span>
    </div>
    <div style="display:flex;gap:10px;">
        <button form="count-form" name="_action" value="save"
                style="padding:8px 16px;border:1.5px solid #fff;border-radius:6px;
                       background:transparent;color:#fff;font-size:12px;
                       font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;">
            Save
        </button>
        <button form="count-form" name="_action" value="complete"
                onclick="return confirmSubmit()"
                style="padding:8px 16px;border:none;border-radius:6px;background:#6a0f70;
                       color:#fff;font-size:12px;font-family:'Inter',sans-serif;
                       font-weight:600;cursor:pointer;">
            ✓ Submit & Apply
        </button>
    </div>
</div>

{{-- Bottom padding so the fixed bar doesn't cover last row --}}
<div style="height:64px;"></div>

<script>
// ── Intercept form button clicks to set the correct action ──
document.querySelectorAll('[form="count-form"][name="_action"]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        // Complete action needs confirmation (handled by onclick)
        // Just set the hidden field
        const hidden = document.getElementById('form-action');
        if (hidden) hidden.value = this.value;

        // Set the actual form action URL
        const form = document.getElementById('count-form');
        if (this.value === 'complete') {
            form.action = '{{ route("inventory.stock-count.complete", $session) }}';
        } else {
            form.action = '{{ route("inventory.stock-count.save", $session) }}';
        }
    });
});

// ── Live variance calculation ──
const minQtyMap = {};
const reorderMap = {};

@foreach($lines as $line)
    @php $flatIdx = $loop->index; @endphp
    minQtyMap[{{ $flatIdx }}]  = {{ $line->minimum_qty }};
    reorderMap[{{ $flatIdx }}] = {{ $line->reorder_level }};
@endforeach

document.querySelectorAll('.count-input').forEach(input => {
    input.addEventListener('input', function() {
        const idx    = parseInt(this.dataset.idx);
        const system = parseFloat(this.dataset.system) || 0;
        const val    = this.value !== '' ? parseFloat(this.value) : null;
        const varEl  = document.getElementById('var-' + idx);

        if (val === null || isNaN(val)) {
            varEl.innerHTML = '<span style="color:#ccc;font-size:13px;">—</span>';
            this.style.borderColor = '#d8c8e4';
            this.style.background  = '#fff';
        } else {
            const variance = val - system;
            const color    = variance > 0 ? '#1a7a45' : (variance < 0 ? '#b52020' : '#999');
            const sign     = variance > 0 ? '+' : '';
            varEl.innerHTML = `<span style="font-family:'Inter',sans-serif;font-size:13px;
                               font-weight:700;color:${color};">${sign}${variance.toFixed(0)}</span>`;
            this.style.borderColor = '#9070a0';
            this.style.background  = '#f5f0fa';
        }

        updateSummaryBar();
    });
});

function updateSummaryBar() {
    let counted = 0, low = 0, critical = 0;
    document.querySelectorAll('.count-input').forEach((input, i) => {
        if (input.value === '') return;
        counted++;
        const qty     = parseFloat(input.value) || 0;
        const min     = minQtyMap[i] ?? 0;
        const reorder = reorderMap[i] ?? 0;
        if (qty <= 0 || qty <= min) { critical++; }
        else if (reorder > min && qty <= reorder) { low++; }
    });
    document.getElementById('bar-counted').textContent  = counted;
    document.getElementById('bar-low').textContent      = low;
    document.getElementById('bar-critical').textContent = critical;
}

function confirmSubmit() {
    const counted = parseInt(document.getElementById('bar-counted').textContent);
    const total   = {{ $lines->count() }};
    const low     = parseInt(document.getElementById('bar-low').textContent);
    const crit    = parseInt(document.getElementById('bar-critical').textContent);

    let msg = `Submit stock count?\n\n`;
    msg    += `✓ Items counted: ${counted} / ${total}\n`;
    if (low > 0)  msg += `Low stock items: ${low}\n`;
    if (crit > 0) msg += `Critical/out items: ${crit}\n`;
    msg    += `\nStock adjustments will be applied immediately.`;

    return confirm(msg);
}

// Initial summary bar update
updateSummaryBar();
</script>

@endsection
