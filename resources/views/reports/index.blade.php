@extends('layouts.app')
@section('page-title', 'Reports & Analytics')

@section('head-extra')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('content')
<div class="p-6 space-y-6">

    {{-- ── Header + Period Filter ─────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Appointment Reports</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $from->format('M d, Y') }} — {{ $to->format('M d, Y') }}
            </p>
        </div>

        <form method="GET" action="{{ route('reports.index') }}" id="periodForm" class="flex flex-wrap gap-2 items-center">
            @foreach(['7'=>'7 days','30'=>'30 days','90'=>'3 months','365'=>'1 year'] as $val => $label)
                <button type="submit" name="period" value="{{ $val }}"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition
                        {{ $period == $val
                            ? 'bg-purple-700 text-white border-purple-700'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
            <input type="date" name="from" value="{{ $period === 'custom' ? $from->toDateString() : '' }}"
                class="text-sm border border-gray-300 rounded-lg px-2 py-1.5">
            <span class="text-gray-400 text-sm">→</span>
            <input type="date" name="to" value="{{ $period === 'custom' ? $to->toDateString() : '' }}"
                class="text-sm border border-gray-300 rounded-lg px-2 py-1.5">
            <button type="submit" name="period" value="custom"
                class="px-3 py-1.5 rounded-lg text-sm font-medium border transition
                    {{ $period === 'custom' ? 'bg-purple-700 text-white border-purple-700' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                Custom
            </button>
        </form>
    </div>

    {{-- ── Tab Navigation ────────────────────────────────────────────────── --}}
    <div class="flex gap-1 border-b border-gray-200">
        <button onclick="switchTab('appointments')" id="tab-btn-appointments"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-purple-700 text-purple-700 -mb-px transition">
            Appointments
        </button>
        <button onclick="switchTab('revenue')" id="tab-btn-revenue"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px hover:text-gray-700 transition">
            Revenue
        </button>
        <button onclick="switchTab('patients')" id="tab-btn-patients"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px hover:text-gray-700 transition">
            Patients
        </button>
        <button onclick="switchTab('treatments')" id="tab-btn-treatments"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px hover:text-gray-700 transition">
            Treatments
        </button>
        <button onclick="switchTab('lab')" id="tab-btn-lab"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px hover:text-gray-700 transition">
            Lab
        </button>
        <button onclick="switchTab('inventory')" id="tab-btn-inventory"
                class="px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 -mb-px hover:text-gray-700 transition">
            Inventory
        </button>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── APPOINTMENTS TAB ──────────────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div id="tab-appointments">

    {{-- ── KPI Cards ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
        @php
        $cards = [
            ['label'=>'Total',        'value'=>$totalAppointments,   'color'=>'bg-purple-50 border-purple-200', 'text'=>'text-purple-700'],
            ['label'=>'Completed',    'value'=>$completed,            'color'=>'bg-green-50 border-green-200',  'text'=>'text-green-700'],
            ['label'=>'Cancelled',    'value'=>$cancelled,            'color'=>'bg-red-50 border-red-200',      'text'=>'text-red-600'],
            ['label'=>'No-Show',      'value'=>$noShow,               'color'=>'bg-orange-50 border-orange-200','text'=>'text-orange-600'],
            ['label'=>'Walk-ins',     'value'=>$walkins,              'color'=>'bg-blue-50 border-blue-200',    'text'=>'text-blue-600'],
            ['label'=>'Completion %', 'value'=>$completionRate.'%',   'color'=>'bg-teal-50 border-teal-200',    'text'=>'text-teal-700'],
            ['label'=>'New Patients', 'value'=>$newPatients,          'color'=>'bg-indigo-50 border-indigo-200','text'=>'text-indigo-700'],
        ];
        @endphp
        @foreach($cards as $card)
        <div class="rounded-xl border p-4 {{ $card['color'] }} flex flex-col gap-1">
            <span class="text-xs text-gray-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</span>
            <span class="text-2xl font-bold {{ $card['text'] }}">{{ $card['value'] }}</span>
        </div>
        @endforeach
    </div>

    {{-- ── Row 1: Daily Trend + Status Doughnut ───────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Appointment Trend</h2>
            <div class="relative h-64">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Status Breakdown</h2>
            <div class="relative h-48">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="mt-3 space-y-1" id="statusLegend"></div>
        </div>
    </div>

    {{-- ── Row 2: By Category + By Doctor ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">By Treatment Category</h2>
            <div class="relative h-56">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">By Doctor</h2>
            <div class="relative h-56">
                <canvas id="doctorChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Category KPI Breakdown Table ───────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Treatment Category KPIs</h2>
                <p class="text-xs text-gray-400 mt-0.5">Appointments and revenue per category for the selected period</p>
            </div>
            <a href="{{ route('treatments.price-list') }}"
               class="text-xs text-purple-600 hover:underline font-medium">View Price List →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-right">Appointments</th>
                        <th class="px-4 py-3 text-right">% of Total</th>
                        <th class="px-4 py-3 text-right">Revenue (Rs. )</th>
                        <th class="px-4 py-3 text-right">Billing Txns</th>
                        <th class="px-4 py-3 text-left">Volume Bar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $maxCat = $categoryKpi->max('total') ?: 1; @endphp
                    @forelse($categoryKpi as $cat)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $cat->name }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ number_format($cat->total) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">
                            {{ $totalAppointments > 0 ? round(($cat->total / $totalAppointments) * 100, 1) : 0 }}%
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $cat->revenue > 0 ? 'text-green-700' : 'text-gray-300' }}">
                            {{ $cat->revenue > 0 ? 'Rs. '.number_format($cat->revenue, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">
                            {{ $cat->txn_count > 0 ? $cat->txn_count : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-purple-500 h-2 rounded-full"
                                     style="width: {{ round(($cat->total / $maxCat) * 100) }}%"></div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No category data for this period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($categoryKpi->isNotEmpty())
                <tfoot>
                    <tr class="bg-gray-50 font-medium">
                        <td class="px-4 py-3 text-gray-700">Total</td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ number_format($totalAppointments) }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">100%</td>
                        <td class="px-4 py-3 text-right text-green-700">
                            @php $totalRev = $categoryKpi->sum('revenue'); @endphp
                            {{ $totalRev > 0 ? 'Rs. '.number_format($totalRev, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ $categoryKpi->sum('txn_count') ?: '—' }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Day of Week Bar Heatmap ─────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Busiest Days of the Week</h2>
        @php
            $dayNames = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];
            $maxDow   = $byDayOfWeek->max() ?: 1;
        @endphp
        <div class="flex gap-3 items-end h-24">
            @foreach($dayNames as $dow => $name)
            @php $count = $byDayOfWeek->get($dow, 0); $pct = round(($count / $maxDow) * 100); @endphp
            <div class="flex flex-col items-center gap-1 flex-1">
                <span class="text-xs font-medium text-gray-600">{{ $count }}</span>
                <div class="w-full rounded-t-md"
                     style="height: {{ max(4, intval($pct * 0.6)) }}px; background: rgba(109,40,217,{{ max(0.08, round($pct/100,2)) }});">
                </div>
                <span class="text-xs text-gray-500">{{ $name }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Appointments Table ───────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Recent Appointments</h2>
            <span class="text-xs text-gray-400">{{ $recentAppointments->count() }} records shown</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Date & Time</th>
                        <th class="px-4 py-3 text-left">Patient</th>
                        <th class="px-4 py-3 text-left">Doctor</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentAppointments as $apt)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                            {{ $apt->appointment_date->format('d M Y') }}
                            <span class="text-gray-400 text-xs ml-1">
                                {{ \Carbon\Carbon::parse($apt->appointment_time)->format('H:i') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($apt->patient)
                                <a href="{{ route('patients.show', $apt->patient) }}"
                                   class="text-purple-700 hover:underline font-medium">
                                    {{ $apt->patient->name ?? '—' }}
                                </a>
                            @else <span class="text-gray-400">—</span> @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $apt->doctor->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $apt->treatmentCategory->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $apt->is_walkin ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $apt->is_walkin ? 'Walk-in' : ucfirst($apt->type ?? 'Scheduled') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php
                            $ss = ['completed'=>'bg-green-100 text-green-700','scheduled'=>'bg-purple-100 text-purple-700',
                                   'cancelled'=>'bg-red-100 text-red-600','no_show'=>'bg-orange-100 text-orange-600',
                                   'checkin'=>'bg-blue-100 text-blue-600','in_chair'=>'bg-teal-100 text-teal-700'];
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $ss[$apt->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst(str_replace('_',' ',$apt->status)) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No appointments in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    </div>{{-- /tab-appointments --}}

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── REVENUE TAB ────────────────────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div id="tab-revenue" class="hidden space-y-6">

        {{-- Revenue KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
            $revCards = [
                ['label'=>'Collected',    'value'=>'Rs. '.number_format($revKpis['collected'],0),   'color'=>'bg-green-50 border-green-200',   'text'=>'text-green-700',  'sub'=>null],
                ['label'=>'Outstanding',  'value'=>'Rs. '.number_format($revKpis['outstanding'],0), 'color'=>'bg-amber-50 border-amber-200',   'text'=>'text-amber-700',  'sub'=>null],
                ['label'=>'Invoices',     'value'=>$revKpis['invoice_count'],                    'color'=>'bg-purple-50 border-purple-200', 'text'=>'text-purple-700', 'sub'=>null],
                ['label'=>'Avg Invoice',  'value'=>'Rs. '.number_format($revKpis['avg_invoice'],0), 'color'=>'bg-blue-50 border-blue-200',     'text'=>'text-blue-700',   'sub'=>null],
                ['label'=>'Collection %', 'value'=>$revKpis['collection_rate'].'%',              'color'=>'bg-teal-50 border-teal-200',     'text'=>'text-teal-700',   'sub'=>null],
                ['label'=>'Best Day',     'value'=>$revKpis['top_day'] ? 'Rs. '.number_format($revKpis['top_day_amt'],0) : '—',
                                          'color'=>'bg-indigo-50 border-indigo-200',             'text'=>'text-indigo-700',
                                          'sub'=>$revKpis['top_day'] ? \Carbon\Carbon::parse($revKpis['top_day'])->format('d M') : null],
            ];
            @endphp
            @foreach($revCards as $card)
            <div class="rounded-xl border p-4 {{ $card['color'] }} flex flex-col gap-1">
                <span class="text-xs text-gray-500 font-medium uppercase tracking-wide">{{ $card['label'] }}</span>
                <span class="text-2xl font-bold {{ $card['text'] }}">{{ $card['value'] }}</span>
                @if($card['sub'])<span class="text-xs text-gray-400">{{ $card['sub'] }}</span>@endif
            </div>
            @endforeach
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Daily Collections</h2>
                <div class="relative h-64"><canvas id="revTrendChart"></canvas></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">By Payment Mode</h2>
                <div class="relative h-48"><canvas id="revModeChart"></canvas></div>
                <div class="mt-3 space-y-1" id="revModeLegend"></div>
            </div>
        </div>

        {{-- Top Patients --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700">Top Patients by Revenue</h2>
                <p class="text-xs text-gray-400 mt-0.5">Top 10 — selected period</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Patient</th>
                        <th class="px-4 py-3 text-left">Phone</th>
                        <th class="px-4 py-3 text-right">Invoices</th>
                        <th class="px-4 py-3 text-right">Total Paid</th>
                        <th class="px-4 py-3 text-left">Bar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $maxRev = $revTopPatients->max('total_paid') ?: 1; @endphp
                    @forelse($revTopPatients as $i => $pt)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 font-medium">{{ $i + 1 }}</td>
                        <td class="px-4 py-3"><a href="{{ route('patients.show', $pt->id) }}" class="text-purple-700 hover:underline font-medium">{{ $pt->name }}</a></td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $pt->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">{{ $pt->invoice_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-green-700">Rs. {{ number_format($pt->total_paid, 0) }}</td>
                        <td class="px-4 py-3">
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width:{{ round(($pt->total_paid/$maxRev)*100) }}%"></div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No payments in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Outstanding Invoices --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-700">Outstanding Invoices</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Top 10 unpaid / partial — all time</p>
                </div>
                <a href="{{ route('billing.index', ['status'=>'partial']) }}" class="text-xs text-purple-600 hover:underline">View all →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Invoice</th>
                        <th class="px-4 py-3 text-left">Patient</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-right">Balance Due</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($revOutstanding as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><a href="{{ route('billing.show', $inv) }}" class="text-purple-700 hover:underline font-mono text-xs">{{ $inv->invoice_number }}</a></td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $inv->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $inv->invoice_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($inv->total_amount, 0) }}</td>
                        <td class="px-4 py-3 text-right text-green-600">Rs. {{ number_format($inv->paid_amount, 0) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-red-600">Rs. {{ number_format($inv->balance_due, 0) }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $inv->status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($inv->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No outstanding invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>{{-- /tab-revenue --}}

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- ── PATIENTS TAB ────────────────────────────────────────────────── --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div id="tab-patients" class="hidden space-y-6">

        {{-- KPI row --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">New Patients</p>
                <p class="text-2xl font-bold text-purple-700 mt-1">{{ number_format($patTotal) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">in selected period</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Return Rate</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $patReturnRate }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">patients with 2+ visits</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top Source</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $patSource->first()?->source ?? '—' }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patSource->first()?->total ?? 0 }} patients</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top City</p>
                <p class="text-2xl font-bold text-orange-500 mt-1">{{ $patCity->first()?->city ?? '—' }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patCity->first()?->total ?? 0 }} patients</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- New patients by month chart --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">New Patients — Last 12 Months</h3>
                <canvas id="patMonthChart" height="200"></canvas>
            </div>

            {{-- Gender breakdown --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Gender Breakdown</h3>
                <canvas id="patGenderChart" height="200"></canvas>
                <div id="patGenderLegend" class="mt-3 space-y-1.5"></div>
            </div>

            {{-- Source breakdown --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Patient Sources</h3>
                <p class="text-xs text-gray-400 mb-4">How patients found the clinic</p>
                <div class="space-y-2">
                    @php $srcMax = $patSource->max('total') ?: 1; @endphp
                    @foreach($patSource as $src)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                            <span>{{ ucfirst($src->source) }}</span>
                            <span class="font-medium">{{ $src->total }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-500 rounded-full" style="width:{{ round($src->total/$srcMax*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- City breakdown --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Patients by City</h3>
                <p class="text-xs text-gray-400 mb-4">Top 8 cities / areas</p>
                <div class="space-y-2">
                    @php $cityMax = $patCity->max('total') ?: 1; @endphp
                    @foreach($patCity as $c)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                            <span>{{ $c->city }}</span>
                            <span class="font-medium">{{ $c->total }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-400 rounded-full" style="width:{{ round($c->total/$cityMax*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>{{-- /tab-patients --}}

    {{-- TREATMENTS TAB --}}
    <div id="tab-treatments" class="hidden space-y-6">

        {{-- KPI row --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Visits</p>
                <p class="text-2xl font-bold text-purple-700 mt-1">{{ number_format($txTotalVisits) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">in selected period</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Completion Rate</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $txCompletionRate }}%</p>
                <p class="text-xs text-gray-400 mt-0.5">visits marked completed</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active Plans</p>
                @php $activePlans = $txPlansByStatus->where('status','ongoing')->first()?->total ?? 0; @endphp
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $activePlans }}</p>
                <p class="text-xs text-gray-400 mt-0.5">ongoing treatment plans</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Top Procedure</p>
                <p class="text-lg font-bold text-orange-500 mt-1 truncate">{{ $txVisitsByProc->first()?->procedure_name ?? '---' }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $txVisitsByProc->first()?->total ?? 0 }} visits</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Monthly visit trend --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Visit Trend — Last 6 Months</h3>
                <canvas id="txMonthChart" height="200"></canvas>
            </div>

            {{-- Visit status doughnut --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Visits by Status</h3>
                <canvas id="txStatusChart" height="200"></canvas>
                <div id="txStatusLegend" class="mt-3 space-y-1.5"></div>
            </div>

            {{-- By doctor --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Visits by Doctor</h3>
                <canvas id="txDoctorChart" height="200"></canvas>
            </div>

            {{-- Top procedures --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Top Procedures</h3>
                <p class="text-xs text-gray-400 mb-4">By visit count in selected period</p>
                <div class="space-y-2">
                    @php $procMax = $txVisitsByProc->max('total') ?: 1; @endphp
                    @foreach($txVisitsByProc as $proc)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                            <span>{{ $proc->procedure_name }}</span>
                            <span class="font-medium">{{ $proc->total }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-400 rounded-full" style="width:{{ round($proc->total/$procMax*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @if($txVisitsByProc->isEmpty())
                        <p class="text-xs text-gray-400 text-center py-4">No visit data for this period.</p>
                    @endif
                </div>
            </div>

        </div>
    </div>{{-- /tab-treatments --}}

    {{-- LAB TAB --}}
    <div id="tab-lab" class="hidden space-y-6">

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Cases</p>
                <p class="text-2xl font-bold text-purple-700 mt-1">{{ number_format($labTotal) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">in selected period</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Open Cases</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $labOpenCount }}</p>
                <p class="text-xs text-gray-400 mt-0.5">sent / in-progress / ready</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue</p>
                <p class="text-2xl font-bold text-red-500 mt-1">{{ $labOverdueCount }}</p>
                <p class="text-xs text-gray-400 mt-0.5">past expected return date</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Turnaround</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $labTurnaround ?? '—' }} <span class="text-sm font-normal">days</span></p>
                <p class="text-xs text-gray-400 mt-0.5">sent → received</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Cases by Status</h3>
                <canvas id="labStatusChart" height="220"></canvas>
                <div id="labStatusLegend" class="mt-3 space-y-1.5"></div>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Cases by Work Category</h3>
                <p class="text-xs text-gray-400 mb-4">Crown & Bridge, Implants, etc.</p>
                <div class="space-y-2">
                    @php $catMax = $labByCategory->max('total') ?: 1; @endphp
                    @foreach($labByCategory as $cat)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                            <span>{{ $cat->work_category }}</span>
                            <span class="font-medium">{{ $cat->total }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-purple-400 rounded-full" style="width:{{ round($cat->total/$catMax*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @if($labByCategory->isEmpty())
                        <p class="text-xs text-gray-400 text-center py-4">No lab cases in this period.</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Cases by Vendor</h3>
                <div class="space-y-2">
                    @php $vendMax = $labByVendor->max('total') ?: 1; @endphp
                    @foreach($labByVendor as $v)
                    <div>
                        <div class="flex justify-between text-xs text-gray-600 mb-0.5">
                            <span>{{ $v->name }}</span>
                            <span class="font-medium">{{ $v->total }} cases</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-400 rounded-full" style="width:{{ round($v->total/$vendMax*100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @if($labByVendor->isEmpty())
                        <p class="text-xs text-gray-400 text-center py-4">No lab vendor data for this period.</p>
                    @endif
                </div>
            </div>

        </div>
    </div>{{-- /tab-lab --}}

    {{-- INVENTORY TAB --}}
    <div id="tab-inventory" class="hidden space-y-6">

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Items</p>
                <p class="text-2xl font-bold text-purple-700 mt-1">{{ $invTotalItems }}</p>
                <p class="text-xs text-gray-400 mt-0.5">active products</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Low Stock</p>
                <p class="text-2xl font-bold {{ $invLowStockCount > 0 ? 'text-red-500' : 'text-emerald-600' }} mt-1">{{ $invLowStockCount }}</p>
                <p class="text-xs text-gray-400 mt-0.5">below minimum qty</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Expiring Soon</p>
                <p class="text-2xl font-bold {{ $invExpiring->count() > 0 ? 'text-orange-500' : 'text-emerald-600' }} mt-1">{{ $invExpiring->count() }}</p>
                <p class="text-xs text-gray-400 mt-0.5">within 60 days</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Stock In (period)</p>
                @php $stockIn = $invMovements->where('movement_type','stock_in')->first(); @endphp
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stockIn?->qty ?? 0) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">units received</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Low stock alert list --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden lg:col-span-2">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Low Stock Items</h3>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $invLowStockCount > 0 ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' }}">
                        {{ $invLowStockCount > 0 ? $invLowStockCount . ' items need reorder' : 'All stocked OK' }}
                    </span>
                </div>
                @if($invLowStock->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-5 py-2.5 text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Available</th>
                                <th class="text-right px-4 py-2.5 text-xs font-medium text-gray-500 uppercase">Minimum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($invLowStock as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-2.5 font-medium text-gray-800">{{ $item->product_name }}</td>
                                <td class="px-4 py-2.5 text-gray-500">{{ $item->category ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right font-bold {{ $item->available_qty <= 0 ? 'text-red-600' : 'text-orange-500' }}">
                                    {{ $item->available_qty }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-400">{{ $item->minimum_qty }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="px-5 py-8 text-center text-gray-400 text-sm">All items are above minimum stock levels.</p>
                @endif
            </div>

            {{-- Stock movements summary --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Stock Movements (period)</h3>
                <div class="space-y-2">
                    @foreach($invMovements as $mv)
                    <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50">
                        <span class="text-gray-600 capitalize">{{ str_replace('_',' ',$mv->movement_type) }}</span>
                        <div class="text-right">
                            <span class="font-semibold text-gray-800">{{ number_format($mv->qty) }}</span>
                            <span class="text-xs text-gray-400 ml-1">({{ $mv->cnt }} txns)</span>
                        </div>
                    </div>
                    @endforeach
                    @if($invMovements->isEmpty())
                        <p class="text-xs text-gray-400 text-center py-4">No stock movements in this period.</p>
                    @endif
                </div>
            </div>

            {{-- Expiring items --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Expiring Within 60 Days</h3>
                <p class="text-xs text-gray-400 mb-4">Batches nearing expiry</p>
                @if($invExpiring->isNotEmpty())
                <div class="space-y-2">
                    @foreach($invExpiring as $exp)
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-gray-50">
                        <div>
                            <p class="font-medium text-gray-800">{{ $exp->product_name }}</p>
                            @if($exp->batch_no)<p class="text-gray-400">Batch: {{ $exp->batch_no }}</p>@endif
                        </div>
                        <div class="text-right">
                            <p class="font-semibold {{ \Carbon\Carbon::parse($exp->expiry_date)->diffInDays() <= 14 ? 'text-red-500' : 'text-orange-500' }}">
                                {{ \Carbon\Carbon::parse($exp->expiry_date)->format('d M Y') }}
                            </p>
                            <p class="text-gray-400">Qty: {{ $exp->quantity }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                    <p class="text-xs text-gray-400 text-center py-4">No items expiring within 60 days.</p>
                @endif
            </div>

        </div>
    </div>{{-- /tab-inventory --}}


</div>{{-- /outer wrapper --}}
@endsection

@push('scripts')
<script>
const palette = ['#7c3aed','#10b981','#ef4444','#f59e0b','#3b82f6','#ec4899','#14b8a6','#6366f1'];

// ── Lazy chart init — charts only render when their tab is first shown ──
const _chartInited = {};

function initRevenueCharts() {
    if (_chartInited.revenue) return;
    _chartInited.revenue = true;

    const revDayLabels = @json($revDailyData->pluck('day'));
    const revDayData   = @json($revDailyData->pluck('total'));
    new Chart(document.getElementById('revTrendChart'), {
        type: 'bar',
        data: {
            labels: revDayLabels,
            datasets: [{ label: 'Collections (Rs. )', data: revDayData, backgroundColor: '#10b981', borderRadius: 4 }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    const revModeLabels = @json($revByMode->pluck('payment_mode')->map(fn($m) => ucfirst($m)));
    const revModeData   = @json($revByMode->pluck('total'));
    new Chart(document.getElementById('revModeChart'), {
        type: 'doughnut',
        data: { labels: revModeLabels, datasets: [{ data: revModeData, backgroundColor: palette, borderWidth: 2 }] },
        options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{display:false}} }
    });
    const revModeLeg = document.getElementById('revModeLegend');
    revModeLabels.forEach((l,i) => {
        revModeLeg.innerHTML += `<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium">Rs. ${Number(revModeData[i]).toLocaleString('en-IN')}</span></div>`;
    });
}

function initPatientCharts() {
    if (_chartInited.patients) return;
    _chartInited.patients = true;

    const patMonthLabels = @json($patNewByMonth->pluck('month'));
    const patMonthData   = @json($patNewByMonth->pluck('total'));
    new Chart(document.getElementById('patMonthChart'), {
        type: 'bar',
        data: { labels: patMonthLabels, datasets: [{ label: 'New Patients', data: patMonthData, backgroundColor: '#7c3aed', borderRadius: 4 }] },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
    });

    const patGenderLabels = @json($patGender->pluck('gender')->map(fn($g) => ucfirst($g)));
    const patGenderData   = @json($patGender->pluck('total'));
    new Chart(document.getElementById('patGenderChart'), {
        type: 'doughnut',
        data: { labels: patGenderLabels, datasets: [{ data: patGenderData, backgroundColor: palette, borderWidth: 2 }] },
        options: { responsive:true, cutout:'65%', plugins:{legend:{display:false}} }
    });
    const patGenderLeg = document.getElementById('patGenderLegend');
    patGenderLabels.forEach((l,i) => {
        patGenderLeg.innerHTML += `<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium">${patGenderData[i]}</span></div>`;
    });
}

function initTreatmentCharts() {
    if (_chartInited.treatments) return;
    _chartInited.treatments = true;

    const txMonthLabels = @json($txMonthlyVisits->pluck('month'));
    const txMonthData   = @json($txMonthlyVisits->pluck('total'));
    new Chart(document.getElementById('txMonthChart'), {
        type: 'bar',
        data: { labels: txMonthLabels, datasets: [{ label: 'Visits', data: txMonthData, backgroundColor: '#7c3aed', borderRadius: 4 }] },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
    });

    const txStatusLabels = @json($txVisitsByStatus->pluck('status')->map(fn($s) => ucfirst($s)));
    const txStatusData   = @json($txVisitsByStatus->pluck('total'));
    new Chart(document.getElementById('txStatusChart'), {
        type: 'doughnut',
        data: { labels: txStatusLabels, datasets: [{ data: txStatusData, backgroundColor: palette, borderWidth: 2 }] },
        options: { responsive:true, cutout:'65%', plugins:{legend:{display:false}} }
    });
    const txStatusLeg = document.getElementById('txStatusLegend');
    txStatusLabels.forEach((l,i) => {
        txStatusLeg.innerHTML += `<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium">${txStatusData[i]}</span></div>`;
    });

    const txDoctorLabels = @json($txVisitsByDoctor->pluck('name'));
    const txDoctorData   = @json($txVisitsByDoctor->pluck('total'));
    new Chart(document.getElementById('txDoctorChart'), {
        type: 'bar',
        data: { labels: txDoctorLabels, datasets: [{ label: 'Visits', data: txDoctorData, backgroundColor: palette, borderRadius: 4 }] },
        options: { indexAxis:'y', responsive:true, plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true}} }
    });
}

function initLabCharts() {
    if (_chartInited.lab) return;
    _chartInited.lab = true;

    const labStatusLabels = @json($labByStatus->pluck('status')->map(fn($s) => ucfirst(str_replace('_',' ',$s))));
    const labStatusData   = @json($labByStatus->pluck('total'));
    const labEl = document.getElementById('labStatusChart');
    if (labEl) {
        new Chart(labEl, {
            type: 'doughnut',
            data: { labels: labStatusLabels, datasets: [{ data: labStatusData, backgroundColor: palette, borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
        });
        const labLeg = document.getElementById('labStatusLegend');
        if (labLeg) {
            labStatusLabels.forEach((l, i) => {
                labLeg.innerHTML += `<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium">${labStatusData[i]}</span></div>`;
            });
        }
    }
}

function initAppointmentCharts() {
    if (_chartInited.appointments) return;
    _chartInited.appointments = true;

    const trendLabels = @json($dailyData->pluck('day'));
    const trendData   = @json($dailyData->pluck('total'));
    const trendEl = document.getElementById('trendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'bar',
            data: { labels: trendLabels, datasets: [{ label: 'Appointments', data: trendData, backgroundColor: '#7c3aed', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    const statusLabels = @json($statusBreakdown->pluck('status')->map(fn($s) => ucfirst($s)));
    const statusData   = @json($statusBreakdown->pluck('total'));
    const statusEl = document.getElementById('statusChart');
    if (statusEl) {
        new Chart(statusEl, {
            type: 'doughnut',
            data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: palette, borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
        });
        const statusLeg = document.getElementById('statusLegend');
        if (statusLeg) {
            statusLabels.forEach((l, i) => {
                statusLeg.innerHTML += `<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium">${statusData[i]}</span></div>`;
            });
        }
    }

    const catLabels = @json($byCategory->pluck('name'));
    const catData   = @json($byCategory->pluck('total'));
    const catEl = document.getElementById('categoryChart');
    if (catEl) {
        new Chart(catEl, {
            type: 'bar',
            data: { labels: catLabels, datasets: [{ label: 'Appointments', data: catData, backgroundColor: palette, borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    const docLabels = @json($byDoctor->pluck('name'));
    const docData   = @json($byDoctor->pluck('total'));
    const docEl = document.getElementById('doctorChart');
    if (docEl) {
        new Chart(docEl, {
            type: 'bar',
            data: { labels: docLabels, datasets: [{ label: 'Appointments', data: docData, backgroundColor: '#7c3aed', borderRadius: 4 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
        });
    }
}

// ── Tab switching ──────────────────────────────────────────────────────────
const ALL_TABS = ['appointments', 'revenue', 'patients', 'treatments', 'lab', 'inventory'];

function switchTab(tab) {
    ALL_TABS.forEach(t => {
        const panel = document.getElementById('tab-' + t);
        const btn   = document.getElementById('tab-btn-' + t);
        if (panel) panel.classList.toggle('hidden', t !== tab);
        if (btn) {
            btn.classList.toggle('border-purple-600', t === tab);
            btn.classList.toggle('text-purple-700',   t === tab);
            btn.classList.toggle('border-transparent', t !== tab);
            btn.classList.toggle('text-gray-500',     t !== tab);
        }
    });
    // Lazy-init charts for the newly shown tab
    if (tab === 'appointments') initAppointmentCharts();
    if (tab === 'revenue')      initRevenueCharts();
    if (tab === 'patients')     initPatientCharts();
    if (tab === 'treatments')   initTreatmentCharts();
    if (tab === 'lab')          initLabCharts();
}

// Init the default tab on load
document.addEventListener('DOMContentLoaded', () => {
    switchTab('appointments');
});
</script>
@endpush