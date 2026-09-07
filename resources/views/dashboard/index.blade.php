@extends('layouts.app')

{{--
    DASHBOARD — the PERIOD screen.

    Nothing here is about today. The Daily Huddle owns today; this screen
    answers "how did this window go, against the one before it".

    NO CHARTS, by ruling (7 Sep). A trend chart answers one question — up or
    down — and that answer now sits inside the card as a delta line.
    Everything below the cards is a dense table, not a picture.

    Card idiom is the app's existing one: white, 1px #e8d5f0 border, big
    Cormorant figure, uppercase DM Sans label, small sub-line.
--}}

@section('content')
@php
    $fmt = fn ($n) => number_format((float) $n, 0);

    // Delta line. Up is not automatically good, so the caller says which
    // direction is healthy.
    $chip = function (?int $change, bool $upIsGood = true) {
        if ($change === null) {
            return ['no earlier data', 'text-gray-400'];
        }
        if ($change == 0) {
            return ['no change', 'text-gray-400'];
        }
        $good = ($change > 0) === $upIsGood;
        return [
            ($change > 0 ? '▲ ' : '▼ ') . abs($change) . '% vs previous',
            $good ? 'text-green-600' : 'text-red-600',
        ];
    };

    $periods = ['7' => '7 days', '30' => '30 days', '90' => '90 days', '365' => '1 year'];

    // ONE grid of cards: [label, value, sub-line text, sub-line colour, link, accent]
    $cards = [];

    $flow = function ($label, $display, $meta, $link = null) use ($chip) {
        [$text, $color] = $chip($meta['change']);
        return [$label, $display, $text, $color, $link, null];
    };

    if ($showMoney) {
        // Row 1 — the money for the selected window.
        $cards[] = $flow('Billed',             'Rs. ' . $fmt($numbers['billed']['value']),    $numbers['billed'],           route('finance.income'));
        $cards[] = $flow('Received',           'Rs. ' . $fmt($numbers['collected']['value']), $numbers['collected'],        route('finance.income'));
        $cards[] = $flow('Collected Of Billed', $numbers['collection_ratio']['value'] . '%',  $numbers['collection_ratio']);
        $cards[] = $flow('Net Profit · In Hand', 'Rs. ' . $fmt($numbers['profit']['value']),  $numbers['profit']);
    }

    // Row 2 — activity for the window, then the two STOCKS.
    $cards[] = $flow('Appointments Done', $fmt($numbers['appointments']['value']), $numbers['appointments'], route('appointments.index'));
    $cards[] = $flow('New Patients',      $fmt($numbers['new_patients']['value']), $numbers['new_patients'],
                     Route::has('patients.index') ? route('patients.index') : null);

    if ($showMoney) {
        // STOCKS. CEO ruling 6 Sep: a receivable does not shrink because
        // someone changed a date filter. Same card shape so the grid stays
        // even, but a coloured left edge and their own sub-line so they can
        // never be read as part of the window above.
        $cards[] = ['Outstanding', 'Rs. ' . $fmt($stocks['outstanding']),
                    'as of today · not affected by the date filter', 'text-gray-400',
                    route('finance.income') . '?status=unpaid', 'border-l-4 border-l-amber-400'];

        $cards[] = ['Advance In Wallet', 'Rs. ' . $fmt($stocks['patient_credit']),
                    'as of today · patient money we hold', 'text-gray-400',
                    null, 'border-l-4 border-l-blue-300'];
    }
@endphp

