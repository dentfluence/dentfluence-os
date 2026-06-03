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

</div>
@endsection

@section('scripts')
<script>
const palette = ['#7c3aed','#10b981','#ef4444','#f59e0b','#3b82f6','#ec4899','#14b8a6','#6366f1'];

// 1. Daily Trend
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: @json($dailyData->pluck('day')),
        datasets: [
            { label:'Total',     data:@json($dailyData->pluck('total')),     borderColor:'#7c3aed', backgroundColor:'rgba(124,58,237,0.08)', fill:true,  tension:0.3, pointRadius:3 },
            { label:'Completed', data:@json($dailyData->pluck('completed')), borderColor:'#10b981', backgroundColor:'transparent',           fill:false, tension:0.3, pointRadius:2 },
            { label:'Cancelled', data:@json($dailyData->pluck('cancelled')), borderColor:'#ef4444', backgroundColor:'transparent',           fill:false, tension:0.3, pointRadius:2 },
        ]
    },
    options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}},
        scales:{
            x:{ticks:{maxTicksLimit:10,font:{size:10}},grid:{display:false}},
            y:{beginAtZero:true,ticks:{precision:0,font:{size:10}}}
        }
    }
});

// 2. Status Doughnut
const statusLabels = @json($statusBreakdown->keys()->map(fn($k)=>ucfirst(str_replace('_',' ',$k))));
const statusCounts = @json($statusBreakdown->values());
new Chart(document.getElementById('statusChart'),{
    type:'doughnut',
    data:{labels:statusLabels,datasets:[{data:statusCounts,backgroundColor:palette,borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{display:false}}}
});
const leg = document.getElementById('statusLegend');
statusLabels.forEach((l,i)=>{
    leg.innerHTML+=`<div class="flex items-center gap-2 text-xs text-gray-600"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:${palette[i]}"></span><span>${l}</span><span class="ml-auto font-medium text-gray-800">${statusCounts[i]}</span></div>`;
});

// 3. By Category
new Chart(document.getElementById('categoryChart'),{
    type:'bar',
    data:{labels:@json($byCategory->pluck('name')),datasets:[{label:'Appointments',data:@json($byCategory->pluck('total')),backgroundColor:'#7c3aed',borderRadius:4}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0,font:{size:10}}},y:{ticks:{font:{size:10}}}}}
});

// 4. By Doctor
new Chart(document.getElementById('doctorChart'),{
    type:'bar',
    data:{labels:@json($byDoctor->pluck('name')),datasets:[{label:'Appointments',data:@json($byDoctor->pluck('total')),backgroundColor:'#10b981',borderRadius:4}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0,font:{size:10}}},y:{ticks:{font:{size:10}}}}}
});
</script>
@endsection
