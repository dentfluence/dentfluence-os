@extends('layouts.app')
@section('page-title', 'Lab Dashboard')

@section('content')
<div class="p-4 md:p-6 space-y-6 max-w-7xl mx-auto">

    {{-- HEADER --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-display font-semibold text-[#6a0f70]">Lab Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ now()->format('l, d M Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('lab.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm">
                All Cases
            </a>
            <a href="{{ route('lab.index') }}?status=overdue"
               class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition shadow-sm">
                {{ $kpis['delayed'] }} Overdue
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- KPI CARDS (8)                                                       --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        @php
        $cards = [
            [
                'label'  => 'Waiting to Send',
                'value'  => $kpis['waiting_to_send'],
                'color'  => 'purple',
                'filter' => 'order_placed',
            ],
            [
                'label'  => 'At Lab',
                'value'  => $kpis['at_lab'],
                'color'  => 'indigo',
                'filter' => 'impression_sent',
            ],
            [
                'label'  => 'Trial Pending',
                'value'  => $kpis['trial_pending'],
                'color'  => 'amber',
                'filter' => 'trial',
            ],
            [
                'label'  => 'Ready for Delivery',
                'value'  => $kpis['ready_delivery'],
                'color'  => 'green',
                'filter' => 'final_received',
            ],
            [
                'label'  => 'Delayed',
                'value'  => $kpis['delayed'],
                'color'  => 'red',
                'filter' => 'overdue',
            ],
            [
                'label'  => "Today's Dispatch",
                'value'  => $kpis['today_dispatch'],
                'color'  => 'blue',
                'filter' => 'active',
            ],
            [
                'label'  => "Today's Receive",
                'value'  => $kpis['today_receive'],
                'color'  => 'teal',
                'filter' => 'final_received',
            ],
            [
                'label'  => 'Outstanding Bills',
                'value'  => '₹ ' . number_format($kpis['outstanding_bills'], 0),
                'color'  => 'orange',
                'filter' => null,
                'is_currency' => true,
            ],
        ];
        $colorMap = [
            'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'num' => 'text-purple-700'],
            'indigo' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'border' => 'border-indigo-200', 'num' => 'text-indigo-700'],
            'amber'  => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'border' => 'border-amber-200',  'num' => 'text-amber-700'],
            'green'  => ['bg' => 'bg-green-50',  'text' => 'text-green-600',  'border' => 'border-green-200',  'num' => 'text-green-700'],
            'red'    => ['bg' => 'bg-red-50',    'text' => 'text-red-600',    'border' => 'border-red-200',    'num' => 'text-red-700'],
            'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'border' => 'border-blue-200',   'num' => 'text-blue-700'],
            'teal'   => ['bg' => 'bg-teal-50',   'text' => 'text-teal-600',   'border' => 'border-teal-200',   'num' => 'text-teal-700'],
            'orange' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-200', 'num' => 'text-orange-700'],
        ];
        @endphp

        @foreach($cards as $card)
        @php $c = $colorMap[$card['color']]; @endphp
        <a href="{{ $card['filter'] ? route('lab.index') . '?status=' . $card['filter'] : '#' }}"
           class="bg-white rounded-xl border {{ $c['border'] }} shadow-sm p-4 hover:shadow-md transition block">
            <div>
                <p class="text-xs {{ $c['text'] }} font-medium">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold {{ $c['num'] }} mt-1">{{ $card['value'] }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- MAIN CONTENT — 3 columns on desktop                                --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT (2/3): Active Alerts + Upcoming --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- OVERDUE CASES --}}
            @if($overdueCases->isNotEmpty())
            <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                <div class="bg-red-50 px-5 py-3 border-b border-red-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-red-700">Overdue Cases ({{ $overdueCases->count() }})</h2>
                    <a href="{{ route('lab.index') }}?status=overdue" class="text-xs text-red-600 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($overdueCases as $case)
                    <a href="{{ route('lab.show', $case) }}"
                       class="flex items-center justify-between px-5 py-3 hover:bg-red-50 transition">
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                {{ $case->patient?->name ?? '—' }}
                                <span class="text-xs text-gray-400 ml-1">{{ $case->case_number }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ $case->work_category }} · {{ $case->vendor?->name ?? 'No lab' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">
                                {{ $case->overdueDays() }}d overdue
                            </span>
                            <p class="text-xs text-gray-400 mt-0.5">Due {{ $case->expected_return_date?->format('d M') }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- TRIAL PENDING --}}
            @if($trialCases->isNotEmpty())
            <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden">
                <div class="bg-amber-50 px-5 py-3 border-b border-amber-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-amber-700">Trial Awaiting Doctor Review ({{ $trialCases->count() }})</h2>
                    <a href="{{ route('lab.index') }}?status=trial" class="text-xs text-amber-600 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($trialCases as $case)
                    <a href="{{ route('lab.show', $case) }}"
                       class="flex items-center justify-between px-5 py-3 hover:bg-amber-50 transition">
                        <div>
                            <p class="text-sm font-medium text-gray-800">
                                {{ $case->patient?->name ?? '—' }}
                                <span class="ml-1 text-xs text-amber-600">{{ $case->trialLabel() }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ $case->work_category }} · {{ $case->vendor?->name ?? 'No lab' }}</p>
                        </div>
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Review Now</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- READY FOR DELIVERY --}}
            @if($awaitingCases->isNotEmpty())
            <div class="bg-white rounded-xl border border-green-200 shadow-sm overflow-hidden">
                <div class="bg-green-50 px-5 py-3 border-b border-green-200 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-green-700">Ready for Delivery ({{ $awaitingCases->count() }})</h2>
                    <a href="{{ route('lab.index') }}?status=final_received" class="text-xs text-green-600 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($awaitingCases as $case)
                    <a href="{{ route('lab.show', $case) }}"
                       class="flex items-center justify-between px-5 py-3 hover:bg-green-50 transition">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $case->patient?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $case->work_category }} · Received {{ $case->final_received_date?->format('d M') ?? '—' }}</p>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Book Patient</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- UPCOMING RETURNS (next 7 days) --}}
            @if($upcomingReturns->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">Upcoming Returns — Next 7 Days</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($upcomingReturns as $case)
                    <a href="{{ route('lab.show', $case) }}"
                       class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $case->patient?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $case->work_category }} · {{ $case->vendor?->name ?? 'No lab' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold {{ $case->isDueToday() ? 'text-blue-600' : 'text-gray-700' }}">
                                {{ $case->expected_return_date?->format('d M') }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $case->isDueToday() ? 'Today' : 'in ' . now()->diffInDays($case->expected_return_date) . 'd' }}
                            </p>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if($overdueCases->isEmpty() && $trialCases->isEmpty() && $awaitingCases->isEmpty() && $upcomingReturns->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
                <p class="font-semibold text-gray-700">All caught up!</p>
                <p class="text-sm text-gray-400 mt-1">No overdue cases, no trials pending, no deliveries waiting.</p>
            </div>
            @endif

        </div>

        {{-- RIGHT (1/3): Activity + Quick Actions --}}
        <div class="space-y-5">

            {{-- QUICK ACTIONS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Quick Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('lab.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 bg-[#6a0f70] text-white text-sm font-medium rounded-lg hover:bg-[#380740] transition">
                        + New Lab Case
                    </a>
                    <a href="{{ route('lab.index') }}?status=order_placed"
                       class="flex items-center gap-2.5 px-3 py-2.5 border border-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition">
                        Cases Waiting to Send ({{ $kpis['waiting_to_send'] }})
                    </a>
                    <a href="{{ route('lab.index') }}?status=final_received"
                       class="flex items-center gap-2.5 px-3 py-2.5 border border-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition">
                        Ready for Delivery ({{ $kpis['ready_delivery'] }})
                    </a>
                    <a href="{{ route('lab-vendors.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 border border-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition">
                        Manage Labs
                    </a>
                    <a href="{{ route('lab.reconciliation.index') }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 border border-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition">
                        Monthly Reconciliation
                    </a>
                </div>
            </div>

            {{-- RECENT ACTIVITY --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h2 class="text-sm font-semibold text-gray-700">Recent Activity</h2>
                </div>
                <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                    @forelse($recentEvents as $event)
                    <div class="px-4 py-2.5">
                        <div class="flex items-start gap-2">
                            <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0
                                @switch($event->event_type)
                                    @case('status_changed')       bg-[#6a0f70] @break
                                    @case('prescription_saved')
                                    @case('prescription_updated') bg-purple-400 @break
                                    @case('rated')                bg-amber-400 @break
                                    @case('created')              bg-green-500 @break
                                    @default                      bg-gray-300
                                @endswitch"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-700 truncate">
                                    <span class="font-medium">{{ $event->labCase?->patient?->name ?? '—' }}</span>
                                    — {{ $event->description }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ $event->created_at->diffForHumans() }}
                                    @if($event->createdBy) · {{ $event->createdBy->name }} @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-xs text-gray-400 py-6">No recent activity.</p>
                    @endforelse
                </div>
            </div>

            {{-- DUE TODAY --}}
            @if($dueToday->isNotEmpty())
            <div class="bg-white rounded-xl border border-blue-200 shadow-sm overflow-hidden">
                <div class="bg-blue-50 px-4 py-3 border-b border-blue-200">
                    <h2 class="text-sm font-semibold text-blue-700">Due Today ({{ $dueToday->count() }})</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($dueToday as $case)
                    <a href="{{ route('lab.show', $case) }}"
                       class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50 transition">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $case->patient?->name ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $case->vendor?->name ?? 'No lab' }}</p>
                        </div>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Due Today</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