<div class="p-6 space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }},
                {{ auth()->user()->name }}
            </h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans] mt-1">
                {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}
                <span class="text-gray-300">· compared with {{ $prevFrom->format('d M') }} — {{ $prevTo->format('d M') }}</span>
            </p>
        </div>

        @if(Route::has('huddle.index'))
        <a href="{{ route('huddle.index') }}"
           class="px-4 py-2 border border-[#6a0f70] text-[#6a0f70] text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#f5eef9] transition">
            Today → Daily Huddle
        </a>
        @endif
    </div>

    {{-- ── Range picker ────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach($periods as $key => $label)
        <a href="{{ route('dashboard', ['period' => $key]) }}"
           class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] border transition
                  {{ $period === $key
                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                        : 'bg-white text-gray-500 border-[#e8d5f0] hover:border-[#6a0f70]' }}">
            {{ $label }}
        </a>
        @endforeach

        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2 ml-2">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="{{ $from->toDateString() }}"
                   class="border border-[#e8d5f0] px-2 py-1.5 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}"
                   class="border border-[#e8d5f0] px-2 py-1.5 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">
            <button type="submit"
                    class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] border border-[#6a0f70] text-[#6a0f70] hover:bg-[#f5eef9] transition">
                Apply
            </button>
        </form>
    </div>

    {{-- ── Cards — 4 per row ───────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach($cards as [$label, $display, $sub, $subColor, $link, $accent])

        @if($link)
        <a href="{{ $link }}" class="bg-white border border-[#e8d5f0] {{ $accent }} p-5 hover:border-[#6a0f70] transition block">
        @else
        <div class="bg-white border border-[#e8d5f0] {{ $accent }} p-5">
        @endif

            <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $display }}</p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">{{ $label }}</p>
            <p class="mt-3 text-xs font-[DM_Sans] {{ $subColor }}">{{ $sub }}</p>

        @if($link)
        </a>
        @else
        </div>
        @endif
        @endforeach
    </div>

    @if($showMoney)
    {{-- ── Tables ──────────────────────────────────────────────────────── --}}
    <div x-data="{ tab: 'doctor' }" class="bg-white border border-[#e8d5f0]">

        <div class="flex flex-wrap border-b border-[#e8d5f0]">
            @foreach(['doctor' => 'By doctor', 'category' => 'By treatment category', 'mode' => 'By payment mode'] as $key => $label)
            <button type="button" x-on:click="tab = '{{ $key }}'"
                    class="px-5 py-3 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] border-b-2 transition"
                    x-bind:class="tab === '{{ $key }}'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-400 hover:text-[#6a0f70]'">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- By doctor --}}
        <div x-show="tab === 'doctor'" class="overflow-x-auto">
            @if($byDoctor->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-400 font-[DM_Sans]">No work recorded in this window.</p>
            @else
            <table class="w-full text-sm font-[DM_Sans]">
                <thead>
                    <tr class="text-[11px] text-gray-400 uppercase tracking-widest border-b border-[#f0e4f5]">
                        <th class="text-left  px-5 py-2.5 font-semibold">Doctor</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Visits</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Billed</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Received</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Rs. / visit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f7f0fa]">
                    @foreach($byDoctor as $row)
                    <tr class="hover:bg-[#faf5fc]">
                        <td class="px-5 py-2.5 text-[#380740] font-medium">{{ $row['doctor'] }}</td>
                        <td class="px-5 py-2.5 text-right">{{ $fmt($row['visits']) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ $fmt($row['billed']) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ $fmt($row['collected']) }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">{{ $fmt($row['per_visit']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="px-5 py-3 text-[11px] text-gray-400 font-[DM_Sans] border-t border-[#f0e4f5]">
                Visits are the doctor's own work. Billed is whoever set the price. Received is reception's.
                A large "Unassigned" row means the treatment was not picked on the invoice line.
            </p>
            @endif
        </div>

        {{-- By treatment category --}}
        <div x-show="tab === 'category'" x-cloak class="overflow-x-auto">
            @if($byCategory->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-400 font-[DM_Sans]">Nothing billed in this window.</p>
            @else
            <table class="w-full text-sm font-[DM_Sans]">
                <thead>
                    <tr class="text-[11px] text-gray-400 uppercase tracking-widest border-b border-[#f0e4f5]">
                        <th class="text-left  px-5 py-2.5 font-semibold">Category</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Lines</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Billed</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f7f0fa]">
                    @foreach($byCategory as $cat)
                    <tr class="hover:bg-[#faf5fc]">
                        <td class="px-5 py-2.5 text-[#380740] font-medium">{{ $cat->name }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">{{ $fmt($cat->txn_count) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ $fmt($cat->revenue) }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">
                            {{ $numbers['billed']['value'] > 0 ? round(($cat->revenue / $numbers['billed']['value']) * 100) : 0 }}%
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="px-5 py-3 text-[11px] text-gray-400 font-[DM_Sans] border-t border-[#f0e4f5]">
                Invoice-level discounts are shared across the lines, so these add back to Billed above.
            </p>
            @endif
        </div>

        {{-- By payment mode --}}
        <div x-show="tab === 'mode'" x-cloak class="overflow-x-auto">
            @if($byMode->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-400 font-[DM_Sans]">No payments in this window.</p>
            @else
            <table class="w-full text-sm font-[DM_Sans]">
                <thead>
                    <tr class="text-[11px] text-gray-400 uppercase tracking-widest border-b border-[#f0e4f5]">
                        <th class="text-left  px-5 py-2.5 font-semibold">Mode</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Payments</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f7f0fa]">
                    @foreach($byMode as $mode)
                    <tr class="hover:bg-[#faf5fc]">
                        <td class="px-5 py-2.5 text-[#380740] font-medium">{{ ucfirst(str_replace('_', ' ', $mode->payment_mode ?? 'Not recorded')) }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">{{ $fmt($mode->cnt) }}</td>
                        <td class="px-5 py-2.5 text-right">{{ $fmt($mode->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
    @endif

</div>
@endsection
